<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_atletas MODIFY rg_atleta VARCHAR(15) NOT NULL");
        DB::statement("ALTER TABLE tbl_responsavel MODIFY rg_responsavel VARCHAR(15) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_atletas MODIFY rg_atleta VARCHAR(11) NOT NULL");
        DB::statement("ALTER TABLE tbl_responsavel MODIFY rg_responsavel VARCHAR(11) NOT NULL");
    }
};
