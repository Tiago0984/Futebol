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
 * Migration do período escolar (Fase A, Etapa 1): normaliza o texto livre para o ENUM sem acento e cria o
 * turno do treino. Ela muda a tabela (ALTER TABLE), e DDL no MySQL confirma a transação aberta: por isso roda
 * SEM a transação do RefreshDatabase e marca o banco para ser recriado no fim (como EventoPublicacaoMigrationTest).
 * Cada teste começa voltando a coluna a texto livre pelo down().
 */
class AtletaPeriodoEscolarMigrationTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_up_normaliza_os_valores_antigos(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('tbl_atletas', 'turno_treino_atleta'));

        $esperado = [
            'Manhã' => 'MANHA', 'MANHÃ' => 'MANHA', 'manha' => 'MANHA', ' Tarde ' => 'TARDE', 'NOITE' => 'NOITE',
            'Integral' => 'INTEGRAL', '' => null, '-' => null,
        ];
        $ids = [];
        foreach (array_keys($esperado) as $valor) {
            $ids[$valor] = $this->atletaCom($valor === '-' ? null : (string) $valor);
        }

        $this->migration()->up();

        foreach ($esperado as $valor => $normalizado) {
            $this->assertSame($normalizado, DB::table('tbl_atletas')->where('id_atleta', $ids[$valor])->value('periodo_escolar_atleta'), "valor \"{$valor}\"");
        }
        $this->assertTrue(Schema::hasColumn('tbl_atletas', 'turno_treino_atleta'));
        $this->assertStringContainsString("enum('MANHA','TARDE','NOITE','INTEGRAL')", $this->tipo('periodo_escolar_atleta'));
        $this->assertStringContainsString("enum('MANHA','TARDE','AMBOS')", $this->tipo('turno_treino_atleta'));
    }

    public function test_valor_desconhecido_para_com_a_lista_sem_mexer_em_nada(): void
    {
        $this->migration()->down();
        $vespertino = $this->atletaCom('Vespertino');
        $manha      = $this->atletaCom('Manhã');

        try {
            $this->migration()->up();
            $this->fail('A migration deveria parar com a lista.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("\"Vespertino\" (atleta {$vespertino})", $e->getMessage());
        }

        $this->assertSame('Manhã', DB::table('tbl_atletas')->where('id_atleta', $manha)->value('periodo_escolar_atleta'));
        $this->assertFalse(Schema::hasColumn('tbl_atletas', 'turno_treino_atleta'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_13_000001_periodo_escolar_e_turno_de_treino.php');
    }

    private function atletaCom(?string $periodo): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['periodo_escolar_atleta' => $periodo]);

        return $id;
    }

    private function tipo(string $coluna): string
    {
        return DB::selectOne("SHOW COLUMNS FROM tbl_atletas LIKE '{$coluna}'")->Type;
    }
}
