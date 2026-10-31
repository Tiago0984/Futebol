<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_fale_conosco', function (Blueprint $table) {
            $table->increments('id_fale_conosco');
            $table->integer('id_atleta')->nullable();
            $table->integer('id_responsavel')->nullable();
            $table->enum('origem_fale_conosco', ['APP', 'SITE']);
            $table->enum('perfil_fale_conosco', ['ATLETA', 'RESPONSAVEL', 'VISITANTE']);
            // Retrato do remetente: permanece mesmo se o cadastro for alterado ou excluído.
            $table->string('nome_fale_conosco', 150);
            $table->string('email_fale_conosco', 255);
            $table->string('telefone_fale_conosco', 20)->nullable();
            $table->string('assunto_fale_conosco', 100);
            $table->text('mensagem_fale_conosco');
            $table->dateTime('data_fale_conosco');
            $table->dateTime('data_leitura_fale_conosco')->nullable();
            $table->enum('status_email_fale_conosco', ['PENDENTE', 'ENVIADO', 'FALHOU'])->default('PENDENTE');
            $table->index(['data_fale_conosco', 'id_fale_conosco'], 'fale_conosco_data_idx');
            $table->index('data_leitura_fale_conosco', 'fale_conosco_leitura_idx');
            $table->foreign('id_atleta', 'fk_fale_conosco_atleta')->references('id_atleta')->on('tbl_atletas')->nullOnDelete();
            $table->foreign('id_responsavel', 'fk_fale_conosco_responsavel')->references('id_responsavel')->on('tbl_responsavel')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_fale_conosco');
    }
};
