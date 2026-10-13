<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Treino no turno oposto ao da escola (professor, 13/10/2026): o período escolar passa a decidir em quais
     * treinos o atleta entra, então vira ENUM sem acento (MANHA, TARDE, NOITE, INTEGRAL; rótulos em
     * Atleta::PERIODOS). Até aqui era texto livre, gravado de jeitos diferentes pelo site ("Manhã"), pelo admin
     * ("MANHÃ") e pelo app.
     *
     * 1. Confere os valores: um valor que não é manhã, tarde, noite nem integral para com a lista SEM mexer em
     *    nada (corrigir o cadastro e rodar de novo).
     * 2. Normaliza (pelo começo da palavra, que não depende do acento nem da codificação; vazio vira NULL).
     * 3. ENUM nullable: atletas antigos podem estar sem período; os formulários passam a exigir.
     * 4. turno_treino_atleta (MANHA, TARDE, AMBOS): só quem estuda à noite escolhe; nos outros fica NULL
     *    (o turno sai do período: Atleta::turnosDeTreino).
     */
    public function up(): void
    {
        $normalizado = "CASE
            WHEN UPPER(TRIM(periodo_escolar_atleta)) LIKE 'MANH%' THEN 'MANHA'
            WHEN UPPER(TRIM(periodo_escolar_atleta)) LIKE 'TARD%' THEN 'TARDE'
            WHEN UPPER(TRIM(periodo_escolar_atleta)) LIKE 'NOIT%' THEN 'NOITE'
            WHEN UPPER(TRIM(periodo_escolar_atleta)) LIKE 'INTEGRA%' THEN 'INTEGRAL'
        END";

        $desconhecidos = DB::table('tbl_atletas')
            ->whereRaw("TRIM(periodo_escolar_atleta) <> ''")
            ->whereRaw("({$normalizado}) IS NULL")
            ->get(['id_atleta', 'periodo_escolar_atleta']);

        if ($desconhecidos->isNotEmpty()) {
            throw new RuntimeException(
                'tbl_atletas.periodo_escolar_atleta tem valores que não são manhã, tarde, noite nem integral: '
                . $desconhecidos->map(fn ($a) => "\"{$a->periodo_escolar_atleta}\" (atleta {$a->id_atleta})")->implode('; ')
                . '. Corrija esses cadastros e rode a migration de novo.'
            );
        }

        DB::table('tbl_atletas')->update(['periodo_escolar_atleta' => DB::raw($normalizado)]);

        DB::statement("ALTER TABLE tbl_atletas
            MODIFY periodo_escolar_atleta ENUM('MANHA', 'TARDE', 'NOITE', 'INTEGRAL') NULL DEFAULT NULL,
            ADD turno_treino_atleta ENUM('MANHA', 'TARDE', 'AMBOS') NULL DEFAULT NULL AFTER periodo_escolar_atleta");
    }

    /**
     * Reverse the migrations.
     *
     * Volta a VARCHAR(20); os valores ficam como estão (MANHA, TARDE...), que o texto livre aceita.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_atletas
            DROP COLUMN turno_treino_atleta,
            MODIFY periodo_escolar_atleta VARCHAR(20) NULL DEFAULT NULL");
    }
};
