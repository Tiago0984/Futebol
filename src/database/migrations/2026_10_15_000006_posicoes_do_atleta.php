<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lista fixa de posições (Fase D; lista do dono do projeto, 15/10/2026). Rótulos e siglas em Atleta::POSICOES
    private const POSICOES = [
        'GOLEIRO', 'ZAGUEIRO', 'LATERAL_DIREITO', 'LATERAL_ESQUERDO', 'LIBERO', 'VOLANTE', 'MEIA_CENTRAL',
        'MEIA_OFENSIVO', 'MEIA_DIREITA', 'MEIA_ESQUERDA', 'PONTA_DIREITA', 'PONTA_ESQUERDA', 'CENTROAVANTE',
        'SEGUNDO_ATACANTE',
    ];

    // Texto livre antigo (tbl_atletas.posicao_atleta) => posição da lista
    private const DE_PARA = [
        'GOLEIRO'      => 'GOLEIRO',
        'ZAGUEIRO'     => 'ZAGUEIRO',
        'LATERAL'      => 'LATERAL_DIREITO',
        'VOLANTE'      => 'VOLANTE',
        'MEIA'         => 'MEIA_CENTRAL',
        'ATACANTE'     => 'CENTROAVANTE',
        'CENTROAVANTE' => 'CENTROAVANTE',
    ];

    /**
     * Run the migrations.
     *
     * Posições do atleta (Fase D, Etapa D1): até 3 por atleta (Atleta::POSICOES_MAX), da lista fixa, em ordem
     * (a 1ª é a principal), no lugar do texto livre tbl_atletas.posicao_atleta.
     *
     * 1. Confere os valores antigos: um que não está no de-para para com a lista SEM mexer em nada (corrigir o
     *    cadastro e rodar de novo).
     * 2. Cria tbl_atleta_posicao (único atleta + posição e atleta + ordem; FK para o atleta, NO ACTION).
     * 3. Copia a posição antiga como a principal (LATERAL vira Lateral direito, MEIA vira Meia-central,
     *    ATACANTE vira Centroavante) e apaga a coluna antiga.
     */
    public function up(): void
    {
        $normalizado = 'UPPER(TRIM(posicao_atleta))';

        $desconhecidos = DB::table('tbl_atletas')
            ->whereRaw("TRIM(COALESCE(posicao_atleta, '')) <> ''")
            ->whereRaw("{$normalizado} NOT IN ('" . implode("', '", array_keys(self::DE_PARA)) . "')")
            ->get(['id_atleta', 'posicao_atleta']);

        if ($desconhecidos->isNotEmpty()) {
            throw new RuntimeException(
                'tbl_atletas.posicao_atleta tem valores fora da lista (' . implode(', ', array_keys(self::DE_PARA)) . '): '
                . $desconhecidos->map(fn ($a) => "\"{$a->posicao_atleta}\" (atleta {$a->id_atleta})")->implode('; ')
                . '. Corrija esses cadastros e rode a migration de novo.'
            );
        }

        Schema::create('tbl_atleta_posicao', function (Blueprint $table) {
            $table->increments('id_atleta_posicao');
            $table->integer('id_atleta');
            $table->enum('posicao_atleta_posicao', self::POSICOES);
            $table->unsignedTinyInteger('ordem_atleta_posicao');

            // Os dois únicos começam por id_atleta e servem também à FK
            $table->unique(['id_atleta', 'posicao_atleta_posicao'], 'atleta_posicao_unique');
            $table->unique(['id_atleta', 'ordem_atleta_posicao'], 'atleta_posicao_ordem_unique');

            $table->foreign('id_atleta', 'fk_atleta_posicao_atleta')
                ->references('id_atleta')->on('tbl_atletas')
                ->onUpdate('no action')->onDelete('no action');
        });

        $deParaSql = 'CASE ' . implode(' ', array_map(
            fn ($antigo, $novo) => "WHEN {$normalizado} = '{$antigo}' THEN '{$novo}'",
            array_keys(self::DE_PARA), self::DE_PARA
        )) . ' END';

        DB::statement("INSERT INTO tbl_atleta_posicao (id_atleta, posicao_atleta_posicao, ordem_atleta_posicao)
            SELECT id_atleta, {$deParaSql}, 1 FROM tbl_atletas WHERE TRIM(COALESCE(posicao_atleta, '')) <> ''");

        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->dropColumn('posicao_atleta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Volta a coluna de texto com a posição principal no nome antigo (Lateral direito/esquerdo => LATERAL,
     * meias => MEIA, Líbero => ZAGUEIRO, atacantes => ATACANTE; a 2ª e a 3ª posições se perdem) e apaga a tabela.
     */
    public function down(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->string('posicao_atleta', 50)->nullable()->after('numero_matricula_atleta');
        });

        DB::statement("UPDATE tbl_atletas a JOIN tbl_atleta_posicao p
                ON p.id_atleta = a.id_atleta AND p.ordem_atleta_posicao = 1
            SET a.posicao_atleta = CASE
                WHEN p.posicao_atleta_posicao IN ('GOLEIRO', 'ZAGUEIRO', 'VOLANTE') THEN p.posicao_atleta_posicao
                WHEN p.posicao_atleta_posicao = 'LIBERO' THEN 'ZAGUEIRO'
                WHEN p.posicao_atleta_posicao LIKE 'LATERAL%' THEN 'LATERAL'
                WHEN p.posicao_atleta_posicao LIKE 'MEIA%' THEN 'MEIA'
                ELSE 'ATACANTE'
            END");

        Schema::dropIfExists('tbl_atleta_posicao');
    }
};
