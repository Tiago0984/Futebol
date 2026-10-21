<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Campos opcionais do campeonato (21/10/2026): local, organizador, logo e banner eram NOT NULL no banco, mas
     * opcionais no formulário (criar sem um deles dava erro 500). O local do campeonato passa a ser só o "local
     * principal": cada jogo tem o seu (no evento), e o site mostra os locais dos jogos quando ele está vazio.
     * Só ALTER (mesmos tipos e a collation do banco, utf8mb4_general_ci); não depende de ids nem mexe em dados.
     * SQL aceito pelo MySQL 8 e pelo MariaDB 10.11.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tbl_campeonato
            MODIFY logo_evento VARCHAR(255) NULL DEFAULT NULL,
            MODIFY banner_evento VARCHAR(255) NULL DEFAULT NULL,
            MODIFY organizador_campeonato VARCHAR(100) NULL DEFAULT NULL,
            MODIFY local_evento VARCHAR(100) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * Volta para NOT NULL: os vazios viram texto vazio antes (o site antigo monta o endereço da imagem com ele).
     */
    public function down(): void
    {
        foreach (['logo_evento', 'banner_evento', 'organizador_campeonato', 'local_evento'] as $coluna) {
            DB::table('tbl_campeonato')->whereNull($coluna)->update([$coluna => '']);
        }

        DB::statement('ALTER TABLE tbl_campeonato
            MODIFY logo_evento VARCHAR(255) NOT NULL,
            MODIFY banner_evento VARCHAR(255) NOT NULL,
            MODIFY organizador_campeonato VARCHAR(100) NOT NULL,
            MODIFY local_evento VARCHAR(100) NOT NULL');
    }
};
