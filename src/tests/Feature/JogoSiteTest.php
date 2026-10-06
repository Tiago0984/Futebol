<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 6, Etapa 2: site, API e dashboard leem data, horário, local e status do evento do jogo.
 */
class JogoSiteTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idCampeonato;
    private int $azul;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $idSub11M = $this->idCategoria('Sub-11', 'M');
        $this->idCampeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idSub11M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa Escola', 'organizador_campeonato' => 'Escola', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => now()->subMonth()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Quadra A',
        ]);

        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
        $this->azul      = $time('Time Azul', 'INTERNO');
        $this->verde     = $time('Time Verde', 'INTERNO');
        $this->visitante = $time('Time Visitante', 'EXTERNO');
    }

    // ---------- classificação ----------

    public function test_classificacao_conta_so_jogos_com_placar_e_nao_cancelados(): void
    {
        $this->jogo(-10, $this->azul, $this->visitante, [2, 1]);            // Azul vence
        $this->jogo(-5, $this->verde, $this->visitante, [1, 1]);            // empate
        $this->jogo(-3, $this->azul, $this->verde, [0, 5], 'CANCELADO');    // cancelado: não conta
        $this->jogo(-2, $this->verde, $this->azul, [3, 0], 'INATIVO');      // oculto: não conta
        $this->jogo(+5, $this->azul, $this->verde);                         // futuro sem placar: não é 0×0

        $jogos = Jogo::with(['evento', 'timeCasa', 'timeVisitante'])->get();
        $tabela = collect(Jogo::classificacao($jogos))->keyBy('nome');

        // Azul lidera; Verde e Visitante empatam em 1 ponto (a ordem é só por pontos)
        $this->assertSame('Time Azul', $tabela->keys()->first());
        $this->assertEqualsCanonicalizing(['Time Azul', 'Time Verde', 'Time Visitante'], $tabela->keys()->all());
        $this->assertSame(['pj' => 1, 'v' => 1, 'e' => 0, 'd' => 0, 'gm' => 2, 'gc' => 1, 'pontos' => 3],
            collect($tabela['Time Azul'])->only(['pj', 'v', 'e', 'd', 'gm', 'gc', 'pontos'])->all());
        $this->assertSame(1, $tabela['Time Verde']['pontos']);
        $this->assertSame(1, $tabela['Time Verde']['pj']);
        $this->assertSame(1, $tabela['Time Visitante']['pontos']); // 1 derrota e 1 empate
        $this->assertSame(2, $tabela['Time Visitante']['pj']);
    }

    public function test_desempate_por_vitorias_saldo_gols_marcados_e_nome(): void
    {
        [$o1, $o2, $o3] = [$this->novoTime('Oponente 1'), $this->novoTime('Oponente 2'), $this->novoTime('Oponente 3')];

        // Vitórias: os dois com 3 pontos; Zebra (1 vitória) passa Abelha (3 empates)
        $zebra = $this->novoTime('Zebra'); $abelha = $this->novoTime('Abelha');
        $this->assertNaFrente('Zebra', 'Abelha', [
            $this->jogo(-9, $zebra, $o1, [1, 0]), $this->jogo(-8, $zebra, $o2, [0, 1]),
            $this->jogo(-7, $abelha, $o1, [0, 0]), $this->jogo(-6, $abelha, $o2, [0, 0]), $this->jogo(-5, $abelha, $o3, [0, 0]),
        ]);

        // Saldo: 3 pontos e 1 vitória cada; Zulu (+3) passa Alfa (+1)
        $zulu = $this->novoTime('Zulu'); $alfa = $this->novoTime('Alfa');
        $this->assertNaFrente('Zulu', 'Alfa', [$this->jogo(-9, $zulu, $o1, [3, 0]), $this->jogo(-8, $alfa, $o2, [1, 0])]);

        // Gols marcados: mesmo saldo (+1); Xis (3 gols) passa Bravo (1 gol)
        $xis = $this->novoTime('Xis'); $bravo = $this->novoTime('Bravo');
        $this->assertNaFrente('Xis', 'Bravo', [$this->jogo(-9, $xis, $o1, [3, 2]), $this->jogo(-8, $bravo, $o2, [1, 0])]);

        // Tudo igual: nome em ordem alfabética
        $beta = $this->novoTime('Beta'); $agua = $this->novoTime('Agua');
        $this->assertNaFrente('Agua', 'Beta', [$this->jogo(-9, $beta, $o1, [1, 0]), $this->jogo(-8, $agua, $o2, [1, 0])]);
    }

    public function test_desempate_por_nome_ignora_acentos_e_maiusculas(): void
    {
        $o1 = $this->novoTime('Oponente 1');
        $o2 = $this->novoTime('Oponente 2');
        $o3 = $this->novoTime('Oponente 3');

        // Byte a byte, "Águias" e "águia" ficariam depois de "Zebra"; sem acento e minúsculas, vêm antes
        $zebra  = $this->novoTime('Zebra');
        $aguias = $this->novoTime('Águias');
        $baixa  = $this->novoTime('ébano');
        $jogos  = [
            $this->jogo(-9, $zebra, $o1, [1, 0]),
            $this->jogo(-8, $aguias, $o2, [1, 0]),
            $this->jogo(-7, $baixa, $o3, [1, 0]),
        ];

        $this->assertNaFrente('Águias', 'ébano', $jogos);
        $this->assertNaFrente('ébano', 'Zebra', $jogos);
    }

    // ---------- home ----------

    public function test_proximo_jogo_e_o_proximo_ativo_com_data_horario_e_local_do_evento(): void
    {
        $this->jogo(+2, $this->verde, $this->visitante, null, 'CANCELADO'); // cancelado não é "próximo"
        $this->jogo(+4, $this->azul, $this->visitante, null, 'ATIVO', ['local_evento_calendario' => 'Campo Novo', 'horario_inicio_evento_calendario' => '15:30:00']);
        $this->jogo(+9, $this->verde, $this->azul);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['COPA ESCOLA', 'TIME AZUL', now()->addDays(4)->format('d M') . ', 15:30', 'CAMPO NOVO', 'TIME VISITANTE']);
    }

    // Destaque da home com a regra da agenda do site: só jogo de campeonato
    public function test_proximo_jogo_ignora_amistoso(): void
    {
        $this->jogo(+1, $this->verde, $this->azul, null, 'ATIVO', [], amistoso: true);   // mais perto, mas amistoso
        $jogo = $this->jogo(+5, $this->azul, $this->visitante);

        $this->get('/')->assertOk()
            ->assertViewHas('proximoJogo', fn ($proximo) => $proximo->id_jogo === $jogo->id_jogo)
            ->assertSee('COPA ESCOLA')
            ->assertDontSee('AMISTOSO');
    }

    public function test_sem_jogo_futuro_mostra_o_ultimo_de_campeonato_visivel(): void
    {
        $this->jogo(-8, $this->verde, $this->visitante, [1, 0]);
        $this->jogo(-4, $this->azul, $this->visitante, [3, 2]);
        $this->jogo(-2, $this->verde, $this->azul, [5, 5], 'ATIVO', [], amistoso: true); // amistoso não aparece
        $this->jogo(-1, $this->azul, $this->verde, [9, 9], 'INATIVO');                   // oculto não aparece
        $this->jogo(+3, $this->azul, $this->verde, null, 'ATIVO', [], amistoso: true);   // amistoso futuro também não

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['TIME AZUL', '<span class="lp-score-num">3</span>', 'TIME VISITANTE'], false)
            ->assertDontSee('<span class="lp-score-num">9</span>', false)
            ->assertDontSee('<span class="lp-score-num">5</span>', false)
            ->assertDontSee('AMISTOSO');
    }

    public function test_sem_jogo_de_campeonato_o_destaque_da_home_nao_quebra(): void
    {
        $this->jogo(+2, $this->azul, $this->verde, null, 'ATIVO', [], amistoso: true);
        $this->jogo(-2, $this->verde, $this->azul, [1, 1], 'ATIVO', [], amistoso: true);

        $this->get('/')->assertOk()
            ->assertViewHas('proximoJogo', null)
            ->assertSee('LIGA PREMIERE')
            ->assertDontSee('AMISTOSO');
    }

    public function test_abas_da_home_escondem_oculto_e_marcam_cancelado(): void
    {
        $this->jogo(-6, $this->azul, $this->visitante, [2, 1]);
        $this->jogo(-3, $this->verde, $this->visitante, null, 'CANCELADO');
        $this->jogo(-2, $this->verde, $this->azul, [7, 7], 'INATIVO');

        $this->get('/')->assertOk()
            ->assertSee('2 × 1')
            ->assertSee('<span class="event-selo-cancelado"><i class="fa fa-ban"></i> Cancelado</span>', false)
            ->assertDontSee('7 × 7');
    }

    // ---------- página do campeonato ----------

    public function test_pagina_do_campeonato_ordena_pela_data_do_evento_e_esconde_oculto(): void
    {
        $this->jogo(+6, $this->verde, $this->azul, null, 'ATIVO', [], data: '2099-03-10');
        $this->jogo(+2, $this->azul, $this->visitante, [2, 0], 'ATIVO', [], data: '2099-01-20');
        $this->jogo(+4, $this->verde, $this->visitante, null, 'CANCELADO', [], data: '2099-02-15');
        $this->jogo(+8, $this->azul, $this->verde, [5, 5], 'INATIVO');

        $this->get(route('campeonato.show', $this->idCampeonato))->assertOk()
            ->assertSee('3 jogos registrados')
            ->assertSeeInOrder(['20', 'Jan', '2099', '15', 'Feb', '2099', 'Cancelado', '10', 'Mar', '2099'])
            ->assertDontSee('<span class="score-value">5</span>', false);
    }

    // ---------- API ----------

    public function test_api_mantem_data_jogo_e_traz_horario_local_e_status_do_evento(): void
    {
        $this->jogo(+5, $this->verde, $this->azul, null, 'CANCELADO', ['horario_inicio_evento_calendario' => '10:00:00'], data: '2099-05-02');
        $this->jogo(+3, $this->azul, $this->visitante, [1, 0], 'ATIVO', ['horario_inicio_evento_calendario' => '19:00:00', 'horario_fim_evento_calendario' => '20:30:00'], data: '2099-05-01');
        $this->jogo(+1, $this->azul, $this->verde, null, 'INATIVO');

        $jogos = $this->getJson("/api/v1/campeonatos/{$this->idCampeonato}")->assertOk()->json('data.jogos');

        $this->assertCount(2, $jogos); // oculto não vai
        $this->assertSame('2099-05-01T19:00:00-03:00', $jogos[0]['data_jogo']); // hora local com o deslocamento
        $this->assertSame('19:00 às 20:30', $jogos[0]['horario_jogo']);
        $this->assertSame('Quadra A', $jogos[0]['local_jogo']);
        $this->assertSame('ATIVO', $jogos[0]['status_jogo']);
        $this->assertSame('CANCELADO', $jogos[1]['status_jogo']);
        $this->assertSame(1, $jogos[0]['placar_time_casa_jogos']);
        $this->assertSame('Time Azul', $jogos[0]['time_casa']['nome_time']);
        $this->assertArrayNotHasKey('evento', $jogos[0]);
    }

    // ---------- etiqueta no calendário do site ----------

    public function test_etiqueta_segue_subtipo_campeonato_amistoso_ou_tipo(): void
    {
        $comSubtipo = $this->jogo(+1, $this->azul, $this->visitante, null, 'ATIVO', ['subtipo_evento_calendario' => 'Final']);
        $campeonato = $this->jogo(+2, $this->azul, $this->visitante);
        $amistoso   = $this->jogo(+3, $this->azul, $this->verde, null, 'ATIVO', [], amistoso: true);
        $treino     = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino de finalização', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => now()->addDays(4)->toDateString(), 'status_evento_calendario' => 'ATIVO',
        ]);
        $reuniao    = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Reunião de pais', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => now()->addDays(5)->toDateString(), 'status_evento_calendario' => 'ATIVO',
            'subtipo_evento_calendario' => '',
        ]);

        $this->assertSame('Final', $comSubtipo->evento->etiqueta);
        $this->assertSame('Copa Escola', $campeonato->evento->etiqueta);
        $this->assertSame('Amistoso', $amistoso->evento->etiqueta);
        $this->assertSame('TREINO', $treino->etiqueta);
        $this->assertSame('REUNIÃO', $reuniao->etiqueta); // rótulo com acento de EventoCalendario::TIPOS
    }

    public function test_calendario_do_site_mostra_a_etiqueta_no_proximo_evento_e_na_lista(): void
    {
        $this->jogo(+2, $this->azul, $this->visitante);                     // próximo evento
        $this->eventoCampeonato(+3, 'Abertura da Copa');

        $this->get(route('calendario'))->assertOk()
            ->assertSee('display:inline-block;">Copa Escola</span>', false)
            ->assertSeeInOrder([
                '<div class="event-type-tag tag-jogo">Copa Escola</div>',
                '<div class="event-type-tag tag-campeonato">CAMPEONATO</div>',
            ], false)
            ->assertDontSee('<div class="event-type-tag tag-jogo"></div>', false);
    }

    /**
     * Agenda do site (decisão de 06/10/2026): só eventos CAMPEONATO e jogos de campeonato. Amistoso, treino
     * (à mão), evento JOGO sem tbl_jogos e os outros tipos ficam fora; cancelado aparece com o selo, oculto não.
     */
    public function test_agenda_do_site_mostra_so_campeonatos_e_jogos_de_campeonato(): void
    {
        $abertura  = $this->eventoCampeonato(+1, 'Abertura da Copa');
        $jogo      = $this->jogo(+2, $this->azul, $this->visitante);
        $cancelado = $this->jogo(+3, $this->azul, $this->verde, null, 'CANCELADO');
        $this->jogo(+4, $this->verde, $this->visitante, null, 'INATIVO');                    // oculto
        $this->jogo(+5, $this->verde, $this->azul, null, 'ATIVO', [], amistoso: true);       // amistoso
        foreach (['JOGO' => 'Jogo sem cadastro', 'TREINO' => 'Treino de finalização', 'REUNIAO' => 'Reunião de pais'] as $tipo => $titulo) {
            EventoCalendario::criarPor(null, [
                'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => $tipo,
                'data_evento_calendario' => now()->addDay()->toDateString(), 'status_evento_calendario' => 'ATIVO',
            ]);
        }

        $this->get(route('calendario'))->assertOk()
            ->assertViewHas('eventos', fn ($eventos) => $eventos->pluck('id_evento_calendario')->all()
                === [$abertura->id_evento_calendario, $jogo->id_evento, $cancelado->id_evento])
            ->assertViewHas('proximoEvento', fn ($proximo) => $proximo->id_evento_calendario === $abertura->id_evento_calendario)
            ->assertSee('event-selo-cancelado', false)
            ->assertDontSee('Time Verde x Time Visitante')  // oculto
            ->assertDontSee('Time Verde x Time Azul')       // amistoso
            ->assertDontSee('Jogo sem cadastro')
            ->assertDontSee('Treino de finalização')
            ->assertDontSee('Reunião de pais')
            ->assertDontSee('Treinos Especiais');           // o filtro de treinos saiu
    }

    public function test_proximo_evento_do_site_e_so_jogo_de_campeonato_ativo(): void
    {
        $this->jogo(+1, $this->azul, $this->verde, null, 'ATIVO', [], amistoso: true);    // amistoso: não
        $this->jogo(+2, $this->verde, $this->visitante, null, 'CANCELADO');              // cancelado: só na lista
        $jogo = $this->jogo(+3, $this->azul, $this->visitante);

        $this->get(route('calendario'))->assertOk()
            ->assertViewHas('proximoEvento', fn ($proximo) => $proximo->id_evento_calendario === $jogo->id_evento)
            ->assertSee('<h3 class="cal-next-title">Time Azul x Time Visitante</h3>', false);
    }

    // ---------- dashboard ----------

    public function test_dashboard_mostra_a_data_do_evento_e_amistoso(): void
    {
        $this->jogo(0, $this->azul, $this->verde, null, 'ATIVO', [], amistoso: true, data: '2099-07-14');

        $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('14/07/2099')
            ->assertSee('Amistoso');
    }

    // ---------- ajudantes ----------

    // Na classificação só destes jogos, $primeiro fica acima de $segundo
    private function assertNaFrente(string $primeiro, string $segundo, array $jogos): void
    {
        $ids    = collect($jogos)->pluck('id_jogo');
        $nomes  = array_column(Jogo::classificacao(Jogo::with(['evento', 'timeCasa', 'timeVisitante'])->whereIn('id_jogo', $ids)->get()), 'nome');

        $this->assertLessThan(array_search($segundo, $nomes, true), array_search($primeiro, $nomes, true),
            "{$primeiro} deveria ficar acima de {$segundo}: " . implode(', ', $nomes));
    }

    private function novoTime(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-11', 'M'), 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'INTERNO',
        ]);
    }

    private function eventoCampeonato(int $dias, string $titulo): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => 'CAMPEONATO',
            'data_evento_calendario' => now()->addDays($dias)->toDateString(), 'horario_inicio_evento_calendario' => '08:00',
            'local_evento_calendario' => 'Quadra A', 'status_evento_calendario' => 'ATIVO',
        ]);
    }

    private function jogo(int $dias, int $casa, int $visitante, ?array $placar = null, string $status = 'ATIVO',
        array $evento = [], bool $amistoso = false, ?string $data = null): Jogo
    {
        $ev = EventoCalendario::criarPor(null, array_merge([
            'titulo_evento_calendario'         => Jogo::tituloPara($casa, $visitante),
            'tipo_evento_calendario'           => 'JOGO',
            'data_evento_calendario'           => $data ?? now()->addDays($dias)->toDateString(),
            'horario_inicio_evento_calendario' => '19:00:00',
            'local_evento_calendario'          => 'Quadra A',
            'status_evento_calendario'         => $status,
        ], $evento));

        return Jogo::create([
            'id_evento'                   => $ev->id_evento_calendario,
            'id_campeonato'               => $amistoso ? null : $this->idCampeonato,
            'id_time_casa'                => $casa,
            'id_time_visitante'           => $visitante,
            'placar_time_casa_jogos'      => $placar[0] ?? null,
            'placar_time_visitante_jogos' => $placar[1] ?? null,
        ]);
    }
}
