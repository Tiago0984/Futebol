<?php

namespace Tests\Feature;

use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Configurações do painel: modo escuro e minha conta, sem informações técnicas do sistema (decisão do dono do
 * projeto, 10/11/2026: quem administra a escolinha não precisa delas).
 */
class ConfiguracoesTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_tem_o_modo_escuro_e_minha_conta_sem_informacoes_tecnicas(): void
    {
        config(['mail.default' => 'log']);

        $this->comoAdmin()->get(route('admin.configuracoes.index'))->assertOk()
            ->assertSee('id="darkModeSwitch"', false)
            ->assertSee('Minha conta')
            ->assertSee('href="' . route('admin.perfil') . '"', false)
            ->assertDontSee('id="situacaoDoSistema"', false)
            ->assertDontSee('Versão do código')
            ->assertDontSee('Documentação da API')
            ->assertDontSee('PHP ' . PHP_VERSION);
    }
}
