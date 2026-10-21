<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration dos campos opcionais do campeonato (21/10/2026): local, organizador, logo e banner passam a aceitar
 * NULL. ALTER confirma a transação aberta no MySQL: esta classe roda SEM a transação do RefreshDatabase e marca
 * o banco para o próximo teste recriá-lo (como JogoEscalacaoPosicaoMigrationTest).
 */
class CampeonatoCamposOpcionaisMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    private const COLUNAS = ['logo_evento', 'banner_evento', 'organizador_campeonato', 'local_evento'];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_down_troca_vazio_por_texto_vazio_e_up_volta_a_aceitar_null(): void
    {
        $idCategoria = DB::table('tbl_categoria')->value('id_categoria');
        $id = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idCategoria, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => '2026-11-01', 'data_fim_campeonato' => '2026-11-30', 'local_evento' => 'Arena A',
        ]);

        $this->migration()->down();
        $this->assertSame(['NO', 'NO', 'NO', 'NO'], $this->nulos());
        $campeonato = DB::table('tbl_campeonato')->where('id_campeonato', $id)->first();
        $this->assertSame('', $campeonato->logo_evento);
        $this->assertSame('', $campeonato->organizador_campeonato);
        $this->assertSame('Arena A', $campeonato->local_evento);

        $this->migration()->up();
        $this->assertSame(['YES', 'YES', 'YES', 'YES'], $this->nulos());

        // Mesmos tamanhos e a collation do banco
        $colunas = collect(DB::select("SELECT COLUMN_NAME c, COLUMN_TYPE t, COLLATION_NAME k FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_campeonato'"))->keyBy('c');
        $this->assertSame('varchar(255)', $colunas['logo_evento']->t);
        $this->assertSame('varchar(100)', $colunas['local_evento']->t);
        foreach (self::COLUNAS as $coluna) {
            $this->assertSame('utf8mb4_general_ci', $colunas[$coluna]->k);
        }
    }

    private function nulos(): array
    {
        $colunas = collect(DB::select("SELECT COLUMN_NAME c, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_campeonato'"))->pluck('n', 'c');

        return array_map(fn ($coluna) => $colunas[$coluna], self::COLUNAS);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_000001_campos_opcionais_do_campeonato.php');
    }
}
