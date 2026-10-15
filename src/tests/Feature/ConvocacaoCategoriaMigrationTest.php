<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration da categoria na convocação (Fase H, Etapa H2). Ela muda colunas (DDL confirma a transação aberta no
 * MySQL): por isso esta classe roda SEM a transação do RefreshDatabase e, no fim de cada teste, marca o banco
 * para o próximo teste recriá-lo (como o CampeonatoCategoriasMigrationTest). Os testes do up() começam voltando
 * ao formato antigo pelo down() (com a tabela vazia) e gravam as convocações como antes.
 */
class ConvocacaoCategoriaMigrationTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected $connectionsToTransact = [];

    private int $sub15;
    private int $sub17;
    private int $liga;
    private int $azul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sub15 = $this->idCategoria('Sub-15', 'M');
        $this->sub17 = $this->idCategoria('Sub-17', 'M');
        $this->liga  = $this->campeonato('Liga', $this->sub15);
        $this->azul  = $this->time('AACJ Sub-15');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_da_a_convocacao_a_categoria_do_time_no_campeonato(): void
    {
        $this->migration()->down();
        $this->participantes($this->liga, [$this->azul]);
        $aacj17 = $this->time('AACJ Sub-17');
        $this->participantes($this->liga, [$aacj17], $this->sub17);
        $ana = $this->atleta();
        $bia = $this->atleta();
        DB::table('tbl_convocacao')->insert([
            ['id_campeonato' => $this->liga, 'id_time' => $this->azul, 'id_atleta' => $ana, 'camisa_convocacao' => 10],
            ['id_campeonato' => $this->liga, 'id_time' => $aacj17, 'id_atleta' => $bia, 'camisa_convocacao' => 7],
        ]);

        $this->migration()->up();

        $this->assertEquals([$ana => $this->sub15, $bia => $this->sub17],
            DB::table('tbl_convocacao')->pluck('id_categoria', 'id_atleta')->map(fn ($id) => (int) $id)->all());
    }

    public function test_up_para_com_convocacao_de_time_sem_categoria_no_campeonato(): void
    {
        $this->migration()->down();
        $ana = $this->atleta();
        $id  = DB::table('tbl_convocacao')->insertGetId(['id_campeonato' => $this->liga, 'id_time' => $this->azul, 'id_atleta' => $ana, 'camisa_convocacao' => 10]);

        try {
            $this->migration()->up();
            $this->fail('O up() deveria parar.');
        } catch (RuntimeException $e) {
            $this->assertSame("Convocações com o time sem categoria no campeonato ou em mais de uma: {$id}. "
                . 'Deixe cada time convocador em uma categoria do campeonato antes.', $e->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('tbl_convocacao', 'id_categoria'));
    }

    public function test_down_para_com_atleta_convocado_em_duas_categorias(): void
    {
        $this->participantes($this->liga, [$this->azul]);
        $aacj17 = $this->time('AACJ Sub-17');
        $this->participantes($this->liga, [$aacj17], $this->sub17);
        $ana = $this->atleta();
        $this->convocacaoDireta($this->liga, $this->azul, $ana, 10);
        $this->convocacaoDireta($this->liga, $aacj17, $ana, 4);

        try {
            $this->migration()->down();
            $this->fail('O down() deveria parar.');
        } catch (RuntimeException $e) {
            $this->assertSame("Atletas convocados em mais de uma categoria do mesmo campeonato: campeonato {$this->liga} atleta {$ana}. "
                . 'Remova uma das convocações antes de voltar.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('tbl_convocacao', 'id_categoria'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000002_categoria_na_convocacao.php');
    }

    private function atleta(): int
    {
        return $this->criarAtleta($this->nascidoComIdade(15), 'M', 'ATIVO');
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
            'id_categoria' => $this->sub15, 'logo_time' => 'time.png', 'nome_time' => $nome,
            'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO',
        ]);
    }
}
