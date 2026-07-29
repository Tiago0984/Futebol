<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbl_atleta_responsavel', function (Blueprint $table) {
            $table->foreign(['id_atleta'], 'fk_ar_atleta')->references(['id_atleta'])->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_responsavel'], 'fk_ar_responsavel')->references(['id_responsavel'])->on('tbl_responsavel')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_atleta_responsavel', function (Blueprint $table) {
            $table->dropForeign('fk_ar_atleta');
            $table->dropForeign('fk_ar_responsavel');
        });
    }
};
