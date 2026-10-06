<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Login do responsável no app (Fase 9): senha própria, no padrão campo_tabela. Nullable: o responsável
     * só tem senha depois de usar o link "Defina sua senha" (enviado na aprovação da matrícula).
     */
    public function up(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->string('senha_responsavel')->nullable()->after('email_responsavel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_responsavel', function (Blueprint $table) {
            $table->dropColumn('senha_responsavel');
        });
    }
};
