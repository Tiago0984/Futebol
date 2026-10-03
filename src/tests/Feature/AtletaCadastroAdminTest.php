<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Cadastro e edição de atleta pelo admin: e-mail (opcional, único, login do app)
 * e número de matrícula (gerado também fora da aprovação).
 */
class AtletaCadastroAdminTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- e-mail ----------

    public function test_cria_atleta_sem_email(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['email_atleta' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['nome_atleta' => 'Atleta Novo', 'email_atleta' => null]);
    }

    public function test_cria_atleta_com_email(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['email_atleta' => 'novo@atleta.com']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['nome_atleta' => 'Atleta Novo', 'email_atleta' => 'novo@atleta.com']);
    }

    public function test_cadastro_recusa_email_invalido_ou_repetido(): void
    {
        $idOutro = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idOutro)->update(['email_atleta' => 'ja@existe.com']);

        foreach (['nao-e-email', 'ja@existe.com', 'JA@EXISTE.COM'] as $email) {
            $this->comoAdmin()
                ->post(route('admin.atletas.store'), $this->dadosCadastro(['email_atleta' => $email]))
                ->assertSessionHasErrors('email_atleta');
        }

        $this->assertDatabaseMissing('tbl_atletas', ['nome_atleta' => 'Atleta Novo']);
    }

    public function test_edicao_mantem_o_proprio_email(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['email_atleta' => 'meu@email.com']);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'email_atleta' => 'meu@email.com',
                'nome_atleta'  => 'Nome Novo',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', [
            'id_atleta' => $idAtleta, 'email_atleta' => 'meu@email.com', 'nome_atleta' => 'Nome Novo',
        ]);
    }

    public function test_edicao_recusa_email_de_outro_atleta(): void
    {
        $idOutro  = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idOutro)->update(['email_atleta' => 'outro@email.com']);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['email_atleta' => 'outro@email.com']))
            ->assertSessionHasErrorsIn('edicao', 'email_atleta');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'email_atleta' => null]);
    }

    public function test_edicao_pode_apagar_o_email(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['email_atleta' => 'meu@email.com']);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['email_atleta' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'email_atleta' => null]);
    }

    // ---------- número de matrícula ----------

    public function test_primeiro_cadastro_pelo_admin_recebe_a001(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Atleta cadastrado com sucesso. Matrícula: A001');

        $this->assertDatabaseHas('tbl_atletas', ['nome_atleta' => 'Atleta Novo', 'numero_matricula_atleta' => 'A001']);
    }

    public function test_cadastro_pelo_admin_gera_o_proximo_numero(): void
    {
        $idExistente = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idExistente)->update(['numero_matricula_atleta' => 'A007']);

        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro())->assertSessionHasNoErrors();
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['cpf_atleta' => '333.333.333-33']))
            ->assertSessionHasNoErrors();

        $numeros = DB::table('tbl_atletas')->where('nome_atleta', 'Atleta Novo')->orderBy('id_atleta')->pluck('numero_matricula_atleta');
        $this->assertSame(['A008', 'A009'], $numeros->all());
    }

    public function test_cadastro_pelo_admin_mantem_o_numero_informado(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['numero_matricula_atleta' => 'A050']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['nome_atleta' => 'Atleta Novo', 'numero_matricula_atleta' => 'A050']);
    }

    /**
     * Colisão no índice único sem erro 500. "A002 " (com espaço) é igual a "A002" para o índice
     * (a collation ignora espaços no fim), mas não entra no MAX (não casa com ^A[0-9]+$):
     * toda tentativa propõe A002 e é recusada; depois de 5, o admin recebe uma mensagem.
     */
    public function test_colisao_no_numero_de_matricula_nao_da_erro_500(): void
    {
        $idA001 = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idA002 = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idA001)->update(['numero_matricula_atleta' => 'A001']);
        DB::table('tbl_atletas')->where('id_atleta', $idA002)->update(['numero_matricula_atleta' => 'A002 ']);

        $this->comoAdmin()
            ->from(route('admin.atletas.index'))
            ->post(route('admin.atletas.store'), $this->dadosCadastro())
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHas('erro', 'Não foi possível gerar o número de matrícula agora. Tente salvar de novo.');

        // A transação inteira volta: nem atleta, nem responsável, nem endereço ficam pela metade
        $this->assertDatabaseMissing('tbl_atletas', ['nome_atleta' => 'Atleta Novo']);
        $this->assertDatabaseMissing('tbl_responsavel', ['nome_responsavel' => 'Responsável de Teste']);
    }

    public function test_aprovacao_continua_gerando_o_proximo_numero(): void
    {
        $idExistente = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idExistente)->update(['numero_matricula_atleta' => 'A004']);
        $idPendente = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idPendente), ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHas('sucesso', 'Matrícula de Atleta de Teste aprovada. Número: A005');

        $this->assertDatabaseHas('tbl_atletas', [
            'id_atleta' => $idPendente, 'status_atleta' => 'ATIVO', 'numero_matricula_atleta' => 'A005',
        ]);
    }

    public function test_aprovacao_mantem_numero_que_ja_existia(): void
    {
        $idPendente = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        DB::table('tbl_atletas')->where('id_atleta', $idPendente)->update(['numero_matricula_atleta' => 'A020']);

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idPendente), ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idPendente, 'numero_matricula_atleta' => 'A020']);
    }
}
