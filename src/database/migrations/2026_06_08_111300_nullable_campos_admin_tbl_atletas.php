<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Campos atribuídos pelo admin após o cadastro público — não devem ser obrigatórios no insert inicial
    public function up(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->string('sala_atleta')->nullable()->change();
            $table->string('numero_atleta')->nullable()->change();
            $table->decimal('peso_atleta', 5, 2)->nullable()->change();
            $table->decimal('altura_atleta', 4, 2)->nullable()->change();
            $table->text('descricao_atleta')->nullable()->change();
            $table->string('foto_atleta')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->string('sala_atleta')->nullable(false)->change();
            $table->string('numero_atleta')->nullable(false)->change();
            $table->decimal('peso_atleta', 5, 2)->nullable(false)->change();
            $table->decimal('altura_atleta', 4, 2)->nullable(false)->change();
            $table->text('descricao_atleta')->nullable(false)->change();
            $table->string('foto_atleta')->nullable(false)->change();
        });
    }
};
