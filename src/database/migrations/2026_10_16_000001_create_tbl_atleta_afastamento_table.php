<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Afastamento do atleta (dono do projeto, 16/10/2026): o período (início e fim, os dois obrigatórios) em que o
     * atleta não treina (doença, lesão, viagem), sem inativá-lo: continua ATIVO e com o app. Motivo opcional, só no
     * admin (dado de saúde de menor). Quem registrou e quando. Sem sobreposição entre os afastamentos do mesmo
     * atleta (conferido no model, AtletaAfastamento::sobrepostos).
     *
     * Tipos iguais aos das colunas referenciadas: atleta INT com sinal; usuário BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::create('tbl_atleta_afastamento', function (Blueprint $table) {
            $table->increments('id_atleta_afastamento');
            $table->integer('id_atleta');
            $table->date('data_inicio_atleta_afastamento');
            $table->date('data_fim_atleta_afastamento');
            $table->string('motivo_atleta_afastamento', 255)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_atleta_afastamento')->useCurrent();

            // Começa por id_atleta: serve à FK e às consultas do período (atleta + datas)
            $table->index(['id_atleta', 'data_inicio_atleta_afastamento', 'data_fim_atleta_afastamento'], 'atleta_afastamento_periodo');
            $table->index('id_usuario', 'fk_atleta_afastamento_usuario');

            $table->foreign('id_atleta', 'fk_atleta_afastamento_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_atleta_afastamento_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_atleta_afastamento');
    }
};
