<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Inscrição do atleta no evento (CLAUDE.md, seção 4). Sem status: remover apaga a linha;
     * "Confirmado"/"Cancelado" no app vem do status do evento. Origem, quem e quando ficam registrados.
     * id_time: escalação do atleta num jogo (tela na Fase 6).
     *
     * Tipos iguais aos das colunas referenciadas (FK exige o mesmo tipo e sinal):
     * evento INT UNSIGNED; atleta e time INT com sinal; usuário BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::create('tbl_evento_atleta', function (Blueprint $table) {
            $table->increments('id_evento_atleta');
            $table->unsignedInteger('id_evento_calendario');
            $table->integer('id_atleta');
            $table->integer('id_time')->nullable();
            $table->enum('origem_evento_atleta', ['CATEGORIA', 'INDIVIDUAL']);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_evento_atleta')->useCurrent();

            // O mesmo atleta não entra duas vezes no mesmo evento
            $table->unique(['id_evento_calendario', 'id_atleta'], 'evento_atleta_unique');
            // Agenda do app (Fase 9): eventos de um atleta
            $table->index('id_atleta', 'fk_evento_atleta_atleta');
            $table->index('id_time', 'fk_evento_atleta_time');
            $table->index('id_usuario', 'fk_evento_atleta_usuario');

            $table->foreign('id_evento_calendario', 'fk_evento_atleta_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_atleta', 'fk_evento_atleta_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_time', 'fk_evento_atleta_time')
                ->references('id_time')->on('tbl_time')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_evento_atleta_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_evento_atleta');
    }
};
