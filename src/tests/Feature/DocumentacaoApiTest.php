<?php

namespace Tests\Feature;

use App\Support\DocumentacaoApi;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Página /api/documentacao (Fase 9; refeita em 06/11/2026): toda rota da API registrada está na documentação, com o
 * método (uma rota nova sem documentação quebra este teste), nada documentado deixou de existir e as regras gerais
 * estão escritas. O conteúdo fica em resources/documentacao-api.
 */
class DocumentacaoApiTest extends TestCase
{
    private function rotasDaApi()
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => str_starts_with($rota->uri(), 'api/v1/'))
            ->flatMap(fn ($rota) => collect($rota->methods())->reject(fn ($m) => $m === 'HEAD')
                ->map(fn ($metodo) => [$metodo, '/' . $rota->uri()]));
    }

    public function test_toda_rota_da_api_esta_na_pagina_com_o_metodo(): void
    {
        $html = $this->get(route('api.documentacao'))->assertOk()->getContent();
        $rotas = $this->rotasDaApi();

        $this->assertGreaterThan(30, $rotas->count());

        foreach ($rotas as [$metodo, $uri]) {
            $this->assertStringContainsString("data-rota=\"{$metodo} {$uri}\"", $html, "{$metodo} {$uri} não está na documentação");
        }
    }

    public function test_nada_documentado_deixou_de_existir_e_cada_rota_tem_exemplo(): void
    {
        $reais = $this->rotasDaApi()->map(fn ($rota) => implode(' ', $rota))->all();
        $documentadas = array_map(fn ($rota) => implode(' ', $rota), DocumentacaoApi::rotas());

        $this->assertEqualsCanonicalizing($reais, $documentadas);

        $ids = [];
        foreach (DocumentacaoApi::endpoints() as $item) {
            $this->assertNotEmpty($item['respostas'], "{$item['rota']} sem resposta de exemplo");
            $this->assertNotContains($item['id'], $ids, "id repetido: {$item['id']}");
            $ids[] = $item['id'];
        }
    }

    public function test_menu_filtro_exemplos_e_postman_estao_na_pagina(): void
    {
        $this->get(route('api.documentacao'))
            ->assertOk()
            ->assertSee('futebol/images/logo2.png', false)
            ->assertSee('Documentação da API')
            ->assertSee('Open+Sans', false)
            ->assertDontSee('Oswald')
            ->assertSee('class="camisa"', false)
            ->assertSee('id="search"', false)
            ->assertSee('data-metodo="DELETE"', false)
            ->assertSee('id="alternarGrupos"', false)
            ->assertSee('class="nav-group fechado"', false)
            ->assertSee('class="method guia"', false)
            ->assertSee('Coleção Postman')
            ->assertSee('React Native')
            ->assertSee('data-lang="bash"', false)
            ->assertSee(DocumentacaoApi::PRODUCAO)
            ->assertSee(DocumentacaoApi::LOCAL);

        $colecao = DocumentacaoApi::colecaoPostman('http://exemplo.test/api/v1');
        $this->assertSame('baseUrl', $colecao['variable'][0]['key']);
        $this->assertNotEmpty($colecao['item']);
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
            ->assertSee('Muitas tentativas. Aguarde um pouco e tente de novo.')
            ->assertSee('Faça login para continuar')
            ->assertSee('logo_url');
    }
}
