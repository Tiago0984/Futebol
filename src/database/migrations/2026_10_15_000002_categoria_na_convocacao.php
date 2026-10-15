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
     * Convocação por categoria (Fase H, Etapa H2): num campeonato de várias categorias a AACJ forma um time por
     * categoria ("AACJ Sub-15", "AACJ Sub-17") e o atleta do Sub-15 pode ser convocado também no Sub-17. A
     * convocação ganha a categoria do time no campeonato (time interno joga numa categoria só de cada campeonato):
     * - "um time por atleta" passa a valer em cada categoria: único (campeonato, categoria, atleta) no lugar de
     *   (campeonato, atleta);
     * - a camisa continua sem repetir no time do campeonato (convocacao_camisa_unique, sem mudança);
     * - FK composta (campeonato, categoria, time) → tbl_campeonato_time: só convoca quem é participante naquela
     *   categoria (o time com convocados não sai da categoria sem remover a convocação).
     *
     * Dados existentes: a categoria vem do participante (campeonato, time). Para, com a lista, se alguma convocação
     * tiver o time sem categoria no campeonato ou em mais de uma (não dá para saber qual vale).
     * Tipo igual ao das colunas referenciadas: categoria INT com sinal.
     */
    public function up(): void
    {
        $problemas = DB::table('tbl_convocacao as cv')
            ->leftJoin('tbl_campeonato_time as ct', fn ($j) => $j->on('ct.id_campeonato', '=', 'cv.id_campeonato')->on('ct.id_time', '=', 'cv.id_time'))
            ->groupBy('cv.id_convocacao')
            ->havingRaw('COUNT(ct.id_categoria) <> 1')
            ->pluck('cv.id_convocacao')->all();
        if ($problemas) {
            throw new RuntimeException('Convocações com o time sem categoria no campeonato ou em mais de uma: '
                . implode(', ', $problemas) . '. Deixe cada time convocador em uma categoria do campeonato antes.');
        }

        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable()->after('id_campeonato');
        });

        DB::statement('UPDATE tbl_convocacao cv JOIN tbl_campeonato_time ct
            ON ct.id_campeonato = cv.id_campeonato AND ct.id_time = cv.id_time
            SET cv.id_categoria = ct.id_categoria');

        // O único novo começa por id_campeonato e passa a servir à FK do campeonato antes de o antigo sair
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable(false)->change();
            $table->unique(['id_campeonato', 'id_categoria', 'id_atleta'], 'convocacao_categoria_atleta_unique');
            $table->index(['id_campeonato', 'id_categoria', 'id_time'], 'fk_convocacao_participante');
        });
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->dropUnique('convocacao_campeonato_atleta_unique');
            $table->foreign(['id_campeonato', 'id_categoria', 'id_time'], 'fk_convocacao_participante')
                ->references(['id_campeonato', 'id_categoria', 'id_time'])->on('tbl_campeonato_time')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Volta a um time por atleta em cada campeonato. Para, com a lista, se algum atleta já está convocado em duas
     * categorias do mesmo campeonato (o único antigo não caberia e uma das convocações seria perdida).
     */
    public function down(): void
    {
        $repetidos = DB::table('tbl_convocacao')->groupBy('id_campeonato', 'id_atleta')->havingRaw('COUNT(*) > 1')
            ->selectRaw("CONCAT('campeonato ', id_campeonato, ' atleta ', id_atleta) AS par")->pluck('par')->all();
        if ($repetidos) {
            throw new RuntimeException('Atletas convocados em mais de uma categoria do mesmo campeonato: '
                . implode(', ', $repetidos) . '. Remova uma das convocações antes de voltar.');
        }

        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->dropForeign('fk_convocacao_participante');
        });
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->unique(['id_campeonato', 'id_atleta'], 'convocacao_campeonato_atleta_unique');
        });
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->dropIndex('fk_convocacao_participante');
            $table->dropUnique('convocacao_categoria_atleta_unique');
            $table->dropColumn('id_categoria');
        });
    }
};
