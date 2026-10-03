<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Precisa entrar junto com o User::$primaryKey = 'id_usuario': com a coluna renomeada
     * e o model antigo (ou o contrário), o login do admin quebra com "Unknown column".
     * Nenhuma FK aponta para tbl_usuarios.id, então o RENAME COLUMN não esbarra em constraint.
     *
     * cargo_usuario é VARCHAR (não ENUM) para a lista de cargos crescer sem migration;
     * os valores válidos ficam em User::CARGOS.
     */
    public function up(): void
    {
        Schema::table('tbl_usuarios', function (Blueprint $table) {
            $table->renameColumn('id', 'id_usuario');
            $table->renameIndex('users_email_unique', 'email_usuario_unique');
            $table->string('cargo_usuario', 30)->nullable()->after('foto_usuario');
            $table->enum('nivel_usuario', ['ADMIN', 'EDITOR', 'LEITURA'])->default('LEITURA')->after('cargo_usuario');
        });

        // Quem já existe hoje administra o sistema; usuários novos nascem com o menor nível
        DB::table('tbl_usuarios')->update(['nivel_usuario' => 'ADMIN']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_usuarios', function (Blueprint $table) {
            $table->dropColumn(['cargo_usuario', 'nivel_usuario']);
            $table->renameIndex('email_usuario_unique', 'users_email_unique');
            $table->renameColumn('id_usuario', 'id');
        });
    }
};
