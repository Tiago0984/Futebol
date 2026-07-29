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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_atleta_time');
    }
};
