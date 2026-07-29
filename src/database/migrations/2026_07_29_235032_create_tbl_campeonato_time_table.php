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
        Schema::create('tbl_campeonato_time', function (Blueprint $table) {
            $table->integer('id_campeonato_time', true);
            $table->integer('id_time')->index('fk_ct_time');
            $table->integer('id_campeonato')->index('fk_ct_campeonato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_campeonato_time');
    }
};
