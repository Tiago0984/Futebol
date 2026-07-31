<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario ENUM('JOGO', 'TREINO', 'CAMPEONATO', 'EVENTO', 'REUNIÃO', 'CONFRATERNIZAÇÃO') NOT NULL");

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY local_evento_calendario VARCHAR(255) NULL");

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY horario_inicio_evento_calendario TIME NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario ENUM('JOGO', 'TREINO', 'CAMPEONATO') NOT NULL");

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY local_evento_calendario VARCHAR(255) NOT NULL");

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY horario_inicio_evento_calendario TIME NOT NULL");
    }
};
