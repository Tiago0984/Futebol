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
        Schema::create('tbl_inscricao', function (Blueprint $table) {
            $table->integer('id_inscricao', true);
            $table->integer('id_atleta')->index('fk_inscricao_atleta');
            $table->integer('id_categoria')->index('fk_inscricao_categoria');
            $table->dateTime('data_inscricao')->useCurrent();
            $table->string('status_inscricao', 11)->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_inscricao');
    }
};
