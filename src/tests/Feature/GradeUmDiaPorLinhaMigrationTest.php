<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration que separa as linhas de dois dias da grade (segunda_quarta, terca_quinta) em uma linha por dia.
 *
 * Ela muda o ENUM (ALTER TABLE), e DDL no MySQL confirma a transação aberta: por isso esta classe roda SEM a
 * transação do RefreshDatabase e, no fim de cada teste, marca o banco para o próximo teste recriá-lo
 * (migrate:fresh). A trava do db_futebol_test continua valendo. Cada teste começa voltando o ENUM ao antigo
 * pelo down() (com a grade vazia) e grava os dados no formato de antes.
 *
 * Outubro de 2026: segundas 05 e 12, terças 06 e 13, quartas 07 e 14, quintas 08 e 15, sexta 09.
 */
class GradeUmDiaPorLinhaMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_separa_as_linhas_de_dois_dias_e_move_os_eventos_pela_data_de_origem(): void
    {
        $this->voltarAoEnumAntigo();

        $segQua = $this->linha('segunda_quarta', ['categoria_grade_treino' => 'Sub-9', 'horario_inicio_grade_treino' => '08:00:00',
            'horario_fim_grade_treino' => '09:30:00', 'ordem_grade_treino' => 2]);
        $terQui = $this->linha('terca_quinta', ['categoria_grade_treino' => 'Sub-13', 'status_grade_treino' => 'INATIVO']);
        $sexta  = $this->linha('sexta', ['categoria_grade_treino' => 'Integrado']);

        $seg05 = $this->evento($segQua, '2026-10-05');
        $qua07 = $this->evento($segQua, '2026-10-07');
        $seg12 = $this->evento($segQua, '2026-10-12');
        $qua14 = $this->evento($segQua, '2026-10-14', '2026-10-15'); // mudou de dia à mão: vale a data de origem
        $ter06 = $this->evento($terQui, '2026-10-06');
        $qui08 = $this->evento($terQui, '2026-10-08');
        $sex09 = $this->evento($sexta, '2026-10-09');

        $this->migration()->up();

        // As linhas originais ficam com o primeiro dia; sexta não muda
        $this->assertSame('segunda', $this->dia($segQua));
        $this->assertSame('terca', $this->dia($terQui));
        $this->assertSame('sexta', $this->dia($sexta));

        // Uma cópia para o segundo dia, com os mesmos dados
        $quarta = DB::table('tbl_grade_treino')->where('dia_semana_grade_treino', 'quarta')->sole();
        $quinta = DB::table('tbl_grade_treino')->where('dia_semana_grade_treino', 'quinta')->sole();
        $this->assertSame(['Sub-9', '08:00:00', '09:30:00', 2, 'Campo AACJ', 'ATIVO'], [$quarta->categoria_grade_treino,
            $quarta->horario_inicio_grade_treino, $quarta->horario_fim_grade_treino, $quarta->ordem_grade_treino,
            $quarta->local_grade_treino, $quarta->status_grade_treino]);
        $this->assertSame(['Sub-13', 'INATIVO'], [$quinta->categoria_grade_treino, $quinta->status_grade_treino]);
        $this->assertSame(5, DB::table('tbl_grade_treino')->count());

        // Eventos de segunda e terça ficam; os de quarta e quinta (pela origem) vão para a cópia
        $this->assertSame($segQua, $this->gradeDoEvento($seg05));
        $this->assertSame($segQua, $this->gradeDoEvento($seg12));
        $this->assertSame($quarta->id_grade_treino, $this->gradeDoEvento($qua07));
        $this->assertSame($quarta->id_grade_treino, $this->gradeDoEvento($qua14));
        $this->assertSame($terQui, $this->gradeDoEvento($ter06));
        $this->assertSame($quinta->id_grade_treino, $this->gradeDoEvento($qui08));
        $this->assertSame($sexta, $this->gradeDoEvento($sex09));

        // ENUM só com os 7 dias
        $this->assertSame("enum('segunda','terca','quarta','quinta','sexta','sabado','domingo')", $this->tipoDaColuna());
    }

    public function test_up_trava_evento_com_data_de_origem_fora_dos_dias_da_linha_sem_mexer_em_nada(): void
    {
        $this->voltarAoEnumAntigo();

        $segQua = $this->linha('segunda_quarta');
        $this->evento($segQua, '2026-10-05');
        $terca = $this->evento($segQua, '2026-10-06');

        try {
            $this->migration()->up();
            $this->fail('A migration deveria parar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("{$terca}/{$segQua}/2026-10-06", $e->getMessage());
        }

        $this->assertSame('segunda_quarta', $this->dia($segQua));
        $this->assertSame(1, DB::table('tbl_grade_treino')->count());
        $this->assertSame("enum('segunda_quarta','terca_quinta','sexta','sabado')", $this->tipoDaColuna());
    }

    public function test_down_junta_os_pares_e_devolve_os_eventos(): void
    {
        $this->voltarAoEnumAntigo();

        $segQua = $this->linha('segunda_quarta');
        $terQui = $this->linha('terca_quinta');
        $sabado = $this->linha('sabado');
        $seg05  = $this->evento($segQua, '2026-10-05');
        $qua07  = $this->evento($segQua, '2026-10-07');
        $qui08  = $this->evento($terQui, '2026-10-08');

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame('segunda_quarta', $this->dia($segQua));
        $this->assertSame('terca_quinta', $this->dia($terQui));
        $this->assertSame('sabado', $this->dia($sabado));
        $this->assertSame(3, DB::table('tbl_grade_treino')->count());
        $this->assertSame($segQua, $this->gradeDoEvento($seg05));
        $this->assertSame($segQua, $this->gradeDoEvento($qua07));
        $this->assertSame($terQui, $this->gradeDoEvento($qui08));
        $this->assertSame("enum('segunda_quarta','terca_quinta','sexta','sabado')", $this->tipoDaColuna());
    }

    public function test_down_trava_com_domingo_ou_linha_sem_par_sem_mexer_em_nada(): void
    {
        // Banco já no formato novo (depois do up)
        $segunda = $this->linha('segunda', ['horario_inicio_grade_treino' => '08:00:00']);
        $this->linha('quarta', ['horario_inicio_grade_treino' => '14:00:00']); // horário diferente: não é par
        $domingo = $this->linha('domingo');

        try {
            $this->migration()->down();
            $this->fail('O down() deveria parar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("linha {$domingo} (domingo)", $e->getMessage());
            $this->assertStringContainsString("linha {$segunda} (segunda) sem quarta igual", $e->getMessage());
        }

        $this->assertSame(3, DB::table('tbl_grade_treino')->count());
        $this->assertSame("enum('segunda','terca','quarta','quinta','sexta','sabado','domingo')", $this->tipoDaColuna());
    }

    // ---------- apoio ----------

    private function migration(): object
    {
        return require database_path('migrations/2026_10_08_000001_grade_treino_um_dia_por_linha.php');
    }

    // Grade vazia: o down() só volta o ENUM ao formato antigo
    private function voltarAoEnumAntigo(): void
    {
        $this->migration()->down();
    }

    private function linha(string $dia, array $extra = []): int
    {
        return DB::table('tbl_grade_treino')->insertGetId(array_merge([
            'dia_semana_grade_treino' => $dia, 'categoria_grade_treino' => 'Integrado', 'tipo_grade_treino' => 'TREINO',
            'horario_inicio_grade_treino' => '10:00:00', 'horario_fim_grade_treino' => '11:00:00',
            'local_grade_treino' => 'Campo AACJ', 'ordem_grade_treino' => 1, 'status_grade_treino' => 'ATIVO',
        ], $extra));
    }

    private function evento(int $idGrade, string $dataOrigem, ?string $data = null): int
    {
        return DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => $data ?? $dataOrigem,
            'id_grade_treino' => $idGrade, 'data_grade_evento_calendario' => $dataOrigem,
        ]);
    }

    private function dia(int $idGrade): string
    {
        return DB::table('tbl_grade_treino')->where('id_grade_treino', $idGrade)->value('dia_semana_grade_treino');
    }

    private function gradeDoEvento(int $idEvento): int
    {
        return DB::table('tbl_evento_calendario')->where('id_evento_calendario', $idEvento)->value('id_grade_treino');
    }

    private function tipoDaColuna(): string
    {
        return DB::selectOne("SHOW COLUMNS FROM tbl_grade_treino LIKE 'dia_semana_grade_treino'")->Type;
    }
}
