<?php

namespace Tests\Feature;

use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 9, login com dois perfis: atleta (padrão, resposta igual à de antes mais o "perfil") e responsável
 * (senha_responsavel, só com algum filho ATIVO), cada token só nas rotas do seu perfil, limite de tentativas
 * por e-mail e perfil e a área do responsável (ver, editar e trocar a senha).
 */
class AppPerfisTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA_ATLETA = 'senha-do-atleta';
    private const SENHA_RESPONSAVEL = 'senha-do-responsavel';

    // ---------- login ----------

    public function test_login_sem_perfil_continua_sendo_do_atleta(): void
    {
        $id = $this->atletaComSenha('ana@exemplo.com');

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => self::SENHA_ATLETA])
            ->assertOk()
            ->assertJsonPath('data.perfil', 'atleta')
            ->assertJsonPath('data.atleta.id_atleta', $id);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => self::SENHA_ATLETA, 'perfil' => 'atleta'])
            ->assertOk()->assertJsonPath('data.perfil', 'atleta');
    }

    public function test_login_do_responsavel_com_os_filhos_ativos_e_token_de_30_dias(): void
    {
        $this->travelTo('2026-10-10 10:00:00');
        [$idResponsavel, $idFilho] = $this->responsavelComSenha('mae@exemplo.com');
        $inativo = $this->criarAtleta($this->nascidoComIdade(10), 'M', 'INATIVO');
        $this->vincular($inativo, $idResponsavel);

        $resposta = $this->postJson('/api/v1/auth/login', ['email' => 'MAE@exemplo.com', 'senha' => self::SENHA_RESPONSAVEL, 'perfil' => 'responsavel'])
            ->assertOk();

        $resposta->assertExactJson([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'data'    => [
                'token'       => $resposta->json('data.token'),
                'perfil'      => 'responsavel',
                'responsavel' => ['id_responsavel' => $idResponsavel, 'nome_responsavel' => 'Responsável de Teste', 'email_responsavel' => 'mae@exemplo.com'],
                'atletas'     => [[
                    'id_atleta' => $idFilho, 'nome_atleta' => 'Atleta de Teste', 'numero_matricula_atleta' => null,
                    'foto_atleta' => null, 'grau_parentesco_responsavel' => 'Mãe',
                ]], // o inativo não aparece
            ],
        ]);

        $token = DB::table('personal_access_tokens')->sole();
        $this->assertSame(Responsavel::class, $token->tokenable_type);
        $this->assertSame($idResponsavel, (int) $token->tokenable_id);
        $this->assertSame('2026-11-09 10:00:00', $token->expires_at);
    }

    public function test_responsavel_com_senha_errada_ou_sem_senha_recebe_401(): void
    {
        $this->responsavelComSenha('mae@exemplo.com');
        [$semSenha] = $this->responsavelComSenha('pai@exemplo.com');
        DB::table('tbl_responsavel')->where('id_responsavel', $semSenha)->update(['senha_responsavel' => null]);

        foreach ([['mae@exemplo.com', 'errada'], ['pai@exemplo.com', ''], ['ninguem@exemplo.com', 'x']] as [$email, $senha]) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => $senha ?: 'qualquer', 'perfil' => 'responsavel'])
                ->assertUnauthorized()
                ->assertExactJson(['success' => false, 'message' => 'E-mail ou senha inválidos.']);
        }
    }

    public function test_responsavel_sem_nenhum_filho_ativo_nao_entra(): void
    {
        [, $idFilho] = $this->responsavelComSenha('mae@exemplo.com');
        DB::table('tbl_atletas')->where('id_atleta', $idFilho)->update(['status_atleta' => 'INATIVO']);

        $this->postJson('/api/v1/auth/login', ['email' => 'mae@exemplo.com', 'senha' => self::SENHA_RESPONSAVEL, 'perfil' => 'responsavel'])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha.']);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_mesmo_email_no_atleta_e_no_responsavel_cada_perfil_com_a_sua_senha(): void
    {
        $this->atletaComSenha('familia@exemplo.com');
        $this->responsavelComSenha('familia@exemplo.com');

        $login = fn ($senha, $perfil) => $this->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => $senha, 'perfil' => $perfil]);

        $login(self::SENHA_ATLETA, 'atleta')->assertOk();
        $login(self::SENHA_RESPONSAVEL, 'responsavel')->assertOk();
        $login(self::SENHA_RESPONSAVEL, 'atleta')->assertUnauthorized();
        $login(self::SENHA_ATLETA, 'responsavel')->assertUnauthorized();
    }

    public function test_perfil_invalido_e_recusado(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => 'x', 'perfil' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('perfil');
    }

    // ---------- cada token só nas rotas do seu perfil ----------

    public function test_token_de_um_perfil_e_barrado_nas_rotas_do_outro(): void
    {
        $this->atletaComSenha('ana@exemplo.com');
        $this->responsavelComSenha('mae@exemplo.com');
        $tokenAtleta = $this->login('ana@exemplo.com', self::SENHA_ATLETA, 'atleta');
        $tokenResponsavel = $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel');

        foreach (['GET' => '/api/v1/responsavel', 'PATCH' => '/api/v1/responsavel', 'PUT' => '/api/v1/responsavel/senha'] as $metodo => $rota) {
            $this->comToken($tokenAtleta)->json($metodo, $rota)
                ->assertForbidden()
                ->assertExactJson(['success' => false, 'message' => 'Esta área é do perfil responsável. Entre no app como responsável.']);
        }
        foreach (['GET' => '/api/v1/atleta', 'PATCH' => '/api/v1/atleta', 'PUT' => '/api/v1/atleta/senha'] as $metodo => $rota) {
            $this->comToken($tokenResponsavel)->json($metodo, $rota)
                ->assertForbidden()
                ->assertExactJson(['success' => false, 'message' => 'Esta área é do perfil atleta. Entre no app como atleta.']);
        }

        // Cada um na sua área continua entrando; o logout vale para os dois
        $this->comToken($tokenAtleta)->getJson('/api/v1/atleta')->assertOk();
        $this->comToken($tokenResponsavel)->getJson('/api/v1/responsavel')->assertOk();
        $this->comToken($tokenResponsavel)->postJson('/api/v1/auth/logout')->assertOk();
        $this->comToken($tokenAtleta)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_responsavel_que_perde_o_ultimo_filho_ativo_e_recusado_e_perde_os_tokens(): void
    {
        [, $idFilho] = $this->responsavelComSenha('mae@exemplo.com');
        $token = $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel');

        DB::table('tbl_atletas')->where('id_atleta', $idFilho)->update(['status_atleta' => 'INATIVO']);

        $this->comToken($token)->getJson('/api/v1/responsavel')
            ->assertForbidden()
            ->assertJsonPath('message', 'Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha.');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    // ---------- limite de tentativas por e-mail e perfil ----------

    public function test_limite_por_email_e_perfil_um_perfil_nao_bloqueia_o_outro(): void
    {
        $this->atletaComSenha('familia@exemplo.com');
        $this->responsavelComSenha('familia@exemplo.com');

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => 'errada', 'perfil' => 'responsavel'])
                ->assertUnauthorized();
        }

        // O responsável está bloqueado (mesmo de outro IP e com a senha certa); o atleta do mesmo e-mail não
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.98'])
            ->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => self::SENHA_RESPONSAVEL, 'perfil' => 'responsavel'])
            ->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/v1/auth/login', ['email' => 'familia@exemplo.com', 'senha' => self::SENHA_ATLETA])
            ->assertOk();
    }

    // ---------- área do responsável ----------

    public function test_responsavel_ve_os_proprios_dados_sem_a_senha(): void
    {
        [$idResponsavel, $idFilho] = $this->responsavelComSenha('mae@exemplo.com');
        $token = $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel');

        $dados = $this->comToken($token)->getJson('/api/v1/responsavel')->assertOk()->json('data');

        $this->assertSame($idResponsavel, $dados['id_responsavel']);
        $this->assertSame('111.111.111-11', $dados['cpf_responsavel']);
        $this->assertSame('(11) 99999-9999', $dados['whatsapp_responsavel']);
        $this->assertSame('Rua do Responsável', $dados['endereco']['rua_endereco']);
        $this->assertSame([$idFilho], array_column($dados['atletas'], 'id_atleta'));
        $this->assertArrayNotHasKey('senha_responsavel', $dados);
        $this->assertArrayNotHasKey('assinatura_responsavel', $dados);
    }

    public function test_responsavel_edita_email_telefone_e_whatsapp_e_nao_os_dados_da_secretaria(): void
    {
        [$idResponsavel] = $this->responsavelComSenha('mae@exemplo.com');
        [$outro] = $this->responsavelComSenha('ocupado@exemplo.com');
        $token = $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel');

        $this->comToken($token)->patchJson('/api/v1/responsavel', [
            'email_responsavel' => '  Nova@Exemplo.COM ', 'telefone_responsavel' => '(11) 3333-4444', 'whatsapp_responsavel' => '(11) 98888-7777',
            'nome_responsavel' => 'Outro Nome', 'cpf_responsavel' => '999.999.999-99', 'rg_responsavel' => '9',
        ])->assertOk()->assertJsonPath('data.email_responsavel', 'nova@exemplo.com');

        $this->assertDatabaseHas('tbl_responsavel', [
            'id_responsavel' => $idResponsavel, 'email_responsavel' => 'nova@exemplo.com', 'telefone_responsavel' => '(11) 3333-4444',
            'whatsapp_responsavel' => '(11) 98888-7777', 'nome_responsavel' => 'Responsável de Teste', 'cpf_responsavel' => '111.111.111-11',
        ]);

        // E-mail continua único entre responsáveis (comparando já normalizado)
        $this->comToken($token)->patchJson('/api/v1/responsavel', ['email_responsavel' => 'OCUPADO@exemplo.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email_responsavel' => 'Este e-mail já está cadastrado para outro responsável.']);
        $this->comToken($token)->patchJson('/api/v1/responsavel', ['whatsapp_responsavel' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp_responsavel');
        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $outro, 'email_responsavel' => 'ocupado@exemplo.com']);
    }

    public function test_responsavel_troca_a_senha_e_encerra_os_outros_aparelhos(): void
    {
        [$idResponsavel] = $this->responsavelComSenha('mae@exemplo.com');
        $token = $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel');
        $this->login('mae@exemplo.com', self::SENHA_RESPONSAVEL, 'responsavel'); // outro aparelho

        $this->comToken($token)->putJson('/api/v1/responsavel/senha', [
            'senha_atual' => 'errada', 'nova_senha' => 'senha-nova-123', 'nova_senha_confirmation' => 'senha-nova-123',
        ])->assertUnprocessable()->assertJsonPath('message', 'Senha atual incorreta.');

        $this->comToken($token)->putJson('/api/v1/responsavel/senha', [
            'senha_atual' => self::SENHA_RESPONSAVEL, 'nova_senha' => 'senha-nova-123', 'nova_senha_confirmation' => 'senha-nova-123',
        ])->assertOk()->assertJsonPath('message', 'Senha alterada com sucesso.');

        $this->assertTrue(Hash::check('senha-nova-123', DB::table('tbl_responsavel')->where('id_responsavel', $idResponsavel)->value('senha_responsavel')));
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->comToken($token)->getJson('/api/v1/responsavel')->assertOk();
    }

    // ---------- helpers ----------

    private function atletaComSenha(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA_ATLETA)]);

        return $id;
    }

    // Responsável com senha e um filho ATIVO: [id_responsavel, id_atleta]
    private function responsavelComSenha(string $email): array
    {
        $idFilho = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idResponsavel = $this->criarResponsavel($idFilho);
        DB::table('tbl_responsavel')->where('id_responsavel', $idResponsavel)
            ->update(['email_responsavel' => $email, 'senha_responsavel' => Hash::make(self::SENHA_RESPONSAVEL)]);

        return [$idResponsavel, $idFilho];
    }

    private function vincular(int $idAtleta, int $idResponsavel): void
    {
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel, 'grau_parentesco_responsavel' => 'Mãe']);
    }

    private function login(string $email, string $senha, string $perfil): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => $senha, 'perfil' => $perfil])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        // Cada requisição resolve o token de novo (sem reaproveitar o usuário da anterior)
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
