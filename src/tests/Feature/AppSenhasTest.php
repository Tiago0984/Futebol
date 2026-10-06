<?php

namespace Tests\Feature;

use App\Mail\DefinirSenhaMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 9, senhas do app: convite "Defina sua senha" na aprovação e pelo admin (um a um e os pendentes),
 * "Esqueci minha senha" pela API (resposta sempre igual, com limite), a página do site que usa o link
 * (24 horas, um broker e uma tabela por perfil) e o selo "Sem acesso ao app".
 */
class AppSenhasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const RESPOSTA_ESQUECI = 'Se o e-mail estiver cadastrado neste perfil, você vai receber um link para definir uma nova senha. O link vale por 24 horas.';
    private const LINK_INVALIDO = 'Link inválido ou vencido';

    // ---------- aprovação da matrícula ----------

    public function test_aprovacao_envia_o_convite_ao_atleta_e_ao_responsavel_sem_senha(): void
    {
        Mail::fake();
        $id = $this->pendenteParaAprovar('ana@exemplo.com');

        $this->aprovar($id)->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg,
            ' Convite do app enviado para: o atleta Atleta de Teste (ana@exemplo.com); o responsável Responsável de Teste (responsavel' . $id . '@teste.com).'));

        Mail::assertSent(DefinirSenhaMail::class, 2);
        Mail::assertSent(DefinirSenhaMail::class, fn ($m) => $m->hasTo('ana@exemplo.com') && $m->convite && $m->perfil === 'atleta'
            && str_contains($m->link, '/senha/atleta/'));
        Mail::assertSent(DefinirSenhaMail::class, fn ($m) => $m->hasTo("responsavel{$id}@teste.com") && $m->perfil === 'responsavel');
        $this->assertSame(1, DB::table('password_reset_tokens_atletas')->where('email', 'ana@exemplo.com')->count());
        $this->assertSame(1, DB::table('password_reset_tokens_responsaveis')->where('email', "responsavel{$id}@teste.com")->count());
    }

    public function test_aprovacao_nao_convida_responsavel_que_ja_tem_senha_nem_atleta_sem_email(): void
    {
        Mail::fake();
        $id = $this->pendenteParaAprovar(null);
        DB::table('tbl_responsavel')->update(['senha_responsavel' => Hash::make('ja-tem')]);

        $this->aprovar($id)->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, 'Número: A001'));
        Mail::assertNothingSent();
    }

    public function test_aprovacao_sem_nenhum_email_avisa_que_fica_sem_acesso_ao_app(): void
    {
        Mail::fake();
        $id = $this->pendenteParaAprovar(null);
        DB::table('tbl_responsavel')->update(['email_responsavel' => null]);

        $this->aprovar($id)->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg,
            'Número: A001 Sem acesso ao app: nem o atleta nem o responsável têm e-mail cadastrado.'));
        Mail::assertNothingSent();
    }

    public function test_falha_no_envio_mostra_o_link_para_copiar_e_o_link_funciona(): void
    {
        $id = $this->pendenteParaAprovar('ana@exemplo.com');
        DB::table('tbl_responsavel')->update(['email_responsavel' => null]);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP fora do ar'));

        $this->aprovar($id)
            ->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg,
                ' Não foi possível enviar o convite do app para: o atleta Atleta de Teste (ana@exemplo.com). Copie o link abaixo e envie.'))
            ->assertSessionHas('links_convite');

        $links = session('links_convite');
        $link = $links[0]['link'];
        $this->assertSame('o atleta Atleta de Teste (ana@exemplo.com)', $links[0]['para']);
        $this->get($link)->assertOk()->assertSee('Defina sua senha')->assertDontSee(self::LINK_INVALIDO);

        // A tela mostra o link com o botão de copiar
        $this->comoAdmin()->withSession(['links_convite' => $links])
            ->get(route('admin.matriculas.index'))->assertSee($link, false)->assertSee('Copiar');
    }

    // ---------- página do site ----------

    public function test_definir_a_senha_pelo_link_apaga_os_tokens_daquele_perfil_e_libera_o_login(): void
    {
        Mail::fake();
        $idAtleta = $this->atletaAtivo('familia@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['password' => Hash::make('aleatoria'), 'token_cadastro' => 'do-site']);
        $idResponsavel = $this->criarResponsavel($idAtleta);
        DB::table('tbl_responsavel')->where('id_responsavel', $idResponsavel)
            ->update(['email_responsavel' => 'familia@exemplo.com', 'senha_responsavel' => Hash::make('senha-do-responsavel')]);

        // Sessões antigas no app: uma do atleta (de antes) e uma do responsável, com o mesmo e-mail
        \App\Models\Atleta::find($idAtleta)->createToken('antigo');
        $this->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => 'senha-do-responsavel', 'perfil' => 'responsavel'])->assertOk();

        $link = $this->linkEnviado(fn () => \App\Models\Atleta::find($idAtleta)->enviarLinkDeSenha());
        $this->get($link)->assertOk()->assertSee('Defina sua senha')->assertSee('familia@exemplo.com')
            ->assertSee('Esqueci minha senha');

        [$token, $email] = $this->tokenEEmail($link);
        $this->post(route('senha.salvar', 'atleta'), ['token' => $token, 'email' => $email, 'senha' => 'minha-senha-1', 'senha_confirmation' => 'minha-senha-1'])
            ->assertRedirect(route('senha.definida'));
        $this->followingRedirects()->get(route('senha.definida'))->assertSee('Senha definida');

        $atleta = DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->first();
        $this->assertTrue(Hash::check('minha-senha-1', $atleta->password));
        $this->assertNull($atleta->token_cadastro); // deixa de contar como "sem senha"
        $this->assertSame(0, DB::table('password_reset_tokens_atletas')->count());
        // Só os tokens do perfil atleta saem; o responsável do mesmo e-mail continua conectado
        $this->assertSame(['App\Models\Responsavel'], DB::table('personal_access_tokens')->pluck('tokenable_type')->all());

        $this->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => 'minha-senha-1'])->assertOk();

        // O link não vale de novo
        $this->get($link)->assertOk()->assertSee(self::LINK_INVALIDO);
        $this->post(route('senha.salvar', 'atleta'), ['token' => $token, 'email' => $email, 'senha' => 'outra-senha-2', 'senha_confirmation' => 'outra-senha-2']);
        $this->assertTrue(Hash::check('minha-senha-1', DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->value('password')));
    }

    public function test_link_vencido_email_inexistente_e_token_errado_dao_a_mesma_pagina(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-10 10:00:00');
        $idAtleta = $this->atletaAtivo('ana@exemplo.com');
        $link = $this->linkEnviado(fn () => \App\Models\Atleta::find($idAtleta)->enviarLinkDeSenha());
        [$token] = $this->tokenEEmail($link);

        $paginas = [
            route('senha.definir', ['perfil' => 'atleta', 'token' => $token, 'email' => 'ninguem@exemplo.com']),
            route('senha.definir', ['perfil' => 'atleta', 'token' => 'token-errado', 'email' => 'ana@exemplo.com']),
        ];

        // O token de um perfil não vale no outro (a página do responsável muda só no cabeçalho)
        $this->get(route('senha.definir', ['perfil' => 'responsavel', 'token' => $token, 'email' => 'ana@exemplo.com']))
            ->assertSee(self::LINK_INVALIDO);

        $this->travelTo('2026-10-11 09:59:00'); // dentro das 24 horas
        $this->get($link)->assertOk()->assertDontSee(self::LINK_INVALIDO);

        $this->travelTo('2026-10-11 10:01:00'); // venceu
        $paginas[] = $link;

        $conteudo = null;
        foreach ($paginas as $pagina) {
            $html = $this->get($pagina)->assertOk()->assertSee(self::LINK_INVALIDO)->assertSee('Esqueci minha senha')
                ->assertDontSee('name="senha"', false)->getContent();
            $conteudo ??= $html;
            $this->assertSame($conteudo, $html, "A página muda para {$pagina}");
        }

        // Vencido também no POST: a senha não muda
        $this->post(route('senha.salvar', 'atleta'), ['token' => $token, 'email' => 'ana@exemplo.com', 'senha' => 'minha-senha-1', 'senha_confirmation' => 'minha-senha-1'])
            ->assertRedirect();
        $this->assertNull(DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->value('password'));
    }

    public function test_senha_curta_ou_sem_confirmacao_volta_com_erro(): void
    {
        Mail::fake();
        $idAtleta = $this->atletaAtivo('ana@exemplo.com');
        [$token, $email] = $this->tokenEEmail($this->linkEnviado(fn () => \App\Models\Atleta::find($idAtleta)->enviarLinkDeSenha()));

        $this->post(route('senha.salvar', 'atleta'), ['token' => $token, 'email' => $email, 'senha' => 'curta', 'senha_confirmation' => 'curta'])
            ->assertSessionHasErrors(['senha' => 'A senha precisa ter pelo menos 8 caracteres.']);
        $this->post(route('senha.salvar', 'atleta'), ['token' => $token, 'email' => $email, 'senha' => 'minha-senha-1', 'senha_confirmation' => 'outra'])
            ->assertSessionHasErrors(['senha' => 'A confirmação não é igual à senha.']);
        $this->assertSame(1, DB::table('password_reset_tokens_atletas')->count()); // o link continua valendo
    }

    public function test_email_do_link_explica_a_validade_e_o_esqueci_minha_senha(): void
    {
        $convite = (new DefinirSenhaMail('Ana', 'responsavel', 'http://localhost/senha/x', true))->render();
        $this->assertStringContainsString('perfil <strong>responsável</strong>', $convite);
        $this->assertStringContainsString('24 horas', $convite);
        $this->assertStringContainsString('"Esqueci minha senha"', $convite);
        $this->assertStringContainsString('http://localhost/senha/x', $convite);

        $esqueci = new DefinirSenhaMail('Ana', 'atleta', 'http://localhost/senha/x', false);
        $this->assertSame('Nova senha do app - AACJ Futebol', $esqueci->envelope()->subject);
        $this->assertStringContainsString('Se não foi você', $esqueci->render());
    }

    // ---------- esqueci minha senha (API) ----------

    public function test_esqueci_senha_responde_igual_e_so_envia_para_quem_pode_entrar(): void
    {
        Mail::fake();
        $this->atletaAtivo('ana@exemplo.com');
        $inativo = $this->atletaAtivo('caio@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);
        $comFilho = $this->atletaAtivo(null);
        DB::table('tbl_responsavel')->where('id_responsavel', $this->criarResponsavel($comFilho))->update(['email_responsavel' => 'mae@exemplo.com']);

        $pedidos = [
            ['email' => 'ana@exemplo.com'],                                  // atleta ativo (sem perfil = atleta)
            ['email' => 'ninguem@exemplo.com'],                              // não existe
            ['email' => 'caio@exemplo.com', 'perfil' => 'atleta'],           // inativo
            ['email' => 'ana@exemplo.com', 'perfil' => 'responsavel'],       // existe só como atleta
            ['email' => 'mae@exemplo.com', 'perfil' => 'responsavel'],       // responsável com filho ativo
        ];

        foreach ($pedidos as $i => $pedido) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.{$i}"])->postJson('/api/v1/auth/esqueci-senha', $pedido)
                ->assertOk()
                ->assertExactJson(['success' => true, 'message' => self::RESPOSTA_ESQUECI]);
        }

        Mail::assertSent(DefinirSenhaMail::class, 2);
        Mail::assertSent(DefinirSenhaMail::class, fn ($m) => $m->hasTo('ana@exemplo.com') && ! $m->convite && $m->perfil === 'atleta');
        Mail::assertSent(DefinirSenhaMail::class, fn ($m) => $m->hasTo('mae@exemplo.com') && $m->perfil === 'responsavel');
    }

    public function test_esqueci_senha_nao_gera_outro_link_em_menos_de_60_segundos_e_tem_limite(): void
    {
        Mail::fake();
        $this->atletaAtivo('ana@exemplo.com');

        $pedir = fn ($ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@exemplo.com'])->assertOk();

        $pedir('10.0.2.1');
        $pedir('10.0.2.2');
        Mail::assertSent(DefinirSenhaMail::class, 1); // o broker segura o 2º (menos de 60 segundos)

        $this->travel(61)->seconds();
        $pedir('10.0.2.3');
        Mail::assertSent(DefinirSenhaMail::class, 2); // passado o minuto, gera outro link

        // 4º pedido do mesmo e-mail e perfil em 10 minutos, mesmo de outro IP: 429
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.99'])
            ->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ANA@exemplo.com'])->assertStatus(429);
        // O outro perfil do mesmo e-mail não é bloqueado
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.100'])
            ->postJson('/api/v1/auth/esqueci-senha', ['email' => 'ana@exemplo.com', 'perfil' => 'responsavel'])->assertOk();

        // 5 por minuto no mesmo IP
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.3.1'])
                ->postJson('/api/v1/auth/esqueci-senha', ['email' => "outro{$i}@exemplo.com"])->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.3.1'])
            ->postJson('/api/v1/auth/esqueci-senha', ['email' => 'outro9@exemplo.com'])->assertStatus(429);
    }

    // ---------- admin: convites e selo ----------

    public function test_admin_reenvia_o_convite_do_atleta_e_do_responsavel(): void
    {
        Mail::fake();
        $id = $this->atletaAtivo('ana@exemplo.com');
        $idResponsavel = $this->criarResponsavel($id);

        $this->comoAdmin()->post(route('admin.atletas.convite', $id), ['perfil' => 'atleta'])
            ->assertSessionHas('sucesso', 'Convite do app enviado para: o atleta Atleta de Teste (ana@exemplo.com).');
        $this->comoAdmin()->post(route('admin.atletas.convite', $id), ['perfil' => 'responsavel', 'id_responsavel' => $idResponsavel])
            ->assertSessionHas('sucesso', "Convite do app enviado para: o responsável Responsável de Teste (responsavel{$id}@teste.com).");

        Mail::assertSent(DefinirSenhaMail::class, 2);
    }

    public function test_admin_convite_recusa_sem_email_responsavel_de_outro_e_atleta_inativo(): void
    {
        Mail::fake();
        $semEmail = $this->atletaAtivo(null);
        $outro = $this->atletaAtivo('bia@exemplo.com');
        $responsavelDoOutro = $this->criarResponsavel($outro);
        $inativo = $this->atletaAtivo('caio@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);

        $this->comoAdmin()->post(route('admin.atletas.convite', $semEmail), ['perfil' => 'atleta'])
            ->assertSessionHas('erro', 'Sem e-mail cadastrado: edite o atleta e informe o e-mail antes de enviar o convite.');
        $this->comoAdmin()->post(route('admin.atletas.convite', $semEmail), ['perfil' => 'responsavel', 'id_responsavel' => $responsavelDoOutro])
            ->assertSessionHas('erro', 'Este responsável não é do atleta.');
        $this->comoAdmin()->post(route('admin.atletas.convite', $inativo), ['perfil' => 'atleta'])
            ->assertSessionHas('erro', 'O convite do app é só para atleta ativo.');

        Mail::assertNothingSent();
    }

    public function test_enviar_convites_pendentes_so_para_quem_esta_ativo_e_sem_senha(): void
    {
        Mail::fake();
        $semSenha = $this->atletaAtivo('sem.senha@exemplo.com');                     // admin: password NULL
        $doSite = $this->atletaAtivo('do.site@exemplo.com');                          // site: senha aleatória + token_cadastro
        DB::table('tbl_atletas')->where('id_atleta', $doSite)->update(['password' => Hash::make('x'), 'token_cadastro' => 'tk']);
        $comSenha = $this->atletaAtivo('com.senha@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $comSenha)->update(['password' => Hash::make('x')]);
        $inativo = $this->atletaAtivo('inativo@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);

        $this->criarResponsavel($comSenha);                                            // sem senha, filho ativo
        $responsavelComSenha = $this->criarResponsavel($semSenha);
        DB::table('tbl_responsavel')->where('id_responsavel', $responsavelComSenha)->update(['senha_responsavel' => Hash::make('x')]);
        $this->criarResponsavel($inativo);                                             // só filho inativo

        $this->comoAdmin()->get(route('admin.atletas.index'))->assertSee('Enviar convites pendentes (3)');

        $this->comoAdmin()->post(route('admin.atletas.convitesPendentes'))
            ->assertSessionHas('sucesso', fn ($msg) => str_starts_with($msg, '3 convite(s) do app processado(s). Convite do app enviado para: '));

        Mail::assertSent(DefinirSenhaMail::class, 3);
        foreach (['sem.senha@exemplo.com', 'do.site@exemplo.com', "responsavel{$comSenha}@teste.com"] as $email) {
            Mail::assertSent(DefinirSenhaMail::class, fn ($m) => $m->hasTo($email));
        }

        // Sem pendentes, o botão some (o link não muda nada até ser usado, então o atleta continua sem senha:
        // aqui todos definem a senha)
        DB::table('tbl_atletas')->update(['password' => Hash::make('x'), 'token_cadastro' => null]);
        DB::table('tbl_responsavel')->update(['senha_responsavel' => Hash::make('x')]);
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertDontSee('Enviar convites pendentes');
        $this->comoAdmin()->post(route('admin.atletas.convitesPendentes'))
            ->assertSessionHas('sucesso', 'Nenhum convite pendente: todos os atletas ativos e responsáveis com e-mail já definiram a senha.');
    }

    public function test_selo_sem_acesso_ao_app_quando_ninguem_tem_email(): void
    {
        $semNinguem = $this->atletaAtivo(null);
        DB::table('tbl_responsavel')->where('id_responsavel', $this->criarResponsavel($semNinguem))->update(['email_responsavel' => null]);
        $soResponsavel = $this->atletaAtivo(null);
        $this->criarResponsavel($soResponsavel);

        $this->assertTrue(\App\Models\Atleta::find($semNinguem)->semAcessoAoApp());
        $this->assertFalse(\App\Models\Atleta::find($soResponsavel)->semAcessoAoApp());

        $html = $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Sem acesso ao app</span>'));
        $this->assertStringContainsString('Responsável Responsável de Teste: responsavel' . $soResponsavel . '@teste.com', $html);
    }

    // ---------- helpers ----------

    // Atleta ATIVO na Sub-13 M, com o e-mail dado (ou sem)
    private function atletaAtivo(?string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email]);

        return $id;
    }

    // Matrícula pendente pronta para aprovar (autorização assinada; o responsável tem e-mail e não tem senha)
    private function pendenteParaAprovar(?string $emailAtleta): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $emailAtleta]);
        $this->criarAutorizacao($id);

        return $id;
    }

    private function aprovar(int $id)
    {
        return $this->comoAdmin()->patch(route('admin.matriculas.aprovar', $id), ['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
    }

    // Roda o envio (com Mail::fake) e devolve o link do e-mail
    private function linkEnviado(callable $enviar): string
    {
        $this->assertNull($enviar());

        return Mail::sent(DefinirSenhaMail::class)->last()->link;
    }

    // [token, email] do link /senha/{perfil}/{token}?email=...
    private function tokenEEmail(string $link): array
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return [basename(parse_url($link, PHP_URL_PATH)), $query['email']];
    }
}
