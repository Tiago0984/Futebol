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
        Schema::create('tbl_banner', function (Blueprint $table) {
            $table->integer('id_banner', true);
            $table->string('titulo_banner', 100)->nullable();
            $table->string('subtitulo_banner')->nullable();
            $table->string('foto_banner');
            $table->integer('ordem_banner')->nullable()->default(0);
            $table->string('status_banner', 10)->nullable()->default('ATIVO');
            $table->dateTime('criado_em')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_banner');
    }
};
