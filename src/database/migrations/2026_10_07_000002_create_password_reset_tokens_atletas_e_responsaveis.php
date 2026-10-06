<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tokens dos links "Defina sua senha" e "Esqueci minha senha" do app (Fase 9), uma tabela por perfil,
     * no formato que o broker de senhas do Laravel usa (como password_reset_tokens, a do admin). Separadas
     * porque o mesmo e-mail pode ser do atleta e do responsável: um link não pode apagar o do outro.
     */
    public function up(): void
    {
        foreach (['password_reset_tokens_atletas', 'password_reset_tokens_responsaveis'] as $tabela) {
            Schema::create($tabela, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens_responsaveis');
        Schema::dropIfExists('password_reset_tokens_atletas');
    }
};
