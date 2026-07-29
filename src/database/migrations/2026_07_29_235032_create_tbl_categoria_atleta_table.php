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
        Schema::create('tbl_categoria_atleta', function (Blueprint $table) {
            $table->integer('id_categoria_atleta', true);
            $table->integer('id_categoria')->index('fk_ca_categoria');
            $table->integer('id_atleta')->index('fk_ca_atleta');
            $table->dateTime('data_inicio_categoria_atleta');
            $table->dateTime('data_fim_categoria_atleta')->nullable();
            $table->dateTime('data_atualizacao_categoria_atleta')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->string('status_categoria_atleta', 20)->default('ATIVO');
            $table->text('observacao_categoria_atleta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_categoria_atleta');
    }
};
