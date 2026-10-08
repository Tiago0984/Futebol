<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration da data de publicação do evento (Fase 10, Etapa 4): todos os eventos existentes ficam publicados.
 *
 * Ela muda a tabela (ALTER TABLE), e DDL no MySQL confirma a transação aberta: por isso esta classe roda SEM a
 * transação do RefreshDatabase e, no fim de cada teste, marca o banco para o próximo teste recriá-lo
 * (como GradeUmDiaPorLinhaMigrationTest). Cada teste começa tirando a coluna pelo down().
 */
class EventoPublicacaoMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_publica_todos_os_eventos_existentes(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('tbl_evento_calendario', 'data_publicacao_evento_calendario'));

        $ids = [
            $this->evento('2026-01-10', 'ATIVO'),
            $this->evento('2026-10-08', 'CANCELADO'),
            $this->evento('2099-05-05', 'INATIVO'),
        ];

        $this->migration()->up();

        $datas = DB::table('tbl_evento_calendario')->whereIn('id_evento_calendario', $ids)
            ->pluck('data_publicacao_evento_calendario');
        $this->assertCount(3, $datas);
        $this->assertNotContains(null, $datas->all());
        $this->assertSame(0, DB::table('tbl_evento_calendario')->whereNull('data_publicacao_evento_calendario')->count());
    }

    public function test_down_tira_a_coluna_e_up_volta_a_criar(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('tbl_evento_calendario', 'data_publicacao_evento_calendario'));

        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('tbl_evento_calendario', 'data_publicacao_evento_calendario'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_08_000002_add_data_publicacao_to_tbl_evento_calendario.php');
    }

    private function evento(string $data, string $status): int
    {
        return DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => $data, 'status_evento_calendario' => $status,
        ]);
    }
}
