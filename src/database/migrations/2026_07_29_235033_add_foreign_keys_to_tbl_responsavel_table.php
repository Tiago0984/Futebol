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
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->foreign(['id_endereco'], 'fk_responsavel_endereco')->references(['id_endereco'])->on('tbl_endereco')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->dropForeign('fk_responsavel_endereco');
        });
    }
};
