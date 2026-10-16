<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Migration da posição e do titular/reserva na escalação (Fase D, Etapa D2): duas colunas nullable em
 * tbl_evento_atleta. ALTER confirma a transação aberta no MySQL: esta classe roda SEM a transação do
 * RefreshDatabase e marca o banco para o próximo teste recriá-lo (como EventoPublicacaoMigrationTest).
 */
class JogoEscalacaoPosicaoMigrationTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected $connectionsToTransact = [];

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_down_tira_as_colunas_e_up_volta_com_os_valores_da_lista(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('tbl_evento_atleta', 'posicao_evento_atleta'));
        $this->assertFalse(Schema::hasColumn('tbl_evento_atleta', 'titular_evento_atleta'));

        $this->migration()->up();

        $colunas = collect(DB::select("SHOW COLUMNS FROM tbl_evento_atleta WHERE Field IN ('posicao_evento_atleta', 'titular_evento_atleta')"))
            ->keyBy('Field');
        $this->assertSame('YES', $colunas['posicao_evento_atleta']->Null);
        $this->assertStringContainsString("'SEGUNDO_ATACANTE'", $colunas['posicao_evento_atleta']->Type);
        $this->assertSame("enum('TITULAR','RESERVA')", $colunas['titular_evento_atleta']->Type);
        $this->assertSame('YES', $colunas['titular_evento_atleta']->Null);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000007_posicao_e_titular_na_escalacao.php');
    }
}
