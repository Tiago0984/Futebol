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
        Schema::create('tbl_noticias', function (Blueprint $table) {
            $table->integer('id_noticia', true);
            $table->string('titulo_noticia', 150);
            $table->text('conteudo_noticia');
            $table->string('foto_noticia')->nullable();
            $table->string('categoria_noticia', 50)->default('Avisos Oficiais');
            $table->dateTime('data_publicacao_noticia')->useCurrent();
            $table->string('autor_noticia', 100)->nullable();
            $table->string('status_noticia', 10)->nullable()->default('ATIVO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_noticias');
    }
};
