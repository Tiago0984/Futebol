<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_grade_treino', function (Blueprint $table) {
            $table->increments('id_grade_treino');
            $table->enum('dia_semana_grade_treino', ['segunda_quarta', 'terca_quinta', 'sexta', 'sabado']);
            $table->string('categoria_grade_treino', 60);
            $table->enum('tipo_grade_treino', ['TREINO', 'JOGO', 'LIVRE'])->default('TREINO');
            $table->time('horario_inicio_grade_treino')->nullable();
            $table->time('horario_fim_grade_treino')->nullable();
            $table->string('horario_obs_grade_treino', 60)->nullable();
            $table->string('local_grade_treino');
            $table->integer('ordem_grade_treino')->default(1);
            $table->enum('status_grade_treino', ['ATIVO', 'INATIVO'])->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_grade_treino');
    }
};
