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
        Schema::create('tbl_categoria', function (Blueprint $table) {
            $table->integer('id_categoria', true);
            $table->string('nome_categoria', 50);
            $table->integer('idade_min_categoria');
            $table->integer('idade_max_categoria');
            $table->string('sexo_categoria', 1);
            $table->string('status_categoria', 10)->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_categoria');
    }
};
