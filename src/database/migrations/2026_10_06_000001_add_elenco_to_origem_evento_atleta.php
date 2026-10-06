<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Origem nova da inscrição: ELENCO, para o jogo (tbl_jogos), que passa a inscrever o elenco dos times
     * internos que jogam (tbl_atleta_time), e não a categoria inteira (CLAUDE.md, seção 4, "Jogos").
     * Assim a troca de time tira só quem veio pelo elenco do time que saiu e mantém as escolhas do admin.
     * Valor acrescentado no fim, sem acento: o ENUM é ampliado direto, sem mexer nas linhas existentes.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_atleta
            MODIFY origem_evento_atleta ENUM('CATEGORIA', 'INDIVIDUAL', 'ELENCO') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * O ENUM antigo não tem ELENCO: essas inscrições voltam como INDIVIDUAL (a sincronização pela
     * categoria não mexe nelas, como o "Preencher pelo elenco" gravava antes).
     */
    public function down(): void
    {
        DB::table('tbl_evento_atleta')->where('origem_evento_atleta', 'ELENCO')->update(['origem_evento_atleta' => 'INDIVIDUAL']);

        DB::statement("ALTER TABLE tbl_evento_atleta
            MODIFY origem_evento_atleta ENUM('CATEGORIA', 'INDIVIDUAL') NOT NULL");
    }
};
