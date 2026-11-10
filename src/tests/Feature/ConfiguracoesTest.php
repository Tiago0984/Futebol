<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Configurações do painel (10/11/2026): modo escuro e a situação do sistema, só leitura (e-mail, fila, link das
 * fotos, contatos), sem mostrar senhas nem chaves.
 */
class ConfiguracoesTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_mostra_a_situacao_do_sistema_sem_segredos(): void
    {
        config([
            'mail.default' => 'log', 'mail.mailers.smtp.password' => 'senha-secreta-smtp',
            'contato.email' => 'secretaria@aacj.test', 'contato.telefone' => null,
            'services.expo.access_token' => 'chave-secreta-expo',
        ]);

        $this->comoAdmin()->get(route('admin.configuracoes.index'))->assertOk()
            ->assertSee('id="darkModeSwitch"', false)
            ->assertSee('Não entrega: vai para o log (log)')
            ->assertSee('Andando (')
            ->assertSee('E-mail: secretaria@aacj.test')
            ->assertSee('Telefone: <span class="text-body-secondary">não configurado', false)
            ->assertSee('Com chave de acesso da Expo')
            ->assertSee('PHP ' . PHP_VERSION)
            ->assertDontSee('senha-secreta-smtp')
            ->assertDontSee('chave-secreta-expo');
    }

    public function test_avisa_fila_parada_e_falhas(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'config-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x',
        ]);

        $this->comoAdmin()->get(route('admin.configuracoes.index'))->assertOk()
            ->assertSee('Parada desde ' . now()->subHour()->format('d/m H:i'))
            ->assertSee('1 tarefa(s) com falha.');
    }
}
