<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campeonato inativo some do site (21/10/2026), como já sumia da API: menu, lista, página (404), home e os jogos
 * dele na agenda pública e no "Próximo jogo". Antes, só a API conferia o status.
 */
class SiteCampeonatoInativoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $botafogo;
    private int $santos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->botafogo = $this->time('Botafogo');
        $this->santos   = $this->time('Santos');
    }

    public function test_campeonato_inativo_some_do_site_com_os_jogos(): void
    {
        $ativo   = $this->campeonato('Copa Que Aparece');
        $inativo = $this->campeonato('Copa Escondida');
        $this->jogo($ativo, 'Arena Visivel');
        $this->jogo($inativo, 'Arena Escondida');

        $this->actingAs($this->admin, 'admin')->patch(route('admin.campeonatos.toggleStatus', $inativo))->assertRedirect();
        $this->assertSame('INATIVO', DB::table('tbl_campeonato')->where('id_campeonato', $inativo)->value('status_campeonato'));

        // Menu do topo, lista e home
        foreach ([route('campeonato'), route('home')] as $pagina) {
            $this->get($pagina)->assertOk()->assertSee('Copa Que Aparece')->assertDontSee('Copa Escondida');
        }

        // Página e API: 404
        $this->get(route('campeonato.show', $ativo))->assertOk();
        $this->get(route('campeonato.show', $inativo))->assertNotFound();
        $this->getJson("/api/v1/campeonatos/{$inativo}")->assertNotFound();

        // Agenda pública: só o jogo do campeonato ativo
        $this->get('/calendario')->assertOk()->assertSee('Arena Visivel')->assertDontSee('Arena Escondida');

        // Reativado, volta
        $this->actingAs($this->admin, 'admin')->patch(route('admin.campeonatos.toggleStatus', $inativo));
        $this->get(route('campeonato.show', $inativo))->assertOk();
        $this->get('/calendario')->assertSee('Arena Escondida');
    }

    // ---------- auxiliares ----------

    private function campeonato(string $nome): int
    {
        $id = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => $nome, 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        $this->participantes($id, [$this->botafogo, $this->santos], $this->idSub15M);

        return $id;
    }

    // Jogo entre dois externos: nasce publicado
    private function jogo(int $idCampeonato, string $local): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), [
            'id_campeonato' => $idCampeonato, 'id_categoria' => $this->idSub15M,
            'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
            'data_evento_calendario' => now()->addDays(10 + $idCampeonato)->toDateString(),
            'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => $local, 'placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => '',
        ])->assertSessionHasNoErrors();
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO',
            'status_time' => 'ATIVO',
        ]);
    }
}
