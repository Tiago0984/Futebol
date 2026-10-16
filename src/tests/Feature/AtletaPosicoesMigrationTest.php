<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration das posições do atleta (Fase D, Etapa D1): o texto livre posicao_atleta vira a posição principal
 * em tbl_atleta_posicao (LATERAL => Lateral direito, MEIA => Meia-central, ATACANTE => Centroavante) e sai.
 *
 * CREATE/ALTER/DROP confirmam a transação aberta no MySQL: esta classe roda SEM a transação do RefreshDatabase
 * e, no fim de cada teste, marca o banco para o próximo teste recriá-lo (como ElencoFixoMigrationTest).
 * Cada teste começa voltando a coluna antiga pelo down().
 */
class AtletaPosicoesMigrationTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function atletaCom(?string $posicao): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['posicao_atleta' => $posicao]);

        return $id;
    }

    public function test_up_converte_a_posicao_antiga_na_principal_e_tira_a_coluna(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('tbl_atleta_posicao'));

        $ids = [
            'LATERAL'  => $this->atletaCom('LATERAL'),
            'meia '    => $this->atletaCom('meia '),     // minúscula e espaço: normalizado
            'ATACANTE' => $this->atletaCom('ATACANTE'),
            'GOLEIRO'  => $this->atletaCom('GOLEIRO'),
            'vazio'    => $this->atletaCom(''),
            'nulo'     => $this->atletaCom(null),
        ];

        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('tbl_atletas', 'posicao_atleta'));
        $principal = fn ($id) => DB::table('tbl_atleta_posicao')->where('id_atleta', $id)->where('ordem_atleta_posicao', 1)
            ->value('posicao_atleta_posicao');
        $this->assertSame('LATERAL_DIREITO', $principal($ids['LATERAL']));
        $this->assertSame('MEIA_CENTRAL', $principal($ids['meia ']));
        $this->assertSame('CENTROAVANTE', $principal($ids['ATACANTE']));
        $this->assertSame('GOLEIRO', $principal($ids['GOLEIRO']));
        $this->assertSame(4, DB::table('tbl_atleta_posicao')->count());       // vazio e nulo ficam sem posição
    }

    public function test_valor_desconhecido_para_sem_mexer_em_nada(): void
    {
        $this->migration()->down();
        $id = $this->atletaCom('Ponta');

        try {
            $this->migration()->up();
            $this->fail('A migration deveria parar com o valor desconhecido.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("\"Ponta\" (atleta {$id})", $e->getMessage());
        }

        $this->assertFalse(Schema::hasTable('tbl_atleta_posicao'));
        $this->assertSame('Ponta', DB::table('tbl_atletas')->where('id_atleta', $id)->value('posicao_atleta'));
    }

    public function test_down_volta_a_principal_no_nome_antigo(): void
    {
        $lateral = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $ponta   = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atleta_posicao')->insert([
            ['id_atleta' => $lateral, 'posicao_atleta_posicao' => 'LATERAL_ESQUERDO', 'ordem_atleta_posicao' => 1],
            ['id_atleta' => $lateral, 'posicao_atleta_posicao' => 'VOLANTE', 'ordem_atleta_posicao' => 2],
            ['id_atleta' => $ponta, 'posicao_atleta_posicao' => 'PONTA_DIREITA', 'ordem_atleta_posicao' => 1],
        ]);

        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('tbl_atleta_posicao'));
        $this->assertSame('LATERAL', DB::table('tbl_atletas')->where('id_atleta', $lateral)->value('posicao_atleta'));
        $this->assertSame('ATACANTE', DB::table('tbl_atletas')->where('id_atleta', $ponta)->value('posicao_atleta'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000006_posicoes_do_atleta.php');
    }
}
