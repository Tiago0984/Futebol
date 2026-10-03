<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Atletas aprovados (ATIVO ou INATIVO) sem número de matrícula recebem o próximo (A001, A002...),
     * em ordem de id_atleta. Antes, só a aprovação da matrícula gerava número; o cadastro pelo admin
     * deixava o atleta sem. Pendentes e rejeitados ficam como estão (recebem na aprovação).
     * Num banco novo não há ninguém para preencher e a migration não faz nada.
     * O cálculo fica aqui (e não em Atleta::atribuirNumeroMatricula) para a migration não mudar se o model mudar.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $semNumero = DB::table('tbl_atletas')
                ->whereIn(DB::raw('UPPER(status_atleta)'), ['ATIVO', 'INATIVO'])
                ->where(fn ($q) => $q->whereNull('numero_matricula_atleta')->orWhere('numero_matricula_atleta', ''))
                ->orderBy('id_atleta')
                ->lockForUpdate()
                ->pluck('id_atleta');

            if ($semNumero->isEmpty()) {
                return;
            }

            $maior = (int) DB::table('tbl_atletas')
                ->whereRaw("numero_matricula_atleta REGEXP '^A[0-9]+$'")
                ->lockForUpdate()
                ->max(DB::raw('CAST(SUBSTRING(numero_matricula_atleta, 2) AS UNSIGNED)'));

            foreach ($semNumero as $idAtleta) {
                $maior++;

                DB::table('tbl_atletas')
                    ->where('id_atleta', $idAtleta)
                    ->update(['numero_matricula_atleta' => 'A' . str_pad((string) $maior, 3, '0', STR_PAD_LEFT)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Sem volta: não há como distinguir os números gerados aqui dos gerados depois
     * (o backup tem o estado anterior).
     */
    public function down(): void
    {
        //
    }
};
