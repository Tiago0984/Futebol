<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fase I, Etapa I1b (dono do projeto, 21/10/2026): no jogo (tbl_jogos), todas nullable.
     * - rodada_jogo: a rodada na fase de grupos ("Rodada 5");
     * - id_jogo_ida: no jogo de volta do mata-mata, o jogo de ida (INT com sinal, como id_jogo; único: uma ida
     *   tem no máximo uma volta; FK fk_jogo_ida para o próprio tbl_jogos, NO ACTION);
     * - prorrogacao_time_casa_jogos / _visitante_: gols só da prorrogação (jogo único do mata-mata empatado);
     * - penaltis_time_casa_jogos / _visitante_: disputa de pênaltis.
     * O placar que já existe continua sendo o do tempo normal. Os jogos existentes ficam com tudo vazio.
     * Só ALTER: não depende de ids. SQL aceito pelo MySQL 8 e pelo MariaDB 10.11.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tbl_jogos
            ADD rodada_jogo TINYINT UNSIGNED NULL DEFAULT NULL AFTER fase_jogo,
            ADD id_jogo_ida INT NULL DEFAULT NULL AFTER rodada_jogo,
            ADD prorrogacao_time_casa_jogos TINYINT UNSIGNED NULL DEFAULT NULL AFTER placar_time_visitante_jogos,
            ADD prorrogacao_time_visitante_jogos TINYINT UNSIGNED NULL DEFAULT NULL AFTER prorrogacao_time_casa_jogos,
            ADD penaltis_time_casa_jogos TINYINT UNSIGNED NULL DEFAULT NULL AFTER prorrogacao_time_visitante_jogos,
            ADD penaltis_time_visitante_jogos TINYINT UNSIGNED NULL DEFAULT NULL AFTER penaltis_time_casa_jogos,
            ADD UNIQUE KEY jogo_ida_unique (id_jogo_ida),
            ADD CONSTRAINT fk_jogo_ida FOREIGN KEY (id_jogo_ida) REFERENCES tbl_jogos (id_jogo)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tbl_jogos DROP FOREIGN KEY fk_jogo_ida');
        DB::statement('ALTER TABLE tbl_jogos
            DROP INDEX jogo_ida_unique,
            DROP COLUMN penaltis_time_visitante_jogos,
            DROP COLUMN penaltis_time_casa_jogos,
            DROP COLUMN prorrogacao_time_visitante_jogos,
            DROP COLUMN prorrogacao_time_casa_jogos,
            DROP COLUMN id_jogo_ida,
            DROP COLUMN rodada_jogo');
    }
};
