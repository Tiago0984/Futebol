<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Amplia o ENUM com os valores antigos e novos para permitir a conversão
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('ATIVO', 'INATIVO', 'CANCELADO', 'CONFIRMADO', 'ALTERADO') NOT NULL DEFAULT 'CONFIRMADO'");

        DB::table('tbl_evento_calendario')->where('status_evento_calendario', 'ATIVO')->update(['status_evento_calendario' => 'CONFIRMADO']);
        DB::table('tbl_evento_calendario')->where('status_evento_calendario', 'INATIVO')->update(['status_evento_calendario' => 'CANCELADO']);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('CANCELADO', 'CONFIRMADO', 'ALTERADO') NOT NULL DEFAULT 'CONFIRMADO'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('ATIVO', 'INATIVO', 'CANCELADO', 'CONFIRMADO', 'ALTERADO') NOT NULL DEFAULT 'ATIVO'");

        DB::table('tbl_evento_calendario')->whereIn('status_evento_calendario', ['CONFIRMADO', 'ALTERADO'])->update(['status_evento_calendario' => 'ATIVO']);
        DB::table('tbl_evento_calendario')->where('status_evento_calendario', 'CANCELADO')->update(['status_evento_calendario' => 'INATIVO']);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('ATIVO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'");
    }
};
