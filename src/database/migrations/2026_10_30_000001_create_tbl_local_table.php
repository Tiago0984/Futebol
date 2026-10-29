<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cadastro de locais (Etapa L1, 30/10/2026; pedido do app: o "Abrir mapa" do compromisso só tinha o texto livre do
     * local). Nome único (a collation ignora maiúsculas e acentos), endereço em colunas próprias (não na tbl_endereco,
     * para não haver registro compartilhado), link do Google Maps opcional, ativo/inativo e sem exclusão, como a
     * comissão técnica. Sem latitude e longitude por enquanto. Só cria a tabela: o id_local no evento, na grade e no
     * campeonato vem na L2.
     */
    public function up(): void
    {
        Schema::create('tbl_local', function (Blueprint $table) {
            $table->increments('id_local');
            $table->string('nome_local', 100);
            $table->string('rua_local', 100);
            $table->string('numero_local', 10);
            $table->string('complemento_local', 100)->nullable();
            $table->string('bairro_local', 50);
            $table->string('cidade_local', 50);
            $table->char('estado_local', 2);
            $table->string('cep_local', 9)->nullable();
            $table->string('mapa_url_local', 500)->nullable();
            $table->enum('status_local', ['ATIVO', 'INATIVO'])->default('ATIVO');

            $table->unique('nome_local', 'local_nome_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_local');
    }
};
