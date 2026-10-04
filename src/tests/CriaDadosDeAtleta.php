<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ajudantes para testes que mexem com atleta e categoria: cria registros direto no banco
 * e monta os formulários completos de cadastro e edição do admin.
 */
trait CriaDadosDeAtleta
{
    protected function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'admin');
    }

    // Data de nascimento que dá essa idade no ano atual (o dia não importa)
    protected function nascidoComIdade(int $idade): string
    {
        return (now()->year - $idade) . '-06-15';
    }

    protected function idCategoria(string $nome, string $sexo): int
    {
        return (int) DB::table('tbl_categoria')
            ->where('nome_categoria', $nome)
            ->where('sexo_categoria', $sexo)
            ->value('id_categoria');
    }

    protected function criarAtleta(string $nascimento, string $sexo, string $status): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua de Teste', 'numero_endereco' => '100', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_atletas')->insertGetId([
            'id_endereco'      => $idEndereco,
            'nome_atleta'      => 'Atleta de Teste',
            'data_nasc_atleta' => $nascimento,
            'cpf_atleta'       => '000.000.000-00',
            'rg_atleta'        => '00.000.000-0',
            'sexo_atleta'      => $sexo,
            'escola_atleta'    => 'Escola de Teste',
            'status_atleta'    => $status,
        ]);
    }

    // Responsável vinculado ao atleta (Mãe), com endereço próprio
    protected function criarResponsavel(int $idAtleta): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua do Responsável', 'numero_endereco' => '200', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        $idResponsavel = DB::table('tbl_responsavel')->insertGetId([
            'id_endereco'          => $idEndereco,
            'nome_responsavel'     => 'Responsável de Teste',
            'cpf_responsavel'      => '111.111.111-11',
            'rg_responsavel'       => '11.111.111-1',
            'whatsapp_responsavel' => '(11) 99999-9999',
            'email_responsavel'    => 'responsavel@teste.com',
        ]);

        DB::table('tbl_atleta_responsavel')->insert([
            'id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel, 'grau_parentesco_responsavel' => 'Mãe',
        ]);

        return $idResponsavel;
    }

    // Autorização do atleta (cria o responsável se ele ainda não tiver); padrão: assinada
    protected function criarAutorizacao(int $idAtleta, string $status = 'ASSINADO', ?string $token = null): int
    {
        $idResponsavel = DB::table('tbl_atleta_responsavel')->where('id_atleta', $idAtleta)->value('id_responsavel')
            ?? $this->criarResponsavel($idAtleta);

        return DB::table('tbl_autorizacoes')->insertGetId([
            'id_atleta'          => $idAtleta,
            'id_responsavel'     => $idResponsavel,
            'token_assinatura'   => $token ?? 'token-teste-' . $idAtleta . '-' . $status,
            'status_autorizacao' => $status,
        ]);
    }

    protected function colocarNaCategoria(int $idAtleta, int $idCategoria, ?string $observacao = null): void
    {
        DB::table('tbl_categoria_atleta')->insert([
            'id_categoria'                 => $idCategoria,
            'id_atleta'                    => $idAtleta,
            'data_inicio_categoria_atleta' => now()->subMonth(),
            'status_categoria_atleta'      => 'ATIVO',
            'observacao_categoria_atleta'  => $observacao,
        ]);
    }

    // Formulário completo de cadastro do admin (modal "Novo Atleta")
    protected function dadosCadastro(array $extra = []): array
    {
        return array_merge([
            'nome_atleta'                 => 'Atleta Novo',
            'data_nasc_atleta'            => $this->nascidoComIdade(12),
            'cpf_atleta'                  => '222.222.222-22',
            'rg_atleta'                   => '22.222.222-2',
            'escola_atleta'               => 'Escola de Teste',
            'sexo_atleta'                 => 'M',
            'nome_responsavel'            => 'Responsável de Teste',
            'cpf_responsavel'             => '111.111.111-11',
            'whatsapp_responsavel'        => '(11) 99999-9999',
            'grau_parentesco_responsavel' => 'Mãe',
            'cep_endereco'                => '01000-000',
            'rua_endereco'                => 'Rua de Teste',
            'numero_endereco'             => '100',
            'bairro_endereco'             => 'Centro',
            'cidade_endereco'             => 'São Paulo',
            'estado_endereco'             => 'SP',
        ], $extra);
    }

    // Formulário completo de edição do admin, com os dados atuais do atleta
    protected function dadosEdicao(int $idAtleta, array $extra = []): array
    {
        $atleta = DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->first();

        return array_merge([
            'nome_atleta'                 => $atleta->nome_atleta,
            'data_nasc_atleta'            => $atleta->data_nasc_atleta,
            'cpf_atleta'                  => $atleta->cpf_atleta,
            'rg_atleta'                   => $atleta->rg_atleta,
            'escola_atleta'               => $atleta->escola_atleta,
            'sexo_atleta'                 => $atleta->sexo_atleta,
            'status_atleta'               => $atleta->status_atleta,
            'nome_responsavel'            => 'Responsável de Teste',
            'cpf_responsavel'             => '111.111.111-11',
            'whatsapp_responsavel'        => '(11) 99999-9999',
            'grau_parentesco_responsavel' => 'Mãe',
            'cep_endereco'                => '01000-000',
            'rua_endereco'                => 'Rua de Teste',
            'numero_endereco'             => '100',
            'bairro_endereco'             => 'Centro',
            'cidade_endereco'             => 'São Paulo',
            'estado_endereco'             => 'SP',
        ], $extra);
    }
}
