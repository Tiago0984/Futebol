<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * id_categoria: preenchido em evento de categoria, vazio em evento individual (CLAUDE.md, seção 4).
     * id_usuario: responsável = quem criou o evento; gravado só na criação, nunca sobrescrito.
     * Os eventos que já existem ficam com id_usuario NULL (não se sabe quem os criou).
     *
     * Tipos iguais aos das colunas referenciadas (FK exige o mesmo tipo e sinal), por isso sem foreignId():
     * tbl_categoria.id_categoria é INT com sinal; tbl_usuarios.id_usuario é BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable()->after('subtipo_evento_calendario');
            $table->unsignedBigInteger('id_usuario')->nullable()->after('status_evento_calendario');

            $table->index('id_categoria', 'fk_evento_categoria');
            $table->index('id_usuario', 'fk_evento_usuario');

            $table->foreign('id_categoria', 'fk_evento_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_evento_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropForeign('fk_evento_categoria');
            $table->dropForeign('fk_evento_usuario');
            $table->dropIndex('fk_evento_categoria');
            $table->dropIndex('fk_evento_usuario');
            $table->dropColumn(['id_categoria', 'id_usuario']);
        });
    }
};
