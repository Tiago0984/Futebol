<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Origem nova da inscrição: CONVOCACAO, para o jogo de campeonato, que passa a inscrever os convocados
     * do campeonato (tbl_convocacao) no lugar do elenco fixo (CLAUDE.md, seção 4, "Convocação"; Fase G,
     * Etapa 2). A troca de time tira só quem veio pela convocação do time que saiu. O amistoso continua
     * gravando ELENCO até a Etapa 3. Valor acrescentado no fim, sem acento: o ENUM é ampliado direto, sem
     * mexer nas linhas existentes.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_atleta
            MODIFY origem_evento_atleta ENUM('CATEGORIA', 'INDIVIDUAL', 'ELENCO', 'CONVOCACAO') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * O ENUM antigo não tem CONVOCACAO: essas inscrições voltam como ELENCO, a origem automática pelo time
     * que existia antes (a troca de time continua tirando quem veio por ela).
     */
    public function down(): void
    {
        DB::table('tbl_evento_atleta')->where('origem_evento_atleta', 'CONVOCACAO')->update(['origem_evento_atleta' => 'ELENCO']);

        DB::statement("ALTER TABLE tbl_evento_atleta
            MODIFY origem_evento_atleta ENUM('CATEGORIA', 'INDIVIDUAL', 'ELENCO') NOT NULL");
    }
};
