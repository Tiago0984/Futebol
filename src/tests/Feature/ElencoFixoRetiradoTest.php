<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase G, Etapa 4: sem o elenco fixo (tbl_atleta_time, apagada depois pela migration 2026_10_15_000005). O atleta é da AACJ, na
 * categoria dele; o time e a camisa são da convocação de cada campeonato. O cadastro do atleta não tem times
 * nem camisa; o site mostra os jogadores como da AACJ (com a categoria e as convocações), a home mostra as
 * categorias e a página do campeonato só os escudos; a API devolve "times" vazio.
 */
class ElencoFixoRetiradoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idSub11M;
    private int $azul;
    private int $taca;
    private int $rui;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idSub11M = $this->idCategoria('Sub-11', 'M');
        $this->azul = DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'azul.png', 'nome_time' => 'Time Azul', 'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO',
        ]);
        $this->taca = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Taça da Base', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'TORNEIO',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Arena', 'status_campeonato' => 'ATIVO',
        ]);

        $this->rui = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $this->rui)->update(['nome_atleta' => 'Rui Base']);
        $this->colocarNaCategoria($this->rui, $this->idSub11M);
        $this->convocacaoDireta($this->taca, $this->azul, $this->rui, 9);
    }

    public function test_cadastro_do_atleta_sem_times_nem_camisa(): void
    {
        $this->criarResponsavel($this->rui);

        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()
            ->assertDontSee('name="times[]"', false)
            ->assertDontSee('name="camisa_atleta_time"', false)
            ->assertDontSee('Camisa Nº')
            ->assertDontSee('data-times=', false);

        // Times e camisa enviados (formulário antigo) são ignorados
        $this->comoAdmin()->put(route('admin.atletas.update', $this->rui), $this->dadosEdicao($this->rui, [
            'times' => [$this->azul], 'camisa_atleta_time' => '30',
        ]))->assertSessionHas('sucesso');
    }

    public function test_exclusao_do_atleta_e_do_time_sem_o_elenco(): void
    {
        DB::table('tbl_convocacao')->delete();

        $this->assertTrue(Atleta::find($this->rui)->excluirComDependencias());
        $this->assertDatabaseMissing('tbl_atletas', ['id_atleta' => $this->rui]);

        DB::table('tbl_campeonato_time')->delete();
        DB::table('tbl_time')->where('id_time', $this->azul)->update(['status_time' => 'INATIVO']);
        $this->comoAdmin()->delete(route('admin.times.destroy', $this->azul))->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('tbl_time', ['id_time' => $this->azul]);
    }

    public function test_pagina_jogadores_mostra_o_atleta_da_aacj_na_categoria(): void
    {
        $html = $this->get(route('jogadores.vitrine'))->assertOk()
            ->assertSee('AACJ · Sub-11 Masculino')
            ->assertSee('data-categoria="' . $this->idSub11M . '"', false)
            ->assertSee('<option value="' . $this->idSub11M . '" >Sub-11 Masculino</option>', false)
            ->assertDontSee('id="filter-time"', false)
            ->assertDontSee('Time Azul')                   // sem o nome do time
            ->assertDontSee('Gols Totais')->assertDontSee('Partidas')
            ->getContent();

        // Modal: a convocação (campeonato, categoria e camisa) e a contagem de campeonatos convocado
        preg_match('#id="modalAtleta' . $this->rui . '".*?</footer>\s*</div>#s', $html, $modal);
        $this->assertStringContainsString('Taça da Base', $modal[0]);
        $this->assertStringContainsString('Sub-11 Masculino | nº 9', $modal[0]);
        $this->assertMatchesRegularExpression('#perf-value">1</span>\s*<span class="perf-label">Convocações#', $modal[0]);

        // Vindo da home (?categoria=ID): a categoria já marcada no filtro
        $this->get(route('jogadores.vitrine', ['categoria' => $this->idSub11M]))
            ->assertSee('<option value="' . $this->idSub11M . '" selected>Sub-11 Masculino</option>', false);
    }

    public function test_home_mostra_as_categorias_e_o_campeonato_so_os_escudos(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('NOSSAS CATEGORIAS')
            ->assertDontSee('NOSSOS TIMES')
            ->assertSee('SUB-11 MASCULINO')
            ->assertSee('1 atleta')
            ->assertSee(e(route('jogadores.vitrine', ['categoria' => $this->idSub11M])), false)
            ->assertDontSee('VER ELENCO');

        $this->participantes($this->taca, []); // a categoria na lista do campeonato
        $this->get(route('campeonato.show', $this->taca))->assertOk()
            ->assertSee('camp-team-type-tag tag-interno">AACJ', false)
            ->assertDontSee('VER ELENCO')
            ->assertDontSee('modalTime' . $this->azul, false)
            ->assertDontSee('Rui Base');
    }

    public function test_api_devolve_times_vazio(): void
    {
        Sanctum::actingAs(Atleta::find($this->rui));

        $this->getJson('/api/v1/atleta')->assertOk()->assertJsonPath('data.times', []);
    }
}
