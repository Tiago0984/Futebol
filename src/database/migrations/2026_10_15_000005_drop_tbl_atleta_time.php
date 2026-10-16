<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fase G, Etapa 4 (dono do projeto, 15/10/2026): o elenco fixo saiu do sistema (o atleta é da AACJ, na
     * categoria dele; o time e a camisa são da convocação de cada campeonato) e nenhum código lê nem grava
     * tbl_atleta_time. A tabela é apagada com as linhas (dados fictícios: camisa, posição e contadores
     * digitados à mão). As FKs dela (fk_atleta_time_atleta e fk_atleta_time_time) saem junto com a tabela.
     */
    public function up(): void
    {
        Schema::dropIfExists('tbl_atleta_time');
    }

    /**
     * Reverse the migrations.
     *
     * Recria a tabela vazia, com a mesma estrutura e as mesmas FKs (as linhas apagadas não voltam: só pelo
     * backup).
     */
    public function down(): void
    {
        Schema::create('tbl_atleta_time', function (Blueprint $table) {
            $table->integer('id_atleta_time', true);
            $table->integer('id_time')->index('fk_atleta_time_time');
            $table->integer('id_atleta')->index('fk_atleta_time_atleta');
            $table->integer('camisa_atleta_time');
            $table->string('posicao_atleta_time', 20);
            $table->integer('jogos_atleta_time')->default(0);
            $table->integer('convocacao_atleta_time')->default(0);
            $table->integer('gols_atleta_time')->default(0);
            $table->integer('defesas_atleta_time')->default(0);
            $table->string('status_atleta_time', 20)->default('ATIVO');

            $table->foreign(['id_atleta'], 'fk_atleta_time_atleta')->references(['id_atleta'])->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_time'], 'fk_atleta_time_time')->references(['id_time'])->on('tbl_time')->onUpdate('no action')->onDelete('no action');
        });
    }
};
