<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration do CPF formatado e único (21/10/2026): para com a lista quando há repetidos pelos dígitos (sem mexer
 * em nada) e, sem repetidos, formata todos e cria os índices. ALTER confirma a transação aberta no MySQL: esta
 * classe roda SEM a transação do RefreshDatabase e marca o banco para o próximo teste recriá-lo.
 */
class CpfMigrationTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_para_com_os_repetidos_e_depois_formata_e_cria_os_indices(): void
    {
        $this->migration()->down();

        $a = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $b = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $a)->update(['cpf_atleta' => '529.982.247-25']);
        DB::table('tbl_atletas')->where('id_atleta', $b)->update(['cpf_atleta' => '52998224725']);   // o mesmo, sem pontos

        try {
            $this->migration()->up();
            $this->fail('A migration devia parar com os repetidos.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("tbl_atletas: CPF 529.982.247-25 nos ids {$a}, {$b}", $e->getMessage());
        }
        $this->assertSame('52998224725', DB::table('tbl_atletas')->where('id_atleta', $b)->value('cpf_atleta'), 'nada mudou');

        // Corrigido o repetido: formata e cria os índices
        DB::table('tbl_atletas')->where('id_atleta', $b)->update(['cpf_atleta' => '11144477735']);
        $this->migration()->up();

        $this->assertSame('111.444.777-35', DB::table('tbl_atletas')->where('id_atleta', $b)->value('cpf_atleta'));
        $this->assertSame(2, collect(DB::select("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
            AND INDEX_NAME IN ('cpf_atleta_unique', 'cpf_responsavel_unique')"))->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_000003_cpf_formatado_e_unico.php');
    }
}
