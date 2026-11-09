<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de evento individual cadastrados pelo painel (dono do projeto, 09/11/2026: quem cadastra ficava preso a
     * Avaliação, Reunião e Evento). O tipo do evento continua sendo um dos três do ENUM (o app, o filtro ?tipo= da API,
     * a duração padrão do conflito e os ramos do menu dependem dele): cada tipo cadastrado diz como "funciona"
     * (base_tipo_evento_individual) e o nome dele vai para o subtipo do evento, que o app já mostra.
     *
     * - tbl_tipo_evento_individual: nome único (a collation ignora maiúsculas e acentos), a base, ativo/inativo
     *   (sem exclusão, como os locais), quem cadastrou e quando.
     * - tbl_evento_calendario.id_tipo_evento_individual: o tipo escolhido (NULL = um dos três de sempre). O subtipo
     *   guarda a cópia do nome (como o local guarda a do nome do local).
     */
    public function up(): void
    {
        Schema::create('tbl_tipo_evento_individual', function (Blueprint $table) {
            $table->increments('id_tipo_evento_individual');
            $table->string('nome_tipo_evento_individual', 60);
            $table->enum('base_tipo_evento_individual', ['AVALIACAO', 'REUNIAO', 'EVENTO']);
            $table->enum('status_tipo_evento_individual', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->dateTime('data_tipo_evento_individual')->useCurrent();

            $table->unique('nome_tipo_evento_individual', 'tipo_evento_individual_nome_unique');
            $table->foreign('id_usuario', 'fk_tipo_evento_individual_usuario')->references('id_usuario')->on('tbl_usuarios');
        });

        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->unsignedInteger('id_tipo_evento_individual')->nullable()->after('subtipo_evento_calendario');

            $table->foreign('id_tipo_evento_individual', 'fk_evento_tipo_individual')
                ->references('id_tipo_evento_individual')->on('tbl_tipo_evento_individual');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropForeign('fk_evento_tipo_individual');
            $table->dropColumn('id_tipo_evento_individual');
        });

        Schema::dropIfExists('tbl_tipo_evento_individual');
    }
};
