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
     * E-mail do responsável único (Fase 9): é o login do perfil responsável no app. Primeiro confere os
     * repetidos já normalizados (minúsculas, sem espaços): se houver, para com a lista SEM mexer em nada (a
     * limpeza é feita antes, com OK do dono do projeto). Depois normaliza (vazio vira NULL, e NULL pode
     * repetir) e cria o índice.
     */
    public function up(): void
    {
        $normalizado = "NULLIF(LOWER(TRIM(email_responsavel)), '')";

        $repetidos = DB::table('tbl_responsavel')
            ->selectRaw("{$normalizado} AS email, GROUP_CONCAT(id_responsavel ORDER BY id_responsavel) AS ids")
            ->whereRaw("{$normalizado} IS NOT NULL")
            ->groupByRaw($normalizado)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($repetidos->isNotEmpty()) {
            throw new RuntimeException(
                'tbl_responsavel.email_responsavel tem e-mails repetidos: '
                . $repetidos->map(fn ($r) => "{$r->email} (responsáveis {$r->ids})")->implode('; ')
                . '. Corrija esses cadastros e rode a migration de novo.'
            );
        }

        DB::table('tbl_responsavel')->whereNotNull('email_responsavel')
            ->update(['email_responsavel' => DB::raw($normalizado)]);

        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->unique('email_responsavel', 'email_responsavel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->dropUnique('email_responsavel_unique');
        });
    }
};
