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
        Schema::create('tbl_campeonato', function (Blueprint $table) {
            $table->integer('id_campeonato', true);
            $table->integer('id_categoria')->index('fk_campeonato_categoria');
            $table->string('logo_evento');
            $table->string('banner_evento');
            $table->string('nome_campeonato', 100);
            $table->string('organizador_campeonato', 100);
            $table->text('descricao_campeonato')->nullable();
            $table->string('tipo_campeonato', 20);
            $table->dateTime('data_inicio_campeonato');
            $table->dateTime('data_fim_campeonato');
            $table->string('local_evento', 100);
            $table->string('status_campeonato', 10)->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_campeonato');
    }
};
