<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_recuperacao_senha', function (Blueprint $table) {
            $table->increments('id_recuperacao_senha');
            $table->integer('id_atleta')->nullable()->unique();
            $table->integer('id_responsavel')->nullable()->unique();
            $table->enum('perfil_recuperacao_senha', ['ATLETA', 'RESPONSAVEL']);
            $table->string('email_recuperacao_senha', 255);
            $table->string('codigo_hash_recuperacao_senha')->nullable();
            $table->dateTime('data_codigo_recuperacao_senha');
            $table->dateTime('data_expiracao_codigo_recuperacao_senha')->nullable();
            $table->unsignedTinyInteger('tentativas_recuperacao_senha')->default(0);
            $table->char('reset_token_hash_recuperacao_senha', 64)->nullable()->unique('recuperacao_reset_token_unique');
            $table->dateTime('data_expiracao_token_recuperacao_senha')->nullable();
            // E-mail é um retrato do envio; pode passar a outra conta após uma alteração de cadastro.
            $table->index(['perfil_recuperacao_senha', 'email_recuperacao_senha'], 'recuperacao_perfil_email_idx');
            $table->foreign('id_atleta', 'fk_recuperacao_atleta')->references('id_atleta')->on('tbl_atletas')->cascadeOnDelete();
            $table->foreign('id_responsavel', 'fk_recuperacao_responsavel')->references('id_responsavel')->on('tbl_responsavel')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_recuperacao_senha');
    }
};
