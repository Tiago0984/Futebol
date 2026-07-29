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
        Schema::create('tbl_galeria', function (Blueprint $table) {
            $table->integer('id_galeria', true);
            $table->string('titulo_galeria', 100)->nullable();
            $table->string('foto_galeria');
            $table->string('categoria_galeria', 60)->default('GERAL');
            $table->integer('ordem_galeria')->nullable()->default(0);
            $table->string('status_galeria', 10)->nullable()->default('ATIVO');
            $table->dateTime('criado_em')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_galeria');
    }
};
