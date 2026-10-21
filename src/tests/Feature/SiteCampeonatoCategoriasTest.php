<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\CampeonatoGrupo;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase H, Etapa H5: o site e a API do campeonato com várias categorias. A página do campeonato tem uma aba por
 * categoria (times, jogos com a fase e a classificação de cada grupo, com os classificados destacados); a home
 * mostra a prévia da primeira tabela; a lista de campeonatos mostra as categorias; a API traz as categorias, a
 * fase e a categoria de cada jogo e a classificação.
 */
class SiteCampeonatoCategoriasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $idSub17M;
    private int $copa;
    private int $aacj15;
    private int $aacj17;
    private int $botafogo;
    private int $santos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub17M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa São Carlos', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Arena', 'status_campeonato' => 'ATIVO',
        ]);
        $this->aacj15   = $this->time('AACJ Sub-15', 'INTERNO');
        $this->aacj17   = $this->time('AACJ Sub-17', 'INTERNO');
        $this->botafogo = $this->time('Botafogo', 'EXTERNO');
        $this->santos   = $this->time('Santos', 'EXTERNO');
        $this->participantes($this->copa, [$this->aacj15, $this->botafogo, $this->santos], $this->idSub15M);
        $this->participantes($this->copa, [$this->aacj17, $this->botafogo], $this->idSub17M);

        $grupo = CampeonatoGrupo::create(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => 'Chave A'])->id_campeonato_grupo;
        $campeonato = Campeonato::find($this->copa);
        $campeonato->definirGrupos($this->idSub15M, [$this->aacj15 => $grupo, $this->botafogo => $grupo, $this->santos => $grupo]);
        $campeonato->definirClassificados($this->idSub15M, 1);

        $this->jogo($this->aacj15, $this->botafogo, 2, 0, $this->idSub15M);
        $this->jogo($this->botafogo, $this->santos, 1, 1, $this->idSub15M);
        $this->jogo($this->aacj15, $this->santos, 0, 4, $this->idSub15M, 'SEMIFINAL'); // mata-mata: não conta
        $this->jogo($this->aacj17, $this->botafogo, 3, 2, $this->idSub17M);
        $this->jogo($this->aacj17, $this->santos, 9, 0, $this->idSub17M, rascunho: true); // rascunho: fora do site
    }

    public function test_pagina_do_campeonato_tem_uma_aba_por_categoria(): void
    {
        $html = $this->get(route('campeonato.show', $this->copa))->assertOk()
            ->assertSee('camp-hero-cat">Sub-15 Masculino', false)
            ->assertSee('camp-hero-cat">Sub-17 Masculino', false)
            ->assertSeeInOrder(['camp-cat-tab-btn is-active', 'Sub-15 Masculino', 'Sub-17 Masculino'], false)
            ->getContent();

        [$sub15, $sub17] = $this->paineis($html);

        // Sub-15: os times dela, os jogos com a fase da semifinal e a Chave A com o primeiro destacado
        $this->assertStringContainsString('AACJ Sub-15', $sub15);
        $this->assertStringNotContainsString('AACJ Sub-17', $sub15);
        $this->assertStringContainsString('match-secao">Semifinal', $sub15);   // título da seção (Fase I, Etapa I1b)
        $this->assertMatchesRegularExpression('#match-info-fase"><i class="fa fa-trophy"></i>\s*Semifinal#', $sub15);
        $this->assertStringContainsString('camp-grupo-titulo">Chave A', $sub15);
        $this->assertSame(1, substr_count($sub15, 'camp-classificado-tag'));
        $this->assertStringContainsString('Passam o primeiro de cada grupo (chave).', $sub15);
        $this->assertMatchesRegularExpression('#camp-classificado.*?AACJ Sub-15#s', $sub15);

        // Sub-17: o jogo publicado entra; o rascunho (9 x 0) não; sem grupo e sem classificados definidos
        $this->assertStringContainsString('AACJ Sub-17', $sub17);
        $this->assertStringNotContainsString('score-value">9', $sub17);
        $this->assertStringNotContainsString('camp-classificado-tag', $sub17);
        $this->assertStringNotContainsString('camp-grupo-titulo', $sub17);
    }

    public function test_campeonato_de_uma_categoria_fica_sem_abas(): void
    {
        DB::table('tbl_campeonato_time')->where('id_categoria', $this->idSub17M)->delete();
        DB::table('tbl_jogos')->whereIn('id_evento', EventoCalendario::where('id_categoria', $this->idSub17M)->pluck('id_evento_calendario'))->delete();
        Campeonato::find($this->copa)->gravarCategoriasEParticipantes([$this->idSub15M], [$this->idSub15M => [$this->aacj15, $this->botafogo, $this->santos]]);

        $this->get(route('campeonato.show', $this->copa))->assertOk()
            ->assertDontSee('camp-cat-tab-btn', false)
            ->assertSee('camp-grupo-titulo">Chave A', false);
    }

    public function test_home_e_lista_de_campeonatos(): void
    {
        // Home: a primeira tabela da primeira categoria, com o título e o classificado
        $this->get('/')->assertOk()
            ->assertSee('<strong>Sub-15 Masculino · Chave A</strong>', false)
            ->assertSee('camp-classificado-tag', false)
            ->assertSee('Ver classificação completa');

        // Lista: os selos das categorias do campeonato
        $this->get(route('campeonato'))->assertOk()
            ->assertSee('SUB-15 MASCULINO')
            ->assertSee('SUB-17 MASCULINO');
    }

    public function test_api_traz_categorias_fase_e_classificacao(): void
    {
        $lista = $this->getJson('/api/v1/campeonatos')->assertOk()->json('data.0');
        $this->assertSame(['Sub-15 Masculino', 'Sub-17 Masculino'], array_column($lista['categorias'], 'rotulo'));

        $dados = $this->getJson("/api/v1/campeonatos/{$this->copa}")->assertOk()->json('data');

        // Jogos publicados, com a fase e a categoria; o rascunho fica fora
        $this->assertCount(4, $dados['jogos']);
        $semifinal = collect($dados['jogos'])->firstWhere('fase_jogo', 'SEMIFINAL');
        $this->assertSame('Semifinal', $semifinal['fase']);
        $this->assertSame($this->idSub15M, $semifinal['id_categoria']);

        // Classificação: Sub-15 com a Chave A (1 classificado) e Sub-17 sem grupo
        [$sub15, $sub17] = $dados['classificacao'];
        $this->assertSame(['Sub-15 Masculino', 1], [$sub15['categoria'], $sub15['classificados']]);
        $this->assertSame('Chave A', $sub15['grupos'][0]['grupo']);
        $this->assertSame(
            [['AACJ Sub-15', 3, 1, true], ['Santos', 1, 1, false], ['Botafogo', 1, 2, false]], // Santos à frente pelo saldo (0 contra -2)
            array_map(fn ($l) => [$l['time'], $l['pontos'], $l['jogos'], $l['classificado']], $sub15['grupos'][0]['tabela']));
        $this->assertSame(['posicao', 'id_time', 'time', 'logo', 'logo_url', 'pontos', 'jogos', 'vitorias', 'empates', 'derrotas',
            'gols_marcados', 'gols_sofridos', 'saldo', 'classificado', 'empate_sorteio'], array_keys($sub15['grupos'][0]['tabela'][0]));
        $this->assertSame($this->aacj15, $sub15['grupos'][0]['tabela'][0]['id_time']);

        // Times sem a tabela de ligação (pivot) e com o endereço completo do escudo
        $this->assertArrayNotHasKey('pivot', $dados['times'][0]);
        $this->assertArrayHasKey('logo_url', $dados['times'][0]);
        $this->assertArrayHasKey('logo_url', $dados['jogos'][0]['time_casa']);
        $this->assertNull($sub17['grupos'][0]['grupo']);
        $this->assertSame([['AACJ Sub-17', 3], ['Botafogo', 0]], array_map(fn ($l) => [$l['time'], $l['pontos']], $sub17['grupos'][0]['tabela']));
    }

    // ---------- apoio ----------

    // Painéis das abas, na ordem: o HTML de cada categoria
    private function paineis(string $html): array
    {
        preg_match_all('#<div class="camp-cat-panel[^"]*"\s+id="cat-\d+".*?(?=<div class="camp-cat-panel|<script>\s*// Abas)#s', $html, $partes);

        return $partes[0];
    }

    private function jogo(int $casa, int $visitante, int $golsCasa, int $golsVisitante, int $idCategoria, string $fase = 'GRUPOS', bool $rascunho = false): Jogo
    {
        $evento = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => Jogo::tituloPara($casa, $visitante), 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => now()->subDay()->toDateString(), 'horario_inicio_evento_calendario' => '14:00:00',
            'status_evento_calendario' => 'ATIVO', 'id_categoria' => $idCategoria,
        ], inscreverCategoria: false, rascunho: $rascunho);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $this->copa, 'fase_jogo' => $fase,
            'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
            'placar_time_casa_jogos' => $golsCasa, 'placar_time_visitante_jogos' => $golsVisitante,
        ]);
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
    }
}
