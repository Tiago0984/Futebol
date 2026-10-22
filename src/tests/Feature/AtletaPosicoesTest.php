<?php

namespace Tests\Feature;

use App\Models\Atleta;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase D, Etapa D1: posições do atleta (até 3, da lista fixa Atleta::POSICOES, a principal primeiro), no lugar
 * do texto livre posicao_atleta. Admin (cadastro, edição e lista), página Jogadores do site e GET /v1/atleta.
 */
class AtletaPosicoesTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private function codigos(int $idAtleta): array
    {
        return DB::table('tbl_atleta_posicao')->where('id_atleta', $idAtleta)
            ->orderBy('ordem_atleta_posicao')->pluck('posicao_atleta_posicao')->all();
    }

    private function atletaAtivo(): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        $this->criarResponsavel($id);

        return $id;
    }

    public function test_cadastro_grava_as_posicoes_em_ordem_e_a_lista_mostra_as_siglas(): void
    {
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro([
            'posicoes' => ['VOLANTE', '', 'MEIA_CENTRAL'],
        ]))->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $id = DB::table('tbl_atletas')->where('cpf_atleta', $this->cpfDeTeste(300000001))->value('id_atleta');
        $this->assertSame(['VOLANTE', 'MEIA_CENTRAL'], $this->codigos($id));       // o vazio do meio sai
        $this->assertSame([1, 2], DB::table('tbl_atleta_posicao')->where('id_atleta', $id)
            ->orderBy('ordem_atleta_posicao')->pluck('ordem_atleta_posicao')->map(fn ($o) => (int) $o)->all());

        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('VOL · MC')
            ->assertSee('data-posicao=" VOLANTE MEIA_CENTRAL "', false)
            ->assertSee('data-posicoes="VOLANTE,MEIA_CENTRAL"', false)
            ->assertSee('name="posicoes[]"', false)
            ->assertSee('<optgroup label="Atacantes">', false)
            ->assertDontSee('name="posicao_atleta"', false);
    }

    public function test_cadastro_sem_posicao(): void
    {
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['posicoes' => ['', '', '']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('tbl_atleta_posicao')->count());
    }

    public function test_edicao_troca_e_limpa_as_posicoes(): void
    {
        $id = $this->atletaAtivo();
        Atleta::find($id)->definirPosicoes(['GOLEIRO']);

        $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, [
            'posicoes' => ['PONTA_ESQUERDA', 'SEGUNDO_ATACANTE', 'CENTROAVANTE'],
        ]))->assertSessionHasNoErrors()->assertSessionHas('sucesso');
        $this->assertSame(['PONTA_ESQUERDA', 'SEGUNDO_ATACANTE', 'CENTROAVANTE'], $this->codigos($id));

        // Formulário sem posição nenhuma: o atleta fica sem
        $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, ['posicoes' => ['', '', '']]))
            ->assertSessionHasNoErrors();
        $this->assertSame([], $this->codigos($id));
    }

    public function test_posicao_repetida_invalida_ou_mais_de_tres_e_recusada(): void
    {
        $id = $this->atletaAtivo();
        Atleta::find($id)->definirPosicoes(['GOLEIRO']);

        foreach ([
            [['VOLANTE', 'VOLANTE'], 'posicoes'],
            [['MEIA'], 'posicoes.0'],                                       // nome antigo não vale mais
            [['GOLEIRO', 'ZAGUEIRO', 'LIBERO', 'VOLANTE'], 'posicoes'],
        ] as [$posicoes, $campo]) {
            $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, ['posicoes' => $posicoes]))
                ->assertSessionHasErrors($campo, null, 'edicao');
            $this->assertSame(['GOLEIRO'], $this->codigos($id));
        }

        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['posicoes' => ['VOLANTE', 'VOLANTE']]))
            ->assertSessionHasErrors('posicoes');
        // Uma mensagem só (antes, uma por select repetido)
        $this->assertSame(['A mesma posição foi escolhida mais de uma vez.'], session('errors')->all());
        $this->assertDatabaseMissing('tbl_atletas', ['cpf_atleta' => $this->cpfDeTeste(300000001)]);
    }

    public function test_pagina_jogadores_mostra_a_principal_e_filtra_por_qualquer_posicao(): void
    {
        $id = $this->atletaAtivo();
        Atleta::find($id)->definirPosicoes(['LATERAL_ESQUERDO', 'MEIA_ESQUERDA']);

        $html = $this->get(route('jogadores.vitrine'))->assertOk()
            ->assertSee('data-posicao=" lateral_esquerdo meia_esquerda "', false)
            ->assertSee('<figcaption class="sticker-pos-tag">LE</figcaption>', false)
            ->assertSee('Lateral esquerdo · Meia-esquerda')
            ->assertSee('<option value="segundo_atacante">Segundo atacante</option>', false)
            ->getContent();

        $this->assertStringNotContainsString('<option value="MEIA">', $html);
    }

    public function test_api_devolve_as_posicoes_e_a_principal(): void
    {
        $id = $this->atletaAtivo();
        Atleta::find($id)->definirPosicoes(['VOLANTE', 'MEIA_CENTRAL']);

        Sanctum::actingAs(Atleta::find($id));
        $this->getJson('/api/v1/atleta')->assertOk()
            ->assertJsonPath('data.posicao_atleta', 'VOLANTE')
            ->assertJsonPath('data.posicoes.0.posicao', 'VOLANTE')
            ->assertJsonPath('data.posicoes.0.sigla', 'VOL')
            ->assertJsonPath('data.posicoes.1.nome', 'Meia-central')
            ->assertJsonCount(2, 'data.posicoes');

        // Sem posição: principal null e lista vazia
        Atleta::find($id)->definirPosicoes([]);
        $this->getJson('/api/v1/atleta')->assertOk()
            ->assertJsonPath('data.posicao_atleta', null)
            ->assertJsonPath('data.posicoes', []);
    }

    public function test_exclusao_definitiva_apaga_as_posicoes(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');
        Atleta::find($id)->definirPosicoes(['ZAGUEIRO', 'LIBERO']);

        $this->assertTrue(Atleta::find($id)->excluirComDependencias());
        $this->assertSame(0, DB::table('tbl_atleta_posicao')->where('id_atleta', $id)->count());
        $this->assertDatabaseMissing('tbl_atletas', ['id_atleta' => $id]);
    }
}
