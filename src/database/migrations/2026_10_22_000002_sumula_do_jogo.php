<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Súmula do jogo (Etapa S1, 22/10/2026): quem fez cada gol e os cartões, lançados pelo admin na tela do jogo.
     *
     * tbl_jogo_gol: uma linha por gol, no time que ganhou o gol no placar (id_time). Só os gols dos times da AACJ
     * têm autor conhecido: id_atleta NULL = autor não identificado, ou gol contra de um adversário. Gol contra de um
     * atleta da AACJ fica com ele em id_atleta e o time adversário em id_time (soma para o adversário e não conta
     * como gol dele). Os pênaltis da disputa (depois do empate) não entram: não são gols do jogo.
     *
     * tbl_cartoes ganha id_usuario (quem lançou), como as outras tabelas do admin; as linhas antigas ficam NULL.
     *
     * Tipos iguais aos das colunas referenciadas: tbl_jogos.id_jogo, tbl_time.id_time e tbl_atletas.id_atleta são
     * INT com sinal; tbl_usuarios.id_usuario é BIGINT UNSIGNED. Valores de ENUM sem acento (rótulos no model
     * JogoGol). FKs NO ACTION.
     */
    public function up(): void
    {
        Schema::create('tbl_jogo_gol', function (Blueprint $table) {
            $table->increments('id_jogo_gol');
            $table->integer('id_jogo');
            $table->integer('id_time');
            $table->integer('id_atleta')->nullable();
            $table->integer('id_atleta_assistencia')->nullable();
            $table->enum('tipo_jogo_gol', ['NORMAL', 'PENALTI', 'CONTRA'])->default('NORMAL');
            $table->enum('periodo_jogo_gol', ['TEMPO_NORMAL', 'PRORROGACAO'])->default('TEMPO_NORMAL');
            $table->unsignedTinyInteger('minuto_jogo_gol')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_jogo_gol')->useCurrent();

            $table->index('id_jogo', 'fk_jogo_gol_jogo');
            $table->index('id_time', 'fk_jogo_gol_time');
            $table->index('id_atleta', 'fk_jogo_gol_atleta');
            $table->index('id_atleta_assistencia', 'fk_jogo_gol_assistencia');
            $table->index('id_usuario', 'fk_jogo_gol_usuario');

            $table->foreign('id_jogo', 'fk_jogo_gol_jogo')
                ->references('id_jogo')->on('tbl_jogos')->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_time', 'fk_jogo_gol_time')
                ->references('id_time')->on('tbl_time')->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_atleta', 'fk_jogo_gol_atleta')
                ->references('id_atleta')->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_atleta_assistencia', 'fk_jogo_gol_assistencia')
                ->references('id_atleta')->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_jogo_gol_usuario')
                ->references('id_usuario')->on('tbl_usuarios')->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('tbl_cartoes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable()->after('tipo_cartao');
            $table->index('id_usuario', 'fk_cartao_usuario');
            $table->foreign('id_usuario', 'fk_cartao_usuario')
                ->references('id_usuario')->on('tbl_usuarios')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_cartoes', function (Blueprint $table) {
            $table->dropForeign('fk_cartao_usuario');
            $table->dropIndex('fk_cartao_usuario');
            $table->dropColumn('id_usuario');
        });

        Schema::dropIfExists('tbl_jogo_gol');
    }
};
