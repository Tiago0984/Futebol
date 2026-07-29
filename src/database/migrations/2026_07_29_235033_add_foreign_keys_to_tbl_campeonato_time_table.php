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
        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->foreign(['id_campeonato'], 'fk_ct_campeonato')->references(['id_campeonato'])->on('tbl_campeonato')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_time'], 'fk_ct_time')->references(['id_time'])->on('tbl_time')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_campeonato_time', function (Blueprint $table) {
            $table->dropForeign('fk_ct_campeonato');
            $table->dropForeign('fk_ct_time');
        });
    }
};
