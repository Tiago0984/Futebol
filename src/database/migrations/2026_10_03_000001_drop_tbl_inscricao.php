<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tbl_inscricao foi descontinuada: a categoria do atleta fica só em tbl_categoria_atleta.
     * Nenhum código grava nela, e as linhas existentes repetiam tbl_categoria_atleta.
     * Precisa entrar junto com a remoção do model Inscricao e da linha em
     * Atleta::excluirComDependencias(): sem a tabela, aquela linha quebraria a exclusão.
     */
    public function up(): void
    {
        Schema::table('tbl_inscricao', function (Blueprint $table) {
            $table->dropForeign('fk_inscricao_atleta');
            $table->dropForeign('fk_inscricao_categoria');
        });

        Schema::drop('tbl_inscricao');
    }

    /**
     * Reverse the migrations.
     *
     * Recria a estrutura (mesma das migrations create_ e add_foreign_keys_), sem os dados.
     */
    public function down(): void
    {
        Schema::create('tbl_inscricao', function (Blueprint $table) {
            $table->integer('id_inscricao', true);
            $table->integer('id_atleta')->index('fk_inscricao_atleta');
            $table->integer('id_categoria')->index('fk_inscricao_categoria');
            $table->dateTime('data_inscricao')->useCurrent();
            $table->string('status_inscricao', 11)->default('ATIVO');

            $table->foreign(['id_atleta'], 'fk_inscricao_atleta')->references(['id_atleta'])->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_categoria'], 'fk_inscricao_categoria')->references(['id_categoria'])->on('tbl_categoria')->onUpdate('no action')->onDelete('no action');
        });
    }
};
