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
        Schema::create('tbl_evento_calendario', function (Blueprint $table) {
            $table->increments('id_evento_calendario');
            $table->string('titulo_evento_calendario');
            $table->text('descricao_evento_calendario')->nullable();
            $table->enum('tipo_evento_calendario', ['JOGO', 'TREINO', 'CAMPEONATO']);
            $table->string('subtipo_evento_calendario', 60)->nullable();
            $table->date('data_evento_calendario');
            $table->time('horario_inicio_evento_calendario');
            $table->time('horario_fim_evento_calendario')->nullable();
            $table->string('local_evento_calendario');
            $table->enum('destaque_evento_calendario', ['SIM', 'NAO'])->default('NAO');
            $table->enum('status_evento_calendario', ['ATIVO', 'INATIVO'])->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_evento_calendario');
    }
};
