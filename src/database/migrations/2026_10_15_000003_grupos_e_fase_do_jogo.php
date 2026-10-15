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
     * Grupos e fase do jogo (Fase H, Etapa H4): a Copa São Carlos tem, em cada categoria, grupos ("Grupo 01" a
     * "Grupo 03") e depois o mata-mata (opcional). A classificação passa a ser por categoria e grupo e conta só a
     * fase de grupos.
     * - tbl_campeonato_grupo: os grupos de cada categoria do campeonato (FK composta para tbl_campeonato_categoria);
     * - tbl_campeonato_time.id_campeonato_grupo: o grupo do participante na categoria (opcional; um grupo por
     *   time em cada categoria). A FK composta (grupo, campeonato, categoria) só aceita grupo da mesma categoria;
     * - tbl_jogos.fase_jogo: a fase do jogo de campeonato, em lista fixa (valores sem acento; rótulos em
     *   Jogo::FASES). Amistoso: NULL. Os jogos de campeonato existentes ficam na fase de grupos.
     *
     * Tipos iguais aos das colunas referenciadas: campeonato e categoria INT com sinal.
     */
    public function up(): void
    {
        Schema::create('tbl_campeonato_grupo', function (Blueprint $table) {
            $table->increments('id_campeonato_grupo');
            $table->integer('id_campeonato');
            $table->integer('id_categoria');
            $table->string('nome_campeonato_grupo', 30);

            $table->unique(['id_campeonato', 'id_categoria', 'nome_campeonato_grupo'], 'campeonato_grupo_nome_unique');
            // Alvo da FK composta do participante (o grupo precisa ser da mesma categoria dele)
            $table->unique(['id_campeonato_grupo', 'id_campeonato', 'id_categoria'], 'campeonato_grupo_categoria_unique');

            $table->foreign(['id_campeonato', 'id_categoria'], 'fk_cg_campeonato_categoria')
                ->references(['id_campeonato', 'id_categoria'])->on('tbl_campeonato_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->unsignedInteger('id_campeonato_grupo')->nullable()->after('id_categoria');
            $table->index(['id_campeonato_grupo', 'id_campeonato', 'id_categoria'], 'fk_ct_grupo');
            $table->foreign(['id_campeonato_grupo', 'id_campeonato', 'id_categoria'], 'fk_ct_grupo')
                ->references(['id_campeonato_grupo', 'id_campeonato', 'id_categoria'])->on('tbl_campeonato_grupo')
                ->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->enum('fase_jogo', ['GRUPOS', 'DEZESSEIS_AVOS', 'OITAVAS', 'QUARTAS', 'SEMIFINAL', 'TERCEIRO', 'FINAL'])
                ->nullable()->after('id_campeonato');
        });

        DB::table('tbl_jogos')->whereNotNull('id_campeonato')->update(['fase_jogo' => 'GRUPOS']);
    }

    /**
     * Reverse the migrations.
     *
     * Tira a fase, o grupo dos participantes e os grupos (os dados deles se perdem: só para voltar o código).
     */
    public function down(): void
    {
        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dropColumn('fase_jogo');
        });

        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->dropForeign('fk_ct_grupo');
        });
        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->dropIndex('fk_ct_grupo');
            $table->dropColumn('id_campeonato_grupo');
        });

        Schema::dropIfExists('tbl_campeonato_grupo');
    }
};
