<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUS_NOVOS   = ['ATIVO', 'CANCELADO', 'INATIVO'];
    private const STATUS_ANTIGOS = ['CANCELADO', 'CONFIRMADO', 'ALTERADO'];

    /**
     * Run the migrations.
     *
     * Status gravado do evento passa a ser ATIVO / CANCELADO / INATIVO (CLAUDE.md, seção 4).
     * "Alterado" e "Concluído" deixam de ser gravados e passam a ser derivados (Fase 4, Etapa 3).
     * Desfaz o efeito dos commits 59c9743 e 86b8e78 por migration nova (a branch já está no remoto).
     * Sem colisão de acentos entre os valores, o ENUM pode ser ampliado direto (sem passar por VARCHAR).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('CANCELADO', 'CONFIRMADO', 'ALTERADO', 'ATIVO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'");

        DB::table('tbl_evento_calendario')
            ->whereIn('status_evento_calendario', ['CONFIRMADO', 'ALTERADO'])
            ->update(['status_evento_calendario' => 'ATIVO']);

        $this->travaValoresFora(self::STATUS_NOVOS);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('ATIVO', 'CANCELADO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'");
    }

    /**
     * Reverse the migrations.
     *
     * O ENUM antigo não tem "escondido": INATIVO volta como CANCELADO.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('CANCELADO', 'CONFIRMADO', 'ALTERADO', 'ATIVO', 'INATIVO') NOT NULL DEFAULT 'CONFIRMADO'");

        DB::table('tbl_evento_calendario')->where('status_evento_calendario', 'ATIVO')->update(['status_evento_calendario' => 'CONFIRMADO']);
        DB::table('tbl_evento_calendario')->where('status_evento_calendario', 'INATIVO')->update(['status_evento_calendario' => 'CANCELADO']);

        $this->travaValoresFora(self::STATUS_ANTIGOS);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY status_evento_calendario ENUM('CANCELADO', 'CONFIRMADO', 'ALTERADO') NOT NULL DEFAULT 'CONFIRMADO'");
    }

    /**
     * Impede o MODIFY final se sobrar algum valor fora da lista (o MySQL gravaria '' no lugar).
     * Comparação no PHP, byte a byte, como na migration que tirou os acentos dos tipos.
     */
    private function travaValoresFora(array $permitidos): void
    {
        $fora = DB::table('tbl_evento_calendario')
            ->pluck('status_evento_calendario')
            ->unique()
            ->reject(fn ($status) => in_array($status, $permitidos, true))
            ->values();

        if ($fora->isNotEmpty()) {
            throw new RuntimeException(
                'tbl_evento_calendario.status_evento_calendario tem valores fora da lista ('
                . implode(', ', $permitidos) . '): ' . $fora->implode(', ')
                . '. Corrija esses eventos e rode a migration de novo.'
            );
        }
    }
};
