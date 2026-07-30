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
        Schema::create('tbl_jogos', function (Blueprint $table) {
            $table->integer('id_jogo', true);
            $table->integer('id_campeonato')->index('fk_jogo_campeonato');
            $table->integer('id_time_casa')->index('fk_jogo_time_casa');
            $table->integer('id_time_visitante')->index('fk_jogo_time_visitante');
            $table->integer('placar_time_casa_jogos')->nullable();
            $table->integer('placar_time_visitante_jogos')->nullable();
            $table->dateTime('data_jogo')->nullable();
            $table->string('status_jogo', 10)->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_jogos');
    }
};
