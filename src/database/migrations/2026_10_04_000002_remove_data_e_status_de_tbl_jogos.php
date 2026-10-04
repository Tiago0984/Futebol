<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 6, Etapa 2: data, horário, local e status do jogo ficam só no evento.
     * - Remove data_jogo e status_jogo (o código lê do evento; a API mantém os nomes no JSON).
     * - id_evento vira NOT NULL: todo jogo tem evento. A FK é solta e recriada para alterar a coluna.
     */
    public function up(): void
    {
        if (DB::table('tbl_jogos')->whereNull('id_evento')->exists()) {
            throw new RuntimeException('Há jogos sem evento: rode antes a migration 2026_10_04_000001 (ela cria o evento de cada jogo).');
        }

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dropForeign('fk_jogo_evento');
        });

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->unsignedInteger('id_evento')->nullable(false)->change();
            $table->foreign('id_evento', 'fk_jogo_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');

            $table->dropColumn(['data_jogo', 'status_jogo']);
        });
    }

    /**
     * Volta as colunas, preenchidas pelo evento (data + horário de início; INATIVO se o evento está
     * oculto, senão ATIVO, como era antes), e id_evento nullable.
     */
    public function down(): void
    {
        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->dateTime('data_jogo')->nullable()->after('placar_time_visitante_jogos');
            $table->string('status_jogo', 10)->default('ATIVO')->after('data_jogo');
            $table->dropForeign('fk_jogo_evento');
        });

        Schema::table('tbl_jogos', function (Blueprint $table) {
            $table->unsignedInteger('id_evento')->nullable()->change();
            $table->foreign('id_evento', 'fk_jogo_evento')
                ->references('id_evento_calendario')->on('tbl_evento_calendario')
                ->onUpdate('no action')->onDelete('no action');
        });

        DB::table('tbl_jogos as j')
            ->join('tbl_evento_calendario as e', 'e.id_evento_calendario', '=', 'j.id_evento')
            ->update([
                'j.data_jogo'   => DB::raw("TIMESTAMP(e.data_evento_calendario, COALESCE(e.horario_inicio_evento_calendario, '00:00:00'))"),
                'j.status_jogo' => DB::raw("IF(e.status_evento_calendario = 'INATIVO', 'INATIVO', 'ATIVO')"),
            ]);
    }
};
