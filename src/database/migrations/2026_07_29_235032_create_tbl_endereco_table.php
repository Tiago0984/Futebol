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
        Schema::create('tbl_endereco', function (Blueprint $table) {
            $table->integer('id_endereco', true);
            $table->string('rua_endereco', 100);
            $table->string('numero_endereco', 6);
            $table->string('bairro_endereco', 50);
            $table->string('complemento_endereco', 100)->nullable();
            $table->string('cep_endereco', 10);
            $table->string('cidade_endereco', 50);
            $table->string('estado_endereco', 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_endereco');
    }
};
