<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->text('assinatura_responsavel')->nullable()->change();
            $table->string('telefone_responsavel')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->text('assinatura_responsavel')->nullable(false)->change();
            $table->string('telefone_responsavel')->nullable(false)->change();
        });
    }
};
