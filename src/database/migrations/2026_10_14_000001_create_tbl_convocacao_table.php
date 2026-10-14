<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convocação por campeonato (Fase G, Etapa 1): o atleta convocado por um time interno (a "camisa" da AACJ
     * naquele campeonato), com o número da camisa definido na convocação, quem convocou e quando.
     * Um time por atleta em cada campeonato (único campeonato + atleta) e camisa sem repetir no mesmo time
     * do campeonato (único campeonato + time + camisa).
     *
     * Tipos iguais aos das colunas referenciadas: campeonato, time e atleta INT com sinal; usuário BIGINT UNSIGNED.
     */
    public function up(): void
    {
        Schema::create('tbl_convocacao', function (Blueprint $table) {
            $table->increments('id_convocacao');
            $table->integer('id_campeonato');
            $table->integer('id_time');
            $table->integer('id_atleta');
            $table->unsignedTinyInteger('camisa_convocacao');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_convocacao')->useCurrent();

            // Os dois únicos começam por id_campeonato e servem também à FK dele
            $table->unique(['id_campeonato', 'id_atleta'], 'convocacao_campeonato_atleta_unique');
            $table->unique(['id_campeonato', 'id_time', 'camisa_convocacao'], 'convocacao_camisa_unique');
            $table->index('id_time', 'fk_convocacao_time');
            $table->index('id_atleta', 'fk_convocacao_atleta');
            $table->index('id_usuario', 'fk_convocacao_usuario');

            $table->foreign('id_campeonato', 'fk_convocacao_campeonato')
                ->references('id_campeonato')->on('tbl_campeonato')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_time', 'fk_convocacao_time')
                ->references('id_time')->on('tbl_time')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_atleta', 'fk_convocacao_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_usuario', 'fk_convocacao_usuario')
                ->references('id_usuario')->on('tbl_usuarios')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_convocacao');
    }
};
