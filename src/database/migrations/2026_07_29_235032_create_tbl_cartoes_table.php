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
        Schema::create('tbl_cartoes', function (Blueprint $table) {
            $table->integer('id_cartao', true);
            $table->integer('id_atleta')->index('fk_cartao_atleta');
            $table->integer('id_campeonato')->nullable();
            $table->integer('id_jogo')->nullable()->index('fk_cartao_jogo');
            $table->string('tipo_cartao', 10);
            $table->dateTime('data_cartao')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_cartoes');
    }
};
