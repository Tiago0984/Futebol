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
     * Time em várias categorias (dono do projeto, 27/10/2026): um cadastro só para a AACJ e para cada adversário
     * ("Botafogo"), com as categorias de que ele participa marcadas, no lugar de um time por categoria
     * ("AACJ Sub-13", "AACJ Sub-15").
     * - tbl_time_categoria (time, categoria; sem repetir o par). Recebe a categoria do cadastro de cada time e as
     *   categorias em que ele já é participante de algum campeonato (o time que joga acima da categoria do
     *   cadastro continua valendo nelas);
     * - tbl_time.id_categoria passa a aceitar NULL (o cadastro novo grava só a lista; a coluna sai depois que o
     *   código deixar de lê-la);
     * - camisa sem repetir por campeonato, categoria e time (convocacao_camisa_categoria_unique): a AACJ no Sub-13 e
     *   no Sub-15 do mesmo campeonato pode ter um 10 em cada.
     * Tipos iguais aos das colunas referenciadas: tbl_time.id_time e tbl_categoria.id_categoria são INT com sinal.
     * FKs NO ACTION. Não depende de ids.
     */
    public function up(): void
    {
        Schema::create('tbl_time_categoria', function (Blueprint $table) {
            $table->increments('id_time_categoria');
            $table->integer('id_time');
            $table->integer('id_categoria');

            $table->unique(['id_time', 'id_categoria'], 'time_categoria_unique');
            $table->index('id_categoria', 'fk_tc_categoria');

            $table->foreign('id_time', 'fk_tc_time')
                ->references('id_time')->on('tbl_time')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_categoria', 'fk_tc_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });

        DB::statement('INSERT IGNORE INTO tbl_time_categoria (id_time, id_categoria)
            SELECT id_time, id_categoria FROM tbl_time WHERE id_categoria IS NOT NULL
            UNION
            SELECT id_time, id_categoria FROM tbl_campeonato_time');

        Schema::table('tbl_time', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable()->change();
        });

        // O único novo começa por id_campeonato, como o antigo: a FK do campeonato continua com índice
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->unique(['id_campeonato', 'id_categoria', 'id_time', 'camisa_convocacao'], 'convocacao_camisa_categoria_unique');
        });
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->dropUnique('convocacao_camisa_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Volta a uma categoria por time. Para, com a lista, se algum time tem mais de uma categoria ou nenhuma (não dá
     * para saber qual fica) ou se a mesma camisa já se repete no time em categorias diferentes de um campeonato.
     */
    public function down(): void
    {
        $varias = DB::table('tbl_time as t')
            ->leftJoin('tbl_time_categoria as tc', 'tc.id_time', '=', 't.id_time')
            ->groupBy('t.id_time', 't.nome_time')
            ->havingRaw('COUNT(tc.id_categoria) <> 1')
            ->pluck('t.nome_time')->all();
        if ($varias) {
            throw new RuntimeException('Times sem categoria ou com mais de uma: ' . implode(', ', $varias)
                . '. Deixe cada time com uma categoria antes de voltar.');
        }

        $camisas = DB::table('tbl_convocacao')->groupBy('id_campeonato', 'id_time', 'camisa_convocacao')->havingRaw('COUNT(*) > 1')
            ->selectRaw("CONCAT('campeonato ', id_campeonato, ' time ', id_time, ' camisa ', camisa_convocacao) AS caso")->pluck('caso')->all();
        if ($camisas) {
            throw new RuntimeException('Camisas repetidas no mesmo time do campeonato: ' . implode(', ', $camisas)
                . '. Troque uma delas antes de voltar.');
        }

        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->unique(['id_campeonato', 'id_time', 'camisa_convocacao'], 'convocacao_camisa_unique');
        });
        Schema::table('tbl_convocacao', function (Blueprint $table) {
            $table->dropUnique('convocacao_camisa_categoria_unique');
        });

        DB::statement('UPDATE tbl_time t JOIN tbl_time_categoria tc ON tc.id_time = t.id_time SET t.id_categoria = tc.id_categoria');
        Schema::table('tbl_time', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable(false)->change();
        });

        Schema::dropIfExists('tbl_time_categoria');
    }
};
