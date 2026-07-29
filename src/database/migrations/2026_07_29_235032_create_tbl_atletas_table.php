<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_atletas', function (Blueprint $table) {
            $table->integer('id_atleta', true);
            $table->integer('id_endereco')->index('fk_atleta_endereco');
            $table->string('nome_atleta', 100);
            $table->string('numero_atleta')->nullable();
            $table->string('numero_matricula_atleta', 20)->nullable()->unique('numero_atleta');
            $table->string('posicao_atleta', 50)->nullable();
            $table->string('telefone_atleta', 20)->nullable();
            $table->date('data_nasc_atleta');
            $table->string('cpf_atleta', 14);
            $table->string('rg_atleta', 11);
            $table->decimal('peso_atleta', 5)->nullable();
            $table->decimal('altura_atleta', 4)->nullable();
            $table->string('sexo_atleta', 1);
            $table->string('escola_atleta', 100);
            $table->string('serie_atleta', 20)->nullable();
            $table->text('descricao_atleta')->nullable();
            $table->string('foto_atleta')->nullable();
            $table->string('sala_atleta')->nullable();
            $table->string('periodo_escolar_atleta', 20)->nullable();
            $table->string('status_atleta', 20)->default('PENDENTE');
            $table->string('email_atleta')->nullable()->unique('email_atleta');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string('token_cadastro', 100)->nullable()->unique('token_cadastro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_atletas');
    }
};
