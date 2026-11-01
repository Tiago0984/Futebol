<?php

namespace Tests\Feature;

use App\Services\ClienteExpo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClienteExpoTest extends TestCase
{
    private function mensagens(int $quantidade): array
    {
        return array_map(fn ($i) => ['to' => "ExpoPushToken[teste{$i}]", 'data' => ['id_notificacao' => $i]], range(1, $quantidade));
    }

    public function test_lotes_de_100_com_json_e_autorizacao_opcional(): void
    {
        config(['services.expo.access_token' => 'segredo-de-teste']);
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['data' => array_fill(0, 100, ['status' => 'ok'])])
            ->push(['data' => [['status' => 'ok']]]);

        app(ClienteExpo::class)->enviar($this->mensagens(101), fn () => $this->fail('Não deveria apagar token.'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->url() === ClienteExpo::URL && count($r->data()) === 100
            && $r->hasHeader('Authorization', 'Bearer segredo-de-teste')
            && $r->hasHeader('Accept', 'application/json') && $r->hasHeader('Content-Type', 'application/json'));
        Http::assertSent(fn ($r) => count($r->data()) === 1 && $r[0]['to'] === 'ExpoPushToken[teste101]');
    }

    public function test_ticket_invalido_apaga_apenas_o_token_correspondente(): void
    {
        config(['services.expo.access_token' => null]);
        Log::spy();
        Http::fake([ClienteExpo::URL => Http::response(['data' => [
            ['status' => 'ok'],
            ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
            ['status' => 'error', 'details' => ['error' => 'MessageTooBig']],
        ]])]);
        $apagados = [];
        app(ClienteExpo::class)->enviar($this->mensagens(3), function ($token) use (&$apagados) { $apagados[] = $token; });
        $this->assertSame(['ExpoPushToken[teste2]'], $apagados);
        Http::assertSent(fn ($r) => ! $r->hasHeader('Authorization'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_falha_http_nao_interrompe_os_lotes_seguintes(): void
    {
        Log::spy();
        Http::fakeSequence()->push([], 500)->push(['data' => [['status' => 'ok']]]);
        app(ClienteExpo::class)->enviar($this->mensagens(101), fn () => $this->fail('Não deveria apagar.'));
        Http::assertSentCount(2);
        Log::shouldHaveReceived('warning')->with('Falha HTTP no envio de push pela Expo.', ['status' => 500, 'quantidade' => 100])->once();
    }

    public function test_timeout_so_registra_falha(): void
    {
        Log::spy();
        Http::fake([ClienteExpo::URL => Http::failedConnection()]);
        app(ClienteExpo::class)->enviar($this->mensagens(1), fn () => $this->fail('Não deveria apagar.'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_resposta_inesperada_nao_remove_tokens(): void
    {
        Log::spy();
        Http::fake([ClienteExpo::URL => Http::response(['data' => ['status' => 'error']])]);
        app(ClienteExpo::class)->enviar($this->mensagens(1), fn () => $this->fail('Não deveria apagar.'));
        Log::shouldHaveReceived('warning')->once();
    }
}
