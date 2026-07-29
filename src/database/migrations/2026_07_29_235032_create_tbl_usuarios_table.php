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
        Schema::create('tbl_usuarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nome_usuario');
            $table->string('email_usuario')->unique('users_email_unique');
            $table->string('foto_usuario')->nullable();
            $table->string('senha_usuario');
            $table->string('remember_token_usuario', 100)->nullable();
            $table->timestamp('criado_em_usuarios')->nullable();
            $table->timestamp('atualizado_em_usuarios')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_usuarios');
    }
};
