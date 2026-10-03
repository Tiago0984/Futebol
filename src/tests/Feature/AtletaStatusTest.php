<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * PENDENTE e REJEITADO só mudam de status pela tela de Matrículas (aprovação com
 * assinatura, categoria e número). Na tela de Atletas, só ATIVO <-> INATIVO.
 */
class AtletaStatusTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public static function naoAprovados(): array
    {
        return [
            'PENDENTE'  => ['PENDENTE'],
            'REJEITADO' => ['REJEITADO'],
        ];
    }

    #[DataProvider('naoAprovados')]
    public function test_toggle_recusa_atleta_nao_aprovado(string $status): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', $status);

        $this->comoAdmin()
            ->from(route('admin.atletas.index'))
            ->patch(route('admin.atletas.toggleStatus', $idAtleta))
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHas('erro', 'Este atleta ainda não foi aprovado. Use a tela de Matrículas.');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => $status]);
    }

    public function test_toggle_alterna_ativo_e_inativo(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $idAtleta))->assertSessionHas('sucesso');
        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'INATIVO']);

        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $idAtleta))->assertSessionHas('sucesso');
        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'ATIVO']);
    }

    #[DataProvider('naoAprovados')]
    public function test_edicao_nao_muda_status_de_atleta_nao_aprovado(string $status): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', $status);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'status_atleta' => 'ATIVO',
                'nome_atleta'   => 'Nome Corrigido',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', [
            'id_atleta' => $idAtleta, 'status_atleta' => $status, 'nome_atleta' => 'Nome Corrigido',
        ]);
    }

    public function test_edicao_alterna_status_de_atleta_aprovado(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['status_atleta' => 'INATIVO']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'INATIVO']);
    }

    public function test_lista_mostra_selo_correto_e_esconde_o_botao_de_pendente_e_rejeitado(): void
    {
        $idPendente  = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        $idRejeitado = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');
        $idAtivo     = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        $resposta = $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk();

        $resposta->assertSee('badge-status pendente', false)
            ->assertSee('badge-status rejeitado', false)
            ->assertSee(route('admin.atletas.toggleStatus', $idAtivo), false)
            ->assertDontSee(route('admin.atletas.toggleStatus', $idPendente), false)
            ->assertDontSee(route('admin.atletas.toggleStatus', $idRejeitado), false);
    }
}
