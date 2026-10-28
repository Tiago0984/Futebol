<?php

namespace Tests\Feature;

use App\Models\Time;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration das categorias do time (27/10/2026): tbl_time_categoria recebe a categoria do cadastro e as dos
 * campeonatos de cada time; a camisa passa a não repetir por campeonato, categoria e time. ALTER confirma a
 * transação aberta no MySQL: esta classe roda SEM a transação do RefreshDatabase e marca o banco para o próximo
 * teste recriá-lo (como CampeonatoCamposOpcionaisMigrationTest).
 */
class TimeCategoriasMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_copia_a_categoria_do_cadastro_e_as_dos_campeonatos(): void
    {
        $this->migration()->down();

        $sub13 = $this->categoria('Sub-13');
        $sub15 = $this->categoria('Sub-15');
        $aacj = $this->time('AACJ Sub-13', 'INTERNO', $sub13);
        $botafogo = $this->time('Botafogo', 'EXTERNO', $sub15);

        // O Botafogo é do Sub-15 no cadastro, mas joga também no Sub-13 deste campeonato
        $copa = $this->campeonato($sub13);
        $this->participantes($copa, [$aacj, $botafogo], $sub13);

        $this->migration()->up();

        $this->assertSame([$sub13], $this->categoriasDo($aacj));
        $this->assertSame([$sub13, $sub15], $this->categoriasDo($botafogo));
        $this->assertSame([$sub13, $sub15], Time::find($botafogo)->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all());

        $this->assertSame('YES', DB::selectOne("SELECT IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_time' AND COLUMN_NAME = 'id_categoria'")->n);
        $this->assertSame(['convocacao_camisa_categoria_unique', 'convocacao_categoria_atleta_unique'], $this->unicosDaConvocacao());
    }

    public function test_down_volta_a_uma_categoria_e_para_com_time_em_varias(): void
    {
        $sub13 = $this->categoria('Sub-13');
        $sub15 = $this->categoria('Sub-15');
        $aacj = $this->time('AACJ', 'INTERNO', null);
        DB::table('tbl_time_categoria')->insert([['id_time' => $aacj, 'id_categoria' => $sub13], ['id_time' => $aacj, 'id_categoria' => $sub15]]);

        try {
            $this->migration()->down();
            $this->fail('O down() deveria parar com o time em duas categorias.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('AACJ', $e->getMessage());
        }

        DB::table('tbl_time_categoria')->where('id_categoria', $sub15)->delete();
        $this->migration()->down();

        $this->assertSame($sub13, (int) DB::table('tbl_time')->where('id_time', $aacj)->value('id_categoria'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('tbl_time_categoria'));
        $this->assertSame(['convocacao_camisa_unique', 'convocacao_categoria_atleta_unique'], $this->unicosDaConvocacao());

        $this->migration()->up();
    }

    private function categoria(string $nome): int
    {
        return (int) DB::table('tbl_categoria')->where('nome_categoria', $nome)->where('sexo_categoria', 'M')->value('id_categoria');
    }

    private function time(string $nome, string $tipo, ?int $idCategoria): int
    {
        return DB::table('tbl_time')->insertGetId(['nome_time' => $nome, 'tipo_time' => $tipo, 'logo_time' => 'time-azul.png',
            'id_categoria' => $idCategoria]);
    }

    private function campeonato(int $idCategoria): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idCategoria, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => '2026-11-01', 'data_fim_campeonato' => '2026-11-30',
        ]);
    }

    private function categoriasDo(int $idTime): array
    {
        return DB::table('tbl_time_categoria')->where('id_time', $idTime)->orderBy('id_categoria')
            ->pluck('id_categoria')->map(fn ($id) => (int) $id)->all();
    }

    private function unicosDaConvocacao(): array
    {
        return collect(DB::select("SELECT DISTINCT INDEX_NAME i FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_convocacao' AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'"))
            ->pluck('i')->sort()->values()->all();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_27_000001_categorias_do_time.php');
    }
}
