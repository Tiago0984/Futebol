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
        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->foreign(['id_campeonato'], 'fk_jogo_campeonato')->references(['id_campeonato'])->on('tbl_campeonato')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_time_casa'], 'fk_jogo_time_casa')->references(['id_time'])->on('tbl_time')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_time_visitante'], 'fk_jogo_time_visitante')->references(['id_time'])->on('tbl_time')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dropForeign('fk_jogo_campeonato');
            $table->dropForeign('fk_jogo_time_casa');
            $table->dropForeign('fk_jogo_time_visitante');
        });
    }
};
