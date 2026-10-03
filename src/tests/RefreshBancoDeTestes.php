<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RefreshDatabase com trava de segurança: ele roda migrate:fresh, então
 * aborta antes de apagar qualquer coisa se a conexão não for o banco de testes.
 * Todo teste que precise do banco usa este trait no lugar do RefreshDatabase.
 */
trait RefreshBancoDeTestes
{
    use RefreshDatabase {
        refreshDatabase as private refreshDatabaseOriginal;
    }

    private string $bancoDeTestes = 'db_futebol_test';

    public function refreshDatabase()
    {
        $banco = DB::connection()->getDatabaseName();

        if ($banco !== $this->bancoDeTestes) {
            throw new RuntimeException(
                "Testes abortados: conectado em '{$banco}', esperado '{$this->bancoDeTestes}'. "
                . 'Rode php artisan config:clear e confira o phpunit.xml.'
            );
        }

        $this->refreshDatabaseOriginal();
    }
}
