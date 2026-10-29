<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Histórico de alterações do responsável (29/10/2026): nome, CPF, contatos e endereço mudados pelo próprio
     * responsável no app (PATCH /api/v1/responsavel) ficam registrados para a secretaria, uma linha por campo, com o
     * valor antigo, o novo e quando. id_usuario NULL = feito pelo responsável no app. Mesmo molde da
     * tbl_atleta_historico.
     *
     * Tipos iguais aos das colunas referenciadas: tbl_responsavel.id_responsavel é INT com sinal;
     * tbl_usuarios.id_usuario é BIGINT UNSIGNED. FKs NO ACTION (o responsável não é excluído pelo painel).
     */
    public function up(): void
    {
        Schema::create('tbl_responsavel_historico', function (Blueprint $table) {
            $table->increments('id_responsavel_historico');
            $table->integer('id_responsavel');
            $table->string('campo_responsavel_historico', 40);
            $table->text('valor_antigo_responsavel_historico')->nullable();
            $table->text('valor_novo_responsavel_historico')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_responsavel_historico')->useCurrent();

            $table->index(['id_responsavel', 'data_responsavel_historico'], 'idx_responsavel_historico_data');
            $table->index('id_usuario', 'fk_responsavel_historico_usuario');

            $table->foreign('id_responsavel', 'fk_responsavel_historico_responsavel')
                ->references('id_responsavel')->on('tbl_responsavel')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_responsavel_historico_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_responsavel_historico');
    }
};
