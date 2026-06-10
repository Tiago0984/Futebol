<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->renameColumn('numero_atleta', 'numero_matricula_atleta');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->renameColumn('numero_matricula_atleta', 'numero_atleta');
        });
    }
};
