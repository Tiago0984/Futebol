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
        Schema::create('tbl_responsavel', function (Blueprint $table) {
            $table->integer('id_responsavel', true);
            $table->integer('id_endereco')->index('fk_responsavel_endereco');
            $table->string('nome_responsavel', 100);
            $table->string('cpf_responsavel', 14);
            $table->string('rg_responsavel', 11);
            $table->string('telefone_responsavel')->nullable();
            $table->string('whatsapp_responsavel', 20);
            $table->string('email_responsavel')->nullable();
            $table->text('assinatura_responsavel')->nullable();
            $table->string('aceite_responsavel', 1)->default('S');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_responsavel');
    }
};
