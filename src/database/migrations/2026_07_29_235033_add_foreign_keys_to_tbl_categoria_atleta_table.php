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
        Schema::table('tbl_categoria_atleta', function (Blueprint $table) {
            $table->foreign(['id_atleta'], 'fk_ca_atleta')->references(['id_atleta'])->on('tbl_atletas')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['id_categoria'], 'fk_ca_categoria')->references(['id_categoria'])->on('tbl_categoria')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_categoria_atleta', function (Blueprint $table) {
            $table->dropForeign('fk_ca_atleta');
            $table->dropForeign('fk_ca_categoria');
        });
    }
};
