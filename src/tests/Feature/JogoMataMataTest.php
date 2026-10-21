<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase I, Etapa I1b (dono do projeto, 21/10/2026): rodada na fase de grupos; no mata-mata, ida e volta (empate no
 * agregado vai direto para os pênaltis, sem gol fora e sem prorrogação), e jogo único com prorrogação e pênaltis.
 */
class JogoMataMataTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $copa;
    private int $botafogo;
    private int $santos;
    private int $dias = 10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->botafogo = $this->time('Botafogo');
        $this->santos   = $this->time('Santos');
        $this->copa     = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonths(2)->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        $this->participantes($this->copa, [$this->botafogo, $this->santos], $this->idSub15M);
    }

    public function test_colunas_novas(): void
    {
        $colunas = collect(DB::select("SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_jogos'"))->keyBy('c');
        foreach (['rodada_jogo', 'prorrogacao_time_casa_jogos', 'prorrogacao_time_visitante_jogos', 'penaltis_time_casa_jogos', 'penaltis_time_visitante_jogos'] as $coluna) {
            $this->assertSame(['tinyint unsigned', 'YES'], [$colunas[$coluna]->t, $colunas[$coluna]->n], $coluna);
        }
        $this->assertSame(['int', 'YES'], [$colunas['id_jogo_ida']->t, $colunas['id_jogo_ida']->n]);

        $fk = DB::selectOne("SELECT REFERENCED_TABLE_NAME t, REFERENCED_COLUMN_NAME r FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_jogo_ida'");
        $this->assertSame(['tbl_jogos', 'id_jogo'], [$fk->t, $fk->r]);
    }

    public function test_rodada_so_na_fase_de_grupos(): void
    {
        $this->criar(['rodada_jogo' => 3])->assertSessionHasNoErrors();
        $jogo = Jogo::sole();
        $this->assertSame(3, $jogo->rodada_jogo);
        $this->assertSame('Fase de grupos · Rodada 3', $jogo->fase_completa);

        $this->criar(['fase_jogo' => 'OITAVAS', 'rodada_jogo' => 2])
            ->assertSessionHasErrors(['rodada_jogo' => 'A rodada é só da fase de grupos.']);
        $this->criar(['rodada_jogo' => 0])->assertSessionHasErrors(['rodada_jogo' => 'A rodada vai de 1 a 99.']);
    }

    public function test_ida_e_volta_com_agregado_e_penaltis(): void
    {
        // Ida: Botafogo 2 × 1 Santos
        $ida = $this->jogo(['fase_jogo' => 'OITAVAS', 'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1]);
        // Sem a volta cadastrada, o sistema ainda não sabe que é uma ida: vale como jogo único
        $this->assertSame('Botafogo passa', $ida->resultadoDoConfronto()['texto']);

        // Volta com os times na mesma ordem: recusada
        $this->criar(['fase_jogo' => 'OITAVAS', 'id_jogo_ida' => $ida->id_jogo])
            ->assertSessionHasErrors(['id_jogo_ida' => 'A volta precisa ser do mesmo campeonato, categoria e fase da ida, com os times invertidos (o mandante da ida é o visitante da volta).']);
        // Na fase de grupos também não
        $this->criar(['id_time_casa' => $this->santos, 'id_time_visitante' => $this->botafogo, 'id_jogo_ida' => $ida->id_jogo])
            ->assertSessionHasErrors(['id_jogo_ida' => 'Jogo de volta só no mata-mata.']);

        // Volta: Santos 1 × 0 Botafogo = agregado 2 × 2; pênaltis 4 × 3 para o Santos
        $volta = $this->jogo(['fase_jogo' => 'OITAVAS', 'id_time_casa' => $this->santos, 'id_time_visitante' => $this->botafogo,
            'id_jogo_ida' => $ida->id_jogo, 'placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 0]);
        $this->assertNull($volta->resultadoDoConfronto(), 'empate no agregado, sem pênaltis lançados');
        $this->assertSame('Oitavas de final · volta', $volta->fase_completa);
        $this->assertSame('Oitavas de final · ida', $ida->fresh()->fase_completa);
        $this->assertNull($ida->fresh()->resultadoDoConfronto(), 'com a volta cadastrada, a ida não decide');

        // Na volta não há prorrogação; na ida não há pênaltis
        $this->placar($volta, ['prorrogacao_time_casa_jogos' => 1, 'prorrogacao_time_visitante_jogos' => 0])
            ->assertSessionHasErrors(['prorrogacao_time_casa_jogos' => 'Na ida e volta não há prorrogação: o empate no agregado vai direto para os pênaltis.']);
        $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.placar', $ida->id_jogo), [
            'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1, 'penaltis_time_casa_jogos' => 5, 'penaltis_time_visitante_jogos' => 4,
        ]);
        $this->assertNull($ida->fresh()->penaltis_time_casa_jogos, 'a tela da ida nem tem os pênaltis; o servidor ignora');

        $this->placar($volta, ['penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 3])->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Placar salvo: 1 × 0 (pên. 4 × 3).');
        $this->assertSame([
            'id_time' => $this->santos, 'como' => 'pênaltis', 'agregado' => [2, 2],
            'texto' => 'Santos passa nos pênaltis (4 × 3), depois do agregado 2 × 2',
        ], $volta->fresh()->resultadoDoConfronto());

        // Uma ida tem uma volta só
        $this->criar(['fase_jogo' => 'OITAVAS', 'id_time_casa' => $this->santos, 'id_time_visitante' => $this->botafogo, 'id_jogo_ida' => $ida->id_jogo])
            ->assertSessionHasErrors(['id_jogo_ida' => 'O jogo de ida escolhido já tem jogo de volta.']);

        // Agregado decidido: Santos 3 × 0 = 4 × 2; pênaltis aí são recusados
        $this->placar($volta, ['placar_time_casa_jogos' => 3, 'penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 3])
            ->assertSessionHasErrors(['penaltis_time_casa_jogos' => 'Pênaltis só com empate no agregado (está 4 × 2).']);
        $this->placar($volta, ['placar_time_casa_jogos' => 3])->assertSessionHasNoErrors();
        $this->assertSame('Santos passa no agregado (4 × 2)', $volta->fresh()->resultadoDoConfronto()['texto']);

        // A ida com volta não muda de fase nem de times sem desfazer o vínculo
        $this->editar($ida, ['fase_jogo' => 'QUARTAS', 'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1])
            ->assertSessionHasErrors('fase_jogo');
    }

    public function test_jogo_unico_com_prorrogacao_e_penaltis(): void
    {
        $final = $this->jogo(['fase_jogo' => 'FINAL', 'placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 1]);

        $this->placar($final, ['penaltis_time_casa_jogos' => 5, 'penaltis_time_visitante_jogos' => 4])
            ->assertSessionHasErrors(['penaltis_time_casa_jogos' => 'Lance a prorrogação antes dos pênaltis (jogo único: prorrogação e depois pênaltis).']);
        $this->placar($final, ['prorrogacao_time_casa_jogos' => 1])
            ->assertSessionHasErrors(['prorrogacao_time_casa_jogos' => 'Preencha a prorrogação dos dois times, ou deixe as duas vazias.']);

        // Prorrogação decide: Botafogo vence
        $this->placar($final, ['prorrogacao_time_casa_jogos' => 1, 'prorrogacao_time_visitante_jogos' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Botafogo vence na prorrogação', $final->fresh()->resultadoDoConfronto()['texto']);

        // Pênaltis só com a prorrogação também empatada, e não terminam empatados
        $this->placar($final, ['prorrogacao_time_casa_jogos' => 1, 'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 5, 'penaltis_time_visitante_jogos' => 4])
            ->assertSessionHasErrors(['penaltis_time_casa_jogos' => 'Pênaltis só se a prorrogação também terminar empatada.']);
        $this->placar($final, ['prorrogacao_time_casa_jogos' => 0, 'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 3, 'penaltis_time_visitante_jogos' => 3])
            ->assertSessionHasErrors(['penaltis_time_casa_jogos' => 'A disputa de pênaltis não termina empatada.']);
        $this->placar($final, ['prorrogacao_time_casa_jogos' => 0, 'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 3, 'penaltis_time_visitante_jogos' => 4])
            ->assertSessionHasNoErrors();
        $this->assertSame('Santos vence nos pênaltis (3 × 4)', $final->fresh()->resultadoDoConfronto()['texto']);
        $this->assertSame('prorr. 0 × 0 · pên. 3 × 4', $final->fresh()->placar_extra);

        // Prorrogação só com empate no tempo normal, e nunca fora do mata-mata
        $this->placar($final, ['placar_time_casa_jogos' => 2, 'prorrogacao_time_casa_jogos' => 0, 'prorrogacao_time_visitante_jogos' => 0])
            ->assertSessionHasErrors(['prorrogacao_time_casa_jogos' => 'Prorrogação só depois de empate no tempo normal.']);
        $this->criar(['placar_time_casa_jogos' => 0, 'placar_time_visitante_jogos' => 0, 'prorrogacao_time_casa_jogos' => 1, 'prorrogacao_time_visitante_jogos' => 0])
            ->assertSessionHasErrors(['prorrogacao_time_casa_jogos' => 'Prorrogação só no mata-mata.']);
    }

    public function test_site_e_api(): void
    {
        $this->jogo(['rodada_jogo' => 1, 'placar_time_casa_jogos' => 0, 'placar_time_visitante_jogos' => 0]);
        $this->jogo(['rodada_jogo' => 2, 'id_time_casa' => $this->santos, 'id_time_visitante' => $this->botafogo]);
        $this->jogo();   // sem rodada: na seção "Fase de grupos", depois das rodadas
        $final = $this->jogo(['fase_jogo' => 'FINAL', 'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 2,
            'prorrogacao_time_casa_jogos' => 0, 'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 5, 'penaltis_time_visitante_jogos' => 4]);

        $this->get(route('campeonato.show', $this->copa))->assertOk()
            ->assertSeeInOrder(['match-secao">Rodada 1', 'match-secao">Rodada 2', 'match-secao">Fase de grupos', 'match-secao">Final',
                'match-info-fase', 'Final', 'prorr. 0 × 0 · pên. 5 × 4', 'Botafogo vence nos pênaltis (5 × 4)'], false)
            ->assertDontSee('class="match-fase"', false);   // a fase saiu de dentro do placar (quebrava em várias linhas)

        $jogos = collect($this->getJson("/api/v1/campeonatos/{$this->copa}")->assertOk()->json('data.jogos'))->keyBy('id_jogo');
        $this->assertSame(['id_time' => $this->botafogo, 'como' => 'pênaltis', 'agregado' => null, 'texto' => 'Botafogo vence nos pênaltis (5 × 4)'],
            $jogos[$final->id_jogo]['confronto']);
        $this->assertSame([5, 4, null], [$jogos[$final->id_jogo]['penaltis_time_casa_jogos'], $jogos[$final->id_jogo]['penaltis_time_visitante_jogos'], $jogos[$final->id_jogo]['id_jogo_ida']]);
        $this->assertArrayNotHasKey('jogo_volta', $jogos[$final->id_jogo]);
        $this->assertNull($jogos->firstWhere('rodada_jogo', 1)['confronto']);

        // Admin: lista de Jogos e tela do jogo
        $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.index'))->assertOk()
            ->assertSee('Fase de grupos · Rodada 2')->assertSee('Botafogo vence nos pênaltis (5 × 4)');
        $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $final->id_evento))->assertOk()
            ->assertSee('Botafogo vence nos pênaltis (5 × 4)')->assertSee('2 × 2 · prorr. 0 × 0 · pên. 5 × 4');
    }

    // ---------- auxiliares ----------

    private function dados(array $extra = []): array
    {
        return array_merge([
            'id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'fase_jogo' => 'GRUPOS',
            'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
            'data_evento_calendario' => now()->addDays($this->dias++)->toDateString(),
            'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => 'Arena', 'placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => '',
        ], $extra);
    }

    private function criar(array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dados($extra));
    }

    private function jogo(array $extra = []): Jogo
    {
        $this->criar($extra)->assertSessionHasNoErrors();

        return Jogo::with('evento')->orderByDesc('id_jogo')->first();
    }

    private function editar(Jogo $jogo, array $extra)
    {
        return $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo), $this->dados([
            'fase_jogo' => $jogo->fase_jogo, 'id_time_casa' => $jogo->id_time_casa, 'id_time_visitante' => $jogo->id_time_visitante,
            'id_jogo_ida' => $jogo->id_jogo_ida, ...$extra,
        ]));
    }

    // Placar rápido (tela do jogo), partindo do placar atual
    private function placar(Jogo $jogo, array $extra)
    {
        $jogo->refresh();

        return $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.placar', $jogo->id_jogo), [
            'placar_time_casa_jogos' => $jogo->placar_time_casa_jogos, 'placar_time_visitante_jogos' => $jogo->placar_time_visitante_jogos,
            ...$extra,
        ]);
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO',
            'status_time' => 'ATIVO',
        ]);
    }
}
