<?php

use App\Support\Cpf;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CPF único (21/10/2026; débito "CPF único" da seção 7 do CLAUDE.md), no atleta e no responsável. Primeiro
     * confere os repetidos pelos dígitos (com ou sem pontos): se houver, para com a lista SEM mexer em nada (a
     * correção é feita antes, com OK do dono do projeto). Depois grava todos no formato 000.000.000-00 (o mesmo
     * dos models, App\Support\Cpf) e cria os índices cpf_atleta_unique e cpf_responsavel_unique. CPF inválido
     * pelos dígitos verificadores não é recusado aqui (a validação vale ao salvar pelas telas).
     */
    public function up(): void
    {
        $tabelas = ['tbl_atletas' => ['cpf_atleta', 'id_atleta'], 'tbl_responsavel' => ['cpf_responsavel', 'id_responsavel']];

        $problemas = [];
        foreach ($tabelas as $tabela => [$coluna, $id]) {
            $repetidos = DB::table($tabela)->get([$id, $coluna])
                ->groupBy(fn ($linha) => Cpf::digitos($linha->{$coluna}))
                ->filter(fn ($linhas) => $linhas->count() > 1);
            foreach ($repetidos as $digitos => $linhas) {
                $problemas[] = "{$tabela}: CPF " . Cpf::formatar((string) $digitos) . ' nos ids ' . $linhas->pluck($id)->implode(', ');
            }
        }
        if ($problemas) {
            throw new RuntimeException('Há CPFs repetidos: ' . implode('; ', $problemas)
                . '. Corrija esses cadastros e rode a migration de novo.');
        }

        foreach ($tabelas as $tabela => [$coluna, $id]) {
            foreach (DB::table($tabela)->get([$id, $coluna]) as $linha) {
                $formatado = Cpf::formatar($linha->{$coluna});
                if ($formatado !== $linha->{$coluna}) {
                    DB::table($tabela)->where($id, $linha->{$id})->update([$coluna => $formatado]);
                }
            }
        }

        Schema::table('tbl_atletas', fn (Blueprint $table) => $table->unique('cpf_atleta', 'cpf_atleta_unique'));
        Schema::table('tbl_responsavel', fn (Blueprint $table) => $table->unique('cpf_responsavel', 'cpf_responsavel_unique'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_atletas', fn (Blueprint $table) => $table->dropUnique('cpf_atleta_unique'));
        Schema::table('tbl_responsavel', fn (Blueprint $table) => $table->dropUnique('cpf_responsavel_unique'));
    }
};
