<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tipo novo de notificação: AVISO, o recado escrito livremente pelo admin na página de Notificações, para
     * os atletas que ele escolher (sem evento, como a AGENDA). Valor acrescentado no fim, sem acento: o ENUM é
     * ampliado direto, sem mexer nas linhas existentes.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_notificacao
            MODIFY tipo_notificacao ENUM('INSCRICAO', 'REMOCAO', 'ALTERACAO', 'CANCELAMENTO', 'REATIVACAO', 'AGENDA', 'AVISO') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * O ENUM antigo não tem AVISO: esses recados viram AGENDA (também sem evento), com o título e a mensagem
     * como foram enviados.
     */
    public function down(): void
    {
        DB::table('tbl_notificacao')->where('tipo_notificacao', 'AVISO')->update(['tipo_notificacao' => 'AGENDA']);

        DB::statement("ALTER TABLE tbl_notificacao
            MODIFY tipo_notificacao ENUM('INSCRICAO', 'REMOCAO', 'ALTERACAO', 'CANCELAMENTO', 'REATIVACAO', 'AGENDA') NOT NULL");
    }
};
