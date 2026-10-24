<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Responsável pela atividade escolhido da comissão técnica (Etapa C2, 25/10/2026): o formulário do evento e o da
     * grade passam a ter só o select. As colunas de texto de 24/10 (responsavel_evento_calendario,
     * responsavel_grade_treino) ficam, com o "texto antigo", até alguém editar e escolher da lista; nada é apagado nem
     * convertido. Tipo igual ao de tbl_comissao_tecnica.id_comissao_tecnica (INT UNSIGNED). FKs NO ACTION (o
     * profissional não é excluído, só desativado). Só acrescenta colunas nullable; não depende de ids.
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->unsignedInteger('id_comissao_tecnica')->nullable()->after('responsavel_evento_calendario');
            $table->foreign('id_comissao_tecnica', 'fk_evento_comissao_tecnica')
                ->references('id_comissao_tecnica')->on('tbl_comissao_tecnica')
                ->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->unsignedInteger('id_comissao_tecnica')->nullable()->after('responsavel_grade_treino');
            $table->foreign('id_comissao_tecnica', 'fk_grade_comissao_tecnica')
                ->references('id_comissao_tecnica')->on('tbl_comissao_tecnica')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Volta só a estrutura: quem foi escolhido da lista fica sem responsável (o nome não volta como texto).
     */
    public function down(): void
    {
        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->dropForeign('fk_grade_comissao_tecnica');
            $table->dropColumn('id_comissao_tecnica');
        });

        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropForeign('fk_evento_comissao_tecnica');
            $table->dropColumn('id_comissao_tecnica');
        });
    }
};
