<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 10, Etapa 3: página geral de notificações do admin (todas, inclusive AGENDA sem evento), por mês,
 * com filtros na URL, paginação de 50 e poucas consultas.
 */
class NotificacoesPaginaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $ana;
    private int $bia;
    private EventoCalendario $treino;

    protected function setUp(): void
    {
        parent::setUp();

        // Meio do mês: as datas "do mês" e "do mês passado" não dependem do dia em que a suíte roda
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));

        $this->admin = User::factory()->admin()->create(['nome_usuario' => 'Admin Teste']);
        $this->ana = $this->atletaComNome('Ana Teste');
        $this->bia = $this->atletaComNome('Bia Teste');
        $this->treino = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Treino Sub-13', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => '2026-11-03', 'status_evento_calendario' => 'ATIVO',
        ], inscreverCategoria: false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_so_quem_esta_logado_no_admin_acessa(): void
    {
        $this->get(route('admin.notificacoes.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin, 'admin')->get(route('admin.notificacoes.index'))->assertOk();
    }

    public function test_mostra_o_mes_atual_com_agenda_sem_evento_e_link_para_o_evento(): void
    {
        $doEvento = $this->notificar($this->ana, ['id_evento_calendario' => $this->treino->id_evento_calendario, 'id_usuario' => $this->admin->id_usuario]);
        $agenda   = $this->notificar($this->bia, [
            'tipo_notificacao' => 'AGENDA', 'titulo_notificacao' => 'Agenda de novembro disponível',
            'mensagem_notificacao' => 'Seus 8 treinos de novembro de 2026 já estão na agenda.',
        ]);
        $passada  = $this->notificar($this->ana, ['data_notificacao' => '2026-09-20 10:00:00']);

        $resposta = $this->comoAdmin()->get(route('admin.notificacoes.index'))->assertOk();
        $this->assertSame([$agenda->id_notificacao, $doEvento->id_notificacao], $this->ids($resposta));

        $resposta->assertSee('Agenda de novembro disponível')
            ->assertSee('Seus 8 treinos de novembro de 2026 já estão na agenda.')
            ->assertSee('<a href="' . route('admin.calendario.eventos.show', $this->treino->id_evento_calendario) . '">Treino Sub-13</a>', false)
            ->assertSee('<span class="text-muted">—</span>', false) // AGENDA: sem evento
            ->assertSee('Admin Teste')
            ->assertSee('Outubro de 2026');

        // Outro mês pela URL (setas e select levam o mês)
        $this->assertSame([$passada->id_notificacao], $this->ids($this->comoAdmin()->get(route('admin.notificacoes.index', ['mes' => '2026-09']))));
        $resposta->assertSee(route('admin.notificacoes.index', ['mes' => '2026-09']), false)
            ->assertSee(route('admin.notificacoes.index', ['mes' => '2026-11']), false);
    }

    public function test_filtros_de_atleta_tipo_e_leitura(): void
    {
        $anaInscricao = $this->notificar($this->ana);
        $anaAgenda    = $this->notificar($this->ana, ['tipo_notificacao' => 'AGENDA']);
        $biaInscricao = $this->notificar($this->bia);
        DB::table('tbl_notificacao')->where('id_notificacao', $anaAgenda->id_notificacao)->update(['data_leitura_notificacao' => '2026-10-15 13:30:00']);

        $this->assertEqualsCanonicalizing([$anaInscricao->id_notificacao, $anaAgenda->id_notificacao], $this->ids($this->lista(['atleta' => $this->ana])));
        $this->assertEqualsCanonicalizing([$anaInscricao->id_notificacao, $biaInscricao->id_notificacao], $this->ids($this->lista(['tipo' => 'INSCRICAO'])));
        $this->assertSame([$anaAgenda->id_notificacao], $this->ids($this->lista(['leitura' => 'lidas'])));
        $this->assertEqualsCanonicalizing([$anaInscricao->id_notificacao, $biaInscricao->id_notificacao], $this->ids($this->lista(['leitura' => 'nao_lidas'])));
        $this->assertSame([$anaInscricao->id_notificacao], $this->ids($this->lista(['atleta' => $this->ana, 'tipo' => 'INSCRICAO', 'leitura' => 'nao_lidas'])));

        // Filtros vêm marcados; inválidos são ignorados
        $this->lista(['atleta' => $this->ana, 'tipo' => 'AGENDA', 'leitura' => 'lidas'])
            ->assertSee('<option value="' . $this->ana . '" selected>Ana Teste</option>', false)
            ->assertSee('<option value="AGENDA" selected>Agenda</option>', false)
            ->assertSee('<option value="lidas" selected>Lidas</option>', false)
            // Trocar de mês mantém os filtros
            ->assertSee(e(route('admin.notificacoes.index', ['mes' => '2026-09', 'atleta' => $this->ana, 'tipo' => 'AGENDA', 'leitura' => 'lidas'])), false);
        $this->assertCount(3, $this->ids($this->lista(['atleta' => 99999, 'tipo' => 'XYZ', 'leitura' => 'talvez'])));
    }

    public function test_leitura_do_atleta_e_dos_responsaveis(): void
    {
        $r1 = $this->criarResponsavel($this->ana);
        $r2 = $this->segundoResponsavel($this->ana);

        $lida = $this->notificar($this->ana);
        DB::table('tbl_notificacao')->where('id_notificacao', $lida->id_notificacao)->update(['data_leitura_notificacao' => '2026-10-15 13:30:00']);
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $lida->id_notificacao, 'id_responsavel' => $r1]);
        $this->notificar($this->bia); // sem responsável: "—"

        $this->comoAdmin()->get(route('admin.notificacoes.index'))
            ->assertSee('15/10/2026 13:30')
            ->assertSee('Não lida')
            ->assertSee('1 de 2 leram');

        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $lida->id_notificacao, 'id_responsavel' => $r2]);
        $this->comoAdmin()->get(route('admin.notificacoes.index'))->assertSee('2 de 2 leram');
    }

    public function test_ordem_da_mais_nova_e_no_empate_pelo_id(): void
    {
        $antiga   = $this->notificar($this->ana, ['data_notificacao' => '2026-10-10 08:00:00']);
        $empate1  = $this->notificar($this->ana, ['data_notificacao' => '2026-10-12 08:00:00']);
        $empate2  = $this->notificar($this->bia, ['data_notificacao' => '2026-10-12 08:00:00']);
        $nova     = $this->notificar($this->bia, ['data_notificacao' => '2026-10-14 08:00:00']);

        $this->assertSame(
            [$nova->id_notificacao, $empate2->id_notificacao, $empate1->id_notificacao, $antiga->id_notificacao],
            $this->ids($this->comoAdmin()->get(route('admin.notificacoes.index'))),
        );
    }

    public function test_paginacao_de_50_mantem_os_filtros(): void
    {
        $agora = now();
        $linhas = [];
        for ($i = 0; $i < 55; $i++) {
            $linhas[] = [
                'id_atleta' => $this->ana, 'tipo_notificacao' => 'AGENDA', 'titulo_notificacao' => "Aviso {$i}",
                'mensagem_notificacao' => 'Texto', 'data_notificacao' => $agora->copy()->subMinutes($i),
            ];
        }
        DB::table('tbl_notificacao')->insert($linhas);
        $this->notificar($this->bia); // fora do filtro

        $filtros  = ['mes' => '2026-10', 'atleta' => $this->ana, 'tipo' => 'AGENDA'];
        $primeira = $this->lista($filtros);
        $this->assertCount(50, $this->ids($primeira));
        $primeira->assertSee('55 notificação(ões) em Outubro de 2026')
            ->assertSee(e(route('admin.notificacoes.index', [...$filtros, 'page' => 2])), false);

        $segunda = $this->lista([...$filtros, 'page' => 2]);
        $this->assertCount(5, $this->ids($segunda));
        $segunda->assertSee('Aviso 54');
    }

    public function test_poucas_consultas_qualquer_que_seja_o_numero_de_linhas(): void
    {
        $contar = function () {
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->comoAdmin()->get(route('admin.notificacoes.index'))->assertOk();

            return $n;
        };

        $this->notificar($this->ana, ['id_evento_calendario' => $this->treino->id_evento_calendario, 'id_usuario' => $this->admin->id_usuario]);
        $poucas = $contar();

        // Mais atletas, eventos, responsáveis e leituras: o número de consultas não muda
        for ($i = 0; $i < 10; $i++) {
            $atleta = $this->atletaComNome("Atleta {$i}");
            $resp   = $this->criarResponsavel($atleta);
            $evento = EventoCalendario::criarPor($this->admin->id_usuario, [
                'titulo_evento_calendario' => "Evento {$i}", 'tipo_evento_calendario' => 'REUNIAO',
                'data_evento_calendario' => '2026-11-10', 'status_evento_calendario' => 'ATIVO',
            ], inscreverCategoria: false);
            $n = $this->notificar($atleta, ['id_evento_calendario' => $evento->id_evento_calendario, 'id_usuario' => $this->admin->id_usuario]);
            DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $n->id_notificacao, 'id_responsavel' => $resp]);
        }

        $this->assertSame($poucas, $contar());
    }

    public function test_menu_e_link_ver_todas_na_tela_do_evento(): void
    {
        $resposta = $this->comoAdmin()->get(route('admin.notificacoes.index'))->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('admin.notificacoes.index'), '#') . '" class="nav-link active"#', $resposta);

        // "ver todas" abre só as notificações do evento (de qualquer mês de envio)
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $this->treino->id_evento_calendario))
            ->assertSee('href="' . route('admin.notificacoes.index', ['evento' => $this->treino->id_evento_calendario]) . '"', false)
            ->assertSee('ver todas');
    }

    public function test_filtro_por_evento_mostra_as_dele_de_qualquer_mes(): void
    {
        $outro = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Reunião de Pais', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => '2026-11-20', 'status_evento_calendario' => 'ATIVO',
        ], inscreverCategoria: false);
        $doTreino = ['id_evento_calendario' => $this->treino->id_evento_calendario];

        $setembro = $this->notificar($this->ana, [...$doTreino, 'data_notificacao' => '2026-09-05 10:00:00']);
        $outubro  = $this->notificar($this->bia, [...$doTreino, 'tipo_notificacao' => 'ALTERACAO']);
        $novembro = $this->notificar($this->ana, [...$doTreino, 'data_notificacao' => '2026-11-02 08:00:00']);
        $this->notificar($this->ana, ['id_evento_calendario' => $outro->id_evento_calendario]); // outro evento
        $this->notificar($this->bia, ['tipo_notificacao' => 'AGENDA']);                          // sem evento

        $filtro = ['evento' => $this->treino->id_evento_calendario];
        $resposta = $this->lista($filtro);
        $this->assertSame([$novembro->id_notificacao, $outubro->id_notificacao, $setembro->id_notificacao], $this->ids($resposta));

        // Mostra qual evento está filtrado, com o jeito de limpar; o mês some
        $resposta->assertSee('Só as notificações do evento')
            ->assertSee('<a href="' . route('admin.calendario.eventos.show', $this->treino->id_evento_calendario) . '" class="fw-semibold">Treino Sub-13</a>', false)
            ->assertSee('Limpar filtro do evento')
            ->assertSee('href="' . route('admin.notificacoes.index', ['mes' => '2026-10']) . '"', false)
            ->assertSee('3 notificação(ões) deste evento')
            ->assertDontSee('id="formMesLista"', false)
            ->assertSee('<input type="hidden" name="evento" value="' . $this->treino->id_evento_calendario . '">', false);

        // Convive com os outros filtros; limpar o evento mantém eles
        $this->assertSame([$outubro->id_notificacao], $this->ids($this->lista([...$filtro, 'tipo' => 'ALTERACAO'])));
        $this->lista([...$filtro, 'atleta' => $this->ana])
            ->assertSee('href="' . e(route('admin.notificacoes.index', ['mes' => '2026-10', 'atleta' => $this->ana])) . '"', false);

        // Evento inexistente: sem filtro (o mês volta a valer)
        $this->lista(['evento' => 999999])->assertDontSee('Só as notificações do evento')->assertSee('id="formMesLista"', false);
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function lista(array $filtros)
    {
        return $this->comoAdmin()->get(route('admin.notificacoes.index', $filtros))->assertOk();
    }

    private function ids($resposta): array
    {
        return collect($resposta->viewData('notificacoes')->items())->pluck('id_notificacao')->all();
    }

    private function atletaComNome(string $nome): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);

        return $id;
    }

    // Segundo responsável do mesmo atleta (o helper usa um e-mail por atleta, que é único)
    private function segundoResponsavel(int $idAtleta): int
    {
        $id = DB::table('tbl_responsavel')->insertGetId([
            'id_endereco' => DB::table('tbl_endereco')->value('id_endereco'), 'nome_responsavel' => 'Pai de Teste',
            'cpf_responsavel' => '222.222.222-22', 'rg_responsavel' => '22.222.222-2', 'whatsapp_responsavel' => '(11) 98888-8888',
            'email_responsavel' => "pai{$idAtleta}@teste.com",
        ]);
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $idAtleta, 'id_responsavel' => $id, 'grau_parentesco_responsavel' => 'Pai']);

        return $id;
    }

    private function notificar(int $idAtleta, array $extra = []): Notificacao
    {
        return Notificacao::create(array_merge([
            'id_atleta'            => $idAtleta,
            'tipo_notificacao'     => 'INSCRICAO',
            'titulo_notificacao'   => 'Nova atividade na sua agenda',
            'mensagem_notificacao' => 'Treino Sub-13 Masculino · ter, 03/11 · Campo A',
            'data_notificacao'     => now(),
        ], $extra));
    }
}
