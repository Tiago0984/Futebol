<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campeonato inativo no admin (dono do projeto, 21/10/2026): continua na lista de Campeonatos (reativar ou
 * excluir); nos filtros, num grupo "Inativos" no fim; fora do "Todos" das telas de jogos, do formulário de
 * jogo novo e do contador do dashboard. O jogo que já é dele continua editável.
 */
class AdminCampeonatoInativoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $botafogo;
    private int $santos;
    private int $ativo;
    private int $inativo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->botafogo = $this->time('Botafogo');
        $this->santos   = $this->time('Santos');
        $this->ativo    = $this->campeonato('Copa Que Aparece');
        $this->inativo  = $this->campeonato('Copa Escondida');
        $this->post(route('admin.jogos.store'), $this->dadosJogo($this->ativo, 'Arena Visivel'))->assertSessionHasNoErrors();
        $this->post(route('admin.jogos.store'), $this->dadosJogo($this->inativo, 'Arena Escondida'))->assertSessionHasNoErrors();
        DB::table('tbl_campeonato')->where('id_campeonato', $this->inativo)->update(['status_campeonato' => 'INATIVO']);
    }

    public function test_nao_cria_jogo_em_campeonato_inativo_mas_edita_o_que_ja_e_dele(): void
    {
        $this->post(route('admin.jogos.store'), $this->dadosJogo($this->inativo, 'Arena Nova'))
            ->assertSessionHasErrors(['id_campeonato' => 'O campeonato Copa Escondida está inativo. Reative-o (Campeonatos) antes de pôr jogos nele.']);
        $this->assertSame(2, Jogo::count());

        // O jogo que já é do campeonato inativo continua editável (ex.: lançar o placar)
        $jogo = Jogo::where('id_campeonato', $this->inativo)->sole();
        $this->put(route('admin.jogos.update', $jogo->id_jogo), [...$this->dadosJogo($this->inativo, 'Arena Escondida'),
            'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, $jogo->fresh()->placar_time_casa_jogos);

        // Mas um jogo de outro campeonato não passa para ele
        $outro = Jogo::where('id_campeonato', $this->ativo)->sole();
        $this->put(route('admin.jogos.update', $outro->id_jogo), $this->dadosJogo($this->inativo, 'Arena Visivel'))
            ->assertSessionHasErrors('id_campeonato');
    }

    public function test_filtros_com_o_grupo_inativos_e_o_todos_sem_os_jogos_dele(): void
    {
        // O local aparece no bloco do jogo como "· Local" (sem o ponto, ele está também nas sugestões de Local)
        $this->get(route('admin.campeonatos.jogos'))->assertOk()
            ->assertSee('· Arena Visivel')->assertDontSee('· Arena Escondida')
            ->assertSeeInOrder(['Copa Que Aparece', '<optgroup label="Inativos">', 'Copa Escondida (inativo)'], false);

        // O inativo continua abrindo pelo filtro
        $this->get(route('admin.campeonatos.times', $this->inativo))->assertOk()->assertSee('· Arena Escondida');

        $html = $this->get(route('admin.jogos.index'))->assertOk()
            ->assertSeeInOrder(['<optgroup label="Inativos">', 'Copa Escondida (inativo)'], false)->getContent();

        // Formulário de jogo novo: sem o inativo
        preg_match('#<select name="id_campeonato" id="criar_id_campeonato".*?</select>#s', $html, $select);
        $this->assertStringContainsString('Copa Que Aparece', $select[0]);
        $this->assertStringNotContainsString('Copa Escondida', $select[0]);

        // Lista de Campeonatos: continua (para reativar ou excluir)
        $this->get(route('admin.campeonatos.index'))->assertSee('Copa Escondida');
    }

    public function test_dashboard_conta_so_os_ativos(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder(['<div class="kpi-number">1</div>', 'Campeonatos ativos'], false);
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

    private function dadosJogo(int $idCampeonato, string $local): array
    {
        $this->actingAs($this->admin, 'admin');

        return [
            'id_campeonato' => $idCampeonato, 'id_categoria' => $this->idSub15M,
            'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
            'data_evento_calendario' => now()->addDays(10 + Jogo::count())->toDateString(),
            'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => $local, 'placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => '',
        ];
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO',
            'status_time' => 'ATIVO',
        ]);
    }
}
