<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Desempate do Brasileirão na classificação (Fase I, Etapa I1a; dono do projeto, 21/10/2026): pontos, vitórias,
 * saldo, gols marcados, confronto direto (só com dois empatados) e a marca de sorteio. Sem cartões.
 */
class ClassificacaoDesempateTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- regra (jogos montados em memória) ----------

    public function test_criterios_na_ordem_do_brasileirao(): void
    {
        // A e B: 4 pontos; A com 1 vitória e 1 empate, B também... diferença no saldo
        $tabela = $this->tabela([
            ['A', 'C', 2, 0], ['A', 'D', 1, 1],   // A: 4 pts, 1 V, saldo +2
            ['B', 'C', 1, 0], ['B', 'D', 0, 0],   // B: 4 pts, 1 V, saldo +1
        ]);
        $this->assertSame(['A', 'B'], array_slice(array_column($tabela, 'nome'), 0, 2));

        // Mais vitórias vence o empate em pontos: E (2 V, 1 D = 6) x F (1 V, 3 E = 6)
        $tabela = $this->tabela([
            ['E', 'X', 1, 0], ['E', 'Y', 1, 0], ['E', 'Z', 0, 5],
            ['F', 'X', 0, 0], ['F', 'Y', 0, 0], ['F', 'Z', 0, 0], ['F', 'W', 1, 0],
        ]);
        $ordem = array_column($tabela, 'nome');
        $this->assertLessThan(array_search('F', $ordem), array_search('E', $ordem));
    }

    public function test_confronto_direto_decide_entre_dois_empatados_em_tudo(): void
    {
        // Zebra e Águias: 3 pts, 1 V, saldo 0, 2 gols cada; a Zebra venceu o confronto direto
        $tabela = $this->tabela([
            ['Zebra', 'Águias', 2, 1],
            ['Águias', 'Outro', 1, 0],
            ['Zebra', 'Outro', 0, 1],
        ]);
        $linhas = collect($tabela)->keyBy('nome');
        $ordem  = array_column($tabela, 'nome');

        $this->assertSame([3, 1, 0, 2], [$linhas['Zebra']['pontos'], $linhas['Zebra']['v'], $linhas['Zebra']['gm'] - $linhas['Zebra']['gc'], $linhas['Zebra']['gm']]);
        $this->assertSame([3, 1, 0, 2], [$linhas['Águias']['pontos'], $linhas['Águias']['v'], $linhas['Águias']['gm'] - $linhas['Águias']['gc'], $linhas['Águias']['gm']]);
        $this->assertLessThan(array_search('Águias', $ordem), array_search('Zebra', $ordem), 'a Zebra venceu o confronto direto');
        $this->assertFalse($linhas['Zebra']['sorteio']);
        $this->assertFalse($linhas['Águias']['sorteio']);
    }

    public function test_sem_vencedor_no_confronto_direto_ou_com_tres_empatados_marca_sorteio(): void
    {
        // Dois empatados que se enfrentaram com empate: sorteio, pelo nome enquanto isso
        $tabela = $this->tabela([['Zebra', 'Águias', 1, 1]]);
        $this->assertSame(['Águias', 'Zebra'], array_column($tabela, 'nome'));
        $this->assertSame([true, true], array_column($tabela, 'sorteio'));

        // Três empatados em tudo (cada um ganhou um jogo por 1 × 0): sem confronto direto, sorteio
        $tabela = $this->tabela([['A', 'B', 1, 0], ['B', 'C', 1, 0], ['C', 'A', 1, 0]]);
        $this->assertSame(['A', 'B', 'C'], array_column($tabela, 'nome'));
        $this->assertSame([true, true, true], array_column($tabela, 'sorteio'));
    }

    public function test_quem_ainda_nao_jogou_nao_e_marcado(): void
    {
        $times = collect(['Alfa', 'Beta', 'Gama'])->map(fn ($nome, $i) => (object) ['id_time' => $i + 1, 'nome_time' => $nome, 'logo_time' => null]);

        $tabela = Jogo::classificacao(collect(), $times);

        $this->assertSame(['Alfa', 'Beta', 'Gama'], array_column($tabela, 'nome'));
        $this->assertSame([false, false, false], array_column($tabela, 'sorteio'));
    }

    // ---------- site, admin e API ----------

    public function test_site_admin_e_api_mostram_o_empate_para_sorteio(): void
    {
        $admin    = User::factory()->admin()->create();
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $ids      = collect(['Botafogo', 'Santos'])->mapWithKeys(fn ($nome) => [$nome => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO', 'status_time' => 'ATIVO',
        ])]);
        $copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idSub15M, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->subMonth()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        $this->participantes($copa, $ids->values()->all(), $idSub15M);

        // Jogo entre externos, com placar empatado: nasce publicado e conta na tabela
        $this->actingAs($admin, 'admin')->post(route('admin.jogos.store'), [
            'id_campeonato' => $copa, 'id_categoria' => $idSub15M,
            'id_time_casa' => $ids['Botafogo'], 'id_time_visitante' => $ids['Santos'],
            'data_evento_calendario' => now()->subDays(3)->toDateString(),
            'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => '', 'placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 1,
        ])->assertSessionHasNoErrors();

        $legenda = 'se continuar assim no fim, a ordem sai por sorteio';
        $this->get(route('campeonato.show', $copa))->assertOk()->assertSee('camp-sorteio', false)->assertSee($legenda);
        $this->actingAs($admin, 'admin')->get(route('admin.campeonatos.grupos', $copa))->assertOk()->assertSee($legenda);
        $this->getJson("/api/v1/campeonatos/{$copa}")->assertOk()
            ->assertJsonPath('data.classificacao.0.grupos.0.tabela.0.empate_sorteio', true)
            ->assertJsonPath('data.classificacao.0.grupos.0.tabela.1.empate_sorteio', true);
    }

    // ---------- auxiliares ----------

    // Tabela a partir de [casa, visitante, gols da casa, gols do visitante]; os times ganham ids pelo nome
    private function tabela(array $placares): array
    {
        $ids  = [];
        $time = function (string $nome) use (&$ids) {
            $ids[$nome] ??= count($ids) + 1;

            return (object) ['id_time' => $ids[$nome], 'nome_time' => $nome, 'logo_time' => null];
        };

        $jogos = collect($placares)->map(function ($p) use ($time) {
            [$casa, $fora] = [$time($p[0]), $time($p[1])];
            $jogo = new Jogo([
                'fase_jogo' => Jogo::FASE_PADRAO, 'id_time_casa' => $casa->id_time, 'id_time_visitante' => $fora->id_time,
                'placar_time_casa_jogos' => $p[2], 'placar_time_visitante_jogos' => $p[3],
            ]);
            $evento = new EventoCalendario(['status_evento_calendario' => 'ATIVO']);
            $evento->data_publicacao_evento_calendario = now();

            return $jogo->setRelation('evento', $evento)->setRelation('timeCasa', $casa)->setRelation('timeVisitante', $fora);
        });

        return Jogo::classificacao($jogos);
    }
}
