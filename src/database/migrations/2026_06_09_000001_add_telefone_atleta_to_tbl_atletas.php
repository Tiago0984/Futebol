<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->string('telefone_atleta', 20)->nullable()->after('posicao_atleta');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_atletas', function (Blueprint $table) {
            $table->dropColumn('telefone_atleta');
        });
    }
};
