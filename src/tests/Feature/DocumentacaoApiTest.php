<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Página /api/documentacao (Fase 9): toda rota da API registrada aparece na tabela "Todas as rotas", com
 * o método (uma rota nova sem documentação quebra este teste), e as regras gerais estão escritas.
 */
class DocumentacaoApiTest extends TestCase
{
    public function test_toda_rota_da_api_esta_na_tabela_com_o_metodo(): void
    {
        $html = $this->get(route('api.documentacao'))->assertOk()->getContent();

        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => str_starts_with($rota->uri(), 'api/v1/'))
            ->flatMap(fn ($rota) => collect($rota->methods())->reject(fn ($m) => $m === 'HEAD')
                ->map(fn ($metodo) => [$metodo, '/' . $rota->uri()]));

        $this->assertGreaterThan(30, $rotas->count());

        foreach ($rotas as [$metodo, $uri]) {
            $this->assertStringContainsString(
                "<tr><td><span class=\"metodo {$metodo}\">{$metodo}</span></td><td><code>{$uri}</code></td>",
                $html,
                "{$metodo} {$uri} não está na documentação"
            );
        }
    }

    public function test_regras_gerais_estao_documentadas(): void
    {
        $this->get(route('api.documentacao'))
            ->assertOk()
            ->assertSee('"perfil": "responsavel"', false)
            ->assertSee('30 dias')
            ->assertSee('24 horas')
            ->assertSee('?page=N')
            ->assertSee('"por_pagina": 20', false)
            ->assertSee('2026-10-12T18:00:00-03:00')
            ->assertSee('"situacao": "CONFIRMADO"', false)
            ->assertSee('"time_do_atleta"', false)
            ->assertSee('Atleta não encontrado.')
            ->assertSee('Too Many Attempts.')
            ->assertSee('Unauthenticated.');
    }
}
