<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Faixas definidas pelo professor; idade = ano atual − ano de nascimento
    private const FAIXAS = [
        'Sub-9'  => [9, 9],
        'Sub-11' => [10, 11],
        'Sub-13' => [12, 13],
        'Sub-15' => [14, 15],
        'Sub-17' => [16, 17],
    ];

    private const SEXOS = ['M', 'F'];

    /**
     * Run the migrations.
     *
     * Migration (e não seeder) para as categorias existirem no primeiro deploy, que roda
     * migrate e não db:seed. updateOrInsert por (nome, sexo): rodar de novo não duplica,
     * e uma categoria que já exista (a Sub-15 M local, com 13–15) só tem a faixa corrigida.
     */
    public function up(): void
    {
        foreach (self::FAIXAS as $nome => [$min, $max]) {
            foreach (self::SEXOS as $sexo) {
                DB::table('tbl_categoria')->updateOrInsert(
                    ['nome_categoria' => $nome, 'sexo_categoria' => $sexo],
                    ['idade_min_categoria' => $min, 'idade_max_categoria' => $max, 'status_categoria' => 'ATIVO'],
                );
            }
        }

        Schema::table('tbl_categoria', function (Blueprint $table) {
            $table->unique(['nome_categoria', 'sexo_categoria'], 'nome_sexo_categoria_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Remove o índice e as categorias oficiais que nada referencia. As que têm atleta,
     * time ou campeonato ficam (apagar quebraria a FK), e a faixa antiga de uma categoria
     * que já existia antes (Sub-15 M, 13–15) não é restaurada.
     */
    public function down(): void
    {
        Schema::table('tbl_categoria', function (Blueprint $table) {
            $table->dropUnique('nome_sexo_categoria_unique');
        });

        DB::table('tbl_categoria')
            ->whereIn('nome_categoria', array_keys(self::FAIXAS))
            ->whereNotIn('id_categoria', DB::table('tbl_categoria_atleta')->select('id_categoria'))
            ->whereNotIn('id_categoria', DB::table('tbl_time')->select('id_categoria'))
            ->whereNotIn('id_categoria', DB::table('tbl_campeonato')->select('id_categoria'))
            ->delete();
    }
};
