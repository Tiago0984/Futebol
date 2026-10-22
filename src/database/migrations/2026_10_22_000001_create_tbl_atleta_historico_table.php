<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Histórico de alterações do atleta (22/10/2026): nome, CPF e data de nascimento corrigidos pelo próprio
     * atleta no app (PATCH /api/v1/atleta) ficam registrados para a secretaria, uma linha por campo, com o
     * valor antigo, o novo e quando. id_usuario NULL = feito pelo atleta no app (a coluna já serve se o admin
     * passar a registrar). Mesmo molde da tbl_evento_historico.
     *
     * Tipos iguais aos das colunas referenciadas: tbl_atletas.id_atleta é INT com sinal;
     * tbl_usuarios.id_usuario é BIGINT UNSIGNED. FKs NO ACTION (a exclusão definitiva do atleta apaga antes).
     */
    public function up(): void
    {
        Schema::create('tbl_atleta_historico', function (Blueprint $table) {
            $table->increments('id_atleta_historico');
            $table->integer('id_atleta');
            $table->string('campo_atleta_historico', 40);
            $table->text('valor_antigo_atleta_historico')->nullable();
            $table->text('valor_novo_atleta_historico')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_atleta_historico')->useCurrent();

            $table->index(['id_atleta', 'data_atleta_historico'], 'idx_atleta_historico_data');
            $table->index('id_usuario', 'fk_atleta_historico_usuario');

            $table->foreign('id_atleta', 'fk_atleta_historico_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_atleta_historico_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_atleta_historico');
    }
};
