<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Evento gerado pela grade de treino (Fase 7): de qual linha da grade e de qual data ele veio.
     * data_grade_evento_calendario é a data de origem e fica separada de data_evento_calendario: se o
     * admin mudar o treino de dia, a chave continua impedindo que a mesma data seja gerada de novo.
     * Índice único (grade, data) evita duplicar; eventos manuais têm os dois NULL e não colidem
     * (no MySQL, NULL não se repete em índice único).
     *
     * Tipo igual ao da coluna referenciada (FK exige o mesmo tipo e sinal), por isso sem foreignId():
     * tbl_grade_treino.id_grade_treino é INT UNSIGNED.
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->unsignedInteger('id_grade_treino')->nullable()->after('id_usuario');
            $table->date('data_grade_evento_calendario')->nullable()->after('id_grade_treino');

            $table->unique(['id_grade_treino', 'data_grade_evento_calendario'], 'evento_grade_data_unique');

            $table->foreign('id_grade_treino', 'fk_evento_grade')
                ->references('id_grade_treino')->on('tbl_grade_treino')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropForeign('fk_evento_grade');
            $table->dropUnique('evento_grade_data_unique');
            $table->dropColumn(['id_grade_treino', 'data_grade_evento_calendario']);
        });
    }
};
