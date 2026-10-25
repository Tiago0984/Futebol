<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Encerramento da categoria do campeonato (25/10/2026, dono do projeto): nos pontos corridos, o campeão pela
     * tabela só aparece depois que o admin encerra a categoria (o sistema não sabe se ainda vem um returno). NULL =
     * em andamento. Só acrescenta uma coluna nullable; não depende de ids nem mexe em dados.
     */
    public function up(): void
    {
        Schema::table('tbl_campeonato_categoria', function (Blueprint $table) {
            $table->dateTime('data_encerramento_campeonato_categoria')->nullable()->after('classificados_campeonato_categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_campeonato_categoria', function (Blueprint $table) {
            $table->dropColumn('data_encerramento_campeonato_categoria');
        });
    }
};
