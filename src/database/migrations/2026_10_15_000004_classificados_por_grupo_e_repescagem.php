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
     * Ajuste da Fase H, Etapa H4 (explicação do dono da AACJ, 15/10/2026): na "chave" (grupo) de três times,
     * passa o primeiro, ou o primeiro e o segundo, conforme a quantidade de times; às vezes há repescagem
     * antes do mata-mata.
     * - tbl_campeonato_categoria.classificados_campeonato_categoria: quantos passam de cada grupo naquela
     *   categoria do campeonato (NULL = não definido, a tabela não destaca ninguém);
     * - REPESCAGEM na fase do jogo, acrescentada no fim do ENUM (sem acento; rótulo em Jogo::FASES). O ENUM é
     *   ampliado direto, sem mexer nas linhas existentes.
     */
    public function up(): void
    {
        Schema::table('tbl_campeonato_categoria', function (Blueprint $table) {
            $table->unsignedTinyInteger('classificados_campeonato_categoria')->nullable()->after('id_categoria');
        });

        DB::statement("ALTER TABLE tbl_jogos MODIFY fase_jogo
            ENUM('GRUPOS', 'DEZESSEIS_AVOS', 'OITAVAS', 'QUARTAS', 'SEMIFINAL', 'TERCEIRO', 'FINAL', 'REPESCAGEM') NULL");
    }

    /**
     * Reverse the migrations.
     *
     * Para, com a lista, se algum jogo já está na repescagem (o ENUM antigo não tem o valor e a fase se perderia).
     */
    public function down(): void
    {
        $naRepescagem = DB::table('tbl_jogos')->where('fase_jogo', 'REPESCAGEM')->pluck('id_jogo')->all();
        if ($naRepescagem) {
            throw new RuntimeException('Jogos na repescagem: ' . implode(', ', $naRepescagem) . '. Mude a fase deles antes de voltar.');
        }

        DB::statement("ALTER TABLE tbl_jogos MODIFY fase_jogo
            ENUM('GRUPOS', 'DEZESSEIS_AVOS', 'OITAVAS', 'QUARTAS', 'SEMIFINAL', 'TERCEIRO', 'FINAL') NULL");

        Schema::table('tbl_campeonato_categoria', function (Blueprint $table) {
            $table->dropColumn('classificados_campeonato_categoria');
        });
    }
};
