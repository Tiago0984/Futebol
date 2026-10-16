<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration que apaga a tbl_atleta_time (elenco fixo, sem uso desde a Fase G, Etapa 4), com as linhas.
 *
 * DROP/CREATE TABLE confirmam a transação aberta no MySQL: esta classe roda SEM a transação do RefreshDatabase
 * e, no fim de cada teste, marca o banco para o próximo teste recriá-lo (como EventoPublicacaoMigrationTest).
 */
class ElencoFixoMigrationTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_banco_montado_pelas_migrations_nao_tem_a_tabela(): void
    {
        $this->assertFalse(Schema::hasTable('tbl_atleta_time'));
    }

    public function test_down_recria_vazia_com_as_fks_e_up_apaga_com_as_linhas(): void
    {
        $this->migration()->down();
        $this->assertTrue(Schema::hasTable('tbl_atleta_time'));
        $this->assertSame(0, DB::table('tbl_atleta_time')->count());

        // As FKs voltam: time inexistente é recusado
        $atleta = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $time = DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-11', 'M'), 'logo_time' => 'azul.png', 'nome_time' => 'Time Azul',
            'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO',
        ]);
        DB::table('tbl_atleta_time')->insert(['id_time' => $time, 'id_atleta' => $atleta, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => 'MEIA']);
        try {
            DB::table('tbl_atleta_time')->insert(['id_time' => 999999, 'id_atleta' => $atleta, 'camisa_atleta_time' => 11, 'posicao_atleta_time' => '']);
            $this->fail('A FK do time deveria recusar.');
        } catch (\Illuminate\Database\QueryException) {
        }

        // Up: a tabela sai com a linha; o atleta e o time ficam
        $this->migration()->up();
        $this->assertFalse(Schema::hasTable('tbl_atleta_time'));
        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $atleta]);
        $this->assertDatabaseHas('tbl_time', ['id_time' => $time]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000005_drop_tbl_atleta_time.php');
    }
}
