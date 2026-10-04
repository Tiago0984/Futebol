<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Atleta ATIVO sempre com categoria (linha ATIVO em tbl_categoria_atleta): nenhum caminho do admin
 * deixa um atleta ativo sem ela. Pendente e rejeitado recebem a categoria na aprovação em Matrículas.
 */
class AtletaAtivoComCategoriaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const MENSAGEM = 'Escolha a categoria do atleta: todo atleta ativo precisa estar numa categoria.';

    // ---------- 1. cadastro pelo admin ----------

    public function test_cadastro_sem_categoria_e_recusado(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['id_categoria' => '']))
            ->assertSessionHasErrors(['id_categoria' => self::MENSAGEM]);

        $this->assertDatabaseCount('tbl_atletas', 0);
    }

    public function test_cadastro_com_categoria_grava_a_linha_ativa(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro())
            ->assertSessionHasNoErrors();

        $idAtleta = DB::table('tbl_atletas')->value('id_atleta');
        $this->assertSame(0, $this->atletasAtivosSemCategoria());
        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta' => $idAtleta, 'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'status_categoria_atleta' => 'ATIVO',
        ]);
    }

    public function test_cadastro_continua_validando_a_categoria_pela_idade(): void
    {
        // 12 anos não pode ficar na Sub-11 (abaixo da idade)
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['id_categoria' => $this->idCategoria('Sub-11', 'M')]))
            ->assertSessionHasErrors('id_categoria');

        $this->assertDatabaseCount('tbl_atletas', 0);
    }

    // ---------- 2. edição ----------

    public function test_edicao_de_ativo_sem_categoria_exige_escolher_uma(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['nome_atleta' => 'Nome Novo']))
            ->assertSessionHasErrorsIn('edicao', ['id_categoria' => self::MENSAGEM]);

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Atleta de Teste']);
    }

    public function test_edicao_de_ativo_sem_categoria_grava_a_escolhida(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['id_categoria' => $this->idCategoria('Sub-13', 'M')]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->atletasAtivosSemCategoria());
    }

    public function test_edicao_que_ativa_atleta_sem_categoria_exige_escolher_uma(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['status_atleta' => 'ATIVO']))
            ->assertSessionHasErrorsIn('edicao', ['id_categoria' => self::MENSAGEM]);

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'INATIVO']);
    }

    public function test_edicao_de_inativo_sem_categoria_que_continua_inativo_segue_normal(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['nome_atleta' => 'Nome Novo']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Nome Novo', 'status_atleta' => 'INATIVO']);
    }

    public function test_edicao_de_ativo_com_categoria_sem_mexer_no_campo_segue_normal(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));

        // O modal já abre com a categoria atual selecionada (dadosEdicao faz o mesmo)
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['nome_atleta' => 'Nome Novo']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Nome Novo']);
        $this->assertSame(1, DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->count()); // nenhuma linha nova
    }

    // ---------- caminho A: esvaziar o campo de quem já tem categoria ----------

    public function test_ativo_com_categoria_nao_pode_esvaziar_o_campo(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['id_categoria' => '']))
            ->assertSessionHasErrorsIn('edicao', ['id_categoria' => self::MENSAGEM]);

        $this->assertSame(0, $this->atletasAtivosSemCategoria());
        $this->assertDatabaseHas('tbl_categoria_atleta', ['id_atleta' => $idAtleta, 'status_categoria_atleta' => 'ATIVO']);
    }

    public function test_quem_fica_inativo_pode_esvaziar_o_campo_como_antes(): void
    {
        // Ativo que é inativado na mesma edição, e inativo que continua inativo: o campo vazio encerra a linha
        $idAtivo = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtivo, $this->idCategoria('Sub-13', 'M'));
        $idInativo = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');
        $this->colocarNaCategoria($idInativo, $this->idCategoria('Sub-13', 'M'));

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtivo), $this->dadosEdicao($idAtivo, ['id_categoria' => '', 'status_atleta' => 'INATIVO']))
            ->assertSessionHasNoErrors();
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idInativo), $this->dadosEdicao($idInativo, ['id_categoria' => '']))
            ->assertSessionHasNoErrors();

        foreach ([$idAtivo, $idInativo] as $id) {
            $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $id, 'status_atleta' => 'INATIVO']);
            $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $id, 'status_categoria_atleta' => 'ATIVO']);
        }
    }

    // ---------- caminho B: categoria atual inativada ----------

    public function test_select_da_edicao_mostra_a_categoria_inativa_em_uso(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $idSub13M);
        DB::table('tbl_categoria')->whereIn('id_categoria', [$idSub13M, $this->idCategoria('Sub-9', 'F')])
            ->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('class="js-categoria-inativa" hidden disabled', false)
            ->assertSee('Sub-13 Masculino (inativa)')
            ->assertDontSee('Sub-9 Feminino (inativa)'); // inativa sem atleta: não aparece
    }

    public function test_editar_mantendo_a_categoria_atual_inativa_e_aceito(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $idSub13M);
        DB::table('tbl_categoria')->where('id_categoria', $idSub13M)->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['nome_atleta' => 'Nome Novo']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Nome Novo']);
        $linhas = DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->get();
        $this->assertCount(1, $linhas); // a mesma linha, sem encerrar nem abrir outra
        $this->assertSame('ATIVO', $linhas[0]->status_categoria_atleta);
    }

    public function test_trocar_para_outra_categoria_inativa_e_recusado(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $idSub13M);
        DB::table('tbl_categoria')->whereIn('id_categoria', [$idSub13M, $idSub15M])->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['id_categoria' => $idSub15M, 'motivo_categoria' => 'teste']))
            ->assertSessionHasErrorsIn('edicao', 'id_categoria');

        $this->assertDatabaseHas('tbl_categoria_atleta', ['id_atleta' => $idAtleta, 'id_categoria' => $idSub13M, 'status_categoria_atleta' => 'ATIVO']);
    }

    public function test_trocar_da_categoria_inativa_para_uma_ativa_e_aceito(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $idSub13M);
        DB::table('tbl_categoria')->where('id_categoria', $idSub13M)->update(['status_categoria' => 'INATIVO']);

        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['id_categoria' => $idSub15M, 'motivo_categoria' => 'Atleta mais robusto']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_categoria_atleta', ['id_atleta' => $idAtleta, 'id_categoria' => $idSub13M, 'status_categoria_atleta' => 'ENCERRADO']);
        $this->assertDatabaseHas('tbl_categoria_atleta', ['id_atleta' => $idAtleta, 'id_categoria' => $idSub15M, 'status_categoria_atleta' => 'ATIVO']);
    }

    // ---------- 3. botão de status ----------

    public function test_botao_nao_ativa_atleta_sem_categoria(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');

        $this->comoAdmin()
            ->from(route('admin.atletas.index'))
            ->patch(route('admin.atletas.toggleStatus', $idAtleta))
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHas('erro', fn ($msg) => str_contains($msg, 'sem categoria') && str_contains($msg, 'Edite o atleta'));

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'INATIVO']);
    }

    public function test_botao_ativa_atleta_com_categoria(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));

        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $idAtleta))->assertSessionHas('sucesso');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'ATIVO']);
    }

    public function test_botao_inativa_atleta_ativo_sem_categoria(): void
    {
        // Inativar nunca é bloqueado (é o jeito de tirar da lista um ativo antigo sem categoria)
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $idAtleta))->assertSessionHas('sucesso');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'INATIVO']);
    }

    // ---------- 4. pendente e rejeitado ----------

    public static function naoAprovados(): array
    {
        return ['PENDENTE' => ['PENDENTE'], 'REJEITADO' => ['REJEITADO']];
    }

    #[DataProvider('naoAprovados')]
    public function test_edicao_de_nao_aprovado_sem_categoria_segue_normal(string $status): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', $status);

        // Mesmo mandando ATIVO, o status não muda pela edição; a categoria vem na aprovação
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['nome_atleta' => 'Nome Novo', 'status_atleta' => 'ATIVO']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Nome Novo', 'status_atleta' => $status]);
        $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $idAtleta]);
    }

    // ---------- helpers ----------

    private function atletasAtivosSemCategoria(): int
    {
        return DB::table('tbl_atletas as a')
            ->where('a.status_atleta', 'ATIVO')
            ->whereNotExists(fn ($q) => $q->from('tbl_categoria_atleta as ca')
                ->whereColumn('ca.id_atleta', 'a.id_atleta')
                ->where('ca.status_categoria_atleta', 'ATIVO'))
            ->count();
    }
}
