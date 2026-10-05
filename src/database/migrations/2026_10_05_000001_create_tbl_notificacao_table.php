<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Notificações do atleta (Fase 8). Tabela própria, nas convenções do projeto, no lugar da
     * `notifications` do Laravel (polimórfica, BIGINT UNSIGNED, sem FK para tbl_atletas).
     * Título e mensagem ficam congelados no envio; id_evento_calendario é NULL no resumo (AGENDA).
     * Lida = data_leitura_notificacao preenchida.
     *
     * Tipos iguais aos das colunas referenciadas (FK exige o mesmo tipo e sinal):
     * atleta INT com sinal; evento INT UNSIGNED; usuário BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::create('tbl_notificacao', function (Blueprint $table) {
            $table->increments('id_notificacao');
            $table->integer('id_atleta');
            $table->unsignedInteger('id_evento_calendario')->nullable();
            $table->enum('tipo_notificacao', ['INSCRICAO', 'REMOCAO', 'ALTERACAO', 'CANCELAMENTO', 'REATIVACAO', 'AGENDA']);
            $table->string('titulo_notificacao', 120);
            $table->string('mensagem_notificacao', 500);
            $table->json('dados_notificacao')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_notificacao')->useCurrent();
            $table->dateTime('data_leitura_notificacao')->nullable();

            // App (Fase 9): lista do atleta, da mais nova para a mais antiga, e contagem das não lidas.
            // Os dois começam por id_atleta e servem também à FK do atleta.
            $table->index(['id_atleta', 'data_notificacao'], 'idx_notificacao_atleta_data');
            $table->index(['id_atleta', 'data_leitura_notificacao'], 'idx_notificacao_atleta_leitura');
            $table->index('id_evento_calendario', 'fk_notificacao_evento');
            $table->index('id_usuario', 'fk_notificacao_usuario');

            $table->foreign('id_atleta', 'fk_notificacao_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_evento_calendario', 'fk_notificacao_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_notificacao_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_notificacao');
    }
};
