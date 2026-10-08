<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Jogo em rascunho (Fase 10, Etapa 4): data de publicação do evento. NULL = rascunho (não avisa ninguém,
     * fica fora do app e do site); preenchida = publicado. Todos os eventos existentes ficam publicados.
     *
     * DEFAULT CURRENT_TIMESTAMP de propósito: quem grava um evento sem pensar no rascunho (insert direto,
     * migration antiga, código novo) cria um evento publicado, como sempre foi. O rascunho é a exceção e
     * grava NULL explicitamente (só a criação de jogo, na Etapa B).
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dateTime('data_publicacao_evento_calendario')->nullable()->useCurrent()
                ->after('status_evento_calendario');
        });

        // O ADD COLUMN com DEFAULT já preenche as linhas existentes; o UPDATE garante, sem depender disso
        DB::table('tbl_evento_calendario')
            ->whereNull('data_publicacao_evento_calendario')
            ->update(['data_publicacao_evento_calendario' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropColumn('data_publicacao_evento_calendario');
        });
    }
};
