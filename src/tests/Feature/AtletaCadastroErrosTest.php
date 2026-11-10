<?php

namespace Tests\Feature;

use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Erros do "Novo atleta" (10/11/2026): mensagens em português (o painel usa lang/pt_BR, middleware IdiomaPortugues;
 * antes saíam "The nome atleta field is required.") e o modal reaberto pelo Bootstrap, com o resumo dos erros dentro
 * dele (antes vinha aberto à força, com um fundo desenhado na página, e o Cancelar não fechava).
 */
class AtletaCadastroErrosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_cadastro_vazio_mostra_as_mensagens_em_portugues(): void
    {
        $this->comoAdmin()->from(route('admin.atletas.index'))
            ->post(route('admin.atletas.store'), [])
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHasErrors([
                'nome_atleta'          => 'O campo nome é obrigatório.',
                'rg_atleta'            => 'O campo RG é obrigatório.',
                'nome_responsavel'     => 'O campo nome do responsável é obrigatório.',
                'cep_endereco'         => 'O campo CEP é obrigatório.',
                'whatsapp_responsavel' => 'O campo WhatsApp é obrigatório.',
            ]);
    }

    public function test_modal_reabre_pelo_bootstrap_com_o_resumo_e_sem_fundo_preso(): void
    {
        $this->comoAdmin()->from(route('admin.atletas.index'))
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['nome_atleta' => '']));

        $this->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('id="modalNovoAtleta" tabindex="-1" aria-labelledby="modalNovoAtletaLabel" aria-hidden="true"', false)
            ->assertSee('data-abrir="1"', false)
            ->assertSee('id="errosNovoAtleta"', false)
            ->assertSee('Não foi possível salvar. Corrija:')
            ->assertSee('<li>O campo nome é obrigatório.</li>', false)
            ->assertDontSee('field is required')
            // O que foi digitado volta (o resto do formulário)
            ->assertSee('value="' . $this->dadosCadastro()['rg_atleta'] . '"', false)
            ->assertDontSee('<div class="modal-backdrop fade show"></div>', false)
            ->assertDontSee('style="display:block;" aria-modal="true"', false);
    }

    public function test_sem_erro_o_modal_abre_fechado(): void
    {
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('data-abrir="0"', false)
            ->assertDontSee('id="errosNovoAtleta"', false);
    }
}
