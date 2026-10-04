<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 6, Etapa 1: cada jogo passa a ter um evento (1:1). Data, horário, local e status ficam no
     * evento; o jogo guarda campeonato, times e placar (CLAUDE.md, seção 4, "Jogos").
     *
     * - id_evento: INT UNSIGNED como tbl_evento_calendario.id_evento_calendario (FK exige o mesmo tipo),
     *   único (1 jogo = 1 evento). Nullable nesta etapa; vira NOT NULL na Etapa 2.
     * - id_campeonato nullable: amistoso = jogo sem campeonato. A FK é recriada (o MySQL não deixa
     *   alterar a coluna com a FK presa a ela).
     * - Placar nullable: NULL = ainda não jogado (antes o DEFAULT 0 contava jogo futuro como empate 0×0).
     * - Cria o evento de cada jogo que já existe (criarEventosDosJogos).
     */
    public function up(): void
    {
        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dropForeign('fk_jogo_campeonato');
        });

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->integer('id_campeonato')->nullable()->change();
            $table->integer('placar_time_casa_jogos')->nullable()->default(null)->change();
            $table->integer('placar_time_visitante_jogos')->nullable()->default(null)->change();

            $table->unsignedInteger('id_evento')->nullable()->after('id_jogo');
            $table->unique('id_evento', 'jogo_evento_unique');

            $table->foreign('id_campeonato', 'fk_jogo_campeonato')
                ->references('id_campeonato')->on('tbl_campeonato')
                ->onUpdate('no action')->onDelete('no action');
            $table->foreign('id_evento', 'fk_jogo_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');
        });

        $this->criarEventosDosJogos();
    }

    /**
     * Cria um evento JOGO para cada jogo sem evento: título "Casa x Visitante", data e hora de data_jogo,
     * local e categoria do campeonato, responsável NULL (não se sabe quem criou), status INATIVO se o
     * jogo estava inativo. Não inscreve atletas: os jogos existentes já aconteceram, e inscrever a
     * categoria de hoje num jogo passado seria falso. Rodar de novo não duplica.
     * Público para o teste rodar sobre dados simulados.
     */
    public function criarEventosDosJogos(): void
    {
        $jogos = DB::table('tbl_jogos as j')
            ->join('tbl_time as casa', 'casa.id_time', '=', 'j.id_time_casa')
            ->join('tbl_time as vis', 'vis.id_time', '=', 'j.id_time_visitante')
            ->leftJoin('tbl_campeonato as c', 'c.id_campeonato', '=', 'j.id_campeonato')
            ->whereNull('j.id_evento')
            ->select('j.id_jogo', 'j.data_jogo', 'j.status_jogo', 'casa.nome_time as casa', 'vis.nome_time as visitante',
                'c.id_categoria', 'c.local_evento', 'c.data_inicio_campeonato')
            ->get();

        foreach ($jogos as $jogo) {
            $quando = $jogo->data_jogo ?? $jogo->data_inicio_campeonato ?? now()->toDateString();

            $idEvento = DB::table('tbl_evento_calendario')->insertGetId([
                'titulo_evento_calendario'         => "{$jogo->casa} x {$jogo->visitante}",
                'tipo_evento_calendario'           => 'JOGO',
                'id_categoria'                     => $jogo->id_categoria,
                'data_evento_calendario'           => substr($quando, 0, 10),
                'horario_inicio_evento_calendario' => $jogo->data_jogo ? substr($jogo->data_jogo, 11, 8) : null,
                'local_evento_calendario'          => $jogo->local_evento,
                'destaque_evento_calendario'       => 'NAO',
                'status_evento_calendario'         => strtoupper((string) $jogo->status_jogo) === 'INATIVO' ? 'INATIVO' : 'ATIVO',
            ]);

            DB::table('tbl_jogos')->where('id_jogo', $jogo->id_jogo)->update(['id_evento' => $idEvento]);
        }
    }

    /**
     * Desfaz: apaga os eventos dos jogos (com o histórico e as inscrições deles) e volta as colunas.
     * Não dá para voltar com amistosos cadastrados (id_campeonato era NOT NULL).
     */
    public function down(): void
    {
        if (DB::table('tbl_jogos')->whereNull('id_campeonato')->exists()) {
            throw new RuntimeException('Há amistosos (jogos sem campeonato): ajuste ou remova esses jogos antes de desfazer esta migration.');
        }

        $idsEventos = DB::table('tbl_jogos')->whereNotNull('id_evento')->pluck('id_evento');

        DB::table('tbl_jogos')->update(['id_evento' => null]);
        DB::table('tbl_evento_historico')->whereIn('id_evento_calendario', $idsEventos)->delete();
        DB::table('tbl_evento_atleta')->whereIn('id_evento_calendario', $idsEventos)->delete();
        DB::table('tbl_evento_calendario')->whereIn('id_evento_calendario', $idsEventos)->delete();

        DB::table('tbl_jogos')->whereNull('placar_time_casa_jogos')->update(['placar_time_casa_jogos' => 0]);
        DB::table('tbl_jogos')->whereNull('placar_time_visitante_jogos')->update(['placar_time_visitante_jogos' => 0]);

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dropForeign('fk_jogo_evento');
            $table->dropUnique('jogo_evento_unique');
            $table->dropColumn('id_evento');
            $table->dropForeign('fk_jogo_campeonato');
        });

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->integer('id_campeonato')->nullable(false)->change();
            $table->integer('placar_time_casa_jogos')->nullable(false)->default(0)->change();
            $table->integer('placar_time_visitante_jogos')->nullable(false)->default(0)->change();

            $table->foreign('id_campeonato', 'fk_jogo_campeonato')
                ->references('id_campeonato')->on('tbl_campeonato')
                ->onUpdate('no action')->onDelete('no action');
        });
    }
};
