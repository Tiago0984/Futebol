<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Etapa L2 (30/10/2026): o local escolhido do cadastro (tbl_local) no evento, na linha da grade e no local principal
     * do campeonato. id_local INT UNSIGNED (o tipo de tbl_local.id_local), nullable, FKs NO ACTION. O texto do local
     * continua gravado (cópia do nome do local; sem id_local = "texto antigo"): os avisos, o histórico, o site e a API
     * leem o texto. A grade passa a aceitar "Sem local" (local_grade_treino nullable; decisão do dono do projeto: o
     * "Conforme a agenda" do sábado não é um lugar). Não liga nenhum evento sozinho: isso é o "Ligar os usos" da tela de
     * Locais. Não depende de ids.
     */
    public function up(): void
    {
        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->unsignedInteger('id_local')->nullable()->after('local_evento_calendario');
            $table->foreign('id_local', 'fk_evento_local')->references('id_local')->on('tbl_local')
                ->onUpdate('no action')->onDelete('no action');
        });

        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->unsignedInteger('id_local')->nullable()->after('local_grade_treino');
            $table->foreign('id_local', 'fk_grade_local')->references('id_local')->on('tbl_local')
                ->onUpdate('no action')->onDelete('no action');
        });
        DB::statement('ALTER TABLE tbl_grade_treino MODIFY local_grade_treino VARCHAR(255) NULL');

        Schema::table('tbl_campeonato', function (Blueprint $table) {
            $table->unsignedInteger('id_local')->nullable()->after('local_evento');
            $table->foreign('id_local', 'fk_campeonato_local')->references('id_local')->on('tbl_local')
                ->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations. A grade sem local volta com o texto vazio (a coluna antiga não aceitava NULL).
     */
    public function down(): void
    {
        Schema::table('tbl_campeonato', function (Blueprint $table) {
            $table->dropForeign('fk_campeonato_local');
            $table->dropColumn('id_local');
        });

        DB::table('tbl_grade_treino')->whereNull('local_grade_treino')->update(['local_grade_treino' => '']);
        DB::statement('ALTER TABLE tbl_grade_treino MODIFY local_grade_treino VARCHAR(255) NOT NULL');
        Schema::table('tbl_grade_treino', function (Blueprint $table) {
            $table->dropForeign('fk_grade_local');
            $table->dropColumn('id_local');
        });

        Schema::table('tbl_evento_calendario', function (Blueprint $table) {
            $table->dropForeign('fk_evento_local');
            $table->dropColumn('id_local');
        });
    }
};
