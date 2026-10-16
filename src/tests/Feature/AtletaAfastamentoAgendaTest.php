<?php

namespace Tests\Feature;

use App\Models\AtletaAfastamento;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Afastamento do atleta, Etapa 2 (16/10/2026): indisponível para tudo no período. A geração do mês pula as datas;
 * registrar (ou prorrogar) tira o atleta das atividades futuras do período, com um aviso só; encerrar ou excluir
 * devolve só os treinos da grade (pelo turno), e os jogos ficam para o técnico; inscrever à mão é bloqueado e os
 * caminhos em lote deixam o afastado de fora, com aviso.
 */
class AtletaAfastamentoAgendaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $enzo;   // estuda à tarde: treina de manhã
    private int $ana;    // estuda de manhã: treina à tarde
    private int $manha;
    private int $tarde;
    private int $idSub15M;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-04 10:00:00'); // quarta-feira
        $this->admin = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->enzo = $this->atleta('Enzo', 'TARDE');
        $this->ana  = $this->atleta('Ana', 'MANHA');

        // Grade real: terça e quinta, de manhã e à tarde, sem categoria
        foreach (['terca', 'quinta'] as $dia) {
            $this->manha = $this->grade($dia, '09:00', '11:00');
            $this->tarde = $this->grade($dia, '14:00', '17:00');
        }
    }

    protected function tearDown(): void
    {
        EventoCalendario::flushEventListeners();
        parent::tearDown();
    }

    public function test_geracao_do_mes_pula_as_datas_do_afastamento(): void
    {
        $this->afastar($this->enzo, '2026-11-04', '2026-11-12');

        $this->comoAdminFixo()->get(route('admin.calendario.grade.previa', ['mes' => '2026-11']))
            ->assertSee('1 atleta(s) afastado(s)')
            ->assertSee('Enzo (afastado até 12/11)');

        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])->assertSessionHas('sucesso');

        $this->assertSame(['2026-11-17', '2026-11-19', '2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));
        $this->assertCount(7, $this->datasDe($this->ana)); // 05 a 26/11, à tarde
    }

    public function test_registrar_tira_das_atividades_futuras_do_periodo_com_um_aviso(): void
    {
        $this->gerarNovembro();
        $passado = $this->eventoCom($this->enzo, '2026-11-03');
        $reuniao = $this->eventoCom($this->enzo, '2026-11-06');

        $this->comoAdminFixo()->post(route('admin.atletas.afastamentos.store', $this->enzo), [
            'data_inicio_atleta_afastamento' => '2026-11-04', 'data_fim_atleta_afastamento' => '2026-11-12',
            'motivo_atleta_afastamento' => 'Entorse',
        ])->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'Saiu de 4 atividades') && str_contains($m, '1 atleta(s) notificado(s)'));

        $this->assertSame(['2026-11-17', '2026-11-19', '2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));
        $this->assertTrue($this->inscrito($this->enzo, $passado));   // o passado fica como está
        $this->assertFalse($this->inscrito($this->enzo, $reuniao));  // indisponível para tudo, não só treino
        $this->assertCount(7, $this->datasDe($this->ana));

        $aviso = DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->orderByDesc('id_notificacao')->first();
        $this->assertSame('AGENDA', $aviso->tipo_notificacao);
        $this->assertSame('Afastamento registrado', $aviso->titulo_notificacao);
        $this->assertSame('Você está afastado de 04/11 a 12/11 e não participa das atividades nesse período. Você saiu de 4 atividades da agenda.',
            $aviso->mensagem_notificacao);
        $this->assertStringNotContainsString('Entorse', $aviso->mensagem_notificacao); // motivo só no admin
        $this->assertSame(0, DB::table('tbl_notificacao')->where('tipo_notificacao', 'REMOCAO')->count());
    }

    public function test_prorrogar_tira_do_periodo_novo_e_mudar_so_o_motivo_nao_avisa(): void
    {
        $this->gerarNovembro();
        $afastamento = $this->registrar('2026-11-04', '2026-11-12');
        $avisos = DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->count();

        $this->mudar($afastamento, '2026-11-04', '2026-11-12', 'Gripe forte');
        $this->assertSame($avisos, DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->count());

        $this->mudar($afastamento, '2026-11-04', '2026-11-19');
        $this->assertSame(['2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));
        $this->assertSame('Seu afastamento mudou', DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->orderByDesc('id_notificacao')->value('titulo_notificacao'));

        // Encurtar devolve os treinos dos dias liberados
        $this->mudar($afastamento, '2026-11-04', '2026-11-10');
        $this->assertSame(['2026-11-12', '2026-11-17', '2026-11-19', '2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));
    }

    public function test_encerrar_devolve_os_treinos_pelo_turno_e_lista_os_jogos_para_conferir(): void
    {
        $this->gerarNovembro();
        $jogo = $this->jogoComEnzoConvocado('2026-11-07');
        $afastamento = $this->registrar('2026-11-01', '2026-11-12');
        $this->assertFalse($this->inscrito($this->enzo, $jogo->id_evento));

        $this->comoAdminFixo()->patch(route('admin.atletas.afastamentos.encerrar', [$this->enzo, $afastamento->id_atleta_afastamento]))
            ->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'Voltou para 3 treino(s) da grade'))
            ->assertSessionHas('aviso', fn ($m) => str_contains($m, 'Preencher pela convocação') && str_contains($m, '07/11'));

        // Volta só aos treinos da manhã (o turno dele); o jogo fica com o técnico
        $this->assertSame(['2026-11-05', '2026-11-10', '2026-11-12', '2026-11-17', '2026-11-19', '2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));
        $this->assertSame(0, DB::table('tbl_evento_atleta')->where('id_atleta', $this->enzo)
            ->whereIn('id_evento_calendario', EventoCalendario::where('id_grade_treino', $this->tarde)->select('id_evento_calendario'))->count());
        $this->assertFalse($this->inscrito($this->enzo, $jogo->id_evento));
        $this->assertSame('Você volta às atividades a partir de hoje. Você voltou para 3 treinos da agenda.',
            DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->orderByDesc('id_notificacao')->value('mensagem_notificacao'));
    }

    public function test_excluir_afastamento_futuro_devolve_os_treinos(): void
    {
        $this->gerarNovembro();
        $afastamento = $this->registrar('2026-11-16', '2026-11-20');
        $this->assertSame(['2026-11-05', '2026-11-10', '2026-11-12', '2026-11-24', '2026-11-26'], $this->datasDe($this->enzo));

        $this->comoAdminFixo()->delete(route('admin.atletas.afastamentos.destroy', [$this->enzo, $afastamento->id_atleta_afastamento]))
            ->assertSessionHas('sucesso');

        $this->assertCount(7, $this->datasDe($this->enzo));
        $this->assertSame('Afastamento cancelado', DB::table('tbl_notificacao')->where('id_atleta', $this->enzo)->orderByDesc('id_notificacao')->value('titulo_notificacao'));
    }

    public function test_inscrever_a_mao_e_bloqueado_e_os_lotes_deixam_de_fora_com_aviso(): void
    {
        $this->afastar($this->enzo, '2026-11-04', '2026-11-12');
        $evento = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Avaliação física', 'tipo_evento_calendario' => 'AVALIACAO',
            'data_evento_calendario' => '2026-11-10', 'horario_inicio_evento_calendario' => '09:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        // Select da tela: desabilitado com o motivo
        $this->comoAdminFixo()->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertSee('Enzo · afastado até 12/11');

        $this->comoAdminFixo()->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $this->enzo])
            ->assertSessionHas('erro', 'Enzo está afastado até 12/11. Encerre o afastamento antes de inscrever.');

        // "Adicionar todos de uma categoria": a Ana entra, o Enzo fica de fora com o aviso
        $this->comoAdminFixo()->post(route('admin.calendario.eventos.inscricoes.categoria', $evento->id_evento_calendario), ['id_categoria' => $this->idSub15M])
            ->assertSessionHas('avisos_afastamento', ['Enzo, afastado até 12/11: fora de Avaliação física · 10/11.']);
        $this->assertTrue($this->inscrito($this->ana, $evento->id_evento_calendario));
        $this->assertFalse($this->inscrito($this->enzo, $evento->id_evento_calendario));

        // Fora do período, inscreve normalmente
        $depois = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Reunião', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => '2026-11-13', 'status_evento_calendario' => 'ATIVO',
        ]);
        $this->comoAdminFixo()->post(route('admin.calendario.eventos.inscricoes.store', $depois->id_evento_calendario), ['id_atleta' => $this->enzo])
            ->assertSessionHas('sucesso');
    }

    public function test_criar_jogo_deixa_o_convocado_afastado_de_fora(): void
    {
        $this->afastar($this->enzo, '2026-11-04', '2026-11-12');

        $jogo = $this->jogoComEnzoConvocado('2026-11-07', function ($resposta) {
            $resposta->assertSessionHas('avisos_afastamento', fn ($avisos) => str_contains($avisos[0], 'Enzo, afastado até 12/11'));
        });

        $this->assertFalse($this->inscrito($this->enzo, $jogo->id_evento));
        $this->assertTrue($this->inscrito($this->ana, $jogo->id_evento));
    }

    // ---------- ajudantes ----------

    private function comoAdminFixo(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function atleta(string $nome, string $periodo): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome, 'periodo_escolar_atleta' => $periodo]);
        $this->colocarNaCategoria($id, $this->idSub15M);

        return $id;
    }

    private function grade(string $dia, string $inicio, string $fim): int
    {
        return DB::table('tbl_grade_treino')->insertGetId([
            'dia_semana_grade_treino' => $dia, 'categoria_grade_treino' => 'Treino', 'tipo_grade_treino' => 'TREINO',
            'horario_inicio_grade_treino' => $inicio, 'horario_fim_grade_treino' => $fim, 'local_grade_treino' => 'Campo A',
            'status_grade_treino' => 'ATIVO',
        ]);
    }

    private function gerarNovembro(): void
    {
        $this->comoAdminFixo()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])->assertSessionHas('sucesso');
        $this->assertCount(7, $this->datasDe($this->enzo)); // 05 a 26/11, de manhã
    }

    // Datas dos treinos da grade em que o atleta está inscrito
    private function datasDe(int $idAtleta): array
    {
        return EventoCalendario::whereNotNull('id_grade_treino')
            ->whereHas('inscricoes', fn ($q) => $q->where('id_atleta', $idAtleta))
            ->orderBy('data_evento_calendario')->get()
            ->map(fn ($e) => $e->data_evento_calendario->toDateString())->all();
    }

    private function eventoCom(int $idAtleta, string $data): int
    {
        $evento = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => "Reunião {$data}", 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => $data, 'horario_inicio_evento_calendario' => '19:00', 'status_evento_calendario' => 'ATIVO',
        ]);
        $evento->inscrever($idAtleta, 'INDIVIDUAL', $this->admin->id_usuario);

        return $evento->id_evento_calendario;
    }

    private function inscrito(int $idAtleta, int $idEvento): bool
    {
        return DB::table('tbl_evento_atleta')->where('id_atleta', $idAtleta)->where('id_evento_calendario', $idEvento)->exists();
    }

    // Afastamento gravado direto (sem passar pela agenda)
    private function afastar(int $idAtleta, string $inicio, string $fim): AtletaAfastamento
    {
        return AtletaAfastamento::create(['id_atleta' => $idAtleta,
            'data_inicio_atleta_afastamento' => $inicio, 'data_fim_atleta_afastamento' => $fim]);
    }

    // Afastamento pela tela (com o efeito na agenda)
    private function registrar(string $inicio, string $fim): AtletaAfastamento
    {
        $this->comoAdminFixo()->post(route('admin.atletas.afastamentos.store', $this->enzo), [
            'data_inicio_atleta_afastamento' => $inicio, 'data_fim_atleta_afastamento' => $fim,
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        return AtletaAfastamento::where('id_atleta', $this->enzo)->latest('id_atleta_afastamento')->first();
    }

    private function mudar(AtletaAfastamento $afastamento, string $inicio, string $fim, ?string $motivo = null): void
    {
        $this->comoAdminFixo()->put(route('admin.atletas.afastamentos.update', [$this->enzo, $afastamento->id_atleta_afastamento]), [
            'data_inicio_atleta_afastamento' => $inicio, 'data_fim_atleta_afastamento' => $fim, 'motivo_atleta_afastamento' => $motivo,
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');
    }

    // Jogo de campeonato (AACJ x Visitante) com o Enzo e a Ana convocados, criado pela tela de Jogos
    private function jogoComEnzoConvocado(string $data, ?callable $conferir = null): Jogo
    {
        $liga = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Liga', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => '2026-11-01', 'data_fim_campeonato' => '2026-11-30', 'local_evento' => 'Campo A',
        ]);
        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
        $aacj = $time('AACJ Sub-15', 'INTERNO');
        $visitante = $time('Visitante', 'EXTERNO');
        $this->participantes($liga, [$aacj, $visitante]);
        $this->convocacaoDireta($liga, $aacj, $this->enzo, 10);
        $this->convocacaoDireta($liga, $aacj, $this->ana, 7);

        $resposta = $this->comoAdminFixo()->post(route('admin.jogos.store'), [
            'id_campeonato' => $liga, 'id_time_casa' => $aacj, 'id_time_visitante' => $visitante,
            'data_evento_calendario' => $data, 'horario_inicio_evento_calendario' => '13:00', 'horario_fim_evento_calendario' => '14:10',
            'local_evento_calendario' => 'Arena', 'confirmar_conflitos' => 1,
        ])->assertSessionHasNoErrors();
        if ($conferir) {
            $conferir($resposta);
        }

        return Jogo::latest('id_jogo')->first();
    }
}
