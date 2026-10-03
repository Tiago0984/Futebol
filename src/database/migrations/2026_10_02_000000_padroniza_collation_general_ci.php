<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Padrão único do projeto (o resto do banco já usa este)
    private const COLLATION = 'utf8mb4_general_ci';

    // Collation de cada tabela ANTES desta migration (levantamento do banco local em 02/10/2026),
    // usado pelo down() para devolver o estado original.
    // Ordem: tabelas do projeto primeiro, tabelas do Laravel por último (sessions no fim).
    private const ORIGINAIS = [
        'tbl_banner'             => 'utf8mb4_0900_ai_ci',
        'tbl_evento_calendario'  => 'utf8mb4_0900_ai_ci',
        'tbl_galeria'            => 'utf8mb4_0900_ai_ci',
        'tbl_grade_treino'       => 'utf8mb4_0900_ai_ci',
        'tbl_videos'             => 'utf8mb4_0900_ai_ci',
        'tbl_usuarios'           => 'utf8mb4_unicode_ci',
        'cache'                  => 'utf8mb4_unicode_ci',
        'cache_locks'            => 'utf8mb4_unicode_ci',
        'failed_jobs'            => 'utf8mb4_unicode_ci',
        'job_batches'            => 'utf8mb4_unicode_ci',
        'jobs'                   => 'utf8mb4_unicode_ci',
        'migrations'             => 'utf8mb4_unicode_ci',
        'password_reset_tokens'  => 'utf8mb4_unicode_ci',
        'personal_access_tokens' => 'utf8mb4_unicode_ci',
        'sessions'               => 'utf8mb4_unicode_ci',
    ];

    private const SCHEMA_ORIGINAL = 'utf8mb4_0900_ai_ci';

    /**
     * Run the migrations.
     *
     * CONVERT TO mantém o charset (utf8mb4) e só troca a collation: nenhum byte de dado
     * é reescrito, apenas a regra de comparação/ordenação e os índices de texto.
     * Nenhuma FK do banco usa coluna de texto, então a ordem entre tabelas é livre.
     */
    public function up(): void
    {
        $banco = DB::getDatabaseName();

        // Padrão do schema: vale para tabelas criadas fora das migrations (SQL manual)
        DB::statement("ALTER DATABASE `{$banco}` CHARACTER SET utf8mb4 COLLATE " . self::COLLATION);

        foreach (array_keys(self::ORIGINAIS) as $tabela) {
            if (Schema::hasTable($tabela)) {
                DB::statement("ALTER TABLE `{$tabela}` CONVERT TO CHARACTER SET utf8mb4 COLLATE " . self::COLLATION);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $banco = DB::getDatabaseName();

        foreach (array_reverse(self::ORIGINAIS, true) as $tabela => $collation) {
            if (Schema::hasTable($tabela)) {
                DB::statement("ALTER TABLE `{$tabela}` CONVERT TO CHARACTER SET utf8mb4 COLLATE {$collation}");
            }
        }

        DB::statement("ALTER DATABASE `{$banco}` CHARACTER SET utf8mb4 COLLATE " . self::SCHEMA_ORIGINAL);
    }
};
