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
        Schema::create('tbl_time', function (Blueprint $table) {
            $table->integer('id_time', true);
            $table->integer('id_categoria')->index('fk_time_categoria');
            $table->string('logo_time');
            $table->string('nome_time', 50);
            $table->enum('tipo_time', ['INTERNO', 'EXTERNO']);
            $table->string('status_time', 10)->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_time');
    }
};
