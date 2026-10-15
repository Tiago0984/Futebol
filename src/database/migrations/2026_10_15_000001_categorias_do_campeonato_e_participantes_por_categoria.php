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
     * Campeonato com várias categorias (Fase H, Etapa H1): a Copa São Carlos tem Sub-11, Sub-13, Sub-15 e
     * Sub-17 no mesmo campeonato. Cria tbl_campeonato_categoria (as categorias do campeonato) e dá a categoria
     * a cada participante de tbl_campeonato_time: o mesmo time pode entrar em várias categorias (o adversário
     * externo tem um cadastro só) e dois times da AACJ podem entrar na mesma ("AACJ Sub-15 A" e "B").
     *
     * A FK composta (id_campeonato, id_categoria) do participante só aceita uma categoria que o campeonato tem.
     * tbl_campeonato.id_categoria continua como a "categoria principal" (idade, convocação e jogos) até a H3.
     *
     * Dados existentes: cada campeonato ganha a categoria atual dele e os participantes ficam nela.
     * Tipos iguais aos das colunas referenciadas: campeonato e categoria INT com sinal.
     */
    public function up(): void
    {
        Schema::create('tbl_campeonato_categoria', function (Blueprint $table) {
            $table->increments('id_campeonato_categoria');
            $table->integer('id_campeonato');
            $table->integer('id_categoria');

            // O único começa por id_campeonato e serve também à FK dele e à FK composta dos participantes
            $table->unique(['id_campeonato', 'id_categoria'], 'campeonato_categoria_unique');
            $table->index('id_categoria', 'fk_cc_categoria');

            $table->foreign('id_campeonato', 'fk_cc_campeonato')
                ->references('id_campeonato')->on('tbl_campeonato')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_categoria', 'fk_cc_categoria')
                ->references('id_categoria')->on('tbl_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });

        DB::statement('INSERT INTO tbl_campeonato_categoria (id_campeonato, id_categoria)
            SELECT id_campeonato, id_categoria FROM tbl_campeonato WHERE id_categoria IS NOT NULL');

        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable()->after('id_campeonato');
        });

        DB::statement('UPDATE tbl_campeonato_time ct JOIN tbl_campeonato c ON c.id_campeonato = ct.id_campeonato
            SET ct.id_categoria = c.id_categoria');

        // Participante repetido (o mesmo time duas vezes no campeonato) ficaria com o único quebrado: fica o de menor id
        DB::statement('DELETE ct FROM tbl_campeonato_time ct JOIN tbl_campeonato_time outro
            ON outro.id_campeonato = ct.id_campeonato AND outro.id_time = ct.id_time
            AND outro.id_campeonato_time < ct.id_campeonato_time');

        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->integer('id_categoria')->nullable(false)->change();
            $table->unique(['id_campeonato', 'id_categoria', 'id_time'], 'campeonato_time_categoria_unique');
            $table->foreign(['id_campeonato', 'id_categoria'], 'fk_ct_campeonato_categoria')
                ->references(['id_campeonato', 'id_categoria'])->on('tbl_campeonato_categoria')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Volta a uma categoria por campeonato. Para, com a lista, se algum campeonato já tem mais de uma categoria
     * ou o mesmo time em duas (os dados não cabem no formato antigo e seriam perdidos).
     */
    public function down(): void
    {
        $varias = DB::table('tbl_campeonato_categoria')->groupBy('id_campeonato')->havingRaw('COUNT(*) > 1')
            ->pluck('id_campeonato')->all();
        $repetidos = DB::table('tbl_campeonato_time')->groupBy('id_campeonato', 'id_time')->havingRaw('COUNT(*) > 1')
            ->pluck('id_campeonato')->unique()->all();

        if ($varias || $repetidos) {
            throw new RuntimeException('Campeonatos com mais de uma categoria ou time em duas categorias: '
                . implode(', ', array_unique([...$varias, ...$repetidos])) . '. Deixe-os com uma categoria antes de voltar.');
        }

        // O índice fk_ct_campeonato (da FK do campeonato) nunca saiu: tirar o único não deixa a FK sem índice
        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->dropForeign('fk_ct_campeonato_categoria');
        });
        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->dropUnique('campeonato_time_categoria_unique');
            $table->dropColumn('id_categoria');
        });

        Schema::dropIfExists('tbl_campeonato_categoria');
    }
};
