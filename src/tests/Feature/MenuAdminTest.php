<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 10, Etapa 1: menu real do admin (barra lateral), filtros das listas pela URL, rotas sem página
 * e exclusão de time, campeonato e categoria em uso.
 */
class MenuAdminTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idSub11M;
    private int $emAndamento;
    private int $encerrado;
    private int $azul;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idSub11M = $this->idCategoria('Sub-11', 'M');

        $campeonato = fn (string $nome, string $inicio, string $fim, string $status = 'ATIVO') => DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => $nome, 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => $inicio, 'data_fim_campeonato' => $fim, 'local_evento' => 'Quadra A',
            'status_campeonato' => $status,
        ]);
        $this->emAndamento = $campeonato('Taça Em Andamento', now()->subWeek()->toDateString(), now()->addWeek()->toDateString());
        $this->encerrado   = $campeonato('Taça Encerrada', now()->subYear()->toDateString(), now()->subYear()->addMonth()->toDateString());
        $campeonato('Taça Inativa', now()->subWeek()->toDateString(), now()->addWeek()->toDateString(), 'INATIVO');

        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
            'status_time' => 'ATIVO',
        ]);
        $this->azul      = $time('Time Azul', 'INTERNO');
        $this->visitante = $time('Time Visitante', 'EXTERNO');
    }

    // ---------- barra lateral ----------

    public function test_todos_os_links_da_barra_respondem_200(): void
    {
        $links = $this->linksDaBarra($this->comoAdmin()->get(route('admin.dashboard'))->assertOk());

        // Ramos, Campeonatos (todos), Jogos, Times do campeonato, Grade, Gerar agenda, Categorias e Times
        $this->assertContains(route('admin.calendario.index', ['ramo' => 'individuais']), $links);
        // Treinos e Outros saíram do menu (os ramos continuam no filtro do Calendário e na linha de caminho)
        $this->assertNotContains(route('admin.calendario.index', ['ramo' => 'treinos']), $links);
        $this->assertNotContains(route('admin.calendario.index', ['ramo' => 'outros']), $links);
        $this->assertContains(route('admin.campeonatos.index'), $links);
        $this->assertContains(route('admin.campeonatos.jogos'), $links);
        // "Jogos" de Campeonatos: os jogos de todos os campeonatos; a lista filtrada e a tela de Campeonatos filtrada saíram do menu
        $this->assertNotContains($this->jogosDoEmAndamento(), $links);
        $this->assertNotContains(route('admin.campeonatos.index', ['campeonato' => $this->emAndamento]), $links);
        $this->assertContains(route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]), $links);
        $this->assertContains(route('admin.times.index'), $links);

        foreach ($links as $link) {
            $this->comoAdmin()->get($link)->assertOk();
        }
    }

    public function test_barra_sem_nomes_ficticios_e_sem_nome_de_campeonato(): void
    {
        $barra = $this->barra($this->comoAdmin()->get(route('admin.dashboard')));

        foreach (['Copa Escola', 'Copa Regional', 'Lucas Silva', 'Leões FC', 'Estrela Azul', 'AACJ x Leões', 'Exame médico',
                  'Escalação', 'Sub-11, Sub-13, Sub-15...', 'Eventos e Grade'] as $ficticio) {
            $this->assertStringNotContainsString($ficticio, $barra);
        }

        // Nenhum campeonato na barra: o "Jogos" de Campeonatos abre todos, e o filtro da tela escolhe um
        $this->assertStringContainsString('title="Jogos dos campeonatos"', $barra);
        $this->assertStringNotContainsString('Taça Em Andamento', $barra);
        $this->assertStringNotContainsString('Taça Encerrada', $barra);
        $this->assertStringNotContainsString('Taça Inativa', $barra); // inativo não está "em andamento"
        foreach (['Calendário', 'Campeonatos', 'Amistosos', 'Individuais', 'Jogos',
                  'Grade de treino', 'Gerar agenda do mês', 'CADASTROS', 'Categorias', 'Times'] as $item) {
            $this->assertStringContainsString($item, $barra);
        }
        $this->assertStringNotContainsString('Ver todos', $barra);
    }

    public function test_contador_de_matriculas_pendentes(): void
    {
        $this->criarAtleta($this->nascidoComIdade(11), 'M', 'PENDENTE');
        $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');

        $this->assertStringContainsString('font-size:0.65rem;">2</span>',
            $this->barra($this->comoAdmin()->get(route('admin.dashboard'))));
    }

    public function test_item_ativo_em_cada_pagina(): void
    {
        $treino  = $this->evento('TREINO');
        $amistoso = $this->jogo(null);
        $daCopa  = $this->jogo($this->emAndamento);

        $esperado = [
            route('admin.calendario.index')                                   => route('admin.calendario.index'),
            // Treinos e Outros não estão no menu: nenhum link (a gaveta Eventos fica aberta)
            route('admin.calendario.index', ['ramo' => 'treinos'])            => null,
            route('admin.calendario.index', ['ramo' => 'outros'])             => null,
            route('admin.calendario.index', ['ramo' => 'individuais'])        => route('admin.calendario.index', ['ramo' => 'individuais']),
            route('admin.calendario.index', ['ramo' => 'campeonatos'])        => route('admin.campeonatos.index'), // Campeonatos (o ramo)
            route('admin.calendario.index', ['tab' => 'grade'])               => route('admin.calendario.index', ['tab' => 'grade']),
            route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]) => route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]),
            route('admin.jogos.index')                                        => route('admin.jogos.index'),
            // Jogos filtrados só por um campeonato (em andamento ou não): Campeonatos e o "Jogos" de dentro dele
            route('admin.jogos.index', ['campeonato' => $this->emAndamento])  => [route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()],
            route('admin.jogos.index', ['campeonato' => $this->encerrado])    => [route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()],
            route('admin.campeonatos.index')                                  => route('admin.campeonatos.index'),
            // Tela de Campeonatos, com ou sem filtro: Campeonatos
            route('admin.campeonatos.index', ['campeonato' => $this->emAndamento]) => route('admin.campeonatos.index'),
            route('admin.campeonatos.index', ['campeonato' => $this->encerrado])   => route('admin.campeonatos.index'),
            route('admin.campeonatos.index', ['campeonato' => 999999])             => route('admin.campeonatos.index'),
            route('admin.times.index')                                        => route('admin.times.index'),
            route('admin.times.elenco', $this->azul)                          => route('admin.times.index'),
            route('admin.categorias.index')                                   => route('admin.categorias.index'),
            // Tela do evento: o ramo dele
            route('admin.calendario.eventos.show', $treino->id_evento_calendario) => null, // idem
            route('admin.calendario.eventos.show', $amistoso->id_evento)      => route('admin.amistosos.times'), // Amistosos
            route('admin.calendario.eventos.show', $daCopa->id_evento)        => route('admin.campeonatos.index'), // jogo de campeonato: Campeonatos (ramo)
        ];

        foreach ($esperado as $pagina => $ativo) {
            $this->assertSame($ativo === null ? [] : (array) $ativo, $this->linksAtivos($this->comoAdmin()->get($pagina)->assertOk()), "Página {$pagina}");
        }
    }

    public function test_campeonatos_abre_a_tela_com_todos_e_a_seta_mostra_jogos(): void
    {
        $gaveta = fn (TestResponse $r) => $this->xpathDaBarra($r)->query('//a[@id="gavetaCampeonatos"]')->item(0);

        // Fora do ramo: fechada; o texto abre a tela de Campeonatos sem filtro (a seta abre e fecha)
        $fora = $this->comoAdmin()->get(route('admin.dashboard'));
        /** @var \DOMElement $a */
        $a = $gaveta($fora);
        $this->assertSame(route('admin.campeonatos.index'), $a->getAttribute('href'));
        $this->assertSame('false', $a->getAttribute('aria-expanded'));
        $this->assertStringNotContainsString('active', $a->getAttribute('class'));
        $this->assertStringNotContainsString('menu-open', $a->parentNode->getAttribute('class'));
        $this->assertStringNotContainsString('href="' . e(route('admin.calendario.index', ['ramo' => 'campeonatos'])) . '"', $this->barra($fora));

        // Subitens: só o "Jogos" do campeonato em andamento (a tela de jogos dele), sem o antigo "Times"
        $this->assertSame([
            ['Jogos', $this->itemJogosDosCampeonatos()],
        ], $this->subitensDaGaveta($fora));

        // Páginas do ramo Campeonatos: ativa e aberta
        $daCopa = $this->jogo($this->emAndamento);
        foreach ([
            route('admin.calendario.index', ['ramo' => 'campeonatos']),
            route('admin.calendario.eventos.show', $daCopa->id_evento),
            $this->jogosDoEmAndamento(),
            route('admin.campeonatos.index'),
            route('admin.campeonatos.index', ['campeonato' => $this->emAndamento]),
            route('admin.campeonatos.times', $this->emAndamento),
        ] as $pagina) {
            $a = $gaveta($this->comoAdmin()->get($pagina)->assertOk());
            $this->assertStringContainsString('active', $a->getAttribute('class'), $pagina);
            $this->assertSame('true', $a->getAttribute('aria-expanded'), $pagina);
            $this->assertStringContainsString('menu-open', $a->parentNode->getAttribute('class'), $pagina);
        }
    }

    public function test_campeonatos_tem_um_jogos_so_com_ou_sem_campeonato_em_andamento(): void
    {
        $jogos = [['Jogos', $this->itemJogosDosCampeonatos()]];

        // Sem campeonato em andamento: a seta e o "Jogos" continuam (a tela mostra todos os campeonatos)
        DB::table('tbl_campeonato')->where('id_campeonato', $this->emAndamento)->update(['status_campeonato' => 'INATIVO']);
        $resposta = $this->comoAdmin()->get(route('admin.dashboard'));
        $this->assertSame(1, $this->xpathDaBarra($resposta)->query('//a[@id="gavetaCampeonatos"]//i[contains(@class, "nav-arrow")]')->length);
        $this->assertSame($jogos, $this->subitensDaGaveta($resposta));

        // Com dois em andamento: o mesmo "Jogos" só (o filtro da tela troca de campeonato)
        DB::table('tbl_campeonato')->update(['status_campeonato' => 'ATIVO',
            'data_inicio_campeonato' => now()->subDay()->toDateString(), 'data_fim_campeonato' => now()->addDay()->toDateString()]);
        $this->assertSame($jogos, $this->subitensDaGaveta($this->comoAdmin()->get(route('admin.dashboard'))));
    }

    // ---------- jogos e times dentro de cada campeonato (menu) ----------

    public function test_jogos_do_campeonato_sem_subitens_e_a_lista_filtrada_continua(): void
    {
        $verde = $this->time('Time Verde');
        $passado   = $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, -3);
        $proximo   = $this->jogoNoDia($this->emAndamento, $verde, $this->azul, 2);
        $cancelado = $this->jogoNoDia($this->emAndamento, $this->visitante, $verde, 1, 'CANCELADO');
        $oculto    = $this->jogoNoDia($this->emAndamento, $verde, $this->visitante, 1, 'INATIVO');
        $outro     = $this->jogoNoDia($this->encerrado, $this->azul, $verde, 1);  // campeonato fora do menu
        $amistoso  = $this->jogoNoDia(null, $this->visitante, $this->azul, 1);

        // "Jogos" sem subitens (o "Times" saiu; nenhum jogo no menu)
        $barra = $this->barra($this->comoAdmin()->get(route('admin.dashboard')));
        $this->assertSame([], $this->subitensDoCampeonato($barra));
        $this->assertStringNotContainsString('Time Verde x Time Azul', $barra);
        $this->assertStringNotContainsString('jogo=', $barra);
        $this->assertStringNotContainsString('time=', $barra);

        // A lista de Jogos filtrada pelo campeonato (sem os de outro campeonato, o amistoso e os ocultos)
        $ids = $this->comoAdmin()->get($this->jogosDoEmAndamento())->assertOk()->viewData('jogos')->pluck('id_jogo')->all();
        $this->assertEqualsCanonicalizing([$passado->id_jogo, $proximo->id_jogo, $cancelado->id_jogo], $ids);
        $this->assertNotContains($oculto->id_jogo, $ids);
        $this->assertNotContains($outro->id_jogo, $ids);
        $this->assertNotContains($amistoso->id_jogo, $ids);
    }

    public function test_item_ativo_nos_jogos_do_campeonato(): void
    {
        $jogo  = $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, 2);
        $todos = $this->jogosDoEmAndamento();

        // Lista filtrada só pelo campeonato: Campeonatos e o "Jogos" dele
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()], $this->linksAtivos($this->comoAdmin()->get($todos)));

        // Com outro filtro junto (um jogo, time ou situação): Jogos do ESPORTE
        foreach ([['jogo' => $jogo->id_jogo], ['time' => $this->azul], ['situacao' => 'INATIVO']] as $filtro) {
            $this->assertSame([route('admin.jogos.index')],
                $this->linksAtivos($this->comoAdmin()->get(route('admin.jogos.index', ['campeonato' => $this->emAndamento, ...$filtro]))));
        }

        // Tela do jogo: Campeonatos (o ramo)
        $this->assertSame([route('admin.campeonatos.index')],
            $this->linksAtivos($this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))));
    }

    public function test_lista_de_jogos_filtrada_por_jogo(): void
    {
        $verde   = $this->time('Time Verde');
        $proximo = $this->jogoNoDia($this->emAndamento, $verde, $this->azul, 2);
        $outro   = $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, 5);
        $oculto  = $this->jogoNoDia($this->emAndamento, $this->visitante, $verde, 3, 'INATIVO');
        $ids = fn (array $filtros) => $this->comoAdmin()->get(route('admin.jogos.index', $filtros))->assertOk()
            ->viewData('jogos')->pluck('id_jogo')->all();

        // Só aquele jogo, com o aviso e o jeito de voltar aos jogos do campeonato; os botões da linha seguem
        $resposta = $this->comoAdmin()->get($this->listaDoJogo($proximo))->assertOk();
        $this->assertSame([$proximo->id_jogo], $resposta->viewData('jogos')->pluck('id_jogo')->all());
        $resposta->assertSee('Mostrando só o jogo <strong>Time Verde x Time Azul</strong>', false)
            ->assertSee('<a href="' . route('admin.jogos.index', ['campeonato' => $this->emAndamento]) . '" class="ms-auto btn btn-sm btn-outline-secondary">', false)
            ->assertSee('Ver todos os jogos do campeonato')
            ->assertSee('<input type="hidden" name="jogo" value="' . $proximo->id_jogo . '">', false)
            ->assertSee(route('admin.calendario.eventos.show', $proximo->id_evento), false)          // escalação (tela do evento)
            ->assertSee('data-id="' . $proximo->id_jogo . '"', false)                                // editar
            ->assertSee(route('admin.calendario.eventos.cancelar', $proximo->id_evento), false)      // cancelar
            ->assertSee(route('admin.calendario.eventos.ocultar', $proximo->id_evento), false)       // ocultar
            ->assertDontSee('oculto(s) fora da lista');

        // "Ver todos os jogos" continua com todos do campeonato (sem os ocultos)
        $this->assertEqualsCanonicalizing([$proximo->id_jogo, $outro->id_jogo], $ids(['campeonato' => $this->emAndamento]));

        // Pedido pelo id, o oculto aparece, com o aviso da situação
        $this->assertSame([$oculto->id_jogo], $ids(['jogo' => $oculto->id_jogo]));
        $this->comoAdmin()->get(route('admin.jogos.index', ['jogo' => $oculto->id_jogo]))
            ->assertSee('Este jogo está <strong>oculto</strong>', false);

        // Convive com campeonato e situação
        $this->assertSame([], $ids(['jogo' => $proximo->id_jogo, 'campeonato' => $this->encerrado]));
        $this->assertSame([], $ids(['jogo' => $proximo->id_jogo, 'situacao' => 'CANCELADO']));
        $this->assertSame([$proximo->id_jogo], $ids(['jogo' => $proximo->id_jogo, 'situacao' => 'ATIVO']));

        // ID inexistente ou inválido: sem erro, sem o filtro de jogo
        foreach ([999999, 'abc'] as $invalido) {
            $this->comoAdmin()->get(route('admin.jogos.index', ['campeonato' => $this->emAndamento, 'jogo' => $invalido]))
                ->assertOk()->assertDontSee('Mostrando só o jogo');
            $this->assertEqualsCanonicalizing([$proximo->id_jogo, $outro->id_jogo], $ids(['campeonato' => $this->emAndamento, 'jogo' => $invalido]));
        }
    }

    // ---------- times do campeonato (cartões) e jogadores de um time, só leitura ----------

    public function test_times_do_campeonato_em_cartoes_e_jogadores_so_leitura(): void
    {
        $verde = $this->time('Time Verde');
        DB::table('tbl_campeonato_time')->insert([
            ['id_campeonato' => $this->emAndamento, 'id_time' => $this->azul],
            ['id_campeonato' => $this->emAndamento, 'id_time' => $this->visitante],
        ]);
        $rui = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $rui)->update(['nome_atleta' => 'Rui Elenco']);
        DB::table('tbl_atleta_time')->insert([
            'id_time' => $this->azul, 'id_atleta' => $rui, 'camisa_atleta_time' => 9, 'posicao_atleta_time' => 'MEIA',
            'status_atleta_time' => 'TITULAR', 'jogos_atleta_time' => 4, 'gols_atleta_time' => 3,
        ]);
        $cartoes = route('admin.campeonatos.times', $this->emAndamento);
        $jogadores = route('admin.campeonatos.times.show', [$this->emAndamento, $this->azul]);

        // Cartões: só os participantes (o Verde não), interno com link e contagem, externo bloqueado
        $resposta = $this->comoAdmin()->get($cartoes)->assertOk();
        // (o Verde aparece só no select de times do modal "Editar jogo", fora da área dos cartões)
        $this->assertStringNotContainsString('Time Verde', explode('id="modalEditarJogo"', $resposta->getContent())[0]);
        $resposta->assertSee('Jogos · Taça Em Andamento')
            ->assertSeeInOrder(['data-id-time="' . $this->azul . '"', 'data-id-time="' . $this->visitante . '"'], false)
            ->assertSee('<a href="' . $jogadores . '" class="escal-team-card">', false)
            ->assertSee('1 atleta')
            ->assertSee('Elenco não disponível nesta associação')
            ->assertDontSee(route('admin.campeonatos.times.show', [$this->emAndamento, $this->visitante]), false)
            ->assertDontSee('Rui Elenco'); // nome de atleta só na tela do time
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()], $this->linksAtivos($resposta));

        // Jogadores do time: o elenco, só para ver (sem adicionar, editar nem tirar)
        $resposta = $this->comoAdmin()->get($jogadores)->assertOk();
        $resposta->assertSee('Elenco · Time Azul')->assertSee('Rui Elenco')->assertSee('MEIA')
            ->assertDontSee('id="formAdicionarElenco"', false)
            ->assertDontSee('data-bs-target="#modalEdit' . $rui . '"', false)
            ->assertDontSee(route('admin.times.elenco.remover', [$this->azul, $rui]), false)
            ->assertDontSee(route('admin.times.elenco.update', [$this->azul, $rui]), false)
            ->assertSee('<a href="' . $cartoes . '" class="btn btn-sm btn-outline-secondary" title="Voltar"', false)
            ->assertSeeInOrder([
                '>Eventos</a>', '>Campeonatos</a>',
                '<a href="' . e(route('admin.campeonatos.index', ['campeonato' => $this->emAndamento])) . '">Taça Em Andamento</a>',
                '<a href="' . $cartoes . '">Jogos</a>',
                'aria-current="page">Time Azul</li>',
            ], false);
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()], $this->linksAtivos($resposta));

        // A tela de Elenco pela lista de Times continua editável
        $this->comoAdmin()->get(route('admin.times.elenco', $this->azul))
            ->assertSee('id="formAdicionarElenco"', false)
            ->assertSee(route('admin.times.elenco.remover', [$this->azul, $rui]), false);

        // Externo, time fora do campeonato e campeonato inexistente
        $this->comoAdmin()->get(route('admin.campeonatos.times.show', [$this->emAndamento, $this->visitante]))
            ->assertRedirect($cartoes)->assertSessionHas('erro');
        $this->comoAdmin()->get(route('admin.campeonatos.times.show', [$this->emAndamento, $verde]))->assertNotFound();
        $this->comoAdmin()->get(route('admin.campeonatos.times', 999999))->assertNotFound();

        // Campeonato que não está em andamento: marca o mesmo "Jogos"
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()],
            $this->linksAtivos($this->comoAdmin()->get(route('admin.campeonatos.times', $this->encerrado))));
    }

    public function test_times_dos_amistosos_separados_por_jogo(): void
    {
        $verde    = $this->time('Time Verde');
        $longe    = $this->jogoNoDia(null, $this->azul, $verde, 20);
        $perto    = $this->jogoNoDia(null, $verde, $this->visitante, 3);
        $passado  = $this->jogoNoDia(null, $this->azul, $this->visitante, -4);
        $this->jogoNoDia(null, $verde, $this->azul, 5, 'INATIVO');               // oculto: fora
        $this->jogoNoDia($this->emAndamento, $this->azul, $verde, 2);            // de campeonato: fora

        $pagina = route('admin.amistosos.times');
        $resposta = $this->comoAdmin()->get($pagina)->assertOk();
        $html = $resposta->getContent();

        // Um bloco por jogo: próximos do mais perto ao mais longe, depois os realizados
        preg_match_all('#data-id-jogo="(\d+)"#', $html, $ids);
        $this->assertSame([$perto->id_jogo, $longe->id_jogo, $passado->id_jogo], array_map('intval', $ids[1]));
        $resposta->assertSeeInOrder(['Próximos amistosos', 'Já realizados'])
            ->assertSee($perto->evento->data_evento_calendario->format('d/m/Y'))
            ->assertDontSee('Ver o jogo')   // saiu: a tela do jogo abre pelo botão de inscritos
            ->assertSee(route('admin.calendario.eventos.show', $perto->id_evento), false);

        // No bloco: o cartão de cada time; o interno abre os escalados daquele jogo, o externo fica bloqueado
        $bloco = $this->blocoDoJogo($html, $perto);
        $this->assertStringContainsString('<a href="' . route('admin.jogos.times.show', [$perto->id_jogo, $verde]) . '" class="escal-team-card">', $bloco);
        $this->assertStringContainsString('0 escalados no jogo', $bloco);
        $this->assertStringContainsString('Elenco não disponível nesta associação', $bloco);
        $this->assertStringNotContainsString(route('admin.jogos.times.show', [$perto->id_jogo, $this->visitante]), $bloco);

        // Menu: Amistosos abre esta tela, sem subitens; título "Jogos dos amistosos"
        $this->assertSame([$pagina], $this->linksAtivos($resposta));
        $xpath = $this->xpathDaBarra($resposta);
        $this->assertSame($pagina, $xpath->query('//a[@id="itemAmistosos"]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//a[@id="itemAmistosos"]/following-sibling::ul')->length);
        $resposta->assertSee('<h1 class="page-title">Jogos dos amistosos</h1>', false);
    }

    public function test_times_do_campeonato_separados_por_jogo(): void
    {
        $verde = $this->time('Time Verde');
        $preto = $this->time('Time Preto');
        DB::table('tbl_campeonato_time')->insert([
            ['id_campeonato' => $this->emAndamento, 'id_time' => $this->azul],
            ['id_campeonato' => $this->emAndamento, 'id_time' => $verde],
            ['id_campeonato' => $this->emAndamento, 'id_time' => $preto],   // sem jogo: vai para o fim
        ]);
        $proximo = $this->jogoNoDia($this->emAndamento, $verde, $this->azul, 4);
        $passado = $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, -2);
        $this->jogoNoDia(null, $this->azul, $verde, 1);                          // amistoso: fora

        $pagina = route('admin.campeonatos.times', $this->emAndamento);
        $resposta = $this->comoAdmin()->get($pagina)->assertOk();
        $html = $resposta->getContent();

        preg_match_all('#data-id-jogo="(\d+)"#', $html, $ids);
        $this->assertSame([$proximo->id_jogo, $passado->id_jogo], array_map('intval', $ids[1]));
        $resposta->assertSeeInOrder(['Próximos jogos', 'Já realizados', 'Participantes sem jogo na lista'])
            ->assertDontSee('Ver o jogo');

        // Cartões do jogo levam aos escalados; o participante sem jogo, ao elenco (só leitura)
        $this->assertStringContainsString(route('admin.jogos.times.show', [$proximo->id_jogo, $verde]), $this->blocoDoJogo($html, $proximo));
        $this->assertStringContainsString(route('admin.jogos.times.show', [$proximo->id_jogo, $this->azul]), $this->blocoDoJogo($html, $proximo));
        preg_match('#id="participantesSemJogo">.*#s', $html, $semJogo);
        $this->assertStringContainsString(route('admin.campeonatos.times.show', [$this->emAndamento, $preto]), $semJogo[0]);
        $this->assertStringNotContainsString(route('admin.campeonatos.times.show', [$this->emAndamento, $verde]), $semJogo[0]);
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()], $this->linksAtivos($resposta));

        // Filtro "Campeonato": todos (inclusive o encerrado e o inativo), o atual selecionado; cada opção abre a tela dele
        $opcoes = [];
        $xpath = new \DOMXPath(tap(new \DOMDocument(), fn ($d) => @$d->loadHTML('<?xml encoding="utf-8"?>' . $html)));
        foreach ($xpath->query('//select[@id="filtroCampeonato"]/option') as $opcao) {
            /** @var \DOMElement $opcao */
            $opcoes[$opcao->getAttribute('value')] = $opcao->hasAttribute('selected');
        }
        $this->assertSame(route('admin.campeonatos.jogos'), array_key_first($opcoes)); // "Todos" primeiro
        $this->assertEqualsCanonicalizing([route('admin.campeonatos.jogos'), ...DB::table('tbl_campeonato')->pluck('id_campeonato')
            ->map(fn ($id) => route('admin.campeonatos.times', $id))->all()], array_keys($opcoes));
        $this->assertSame([$pagina], array_keys(array_filter($opcoes)));
        $this->comoAdmin()->get(route('admin.campeonatos.times', $this->encerrado))->assertOk()
            ->assertSee('Jogos · Taça Encerrada');

        // Jogo em rascunho (Fase 10, Etapa 4): aviso no topo e "Revisar e publicar" só no bloco dele
        $this->assertStringNotContainsString('id="avisoRascunhos"', $html);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $proximo->id_evento)->update(['data_publicacao_evento_calendario' => null]);
        $html = $this->comoAdmin()->get($pagina)->assertOk()->assertSee('1 jogo ainda não publicado')->getContent();
        $this->assertStringContainsString(route('admin.calendario.eventos.show', $proximo->id_evento) . '" class="btn btn-warning btn-sm js-publicar-jogo"',
            $this->blocoDoJogo($html, $proximo));
        $this->assertStringNotContainsString('js-publicar-jogo', $this->blocoDoJogo($html, $passado));
    }

    public function test_jogos_de_todos_os_campeonatos_com_o_nome_do_campeonato_em_cada_bloco(): void
    {
        $verde = $this->time('Time Verde');
        $outra = DB::table('tbl_campeonato')->where('nome_campeonato', 'Taça Encerrada')->value('id_campeonato');
        $doAndamento = $this->jogoNoDia($this->emAndamento, $this->azul, $verde, 4);
        $doEncerrado = $this->jogoNoDia($outra, $verde, $this->azul, 2);
        $amistoso    = $this->jogoNoDia(null, $this->azul, $verde, 1);                // amistoso: fora

        $pagina = route('admin.campeonatos.jogos');
        $resposta = $this->comoAdmin()->get($pagina)->assertOk()
            ->assertSee('<h1 class="page-title">Jogos de todos os campeonatos</h1>', false)
            ->assertDontSee('id="participantesSemJogo"', false);
        $html = $resposta->getContent();

        // Jogos dos dois campeonatos, do mais perto ao mais longe; o amistoso não entra
        preg_match_all('#data-id-jogo="(\d+)"#', $html, $ids);
        $this->assertSame([$doEncerrado->id_jogo, $doAndamento->id_jogo], array_map('intval', $ids[1]));
        $this->assertStringNotContainsString('data-id-jogo="' . $amistoso->id_jogo . '"', $html);

        // Cada bloco com o nome do seu campeonato (sem o antigo "Ver o jogo")
        $this->assertStringContainsString('Taça Encerrada', $this->blocoDoJogo($html, $doEncerrado));
        $this->assertStringContainsString('Taça Em Andamento', $this->blocoDoJogo($html, $doAndamento));
        $this->assertStringNotContainsString('Ver o jogo', $html);

        // Filtro com "Todos" selecionado; o menu marca Campeonatos › Jogos (o item abre esta tela)
        $this->assertStringContainsString('<option value="' . $pagina . '" selected>Todos</option>', $html);
        $this->assertSame([route('admin.campeonatos.index'), $pagina], $this->linksAtivos($resposta));

        // A logo do campeonato fica ao lado do nome
        $this->assertStringContainsString(asset('futebol/images/campeonatos/logo.png'), $this->blocoDoJogo($html, $doAndamento));

        // Nos amistosos o bloco não tem nome de campeonato
        $this->assertStringNotContainsString('js-campeonato-do-jogo',
            $this->comoAdmin()->get(route('admin.amistosos.times'))->getContent());
    }

    public function test_times_por_jogo_em_abas_proximos_e_realizados(): void
    {
        $pagina = route('admin.campeonatos.times', $this->emAndamento);

        // Com jogo marcado: abre em "Próximos", cada aba com o contador
        $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, 3);
        $this->jogoNoDia($this->emAndamento, $this->visitante, $this->azul, -3);
        $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, -5);
        $html = preg_replace('/\s+/', ' ', $this->comoAdmin()->get($pagina)->assertOk()->getContent());
        $this->assertStringContainsString('id="abasDeJogos"', $html);
        $this->assertStringContainsString('class="nav-link active" id="aba-proximos"', $html);
        $this->assertStringContainsString('Próximos jogos <span class="badge bg-secondary ms-1">1</span>', $html);
        $this->assertStringContainsString('Já realizados <span class="badge bg-secondary ms-1">2</span>', $html);
        $this->assertStringContainsString('class="tab-pane fade show active" id="painel-proximos"', $html);

        // Sem próximos: abre em "Já realizados"
        DB::table('tbl_jogos')->delete();
        $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, -2);
        $html = preg_replace('/\s+/', ' ', $this->comoAdmin()->get($pagina)->getContent());
        $this->assertStringContainsString('class="nav-link active" id="aba-realizados"', $html);
        $this->assertStringContainsString('class="tab-pane fade show active" id="painel-realizados"', $html);

        // Amistosos usam as mesmas abas
        $this->comoAdmin()->get(route('admin.amistosos.times'))->assertOk()->assertSee('id="abasDeJogos"', false)->assertSee('Próximos amistosos');
    }

    public function test_cartao_do_jogo_mostra_so_os_escalados_daquele_time_naquele_jogo(): void
    {
        $verde = $this->time('Time Verde');
        $jogo  = $this->jogoNoDia(null, $this->azul, $verde, 3);
        $outro = $this->jogoNoDia(null, $this->azul, $verde, 9);
        $atleta = function (string $nome, string $status = 'ATIVO') {
            $id = $this->criarAtleta($this->nascidoComIdade(11), 'M', $status);
            DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);

            return $id;
        };
        $rui = $atleta('Rui Azul');            // do elenco do Azul, escalado
        $eva = $atleta('Eva Avulsa');          // fora do elenco, escalada no Azul
        $gil = $atleta('Gil Verde');           // escalado no Verde
        $ivo = $atleta('Ivo Sem Time');        // inscrito sem time
        $ana = $atleta('Ana Outro Jogo');      // escalada no Azul em outro jogo
        $zeca = $atleta('Zeca Inativo', 'INATIVO');
        DB::table('tbl_atleta_time')->insert([
            'id_time' => $this->azul, 'id_atleta' => $rui, 'camisa_atleta_time' => 9, 'posicao_atleta_time' => 'MEIA', 'status_atleta_time' => 'RESERVA',
        ]);
        foreach ([[$rui, $this->azul], [$eva, $this->azul], [$gil, $verde], [$ivo, null], [$zeca, $this->azul]] as [$id, $time]) {
            $jogo->evento->inscrever($id, 'INDIVIDUAL', null, $time, notificar: false);
        }
        $outro->evento->inscrever($ana, 'INDIVIDUAL', null, $this->azul, notificar: false);

        // Na tela de times, o cartão conta os escalados ativos de cada time naquele jogo
        $html = $this->comoAdmin()->get(route('admin.amistosos.times'))->getContent();
        $this->assertStringContainsString('2 escalados no jogo', $this->blocoDoJogo($html, $jogo));
        $this->assertStringContainsString('1 escalado no jogo', $this->blocoDoJogo($html, $jogo));
        $this->assertStringContainsString('1 escalado no jogo', $this->blocoDoJogo($html, $outro));

        // Jogadores do Azul nesse jogo: só os escalados nele, com os dados do elenco (ou "Fora do elenco")
        $resposta = $this->comoAdmin()->get(route('admin.jogos.times.show', [$jogo->id_jogo, $this->azul]))->assertOk();
        $this->assertEqualsCanonicalizing([$rui, $eva], $resposta->viewData('escalados')->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        $resposta->assertSee('Time Azul · jogadores do jogo')
            ->assertSee('Rui Azul')->assertSee('MEIA')->assertSee('Reserva')
            ->assertSee('Eva Avulsa')->assertSee('Fora do elenco')
            ->assertDontSee('Gil Verde')->assertDontSee('Ivo Sem Time')->assertDontSee('Ana Outro Jogo')->assertDontSee('Zeca Inativo')
            ->assertSee(route('admin.calendario.eventos.show', $jogo->id_evento), false)
            ->assertSee('<a href="' . route('admin.amistosos.times') . '" class="btn btn-sm btn-outline-secondary" title="Voltar"', false)
            ->assertSeeInOrder(['>Eventos</a>', '>Amistosos</a>', '<a href="' . route('admin.amistosos.times') . '">Jogos</a>', 'aria-current="page">Time Azul</li>'], false);
        $this->assertSame([route('admin.amistosos.times')], $this->linksAtivos($resposta));

        // Jogo de campeonato: voltar e caminho pela tela de jogos do campeonato, item ativo o "Jogos" dele
        $daCopa = $this->jogoNoDia($this->emAndamento, $this->azul, $verde, 6);
        $resposta = $this->comoAdmin()->get(route('admin.jogos.times.show', [$daCopa->id_jogo, $verde]))->assertOk()
            ->assertSeeInOrder(['>Campeonatos</a>', '>Taça Em Andamento</a>', '<a href="' . route('admin.campeonatos.times', $this->emAndamento) . '">Jogos</a>'], false);
        $this->assertSame([route('admin.campeonatos.index'), $this->itemJogosDosCampeonatos()], $this->linksAtivos($resposta));

        // Externo volta com aviso; time que não joga esse jogo, 404
        $comExterno = $this->jogoNoDia(null, $this->azul, $this->visitante, 7);
        $this->comoAdmin()->get(route('admin.jogos.times.show', [$comExterno->id_jogo, $this->visitante]))
            ->assertRedirect(route('admin.amistosos.times'))->assertSessionHas('erro');
        $this->comoAdmin()->get(route('admin.jogos.times.show', [$comExterno->id_jogo, $verde]))->assertNotFound();
    }

    public function test_consultas_da_barra_nao_crescem_com_mais_campeonatos(): void
    {
        $contar = function () {
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->comoAdmin()->get(route('admin.configuracoes.index'))->assertOk();

            return $n;
        };

        $this->jogoNoDia($this->emAndamento, $this->azul, $this->visitante, 2);
        $um = $contar();

        // Mais 5 campeonatos em andamento, cada um com jogos passados e futuros
        for ($i = 1; $i <= 5; $i++) {
            $id = DB::table('tbl_campeonato')->insertGetId([
                'id_categoria' => $this->idSub11M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
                'nome_campeonato' => "Taça Extra {$i}", 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
                'data_inicio_campeonato' => now()->subDay()->toDateString(), 'data_fim_campeonato' => now()->addDay()->toDateString(),
                'local_evento' => 'Quadra', 'status_campeonato' => 'ATIVO',
            ]);
            $this->jogoNoDia($id, $this->azul, $this->visitante, -$i);
            $this->jogoNoDia($id, $this->visitante, $this->azul, $i);
        }

        $this->assertSame($um, $contar());
    }

    public function test_tela_de_campeonatos_filtrada_mostra_so_aquele_e_ver_todos_mostra_todos(): void
    {
        $filtrada = route('admin.campeonatos.index', ['campeonato' => $this->emAndamento]);

        // Só o campeonato do filtro, com o aviso e o jeito de voltar a ver todos; os botões da linha seguem
        $resposta = $this->comoAdmin()->get($filtrada)->assertOk();
        $this->assertSame([$this->emAndamento], $resposta->viewData('campeonatos')->pluck('id_campeonato')->all());
        $resposta->assertSee('Mostrando só o campeonato <strong>Taça Em Andamento</strong>', false)
            ->assertSee('<a href="' . route('admin.campeonatos.index') . '" class="ms-auto btn btn-sm btn-outline-secondary">', false)
            ->assertSee('1 campeonato(s)')
            ->assertSee('<a href="' . route('admin.campeonatos.times', $this->emAndamento) . '"', false)   // Jogos (tela com o filtro nele)
            ->assertSee('data-bs-target="#modalEditarCampeonato"', false)                                  // editar
            ->assertSee(route('admin.campeonatos.toggleStatus', $this->emAndamento), false)               // ocultar
            ->assertDontSee('<span class="fw-semibold">Taça Encerrada</span>', false);

        // "Ver todos" (sem filtro): todos, sem o aviso
        $todos = $this->comoAdmin()->get(route('admin.campeonatos.index'))->assertOk();
        $this->assertCount(3, $todos->viewData('campeonatos'));
        $todos->assertDontSee('Mostrando só o campeonato')->assertSee('<span class="fw-semibold">Taça Encerrada</span>', false);

        // ID inexistente ou inválido: sem erro, lista todos
        foreach ([999999, 'abc', ''] as $invalido) {
            $resposta = $this->comoAdmin()->get(route('admin.campeonatos.index', ['campeonato' => $invalido]))->assertOk();
            $this->assertCount(3, $resposta->viewData('campeonatos'));
            $resposta->assertDontSee('Mostrando só o campeonato');
        }
    }

    // ---------- ramos e filtros do Calendário ----------

    public function test_ramos_separam_os_eventos_pelo_tipo(): void
    {
        $ids = [
            'campeonato' => $this->evento('CAMPEONATO')->id_evento_calendario,
            'jogoCopa'   => $this->jogo($this->emAndamento)->id_evento,
            'amistoso'   => $this->jogo(null)->id_evento,
            'treino'     => $this->evento('TREINO')->id_evento_calendario,
            'avaliacao'  => $this->evento('AVALIACAO')->id_evento_calendario,
            'reuniao'    => $this->evento('REUNIAO')->id_evento_calendario,
            'festa'      => $this->evento('CONFRATERNIZACAO')->id_evento_calendario,
            'evento'     => $this->evento('EVENTO')->id_evento_calendario,
            // Com categoria (ex.: reunião de pais da Sub-11): não é individual, fica em Outros
            'reuniaoCat' => $this->evento('REUNIAO', ['id_categoria' => $this->idSub11M])->id_evento_calendario,
            'avaliaCat'  => $this->evento('AVALIACAO', ['id_categoria' => $this->idSub11M])->id_evento_calendario,
            'jogoAntigo' => $this->evento('JOGO')->id_evento_calendario, // JOGO sem tbl_jogos: só no Calendário
        ];

        $esperado = [
            'campeonatos' => ['campeonato', 'jogoCopa'],
            'amistosos'   => ['amistoso'],
            'treinos'     => ['treino'],
            // Individuais = avaliação, reunião e evento SEM categoria (o técnico escolhe os atletas)
            'individuais' => ['avaliacao', 'reuniao', 'evento'],
            'outros'      => ['festa', 'reuniaoCat', 'avaliaCat'],
        ];

        foreach ($esperado as $ramo => $chaves) {
            $lista = $this->idsDaLista(['ramo' => $ramo]);
            $this->assertEqualsCanonicalizing(array_map(fn ($c) => $ids[$c], $chaves), $lista, "Ramo {$ramo}");

            // A mesma regra marca o ramo de cada evento (item ativo na tela do evento)
            foreach ($chaves as $chave) {
                $this->assertSame($ramo, EventoCalendario::with('jogo')->find($ids[$chave])->ramo());
            }
        }

        $this->assertEqualsCanonicalizing(array_values($ids), $this->idsDaLista());
        $this->assertNull(EventoCalendario::find($ids['jogoAntigo'])->ramo());
    }

    // ---------- evento individual: o técnico descreve e escolhe os atletas ----------

    public function test_evento_individual_inscreve_so_os_escolhidos_e_avisa_com_a_descricao(): void
    {
        $ana = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $bia = $this->criarAtleta($this->nascidoComIdade(12), 'F', 'ATIVO');
        $rui = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');   // não escolhido
        $inativo = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'INATIVO');
        $descricao = 'Exame de sangue. Venha em jejum de 8 horas e traga um documento com foto. '
            . 'O resultado sai em uma semana e será enviado ao departamento médico do clube para avaliação.';

        // O botão e o formulário ficam na lista de Individuais (com os atletas ativos para escolher)
        $pagina = $this->comoAdmin()->get(route('admin.calendario.index', ['ramo' => 'individuais']))->assertOk()
            ->assertSee('data-bs-target="#modalEventoIndividual"', false);
        // Caixa com uma caixa de marcar por atleta ativo (agrupada por categoria, com busca)
        $pagina->assertSee('id="ind_atleta_' . $ana . '"', false)
            ->assertSee('id="ind_busca"', false)
            ->assertSee('js-marcar-grupo', false)
            ->assertDontSee('id="ind_atleta_' . $inativo . '"', false);
        $this->assertMatchesRegularExpression('#<input class="form-check-input js-check-atleta" type="checkbox" name="atletas\[\]"\s+value="'
            . $ana . '"#', $pagina->getContent());
        $this->comoAdmin()->get(route('admin.calendario.index'))->assertDontSee('id="modalEventoIndividual"', false);

        $dados = [
            'tipo_evento_calendario' => 'AVALIACAO', 'subtipo_evento_calendario' => 'Exame médico',
            'titulo_evento_calendario' => 'Exame de sangue', 'data_evento_calendario' => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '08:00', 'local_evento_calendario' => 'Clínica São Lucas',
            'descricao_evento_calendario' => $descricao, 'atletas' => [$ana, $bia],
            'id_categoria' => $this->idSub11M, // ignorado: evento individual é sem categoria
        ];
        $resposta = $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), $dados);

        $evento = EventoCalendario::where('titulo_evento_calendario', 'Exame de sangue')->sole();
        $resposta->assertRedirect(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertSessionHas('sucesso', 'Evento individual criado. 2 atleta(s) inscrito(s). 2 atleta(s) notificado(s).');
        $this->assertNull($evento->id_categoria);
        $this->assertSame('individuais', $evento->ramo());
        $this->assertEqualsCanonicalizing([$ana, $bia], $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['INDIVIDUAL'], $evento->inscricoes()->distinct()->pluck('origem_evento_atleta')->all());

        // Notificação de cada escolhido com a descrição resumida (o texto inteiro fica na agenda)
        $mensagem = \App\Models\Notificacao::where('id_atleta', $ana)->sole()->mensagem_notificacao;
        $this->assertStringStartsWith('Exame de sangue · ', $mensagem);
        $this->assertStringContainsString(' — Exame de sangue. Venha em jejum', $mensagem);
        $this->assertStringEndsWith('…', $mensagem);
        $this->assertSame(0, \App\Models\Notificacao::where('id_atleta', $rui)->count());

        // Aparece na lista de Individuais
        $this->assertContains($evento->id_evento_calendario, $this->idsDaLista(['ramo' => 'individuais', 'mes' => $evento->data_evento_calendario->format('Y-m')]));
    }

    public function test_evento_individual_valida_tipo_e_atletas(): void
    {
        $ana = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $inativo = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'INATIVO');
        $base = [
            'tipo_evento_calendario' => 'REUNIAO', 'titulo_evento_calendario' => 'Conversa com a família',
            'data_evento_calendario' => now()->addWeek()->toDateString(), '_formulario' => 'individual',
        ];

        $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), [...$base, 'atletas' => []])
            ->assertSessionHasErrors(['atletas' => 'Escolha pelo menos um atleta.']);
        $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), [...$base, 'atletas' => [$inativo]])
            ->assertSessionHasErrors(['atletas.0' => 'Escolha só atletas ativos.']);
        $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), [...$base, 'tipo_evento_calendario' => 'TREINO', 'atletas' => [$ana]])
            ->assertSessionHasErrors(['tipo_evento_calendario' => 'Evento individual: escolha Avaliação, Reunião ou Evento.']);
        $this->assertSame(0, EventoCalendario::count());

        // Reunião sem descrição: a notificação fica sem o recado
        $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), [...$base, 'atletas' => [$ana]])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString(' — ', \App\Models\Notificacao::where('id_atleta', $ana)->sole()->mensagem_notificacao);
    }

    public function test_evento_individual_confere_conflito_de_horario(): void
    {
        $ana = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $dia = now()->addDays(10)->toDateString();
        $treino = $this->evento('TREINO', ['data_evento_calendario' => $dia, 'horario_inicio_evento_calendario' => '08:00:00', 'horario_fim_evento_calendario' => '09:30:00']);
        $treino->inscrever($ana, 'INDIVIDUAL', null, notificar: false);

        $dados = [
            'tipo_evento_calendario' => 'AVALIACAO', 'titulo_evento_calendario' => 'Teste físico',
            'data_evento_calendario' => $dia, 'horario_inicio_evento_calendario' => '09:00', 'atletas' => [$ana],
        ];
        $this->comoAdmin()->from(route('admin.calendario.index', ['ramo' => 'individuais']))
            ->post(route('admin.calendario.eventos.individual'), $dados)
            ->assertSessionHas('conflitos_pendentes');
        $this->assertSame(0, EventoCalendario::where('titulo_evento_calendario', 'Teste físico')->count());

        $this->comoAdmin()->post(route('admin.calendario.eventos.individual'), [...$dados, 'confirmar_conflito' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, EventoCalendario::where('titulo_evento_calendario', 'Teste físico')->count());
    }

    public function test_lista_abre_sem_os_ocultos_e_o_filtro_oculto_mostra_so_eles(): void
    {
        $ativo     = $this->evento('TREINO')->id_evento_calendario;
        $cancelado = $this->evento('TREINO', ['status_evento_calendario' => 'CANCELADO'])->id_evento_calendario;
        $oculto    = $this->evento('TREINO', ['status_evento_calendario' => 'INATIVO'])->id_evento_calendario;

        $this->assertEqualsCanonicalizing([$ativo, $cancelado], $this->idsDaLista());
        $this->assertSame([$oculto], $this->idsDaLista(['situacao' => 'INATIVO']));
        $this->assertSame([$cancelado], $this->idsDaLista(['situacao' => 'CANCELADO']));
        $this->assertSame([$ativo], $this->idsDaLista(['situacao' => 'ATIVO']));

        $this->comoAdmin()->get(route('admin.calendario.index'))
            ->assertSee('1 oculto(s) fora da lista')
            ->assertSee(e(route('admin.calendario.index', ['mes' => now()->format('Y-m'), 'situacao' => 'INATIVO'])), false);
    }

    public function test_filtros_da_url_vem_marcados_e_convivem_com_o_mes(): void
    {
        $mes = now()->format('Y-m');
        $anterior = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');

        $resposta = $this->comoAdmin()->get(route('admin.calendario.index', [
            'mes' => $mes, 'ramo' => 'treinos', 'tipo' => 'TREINO', 'origem' => 'grade', 'situacao' => 'CANCELADO',
        ]))->assertOk();

        $resposta->assertSee('<option value="treinos" selected>', false)
            ->assertSee('<option value="TREINO" selected>', false)
            ->assertSee('<option value="grade" selected>', false)
            ->assertSee('<option value="CANCELADO" selected>', false)
            ->assertSee('class="collapse show" id="filterPanel"', false)
            // Setas do mês e o select levam os filtros; o formulário dos filtros leva o mês
            ->assertSee(e(route('admin.calendario.index', ['mes' => $anterior, 'ramo' => 'treinos', 'tipo' => 'TREINO', 'origem' => 'grade', 'situacao' => 'CANCELADO'])), false)
            ->assertSee('<input type="hidden" name="ramo" value="treinos">', false)
            ->assertSee('<input type="hidden" name="mes" value="' . $mes . '">', false);

        // Sem filtro: painel fechado; valor inválido é ignorado
        $this->comoAdmin()->get(route('admin.calendario.index', ['ramo' => 'xyz', 'situacao' => 'ABC']))
            ->assertOk()
            ->assertSee('class="collapse " id="filterPanel"', false)
            ->assertDontSee('<option value="treinos" selected>', false);
    }

    public function test_filtro_de_tipo_e_origem(): void
    {
        $treino  = $this->evento('TREINO')->id_evento_calendario;
        $reuniao = $this->evento('REUNIAO')->id_evento_calendario;

        $this->assertSame([$reuniao], $this->idsDaLista(['tipo' => 'REUNIAO']));
        $this->assertEqualsCanonicalizing([$treino, $reuniao], $this->idsDaLista(['origem' => 'manual']));
        $this->assertSame([], $this->idsDaLista(['origem' => 'grade']));
    }

    public function test_cancelar_pela_lista_volta_com_os_filtros(): void
    {
        $treino = $this->evento('TREINO');
        $lista  = route('admin.calendario.index', ['ramo' => 'treinos', 'mes' => now()->format('Y-m')]);

        $this->comoAdmin()->from($lista)
            ->patch(route('admin.calendario.eventos.cancelar', $treino->id_evento_calendario))
            ->assertRedirect($lista);
    }

    // ---------- filtros de Jogos ----------

    public function test_jogos_filtram_por_campeonato_e_abrem_sem_os_ocultos(): void
    {
        $daCopa   = $this->jogo($this->emAndamento)->id_jogo;
        $amistoso = $this->jogo(null)->id_jogo;
        $oculto   = $this->jogo($this->emAndamento, 'INATIVO')->id_jogo;

        $ids = fn (array $filtros = []) => $this->comoAdmin()->get(route('admin.jogos.index', $filtros))
            ->assertOk()->viewData('jogos')->pluck('id_jogo')->all();

        $this->assertEqualsCanonicalizing([$daCopa, $amistoso], $ids());
        $this->assertSame([$daCopa], $ids(['campeonato' => $this->emAndamento]));
        $this->assertSame([$amistoso], $ids(['campeonato' => 'amistoso']));
        $this->assertSame([$oculto], $ids(['situacao' => 'INATIVO']));
        $this->assertSame([], $ids(['campeonato' => $this->encerrado]));

        $this->comoAdmin()->get(route('admin.jogos.index', ['campeonato' => $this->emAndamento]))
            ->assertSee('<option value="' . $this->emAndamento . '" selected>', false)
            ->assertSee('class="collapse show" id="filterPanel"', false)
            ->assertSee('1 oculto(s) fora da lista');

        $this->comoAdmin()->get(route('admin.jogos.index', ['campeonato' => 'amistoso', 'situacao' => 'INATIVO']))
            ->assertSee('<option value="amistoso" selected>', false)
            ->assertSee('<option value="INATIVO" selected>', false);
    }

    // ---------- rotas sem página e elenco ----------

    public function test_nenhuma_pagina_get_do_admin_da_erro(): void
    {
        $evento = $this->evento('TREINO');
        $jogoDaCopa = $this->jogo($this->emAndamento);
        $atleta = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'PENDENTE');
        $parametros = [
            'admin.calendario.eventos.show' => $evento->id_evento_calendario,
            'admin.campeonatos.edit'        => $this->emAndamento,
            'admin.campeonatos.times'       => $this->emAndamento,
            'admin.campeonatos.times.show'  => [$this->emAndamento, $this->azul],
            'admin.jogos.times.show'        => [$jogoDaCopa->id_jogo, $this->azul],
            'admin.times.elenco'            => $this->azul,
            'admin.matriculas.show'         => $atleta,
        ];

        $rotas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && str_starts_with($r->uri(), 'admin') && $r->getName() !== 'admin.login');

        foreach ($rotas as $rota) {
            $nome = $rota->getName();
            $temParametro = str_contains($rota->uri(), '{');
            $this->assertTrue(! $temParametro || array_key_exists($nome, $parametros), "Rota {$nome} sem parâmetro no teste");

            $status = $this->comoAdmin()->get(route($nome, $parametros[$nome] ?? []))->status();
            $this->assertLessThan(500, $status, "Rota {$nome}");
        }
    }

    public function test_rotas_create_edit_show_sem_pagina_nao_existem(): void
    {
        foreach (['times', 'categorias', 'banners', 'noticias', 'galeria'] as $recurso) {
            $this->assertFalse(Route::has("admin.{$recurso}.create"), $recurso);
            $this->assertFalse(Route::has("admin.{$recurso}.edit"), $recurso);
            $this->assertFalse(Route::has("admin.{$recurso}.show"), $recurso);
        }
        foreach (['admin.atletas.create', 'admin.atletas.edit', 'admin.atletas.show', 'admin.campeonatos.show',
                  'admin.escalacao.index', 'admin.escalacao.show'] as $nome) {
            $this->assertFalse(Route::has($nome), $nome);
        }

        // A URL casa com o PUT/DELETE de times/{time}: método não permitido, não mais erro 500
        $this->comoAdmin()->get('/admin/times/create')->assertMethodNotAllowed();
        $this->comoAdmin()->get('/admin/escalacao')->assertNotFound();
    }

    public function test_elenco_abre_pela_linha_do_time_interno(): void
    {
        $this->comoAdmin()->get(route('admin.times.index'))
            ->assertOk()
            ->assertSee(route('admin.times.elenco', $this->azul), false)
            ->assertDontSee(route('admin.times.elenco', $this->visitante), false);

        $this->comoAdmin()->get(route('admin.times.elenco', $this->azul))
            ->assertOk()
            ->assertSee('Elenco · Time Azul');

        $this->comoAdmin()->get(route('admin.times.elenco', $this->visitante))
            ->assertRedirect(route('admin.times.index'))
            ->assertSessionHas('erro');
    }

    // ---------- exclusões bloqueadas ----------

    public function test_time_com_jogo_nao_e_excluido(): void
    {
        $this->jogo(null);
        DB::table('tbl_time')->where('id_time', $this->azul)->update(['status_time' => 'INATIVO']);

        $this->comoAdmin()->delete(route('admin.times.destroy', $this->azul))
            ->assertRedirect(route('admin.times.index'))
            ->assertSessionHas('erro', 'O time Time Azul tem 1 jogo(s) e não pode ser excluído. Mantenha-o inativo.');
        $this->assertDatabaseHas('tbl_time', ['id_time' => $this->azul]);

        $this->comoAdmin()->followingRedirects()->delete(route('admin.times.destroy', $this->visitante))
            ->assertSee('tem 1 jogo(s) e não pode ser excluído');
    }

    public function test_time_com_elenco_nao_e_excluido_e_sem_nada_sai_com_os_campeonatos(): void
    {
        $atleta = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        DB::table('tbl_atleta_time')->insert(['id_time' => $this->azul, 'id_atleta' => $atleta, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => '']);

        $this->comoAdmin()->delete(route('admin.times.destroy', $this->azul))
            ->assertSessionHas('erro', 'O time Time Azul tem 1 atleta(s) no elenco. Tire-os do time na edição de cada atleta antes de excluir.');
        $this->assertDatabaseHas('tbl_time', ['id_time' => $this->azul]);

        // Sem jogo nem elenco: sai, junto com a participação nos campeonatos
        DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $this->emAndamento, 'id_time' => $this->visitante]);
        $this->comoAdmin()->delete(route('admin.times.destroy', $this->visitante))->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('tbl_time', ['id_time' => $this->visitante]);
        $this->assertDatabaseMissing('tbl_campeonato_time', ['id_time' => $this->visitante]);
    }

    public function test_campeonato_com_jogo_nao_e_excluido(): void
    {
        $this->jogo($this->emAndamento);

        $this->comoAdmin()->delete(route('admin.campeonatos.destroy', $this->emAndamento))
            ->assertRedirect(route('admin.campeonatos.index'))
            ->assertSessionHas('erro', 'O campeonato Taça Em Andamento tem 1 jogo(s) e não pode ser excluído. Inative-o para tirá-lo do site.');
        $this->assertDatabaseHas('tbl_campeonato', ['id_campeonato' => $this->emAndamento]);

        $this->comoAdmin()->followingRedirects()->delete(route('admin.campeonatos.destroy', $this->emAndamento))
            ->assertSee('tem 1 jogo(s) e não pode ser excluído');

        // Sem jogos: sai, junto com os times dele
        DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $this->encerrado, 'id_time' => $this->azul]);
        $this->comoAdmin()->delete(route('admin.campeonatos.destroy', $this->encerrado))->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('tbl_campeonato', ['id_campeonato' => $this->encerrado]);
    }

    public function test_categoria_em_uso_nao_e_excluida(): void
    {
        $this->comoAdmin()->delete(route('admin.categorias.destroy', $this->idSub11M))
            ->assertRedirect(route('admin.categorias.index'))
            ->assertSessionHas('erro', 'A categoria Sub-11 Masculino está em uso (2 time(s) e 3 campeonato(s)) e não pode ser excluída. Mantenha-a inativa.');
        $this->assertDatabaseHas('tbl_categoria', ['id_categoria' => $this->idSub11M]);

        // Sem uso: sai
        $livre = $this->idCategoria('Sub-17', 'F');
        $this->comoAdmin()->delete(route('admin.categorias.destroy', $livre))->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('tbl_categoria', ['id_categoria' => $livre]);
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'admin');
    }

    // Evento de hoje (sempre no mês aberto pela lista)
    private function evento(string $tipo, array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, array_merge([
            'titulo_evento_calendario'         => "Evento {$tipo}",
            'tipo_evento_calendario'           => $tipo,
            'data_evento_calendario'           => now()->toDateString(),
            'horario_inicio_evento_calendario' => '23:59:00', // ainda não concluído hoje
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra), inscreverCategoria: false);
    }

    // Jogo de hoje (Time Azul x Time Visitante); sem campeonato = amistoso
    private function jogo(?int $idCampeonato, string $status = 'ATIVO'): Jogo
    {
        $evento = $this->evento('JOGO', ['titulo_evento_calendario' => 'Time Azul x Time Visitante', 'status_evento_calendario' => $status]);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $idCampeonato,
            'id_time_casa' => $this->azul, 'id_time_visitante' => $this->visitante,
        ]);
    }

    // Jogo do campeonato $dias a partir de hoje (0 = hoje, às 23:59, ainda não concluído)
    private function jogoNoDia(?int $idCampeonato, int $casa, int $visitante, int $dias, string $status = 'ATIVO'): Jogo
    {
        $evento = $this->evento('JOGO', [
            'titulo_evento_calendario' => Jogo::tituloPara($casa, $visitante), 'status_evento_calendario' => $status,
            'data_evento_calendario' => now()->addDays($dias)->toDateString(),
        ]);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $idCampeonato,
            'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
        ]);
    }

    // Lista de Jogos só com o jogo (o link do jogo no menu)
    private function listaDoJogo(Jogo $jogo): string
    {
        return route('admin.jogos.index', ['campeonato' => $jogo->id_campeonato, 'jogo' => $jogo->id_jogo]);
    }

    // HTML do bloco de um jogo nas telas de times por jogo (até o bloco seguinte)
    private function blocoDoJogo(string $html, Jogo $jogo): string
    {
        preg_match('#data-id-jogo="' . $jogo->id_jogo . '">.*?(?=data-id-jogo=|</main>)#s', $html, $bloco);

        return $bloco[0] ?? '';
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO',
        ]);
    }

    // [texto, destino] dos subitens do campeonato em andamento (Taça Em Andamento) na barra
    // Links dentro da gaveta Campeonatos ([texto, href]), na ordem da barra
    private function subitensDaGaveta(TestResponse $resposta): array
    {
        $subitens = [];
        foreach ($this->xpathDaBarra($resposta)->query('//a[@id="gavetaCampeonatos"]/following-sibling::ul//a') as $link) {
            /** @var \DOMElement $link */
            $subitens[] = [trim(preg_replace('/\s+/', ' ', $link->textContent)), $link->getAttribute('href')];
        }

        return $subitens;
    }

    // Lista de Jogos filtrada pelo campeonato em andamento: o "Jogos" dele dentro de Campeonatos
    // Lista de Jogos filtrada pelo campeonato em andamento (fora do menu)
    private function jogosDoEmAndamento(): string
    {
        return route('admin.jogos.index', ['campeonato' => $this->emAndamento]);
    }

    // O "Jogos" dentro de Campeonatos no menu: os jogos de todos os campeonatos (filtro "Todos")
    private function itemJogosDosCampeonatos(): string
    {
        return route('admin.campeonatos.jogos');
    }

    // Subitens do "Jogos" de Campeonatos (não tem)
    private function subitensDoCampeonato(string $barra): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $barra);
        $xpath = new \DOMXPath($dom);

        $subitens = [];
        foreach ($xpath->query('//a[@id="itemJogosCampeonatos"]/following-sibling::ul//a') as $link) {
            /** @var \DOMElement $link */
            $subitens[] = [trim(preg_replace('/\s+/', ' ', $link->textContent)), $link->getAttribute('href')];
        }

        return $subitens;
    }

    private function idsDaLista(array $filtros = []): array
    {
        return $this->comoAdmin()->get(route('admin.calendario.index', $filtros))
            ->assertOk()->viewData('eventos')->pluck('id_evento_calendario')->all();
    }

    // HTML da barra lateral
    private function barra(TestResponse $resposta): string
    {
        preg_match('#<aside class="app-sidebar.*?</aside>#s', $resposta->getContent(), $m);

        return $m[0] ?? '';
    }

    private function xpathDaBarra(TestResponse $resposta): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $this->barra($resposta));

        return new \DOMXPath($dom);
    }

    // Links da barra que levam a uma página do admin
    private function linksDaBarra(TestResponse $resposta): array
    {
        $links = [];
        /** @var \DOMElement $a */
        foreach ($this->xpathDaBarra($resposta)->query('//a[@href]') as $a) {
            $href = $a->getAttribute('href');
            if (str_starts_with($href, url('/admin'))) {
                $links[] = $href;
            }
        }

        return array_values(array_unique($links));
    }

    // Links marcados como ativos (sem os ramos que só abrem/fecham, como "Eventos")
    private function linksAtivos(TestResponse $resposta): array
    {
        $ativos = [];
        /** @var \DOMElement $a */
        foreach ($this->xpathDaBarra($resposta)->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' active ')]") as $a) {
            if ($a->getAttribute('href') !== 'javascript:void(0)') {
                $ativos[] = $a->getAttribute('href');
            }
        }

        return $ativos;
    }
}
