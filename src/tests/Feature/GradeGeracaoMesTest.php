<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Geração da agenda do mês pela grade (Fase 7, Etapa 2): prévia, gerar (tudo ou nada), idempotência,
 * site público sem os gerados e a lista de eventos do admin por mês.
 * "Hoje" fixo: segunda-feira, 16/11/2026, 10:00 (meses permitidos: nov/2026, dez/2026 e jan/2027).
 */
class GradeGeracaoMesTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-16 10:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        EventoCalendario::flushEventListeners();
        parent::tearDown();
    }

    // ---------- meses permitidos ----------

    public function test_meses_permitidos_sao_o_atual_e_os_dois_seguintes(): void
    {
        $this->assertSame(['2026-11', '2026-12', '2027-01'], array_keys(\App\Models\GradeTreino::mesesPermitidos()));
        $this->assertSame('Novembro de 2026', \App\Models\GradeTreino::mesesPermitidos()['2026-11']);
    }

    public function test_mes_fora_do_permitido_e_recusado_na_previa_e_na_geracao(): void
    {
        $this->grade(['categoria_grade_treino' => 'Integrado']);

        foreach (['2026-10', '2027-02', 'abc', ''] as $mes) {
            $this->comoAdminFixo()->get(route('admin.calendario.grade.previa', ['mes' => $mes]))
                ->assertRedirect(route('admin.calendario.index', ['tab' => 'grade']))
                ->assertSessionHas('erro');
            $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => $mes])
                ->assertSessionHas('erro');
        }

        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_visitante_nao_acessa_a_geracao(): void
    {
        $this->get(route('admin.calendario.grade.previa', ['mes' => '2026-11']))->assertRedirect(route('admin.login'));
        $this->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])->assertRedirect(route('admin.login'));
        $this->assertSame(0, EventoCalendario::count());
    }

    // ---------- prévia ----------

    public function test_previa_mostra_datas_puladas_linhas_que_nao_geram_e_zero_atletas(): void
    {
        [$sub13] = $this->cenario();

        $resposta = $this->comoAdminFixo()->get(route('admin.calendario.grade.previa', ['mes' => '2026-11']))->assertOk();

        $previa = $resposta->viewData('previa');
        $linha  = $previa['linhas']->firstWhere('grade.id_grade_treino', $sub13);
        // Seg/qua a partir de hoje (16/11): o das 08:00 às 09:30 de hoje já passou
        $this->assertSame(['18/11', '23/11', '25/11', '30/11'], $linha['novas']->map->format('d/m')->all());
        $this->assertSame(['16/11'], $linha['puladas']->map->format('d/m')->all());

        $resposta->assertSee('Não gera: Jogos são criados na tela de Jogos.')
            ->assertSee('0 atletas')
            ->assertSee('16/11: hoje, já passou; não será gerado');

        // Prévia não grava nada
        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_previa_conta_eventos_e_inscricoes(): void
    {
        $this->cenario();

        $totais = $this->comoAdminFixo()->get(route('admin.calendario.grade.previa', ['mes' => '2026-11']))->viewData('previa')['totais'];

        // Sub-13 M: 4 x 2 atletas; Integrado (sex 20 e 27): 2 x 3 ativos; Sub-9 F (ter/qui 17, 19, 24, 26): 4 x 0
        $this->assertSame(10, $totais['eventos']);
        $this->assertSame(14, $totais['inscricoes']);
        $this->assertSame(1, $totais['puladas']);
        $this->assertSame(1, $totais['nao_geram']);
    }

    // ---------- gerar ----------

    public function test_gerar_cria_os_eventos_com_origem_e_as_inscricoes(): void
    {
        [$sub13, $integrado, , , $ana, $bia, $caio] = $this->cenario();

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2026-11']))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '10 evento(s) gerado(s), 14 inscrição(ões).'));

        $evento = EventoCalendario::where('id_grade_treino', $sub13)->where('data_grade_evento_calendario', '2026-11-18')->firstOrFail();
        $this->assertSame('Treino Sub-13 Masculino', $evento->titulo_evento_calendario);
        $this->assertSame('TREINO', $evento->tipo_evento_calendario);
        $this->assertSame('2026-11-18', $evento->data_evento_calendario->toDateString());
        $this->assertSame('08:00:00', $evento->horario_inicio_evento_calendario);
        $this->assertSame($this->idCategoria('Sub-13', 'M'), $evento->id_categoria);
        $this->assertSame($this->admin->id_usuario, $evento->id_usuario);
        $this->assertSame('ATIVO', $evento->status_evento_calendario);

        // Com categoria: só os ATIVO dela, origem CATEGORIA, com quem e quando
        $this->assertEqualsCanonicalizing([$ana, $bia], $evento->inscricoes()->pluck('id_atleta')->all());
        $this->assertSame(['CATEGORIA'], $evento->inscricoes()->distinct()->pluck('origem_evento_atleta')->all());
        $this->assertSame([$this->admin->id_usuario], $evento->inscricoes()->distinct()->pluck('id_usuario')->all());

        // Sem categoria: todos os ATIVO, origem INDIVIDUAL
        $integradoDia20 = EventoCalendario::where('id_grade_treino', $integrado)->where('data_grade_evento_calendario', '2026-11-20')->firstOrFail();
        $this->assertEqualsCanonicalizing([$ana, $bia, $caio], $integradoDia20->inscricoes()->pluck('id_atleta')->all());
        $this->assertSame(['INDIVIDUAL'], $integradoDia20->inscricoes()->distinct()->pluck('origem_evento_atleta')->all());

        // O treino de hoje que já passou não foi gerado; a linha "Jogos" também não
        $this->assertFalse(EventoCalendario::where('id_grade_treino', $sub13)->where('data_grade_evento_calendario', '2026-11-16')->exists());
        $this->assertSame(10, EventoCalendario::whereNotNull('id_grade_treino')->count());
    }

    public function test_gerar_de_novo_nao_duplica_nem_recria_cancelado_oculto_ou_mudado_de_dia(): void
    {
        [$sub13] = $this->cenario();
        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11']);

        $eventos = EventoCalendario::where('id_grade_treino', $sub13)->orderBy('data_grade_evento_calendario')->get();
        $eventos[0]->mudarStatus('CANCELADO', null);
        $eventos[1]->mudarStatus('INATIVO', null);
        $eventos[2]->atualizarComHistorico(['data_evento_calendario' => '2026-11-28'], null); // mudou de dia

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, 'Nada novo para gerar neste mês.'));

        $this->assertSame(10, EventoCalendario::whereNotNull('id_grade_treino')->count());

        // A prévia mostra os já gerados, com o status de quem não está ativo
        $this->comoAdminFixo()->get(route('admin.calendario.grade.previa', ['mes' => '2026-11']))
            ->assertSee('18/11 (cancelado)')
            ->assertSee('23/11 (oculto)');
    }

    public function test_gerar_e_tudo_ou_nada(): void
    {
        $this->cenario();

        // Simula outro admin gerando ao mesmo tempo: antes do 3º evento, alguém grava a mesma grade e data
        $criados = 0;
        EventoCalendario::creating(function (EventoCalendario $evento) use (&$criados) {
            if (++$criados === 3) {
                DB::table('tbl_evento_calendario')->insert([
                    'titulo_evento_calendario' => 'Concorrente', 'tipo_evento_calendario' => 'TREINO',
                    'data_evento_calendario' => $evento->data_grade_evento_calendario, 'status_evento_calendario' => 'ATIVO',
                    'id_grade_treino' => $evento->id_grade_treino, 'data_grade_evento_calendario' => $evento->data_grade_evento_calendario,
                ]);
            }
        });

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])
            ->assertRedirect(route('admin.calendario.grade.previa', ['mes' => '2026-11']))
            ->assertSessionHas('erro', fn ($msg) => str_contains($msg, 'Nada foi gravado'));

        // Nada ficou: nem os 2 primeiros eventos, nem inscrições, nem notificações
        $this->assertSame(0, EventoCalendario::count());
        $this->assertSame(0, DB::table('tbl_evento_atleta')->count());
        $this->assertSame(0, DB::table('tbl_notificacao')->count());
    }

    public function test_gerar_manda_uma_agenda_por_atleta_com_os_treinos_dele(): void
    {
        [, , , , $ana, $bia, $caio] = $this->cenario();

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '10 evento(s) gerado(s), 14 inscrição(ões). 3 atleta(s) notificado(s).'));

        // Uma AGENDA por atleta (não uma por inscrição); Duda, inativa, não está no lote
        $notificacoes = DB::table('tbl_notificacao')->get()->keyBy('id_atleta');
        $this->assertEqualsCanonicalizing([$ana, $bia, $caio], $notificacoes->keys()->all());
        $this->assertSame(['AGENDA'], $notificacoes->pluck('tipo_notificacao')->unique()->values()->all());

        // Ana: 4 treinos da Sub-13 + 2 Integrados; Caio (Sub-15): só os 2 Integrados
        $anaAgenda = \App\Models\Notificacao::where('id_atleta', $ana)->sole();
        $this->assertSame('Agenda de novembro disponível', $anaAgenda->titulo_notificacao);
        $this->assertSame('Seus 6 treinos de novembro de 2026 já estão na agenda.', $anaAgenda->mensagem_notificacao);
        $this->assertSame(['mes' => '2026-11', 'eventos' => 6], $anaAgenda->dados_notificacao);
        $this->assertNull($anaAgenda->id_evento_calendario);
        $this->assertSame($this->admin->id_usuario, $anaAgenda->id_usuario);
        $this->assertSame('Seus 2 treinos de novembro de 2026 já estão na agenda.', $notificacoes[$caio]->mensagem_notificacao);

        // Gerar de novo: nada novo, ninguém notificado de novo
        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])
            ->assertSessionHas('sucesso', fn ($msg) => str_starts_with($msg, 'Nada novo para gerar neste mês.'));
        $this->assertSame(3, DB::table('tbl_notificacao')->count());
    }

    public function test_agenda_com_um_treino_so_fica_no_singular(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));

        \App\Models\Notificacao::agendaDoMes(\Illuminate\Support\Carbon::parse('2026-12-01'), [$id => 1], null);

        $this->assertSame('Seu treino de dezembro de 2026 já está na agenda.', DB::table('tbl_notificacao')->value('mensagem_notificacao'));
    }

    public function test_mes_futuro_gera_todas_as_datas(): void
    {
        [$sub13] = $this->cenario();

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-12'])->assertSessionHas('sucesso');

        // Dezembro de 2026: seg 7, 14, 21, 28 e qua 2, 9, 16, 23, 30
        $this->assertSame(9, EventoCalendario::where('id_grade_treino', $sub13)->count());
    }

    // ---------- site público ----------

    public function test_site_nao_mostra_os_treinos_gerados(): void
    {
        $this->cenario();
        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11']);
        $manual = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino extra', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => '2026-11-21', 'horario_inicio_evento_calendario' => '15:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        $this->get('/calendario')->assertOk()
            ->assertViewHas('eventos', fn ($eventos) => $eventos->pluck('id_evento_calendario')->all() === [$manual->id_evento_calendario])
            ->assertViewHas('proximoEvento', fn ($proximo) => $proximo->id_evento_calendario === $manual->id_evento_calendario);
    }

    // ---------- lista de eventos do admin por mês ----------

    public function test_lista_mostra_so_o_mes_escolhido(): void
    {
        $nov = $this->eventoManual('2026-11-20');
        $dez = $this->eventoManual('2026-12-05');

        $ids = fn ($resposta) => $resposta->viewData('eventos')->pluck('id_evento_calendario')->all();

        // Padrão: mês atual
        $this->assertSame([$nov], $ids($this->comoAdminFixo()->get(route('admin.calendario.index'))));
        $this->assertSame([$dez], $ids($this->comoAdminFixo()->get(route('admin.calendario.index', ['mes' => '2026-12']))));
        // Inválido: mês atual
        $this->assertSame([$nov], $ids($this->comoAdminFixo()->get(route('admin.calendario.index', ['mes' => '2026-13']))));

        $this->comoAdminFixo()->get(route('admin.calendario.index', ['mes' => '2026-12']))
            ->assertSee('Dezembro de 2026')
            ->assertSee('id="filtroOrigem"', false)
            ->assertSee(route('admin.calendario.index', ['mes' => '2026-11']), false)  // seta: mês anterior
            ->assertSee(route('admin.calendario.index', ['mes' => '2027-01']), false); // seta: próximo mês
    }

    public function test_lista_marca_a_origem_de_cada_evento(): void
    {
        $this->cenario();
        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11']);
        $this->eventoManual('2026-11-21');

        $this->comoAdminFixo()->get(route('admin.calendario.index'))
            ->assertSee('data-origem="grade"', false)
            ->assertSee('data-origem="manual"', false)
            ->assertSee('Gerado pela grade');
    }

    public function test_criar_e_editar_abrem_a_lista_no_mes_do_evento(): void
    {
        $dados = [
            'titulo_evento_calendario' => 'Reunião', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => '2027-01-10', 'horario_inicio_evento_calendario' => '10:00',
        ];

        $this->comoAdminFixo()->post(route('admin.calendario.eventos.store'), $dados)
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2027-01']));

        $id = EventoCalendario::where('titulo_evento_calendario', 'Reunião')->value('id_evento_calendario');
        $this->comoAdminFixo()->put(route('admin.calendario.eventos.update', $id), [...$dados, 'data_evento_calendario' => '2026-12-03'])
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2026-12']));
    }

    public function test_cancelar_e_ocultar_pela_lista_voltam_ao_mes_do_evento(): void
    {
        $id = $this->eventoManual('2026-12-05');

        $this->comoAdminFixo()->from(route('admin.calendario.index', ['mes' => '2026-12']))
            ->patch(route('admin.calendario.eventos.cancelar', $id))
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2026-12']));

        $this->comoAdminFixo()->from(route('admin.calendario.index', ['mes' => '2026-12']))
            ->patch(route('admin.calendario.eventos.ocultar', $id))
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2026-12']));

        // Vindo de outra tela (ex.: Jogos), volta para ela
        $this->comoAdminFixo()->from(route('admin.jogos.index'))
            ->patch(route('admin.calendario.eventos.ocultar', $id))
            ->assertRedirect(route('admin.jogos.index'));
    }

    // ---------- helpers ----------

    private function comoAdminFixo(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * Grade e atletas do cenário:
     *  - Sub-13 M, seg/qua 08:00–09:30: Ana e Bia (ATIVO); Duda inativa e fora;
     *  - Integrado, sexta 18:00–19:00: todos os ATIVO (Ana, Bia e Caio, que é Sub-15 M);
     *  - Sub-9 F, ter/qui 07:00–08:00: nenhum atleta (0 atletas);
     *  - Jogos (tipo JOGO): não gera.
     */
    private function cenario(): array
    {
        $sub13 = $this->grade(['categoria_grade_treino' => 'Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'M'),
            'dia_semana_grade_treino' => 'segunda_quarta', 'horario_inicio_grade_treino' => '08:00', 'horario_fim_grade_treino' => '09:30']);
        $integrado = $this->grade(['categoria_grade_treino' => 'Integrado', 'dia_semana_grade_treino' => 'sexta',
            'horario_inicio_grade_treino' => '18:00', 'horario_fim_grade_treino' => '19:00']);
        $sub9f = $this->grade(['categoria_grade_treino' => 'Sub-9', 'id_categoria' => $this->idCategoria('Sub-9', 'F'),
            'dia_semana_grade_treino' => 'terca_quinta', 'horario_inicio_grade_treino' => '07:00', 'horario_fim_grade_treino' => '08:00']);
        $jogos = $this->grade(['categoria_grade_treino' => 'Jogos', 'tipo_grade_treino' => 'JOGO', 'dia_semana_grade_treino' => 'sabado',
            'horario_inicio_grade_treino' => null, 'horario_fim_grade_treino' => null]);

        $atleta = function (string $nome, string $status, string $categoria) {
            $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', $status);
            DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
            $this->colocarNaCategoria($id, $this->idCategoria($categoria, 'M'));

            return $id;
        };
        $ana  = $atleta('Ana', 'ATIVO', 'Sub-13');
        $bia  = $atleta('Bia', 'ATIVO', 'Sub-13');
        $caio = $atleta('Caio', 'ATIVO', 'Sub-15');
        $atleta('Duda', 'INATIVO', 'Sub-13');

        return [$sub13, $integrado, $sub9f, $jogos, $ana, $bia, $caio];
    }

    private function grade(array $dados): int
    {
        return DB::table('tbl_grade_treino')->insertGetId(array_merge([
            'dia_semana_grade_treino'     => 'sexta',
            'categoria_grade_treino'      => 'Integrado',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '08:00',
            'horario_fim_grade_treino'    => '09:30',
            'local_grade_treino'          => 'Campo A',
            'status_grade_treino'         => 'ATIVO',
        ], $dados));
    }

    private function eventoManual(string $data): int
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Evento ' . $data, 'tipo_evento_calendario' => 'EVENTO',
            'data_evento_calendario' => $data, 'status_evento_calendario' => 'ATIVO',
        ])->id_evento_calendario;
    }
}
