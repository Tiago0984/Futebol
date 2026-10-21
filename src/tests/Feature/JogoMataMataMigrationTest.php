<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration da rodada, ida e volta, prorrogação e pênaltis (Fase I, Etapa I1b): seis colunas nullable em
 * tbl_jogos, o único e a FK do jogo de ida. ALTER confirma a transação aberta no MySQL: esta classe roda SEM a
 * transação do RefreshDatabase e marca o banco para o próximo teste recriá-lo.
 */
class JogoMataMataMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    private const COLUNAS = ['rodada_jogo', 'id_jogo_ida', 'prorrogacao_time_casa_jogos', 'prorrogacao_time_visitante_jogos',
        'penaltis_time_casa_jogos', 'penaltis_time_visitante_jogos'];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_down_tira_as_colunas_e_up_volta(): void
    {
        $this->migration()->down();
        foreach (self::COLUNAS as $coluna) {
            $this->assertFalse(Schema::hasColumn('tbl_jogos', $coluna), $coluna);
        }

        $this->migration()->up();
        foreach (self::COLUNAS as $coluna) {
            $this->assertTrue(Schema::hasColumn('tbl_jogos', $coluna), $coluna);
        }
        $this->assertContains('fk_jogo_ida', array_column(Schema::getForeignKeys('tbl_jogos'), 'name'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_000002_rodada_ida_volta_prorrogacao_e_penaltis.php');
    }
}
