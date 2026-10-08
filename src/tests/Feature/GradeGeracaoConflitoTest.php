<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Conflito de horário na geração da grade (Fase 7, Etapa 3): contra os eventos existentes e dentro do
 * próprio lote, agrupado por par de eventos; conflito real só gera confirmando; aviso fraco só informa.
 * "Hoje" fixo em 16/11/2026; gera dezembro inteiro. Sub-15 M treina ter/qui 09:30–11:00:
 * dias 1, 3, 8, 10, 15, 17, 22, 24, 29 e 31 (10 eventos).
 */
class GradeGeracaoConflitoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $sub15;
    private array $atletas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-16 10:00:00');
        $this->admin = User::factory()->admin()->create();

        $this->sub15 = $this->grade(['categoria_grade_treino' => 'Sub-15', 'id_categoria' => $this->idCategoria('Sub-15', 'M')]);
        foreach (['Ana', 'Bia'] as $nome) {
            $this->atletas[$nome] = $this->atleta($nome);
        }
    }

    // ---------- regra única ----------

    public function test_comparar_intervalos(): void
    {
        $h = fn (string $inicio, string $fim) => [Carbon::parse("2026-12-01 {$inicio}"), Carbon::parse("2026-12-01 {$fim}")];

        $this->assertSame('REAL', EventoCalendario::compararIntervalos($h('09:30', '11:00'), $h('10:00', '12:00')));
        $this->assertNull(EventoCalendario::compararIntervalos($h('09:30', '11:00'), $h('11:00', '12:00'))); // encosta
        $this->assertSame('FRACO', EventoCalendario::compararIntervalos($h('09:30', '11:00'), null));
        $this->assertSame('FRACO', EventoCalendario::compararIntervalos(null, null));
    }

    // ---------- contra os eventos existentes ----------

    public function test_conflito_real_com_evento_existente_aparece_agrupado_na_previa(): void
    {
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Casa x Visitante');

        $resposta = $this->previa();
        $conflitos = $resposta->viewData('previa')['conflitos'];

        $this->assertCount(1, $conflitos['REAL']);
        $grupo = $conflitos['REAL'][0];
        $this->assertSame('2026-12-08', $grupo['data']->toDateString());
        $this->assertSame('Treino Sub-15 Masculino', $grupo['novo']->titulo_evento_calendario);
        $this->assertSame('Casa x Visitante', $grupo['outro']->titulo_evento_calendario);
        $this->assertFalse($grupo['outro_no_lote']);
        $this->assertSame(['Ana', 'Bia'], $grupo['atletas']);
        $this->assertSame(['reais' => 2, 'fracos' => 0, 'dias' => 1, 'atletas' => 2], $conflitos['totais']);

        $resposta->assertSee('Conflito de horário')
            ->assertSee('2 conflito(s) em 1 dia(s), 2 atleta(s).')
            ->assertSee('JOGO "Casa x Visitante" (10:00 às 12:00)', false)
            ->assertSee('Confirmar mesmo assim e gerar 10 evento(s)')
            ->assertSee('name="confirmar_conflito" value="1"', false);
    }

    public function test_aviso_fraco_com_evento_sem_horario_so_informa(): void
    {
        $this->eventoDaCategoria('2026-12-10', null, null, 'Avaliação');

        $resposta = $this->previa();
        $conflitos = $resposta->viewData('previa')['conflitos'];

        $this->assertCount(0, $conflitos['REAL']);
        $this->assertCount(1, $conflitos['FRACO']);
        $resposta->assertSee('Mesmo dia — confira o horário')
            ->assertSee('Gerar 10 evento(s)')
            ->assertDontSee('name="confirmar_conflito"', false);

        // Gera sem confirmar e mostra o aviso azul na lista
        $this->gerar()->assertRedirect(route('admin.calendario.index', ['mes' => '2026-12']))
            ->assertSessionHas('avisos_mesmo_dia', fn ($avisos) => count($avisos) === 1 && str_contains($avisos[0], '10/12')
                && str_contains($avisos[0], '"Avaliação" (A definir): Ana, Bia'));
        $this->assertSame(10, EventoCalendario::whereNotNull('id_grade_treino')->count());
    }

    public function test_nao_contam_cancelado_oculto_outro_dia_encostado_e_atleta_inativo(): void
    {
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Cancelado')->mudarStatus('CANCELADO', null);
        $this->eventoDaCategoria('2026-12-15', '10:00', '12:00', 'Oculto')->mudarStatus('INATIVO', null);
        $this->eventoDaCategoria('2026-12-09', '10:00', '12:00', 'Quarta');      // dia sem treino
        $this->eventoDaCategoria('2026-12-22', '11:00', '12:00', 'Encostado');   // começa quando o treino acaba

        $this->assertSame(0, $this->previa()->viewData('previa')['totais']['conflitos_reais']);

        // Atleta inativo no lote não conta (mesmo que esteja inscrito no outro evento)
        $outro = $this->eventoDaCategoria('2026-12-29', '10:00', '12:00', 'Jogo');
        DB::table('tbl_atletas')->whereIn('id_atleta', array_values($this->atletas))->update(['status_atleta' => 'INATIVO']);
        $evento = new EventoCalendario(GradeTreino::find($this->sub15)->dadosEventoPara(Carbon::parse('2026-12-29')));
        $conflitos = EventoCalendario::conflitosEmLote([['evento' => $evento, 'atletas' => array_values($this->atletas)]]);
        $this->assertSame(0, $conflitos['totais']['reais']);
        $this->assertNotNull($outro);
    }

    // ---------- dentro do lote ----------

    public function test_conflito_entre_eventos_do_proprio_lote_conta_cada_par_uma_vez(): void
    {
        // Integrado (todos os ativos) ter/qui 10:00–12:00 sobrepõe o Sub-15 (09:30–11:00) em todos os dias
        $caio = $this->atleta('Caio', 'Sub-17');
        $this->grade(['categoria_grade_treino' => 'Integrado', 'horario_inicio_grade_treino' => '10:00', 'horario_fim_grade_treino' => '12:00']);

        $conflitos = $this->previa()->viewData('previa')['conflitos'];

        $this->assertCount(10, $conflitos['REAL']); // um grupo por dia, não dois
        foreach ($conflitos['REAL'] as $grupo) {
            $this->assertTrue($grupo['outro_no_lote']);
            $this->assertSame(['Ana', 'Bia'], $grupo['atletas']); // Caio só está no Integrado
        }
        $this->assertSame(20, $conflitos['totais']['reais']);
        $this->assertSame(2, $conflitos['totais']['atletas']);
        $this->assertNotNull($caio);

        $this->previa()->assertSee('(também será gerado)');
    }

    // ---------- confirmação no POST ----------

    public function test_conflito_real_sem_confirmar_nao_grava_nada(): void
    {
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Casa x Visitante');

        $this->gerar()
            ->assertRedirect(route('admin.calendario.grade.previa', ['mes' => '2026-12']))
            ->assertSessionHas('erro', fn ($msg) => str_contains($msg, 'Nada foi gerado: o lote tem 2 conflito(s) de horário'));

        $this->assertSame(0, EventoCalendario::whereNotNull('id_grade_treino')->count());
        $this->assertSame(2, DB::table('tbl_evento_atleta')->count()); // só as do jogo
        $this->assertSame(0, DB::table('tbl_notificacao')->where('tipo_notificacao', 'AGENDA')->count());
    }

    public function test_confirmando_gera_o_lote_inteiro_e_informa_os_conflitos(): void
    {
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Casa x Visitante');

        $this->gerar(['confirmar_conflito' => 1])
            ->assertRedirect(route('admin.calendario.index', ['mes' => '2026-12']))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '10 evento(s) gerado(s), 20 inscrição(ões).')
                && str_contains($msg, 'O lote tinha 2 conflito(s) de horário, confirmado(s).'));

        $this->assertSame(10, EventoCalendario::whereNotNull('id_grade_treino')->count());
    }

    public function test_sem_conflito_gera_sem_confirmar(): void
    {
        $this->gerar()->assertSessionHas('sucesso', fn ($msg) => ! str_contains($msg, 'conflito'));
        $this->assertSame(10, EventoCalendario::whereNotNull('id_grade_treino')->count());
    }

    // ---------- apresentação ----------

    public function test_mais_de_cinco_atletas_no_grupo_ficam_no_details(): void
    {
        foreach (['Caio', 'Duda', 'Edu', 'Fabi', 'Gabi'] as $nome) {
            $this->atleta($nome);
        }
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Casa x Visitante');

        $this->previa()
            ->assertSee('7 atleta(s)')
            ->assertSee('Ana, Bia, Caio, Duda, Edu')
            ->assertSee('<summary class="d-inline text-primary" style="cursor:pointer;">+2</summary>', false)
            ->assertSee('Fabi, Gabi');
    }

    // ---------- consultas ----------

    public function test_calculo_do_lote_nao_faz_uma_consulta_por_evento(): void
    {
        $this->eventoDaCategoria('2026-12-08', '10:00', '12:00', 'Casa x Visitante');
        $lote = GradeTreino::previaDoMes('2026-12')['linhas']->flatMap(fn ($l) => $l['lote'])->all();
        $this->assertCount(10, $lote);

        $contar = function (array $lote) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            EventoCalendario::conflitosEmLote($lote);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $dez   = $contar($lote);
        $vinte = $contar([...$lote, ...$lote]);

        $this->assertLessThanOrEqual(3, $dez);
        $this->assertSame($dez, $vinte);
    }

    // ---------- helpers ----------

    private function previa()
    {
        return $this->actingAs($this->admin, 'admin')
            ->get(route('admin.calendario.grade.previa', ['mes' => '2026-12']))
            ->assertOk();
    }

    private function gerar(array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-12', ...$extra]);
    }

    private function atleta(string $nome, string $categoria = 'Sub-15'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria($categoria, 'M'));

        return $id;
    }

    // Evento manual da Sub-15 M (já nasce com os atletas ATIVO dela inscritos)
    private function eventoDaCategoria(string $data, ?string $inicio, ?string $fim, string $titulo): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idCategoria('Sub-15', 'M'),
            'data_evento_calendario' => $data, 'horario_inicio_evento_calendario' => $inicio, 'horario_fim_evento_calendario' => $fim,
            'status_evento_calendario' => 'ATIVO',
        ]);
    }

    // Horário de terça e quinta: duas linhas iguais (uma por dia); devolve o id da de terça
    private function grade(array $dados): int
    {
        $linha = array_merge([
            'categoria_grade_treino'      => 'Integrado',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '09:30',
            'horario_fim_grade_treino'    => '11:00',
            'local_grade_treino'          => 'Campo A',
            'status_grade_treino'         => 'ATIVO',
        ], $dados);

        DB::table('tbl_grade_treino')->insert(['dia_semana_grade_treino' => 'quinta'] + $linha);

        return DB::table('tbl_grade_treino')->insertGetId(['dia_semana_grade_treino' => 'terca'] + $linha);
    }
}
