<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Formatos antigos (vindos do script SQL original) => opção da lista (Responsavel::GRAUS_PARENTESCO).
    // Mapa fixo, sem depender do model, para a migration não mudar se a lista mudar.
    private const MAPA = [
        'PAI' => 'Pai',
        'MAE' => 'Mãe',
    ];

    /**
     * Run the migrations.
     *
     * BINARY na comparação: a collation utf8mb4_general_ci acha 'MAE' igual a 'Mãe' e a 'mae',
     * e sem ele o filtro pegaria também as linhas que já estão certas.
     */
    public function up(): void
    {
        foreach (self::MAPA as $antigo => $novo) {
            DB::table('tbl_atleta_responsavel')
                ->whereRaw('BINARY grau_parentesco_responsavel = ?', [$antigo])
                ->update(['grau_parentesco_responsavel' => $novo]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Sem volta: não há como saber quais linhas estavam no formato antigo (o backup tem o estado anterior).
     */
    public function down(): void
    {
        //
    }
};
