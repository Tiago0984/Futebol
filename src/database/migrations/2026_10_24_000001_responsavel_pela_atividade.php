<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Responsável pela atividade (24/10/2026, pedido do app; opção (b) do dono do projeto): quem conduz o treino ou
     * o evento (professor, técnico), em texto livre e opcional. Não é o id_usuario do evento (quem criou no admin,
     * que continua como está). Na grade, o mesmo campo, copiado para os eventos pelo "Gerar agenda do mês".
     * Só acrescenta colunas nullable (collation do banco, utf8mb4_general_ci); não depende de ids nem mexe em dados.
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->string('responsavel_evento_calendario', 100)->nullable()->after('local_evento_calendario');
        });

        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->string('responsavel_grade_treino', 100)->nullable()->after('local_grade_treino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->dropColumn('responsavel_grade_treino');
        });

        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropColumn('responsavel_evento_calendario');
        });
    }
};
