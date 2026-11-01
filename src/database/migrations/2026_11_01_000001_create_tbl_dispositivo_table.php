<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_dispositivo', function (Blueprint $table) {
            $table->increments('id_dispositivo');
            $table->integer('id_atleta')->nullable()->index();
            $table->integer('id_responsavel')->nullable()->index();
            // Token opaco: a comparação precisa distinguir maiúsculas e minúsculas.
            $table->string('token', 255)->collation('utf8mb4_bin')->unique();
            $table->enum('plataforma', ['android', 'ios']);
            $table->boolean('ativo_dispositivo')->default(true);
            $table->dateTime('data_ultimo_uso');
            $table->dateTime('data_criacao_dispositivo');
            $table->dateTime('data_atualizacao_dispositivo');
            $table->foreign('id_atleta', 'fk_dispositivo_atleta')->references('id_atleta')->on('tbl_atletas')->cascadeOnDelete();
            $table->foreign('id_responsavel', 'fk_dispositivo_responsavel')->references('id_responsavel')->on('tbl_responsavel')->cascadeOnDelete();
        });

        // MySQL 8 e MariaDB 10.11: a conta é atleta OU responsável, nunca ambos ou nenhum.
        DB::statement('ALTER TABLE tbl_dispositivo ADD CONSTRAINT dispositivo_um_dono CHECK ((id_atleta IS NULL) <> (id_responsavel IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_dispositivo');
    }
};
