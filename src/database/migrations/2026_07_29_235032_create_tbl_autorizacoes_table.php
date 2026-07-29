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
        Schema::create('tbl_autorizacoes', function (Blueprint $table) {
            $table->integer('id_autorizacao', true);
            $table->integer('id_atleta')->index('fk_aut_atleta');
            $table->integer('id_responsavel')->index('fk_aut_responsavel');
            $table->dateTime('data_assinatura_autorizacao')->useCurrent();
            $table->string('token_assinatura', 100)->nullable()->unique('token_assinatura');
            $table->string('status_autorizacao', 20)->default('PENDENTE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_autorizacoes');
    }
};
