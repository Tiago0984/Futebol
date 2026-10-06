<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Leitura das notificações do atleta pelos responsáveis (Fase 9): cada responsável tem a própria marca.
     * A do atleta continua em tbl_notificacao.data_leitura_notificacao. Sem linha = não lida por aquele
     * responsável. Único no par (notificação, responsável).
     *
     * Tipos iguais aos das colunas referenciadas: notificação INT UNSIGNED; responsável INT com sinal.
     */
    public function up(): void
    {
        Schema::create('tbl_notificacao_leitura', function (Blueprint $table) {
            $table->increments('id_notificacao_leitura');
            $table->unsignedInteger('id_notificacao');
            $table->integer('id_responsavel');
            $table->dateTime('data_notificacao_leitura')->useCurrent();

            // O único começa por id_notificacao e serve também à FK dela
            $table->unique(['id_notificacao', 'id_responsavel'], 'notificacao_leitura_unique');
            $table->index('id_responsavel', 'fk_notificacao_leitura_responsavel');

            $table->foreign('id_notificacao', 'fk_notificacao_leitura_notificacao')
                ->references('id_notificacao')->on('tbl_notificacao')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_responsavel', 'fk_notificacao_leitura_responsavel')
                ->references('id_responsavel')->on('tbl_responsavel')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_notificacao_leitura');
    }
};
