<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OBSERVACAO = 'Recalculada pela regra do ano ao remover a Sub-12 (categoria de teste).';

    /**
     * Run the migrations.
     *
     * Remove a Sub-12 (10–12), que veio do script SQL e não está nas faixas oficiais.
     * Só age se ela existir: num banco novo (primeiro deploy) não faz nada.
     * Todos os dados que apontam para ela são de teste (CLAUDE.md, seção 8, pergunta 2):
     * - campeonatos e times vão para a Sub-11 M, a faixa mais próxima;
     * - cada atleta ativo na Sub-12 ganha uma linha nova pela regra do ano
     *   (idade = ano atual − ano de nascimento, mesmo sexo); quem ficar fora de 9–17 fica sem categoria;
     * - as linhas da Sub-12 em tbl_categoria_atleta são apagadas, porque a FK (NO ACTION)
     *   impede apagar a categoria enquanto houver linha apontando para ela, mesmo fechada.
     * O cálculo fica aqui (e não num método do model) para a migration não mudar se o model mudar.
     */
    public function up(): void
    {
        $idsSub12 = DB::table('tbl_categoria')->where('nome_categoria', 'Sub-12')->pluck('id_categoria');

        if ($idsSub12->isEmpty()) {
            return;
        }

        $idSub11M = DB::table('tbl_categoria')
            ->where('nome_categoria', 'Sub-11')
            ->where('sexo_categoria', 'M')
            ->value('id_categoria');

        if ($idSub11M === null) {
            throw new RuntimeException('Sub-11 M não encontrada: rode antes a migration cria_categorias_oficiais.');
        }

        DB::transaction(function () use ($idsSub12, $idSub11M) {
            DB::table('tbl_campeonato')->whereIn('id_categoria', $idsSub12)->update(['id_categoria' => $idSub11M]);
            DB::table('tbl_time')->whereIn('id_categoria', $idsSub12)->update(['id_categoria' => $idSub11M]);

            $ativos = DB::table('tbl_categoria_atleta as ca')
                ->join('tbl_atletas as a', 'a.id_atleta', '=', 'ca.id_atleta')
                ->whereIn('ca.id_categoria', $idsSub12)
                ->where('ca.status_categoria_atleta', 'ATIVO')
                ->select('a.id_atleta', 'a.data_nasc_atleta', 'a.sexo_atleta')
                ->get();

            $ano = (int) now()->format('Y');

            foreach ($ativos as $atleta) {
                $idade = $ano - (int) substr($atleta->data_nasc_atleta, 0, 4);

                $idNova = DB::table('tbl_categoria')
                    ->where('sexo_categoria', $atleta->sexo_atleta)
                    ->where('status_categoria', 'ATIVO')
                    ->where('idade_min_categoria', '<=', $idade)
                    ->where('idade_max_categoria', '>=', $idade)
                    ->whereNotIn('id_categoria', $idsSub12)
                    ->value('id_categoria');

                if ($idNova !== null) {
                    DB::table('tbl_categoria_atleta')->insert([
                        'id_categoria'                 => $idNova,
                        'id_atleta'                    => $atleta->id_atleta,
                        'data_inicio_categoria_atleta' => now(),
                        'status_categoria_atleta'      => 'ATIVO',
                        'observacao_categoria_atleta'  => self::OBSERVACAO,
                    ]);
                }
            }

            DB::table('tbl_categoria_atleta')->whereIn('id_categoria', $idsSub12)->delete();
            DB::table('tbl_categoria')->whereIn('id_categoria', $idsSub12)->delete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Sem volta: a Sub-12 e os vínculos dela eram dados de teste (o backup tem o estado anterior).
     */
    public function down(): void
    {
        //
    }
};
