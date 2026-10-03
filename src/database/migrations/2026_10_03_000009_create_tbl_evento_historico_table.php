<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Histórico de alterações do evento (CLAUDE.md, seção 4): uma linha por campo alterado,
     * com valor antigo, valor novo, quem alterou e quando. Alimenta o status derivado "Alterado"
     * (só data, horário e local contam) e o "Mostrar" que restaura o status anterior ao ocultar.
     *
     * Tipos iguais aos das colunas referenciadas: id_evento_calendario é INT UNSIGNED;
     * tbl_usuarios.id_usuario é BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::create('tbl_evento_historico', function (Blueprint $table) {
            $table->increments('id_evento_historico');
            $table->unsignedInteger('id_evento_calendario');
            $table->string('campo_evento_historico', 40);
            $table->text('valor_antigo_evento_historico')->nullable();
            $table->text('valor_novo_evento_historico')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_evento_historico')->useCurrent();

            $table->index(['id_evento_calendario', 'campo_evento_historico'], 'idx_historico_evento_campo');
            $table->index('id_usuario', 'fk_historico_usuario');

            $table->foreign('id_evento_calendario', 'fk_historico_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_historico_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_evento_historico');
    }
};
