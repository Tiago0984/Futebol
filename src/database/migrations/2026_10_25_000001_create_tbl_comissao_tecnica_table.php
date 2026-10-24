<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Comissão técnica (Etapa C1, 25/10/2026, pedido do app para o perfil do responsável pela atividade): cadastro
     * próprio, separado de tbl_usuarios (login do admin: nem todo profissional entra no painel).
     * - cargo: chave de ComissaoTecnica::CARGOS (lista fixa, ampliável no model; VARCHAR, como tbl_usuarios.cargo_usuario);
     * - foto só vai para o app com foto_autorizada = 1 (decisão do dono do projeto);
     * - telefone e e-mail INSTITUCIONAIS (da escolinha), nunca os pessoais;
     * - id_categoria = a categoria principal, que também fica na lista de categorias atendidas
     *   (tbl_comissao_tecnica_categoria).
     * Tipos iguais aos das colunas referenciadas: tbl_categoria.id_categoria é INT com sinal. FKs NO ACTION
     * (o profissional não é excluído, só desativado). Só cria tabelas; não depende de ids.
     */
    public function up(): void
    {
        Schema::create('tbl_comissao_tecnica', function (Blueprint $table) {
            $table->increments('id_comissao_tecnica');
            $table->string('nome_comissao_tecnica', 100);
            $table->string('cargo_comissao_tecnica', 60);
            $table->string('foto_comissao_tecnica', 255)->nullable();
            $table->boolean('foto_autorizada_comissao_tecnica')->default(false);
            $table->integer('id_categoria')->nullable();
            $table->date('data_inicio_comissao_tecnica')->nullable();
            $table->string('licenca_comissao_tecnica', 100)->nullable();
            $table->string('especialidade_comissao_tecnica', 100)->nullable();
            $table->string('telefone_institucional_comissao_tecnica', 20)->nullable();
            $table->string('email_institucional_comissao_tecnica', 255)->nullable();
            $table->enum('status_comissao_tecnica', ['ATIVO', 'INATIVO'])->default('ATIVO');

            $table->foreign('id_categoria', 'fk_comissao_tecnica_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });

        Schema::create('tbl_comissao_tecnica_categoria', function (Blueprint $table) {
            $table->increments('id_comissao_tecnica_categoria');
            $table->unsignedInteger('id_comissao_tecnica');
            $table->integer('id_categoria');

            $table->unique(['id_comissao_tecnica', 'id_categoria'], 'comissao_tecnica_categoria_unique');
            $table->index('id_categoria', 'fk_ctc_categoria');

            $table->foreign('id_comissao_tecnica', 'fk_ctc_comissao_tecnica')
                ->references('id_comissao_tecnica')->on('tbl_comissao_tecnica')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_categoria', 'fk_ctc_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_comissao_tecnica_categoria');
        Schema::dropIfExists('tbl_comissao_tecnica');
    }
};
