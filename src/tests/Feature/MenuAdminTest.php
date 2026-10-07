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

        // Ramos, campeonato em andamento, "Ver todos", Jogos, Grade, Gerar agenda, Categorias e Times
        $this->assertContains(route('admin.calendario.index', ['ramo' => 'treinos']), $links);
        $this->assertContains(route('admin.jogos.index', ['campeonato' => $this->emAndamento]), $links);
        $this->assertContains(route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]), $links);
        $this->assertContains(route('admin.times.index'), $links);

        foreach ($links as $link) {
            $this->comoAdmin()->get($link)->assertOk();
        }
    }

    public function test_barra_sem_nomes_ficticios_e_so_com_os_campeonatos_em_andamento(): void
    {
        $barra = $this->barra($this->comoAdmin()->get(route('admin.dashboard')));

        foreach (['Copa Escola', 'Copa Regional', 'Lucas Silva', 'Leões FC', 'Estrela Azul', 'AACJ x Leões', 'Exame médico',
                  'Escalação', 'Sub-11, Sub-13, Sub-15...', 'Eventos e Grade'] as $ficticio) {
            $this->assertStringNotContainsString($ficticio, $barra);
        }

        $this->assertStringContainsString('Taça Em Andamento', $barra);
        $this->assertStringNotContainsString('Taça Encerrada', $barra);
        $this->assertStringNotContainsString('Taça Inativa', $barra); // inativo não está "em andamento"
        foreach (['Calendário', 'Campeonatos', 'Ver todos', 'Amistosos', 'Treinos', 'Individuais', 'Outros', 'Jogos',
                  'Grade de treino', 'Gerar agenda do mês', 'CADASTROS', 'Categorias', 'Times'] as $item) {
            $this->assertStringContainsString($item, $barra);
        }
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
            route('admin.calendario.index', ['ramo' => 'treinos'])            => route('admin.calendario.index', ['ramo' => 'treinos']),
            route('admin.calendario.index', ['ramo' => 'campeonatos'])        => route('admin.calendario.index', ['ramo' => 'campeonatos']),
            route('admin.calendario.index', ['tab' => 'grade'])               => route('admin.calendario.index', ['tab' => 'grade']),
            route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]) => route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]),
            route('admin.jogos.index')                                        => route('admin.jogos.index'),
            route('admin.jogos.index', ['campeonato' => $this->emAndamento])  => route('admin.jogos.index', ['campeonato' => $this->emAndamento]),
            route('admin.jogos.index', ['campeonato' => $this->encerrado])    => route('admin.jogos.index'),
            route('admin.campeonatos.index')                                  => route('admin.campeonatos.index'),
            route('admin.times.index')                                        => route('admin.times.index'),
            route('admin.times.elenco', $this->azul)                          => route('admin.times.index'),
            route('admin.categorias.index')                                   => route('admin.categorias.index'),
            // Tela do evento: o ramo dele
            route('admin.calendario.eventos.show', $treino->id_evento_calendario) => route('admin.calendario.index', ['ramo' => 'treinos']),
            route('admin.calendario.eventos.show', $amistoso->id_evento)      => route('admin.jogos.index', ['campeonato' => 'amistoso']),
            route('admin.calendario.eventos.show', $daCopa->id_evento)        => route('admin.calendario.index', ['ramo' => 'campeonatos']),
        ];

        foreach ($esperado as $pagina => $ativo) {
            $this->assertSame([$ativo], $this->linksAtivos($this->comoAdmin()->get($pagina)->assertOk()), "Página {$pagina}");
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
            'jogoAntigo' => $this->evento('JOGO')->id_evento_calendario, // JOGO sem tbl_jogos: só no Calendário
        ];

        $esperado = [
            'campeonatos' => ['campeonato', 'jogoCopa'],
            'amistosos'   => ['amistoso'],
            'treinos'     => ['treino'],
            'individuais' => ['avaliacao'],
            'outros'      => ['reuniao', 'festa', 'evento'],
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
        $atleta = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'PENDENTE');
        $parametros = [
            'admin.calendario.eventos.show' => $evento->id_evento_calendario,
            'admin.campeonatos.edit'        => $this->emAndamento,
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
