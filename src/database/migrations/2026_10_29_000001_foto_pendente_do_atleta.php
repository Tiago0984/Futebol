<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Foto do atleta enviada pelo app (29/10/2026, dono do projeto): fica pendente até a secretaria aprovar no painel
     * (o atleta é menor e a foto vai para o site). foto_pendente_atleta = nome do arquivo no disco "local"
     * (storage/app/private/fotos-pendentes, fora de public/); NULL = nada esperando aprovação. Só acrescenta colunas
     * nullable; não depende de ids nem mexe em dados.
     */
    public function up(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->string('foto_pendente_atleta', 255)->nullable()->after('foto_atleta');
            $table->dateTime('data_foto_pendente_atleta')->nullable()->after('foto_pendente_atleta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->dropColumn(['foto_pendente_atleta', 'data_foto_pendente_atleta']);
        });
    }
};
