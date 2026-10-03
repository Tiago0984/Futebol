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
     * A grade passa a apontar para a categoria (coluna única: feminino treina só com feminino,
     * e horários femininos entram como linhas novas). Cada texto "Sub-N" vai para a categoria
     * Sub-N masculina, que é a que a grade atual descreve. Itens gerais ("Integrado",
     * "Treino Livre" e a linha "Jogos", até a Fase 6) ficam com id_categoria NULL.
     * categoria_grade_treino continua como rótulo dos itens gerais.
     *
     * id_categoria é INT com sinal, igual a tbl_categoria.id_categoria (foreignId() criaria
     * BIGINT UNSIGNED e a FK não seria aceita).
     */
    public function up(): void
    {
        // Trava antes de qualquer ALTER (DDL no MySQL não volta com rollback):
        // todo texto "Sub-..." precisa ter a categoria masculina correspondente
        $mapa = $this->mapaSubParaCategoria();

        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable()->after('dia_semana_grade_treino');
            $table->index('id_categoria', 'fk_grade_categoria');
            $table->foreign('id_categoria', 'fk_grade_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });

        $this->preencherIdCategoria($mapa);
    }

    // Públicos para o teste rodar só a parte de dados (ALTER TABLE confirmaria a transação do teste)
    public function preencherIdCategoria(array $mapa): void
    {
        foreach ($mapa as $texto => $idCategoria) {
            DB::table('tbl_grade_treino')
                ->where('categoria_grade_treino', $texto)
                ->update(['id_categoria' => $idCategoria]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->dropForeign('fk_grade_categoria');
            $table->dropIndex('fk_grade_categoria');
            $table->dropColumn('id_categoria');
        });
    }

    /**
     * Texto "Sub-N" da grade => id da categoria Sub-N masculina. Lança exceção se algum não existir.
     */
    public function mapaSubParaCategoria(): array
    {
        $textos = DB::table('tbl_grade_treino')
            ->where('categoria_grade_treino', 'like', 'Sub-%')
            ->distinct()
            ->pluck('categoria_grade_treino');

        $mapa     = [];
        $faltando = [];

        foreach ($textos as $texto) {
            $id = DB::table('tbl_categoria')
                ->where('nome_categoria', trim($texto))
                ->where('sexo_categoria', 'M')
                ->value('id_categoria');

            if ($id === null) {
                $faltando[] = $texto;
            } else {
                $mapa[$texto] = $id;
            }
        }

        if ($faltando) {
            throw new RuntimeException(
                'Grade com categoria sem correspondente masculino em tbl_categoria: '
                . implode(', ', $faltando) . '. Cadastre a categoria ou corrija a grade e rode de novo.'
            );
        }

        return $mapa;
    }
};
