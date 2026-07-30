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
        Schema::create('tbl_videos', function (Blueprint $table) {
            $table->increments('id_video');
            $table->string('titulo_video');
            $table->string('url_video', 500);
            $table->text('descricao_video')->nullable();
            $table->string('status_video', 20)->default('ATIVO');
            $table->integer('ao_vivo_video')->default(0);
            $table->string('secao_video', 20)->default('home');
            $table->timestamp('criado_em_video')->useCurrent();
            $table->timestamp('atualizado_em_video')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_videos');
    }
};
