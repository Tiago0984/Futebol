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
        Schema::create('tbl_atleta_responsavel', function (Blueprint $table) {
            $table->integer('id_atleta_responsavel', true);
            $table->integer('id_responsavel')->index('fk_ar_responsavel');
            $table->integer('id_atleta')->index('fk_ar_atleta');
            $table->string('grau_parentesco_responsavel', 20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_atleta_responsavel');
    }
};
