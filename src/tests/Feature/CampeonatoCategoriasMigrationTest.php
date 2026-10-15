<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration das categorias do campeonato (Fase H, Etapa H1). Ela cria tabela e muda colunas (DDL confirma a
 * transação aberta no MySQL): por isso esta classe roda SEM a transação do RefreshDatabase e, no fim de cada
 * teste, marca o banco para o próximo teste recriá-lo (como o GradeUmDiaPorLinhaMigrationTest). Cada teste
 * começa voltando ao formato antigo pelo down() (com as tabelas vazias) e grava os dados como antes.
 */
class CampeonatoCategoriasMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_da_a_cada_campeonato_a_categoria_dele_e_aos_participantes_tambem(): void
    {
        $this->migration()->down();

        $sub11 = $this->idCategoria('Sub-11', 'M');
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $azul  = $this->time('Time Azul');
        $verde = $this->time('Time Verde');
        $copa  = $this->campeonato('Copa', $sub15);
        $liga  = $this->campeonato('Liga', $sub11);
        DB::table('tbl_campeonato_time')->insert([
            ['id_campeonato' => $copa, 'id_time' => $azul],
            ['id_campeonato' => $copa, 'id_time' => $verde],
            ['id_campeonato' => $liga, 'id_time' => $azul],
            ['id_campeonato' => $liga, 'id_time' => $azul], // repetido: fica um
        ]);

        $this->migration()->up();

        $this->assertEquals([[$copa, $sub15], [$liga, $sub11]], DB::table('tbl_campeonato_categoria')->orderBy('id_campeonato')->get()
            ->map(fn ($l) => [(int) $l->id_campeonato, (int) $l->id_categoria])->all());
        $this->assertEquals([[$copa, $sub15, $azul], [$copa, $sub15, $verde], [$liga, $sub11, $azul]],
            DB::table('tbl_campeonato_time')->orderBy('id_campeonato')->orderBy('id_time')->get()
                ->map(fn ($l) => [(int) $l->id_campeonato, (int) $l->id_categoria, (int) $l->id_time])->all());
    }

    public function test_down_para_com_campeonato_de_varias_categorias(): void
    {
        $copa = $this->campeonato('Copa', $this->idCategoria('Sub-15', 'M'));
        DB::table('tbl_campeonato_categoria')->insert([
            ['id_campeonato' => $copa, 'id_categoria' => $this->idCategoria('Sub-15', 'M')],
            ['id_campeonato' => $copa, 'id_categoria' => $this->idCategoria('Sub-17', 'M')],
        ]);

        try {
            $this->migration()->down();
            $this->fail('O down() deveria parar.');
        } catch (RuntimeException $e) {
            $this->assertSame("Campeonatos com mais de uma categoria ou time em duas categorias: {$copa}. Deixe-os com uma categoria antes de voltar.", $e->getMessage());
        }
        $this->assertSame(2, DB::table('tbl_campeonato_categoria')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000001_categorias_do_campeonato_e_participantes_por_categoria.php');
    }

    private function idCategoria(string $nome, string $sexo): int
    {
        return (int) DB::table('tbl_categoria')->where('nome_categoria', $nome)->where('sexo_categoria', $sexo)->value('id_categoria');
    }

    private function campeonato(string $nome, int $idCategoria): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => $nome, 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Campo A',
        ]);
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-15', 'M'), 'logo_time' => 'time.png', 'nome_time' => $nome,
            'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO',
        ]);
    }
}
