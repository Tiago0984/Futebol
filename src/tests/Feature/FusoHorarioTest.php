<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fuso de Brasília no app e na sessão MySQL: "hoje" vira à meia-noite de Brasília, e
 * CURRENT_TIMESTAMP/NOW() do banco gravam a mesma hora que o now() do Laravel.
 * Só leitura: não usa tabelas.
 */
class FusoHorarioTest extends TestCase
{
    public function test_app_esta_no_horario_de_brasilia(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get()); // date('Y') das views
        $this->assertSame('-03:00', now()->format('P'));
    }

    public function test_now_do_laravel_e_now_do_banco_dao_a_mesma_hora(): void
    {
        $banco = DB::selectOne('SELECT NOW() AS agora, @@session.time_zone AS fuso,
            TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), NOW()) AS diferenca');

        $this->assertSame('-03:00', $banco->fuso);
        $this->assertSame(-3, (int) $banco->diferenca);

        // A string do banco, lida no fuso do app, é o mesmo instante do now() (folga de alguns segundos)
        $this->assertLessThanOrEqual(5, abs(Carbon::parse($banco->agora)->diffInSeconds(now())));
    }
}
