<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Escalação do jogo com posição e titular/reserva (Fase D, Etapa D2; titular/reserva por jogo, decisão do
     * dono do projeto, 15/10/2026). Na inscrição do atleta no evento (tbl_evento_atleta):
     * - posicao_evento_atleta: a posição em que ele joga naquele jogo (mesma lista de tbl_atleta_posicao;
     *   rótulos em Atleta::POSICOES); NULL = não definida (a tela sugere a principal do atleta);
     * - titular_evento_atleta: TITULAR ou RESERVA (rótulos em EventoAtleta::TITULARIDADES); NULL = a definir.
     * As inscrições existentes ficam com as duas vazias. Só ALTER: não depende de ids.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_atleta
            ADD posicao_evento_atleta ENUM('GOLEIRO', 'ZAGUEIRO', 'LATERAL_DIREITO', 'LATERAL_ESQUERDO', 'LIBERO',
                'VOLANTE', 'MEIA_CENTRAL', 'MEIA_OFENSIVO', 'MEIA_DIREITA', 'MEIA_ESQUERDA', 'PONTA_DIREITA',
                'PONTA_ESQUERDA', 'CENTROAVANTE', 'SEGUNDO_ATACANTE') NULL DEFAULT NULL AFTER id_time,
            ADD titular_evento_atleta ENUM('TITULAR', 'RESERVA') NULL DEFAULT NULL AFTER posicao_evento_atleta");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tbl_evento_atleta DROP COLUMN titular_evento_atleta, DROP COLUMN posicao_evento_atleta');
    }
};
