<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Central do campeonato (padrão das telas, Fase 3, 30/10/2026): as abas (Visão geral, Participantes e convocação,
 * Jogos, Grupos e classificação, Mata-mata e campeão), cada uma uma tela, com o mesmo topo; os passos de cada
 * categoria na Visão geral (SituacaoDoCampeonato) e o campeão e o encerramento na aba Mata-mata.
 */
class CentralDoCampeonatoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub13M;
    private int $idSub15M;
    private int $copa;
    private array $t = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->copa     = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => 'Copa Central', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => today()->subMonth()->toDateString(), 'data_fim_campeonato' => today()->addMonth()->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        foreach (['Alfa', 'Beta', 'Gama', 'Delta'] as $nome) {
            $this->t[$nome] = $this->timeExterno($nome);
        }
        // Sub-15 com os quatro externos; Sub-13 marcado, sem nenhum time
        $this->participantes($this->copa, array_values($this->t), $this->idSub15M);
        DB::table('tbl_campeonato_categoria')->insert(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub13M]);
    }

    public function test_abas_e_caminho_iguais_em_todas_as_telas_da_central(): void
    {
        $abas = ['visao' => 'show', 'participantes' => 'participantes', 'jogos' => 'times', 'grupos' => 'grupos', 'mata-mata' => 'mata-mata'];

        foreach ($abas as $aba => $rota) {
            $resposta = $this->comoAdmin()->get(route("admin.campeonatos.{$rota}", $this->copa))->assertOk()
                ->assertSee('<h1 class="page-title">Copa Central</h1>', false)
                ->assertSee('aria-current="page" data-aba="' . $aba . '"', false)
                ->assertSee(route('admin.campeonatos.edit', [$this->copa, 'voltar' => 'central']), false);
            foreach ($abas as $rotaDaOutra) {
                $resposta->assertSee('href="' . route("admin.campeonatos.{$rotaDaOutra}", $this->copa) . '"', false);
            }
            // Caminho: Campeonatos leva à lista de campeonatos; o nome, à Visão geral (fora da própria Visão geral)
            $resposta->assertSee('<a href="' . route('admin.campeonatos.index') . '">Campeonatos</a>', false);
            if ($aba !== 'visao') {
                $resposta->assertSee('<a href="' . route('admin.campeonatos.show', $this->copa) . '">Copa Central</a>', false);
            }
        }

        $this->comoAdmin()->get(route('admin.campeonatos.show', 999999))->assertNotFound();
    }

    public function test_visao_geral_mostra_os_passos_e_o_proximo_de_cada_categoria(): void
    {
        $aacj = $this->timeExterno('AACJ Masculino', 'INTERNO');
        $this->participantes($this->copa, [$aacj], $this->idSub15M);

        $resposta = $this->comoAdmin()->get(route('admin.campeonatos.show', $this->copa))->assertOk();
        $porCategoria = collect($resposta->viewData('categorias'))->keyBy(fn ($c) => (int) $c['categoria']->id_categoria);

        // Sub-13 sem times: o primeiro passo pendente, para incluir os times pelo Editar campeonato
        $sub13 = $porCategoria[$this->idSub13M];
        $this->assertSame('pendente', $sub13['etapas']['participantes']['situacao']);
        $this->assertSame('bloqueado', $sub13['etapas']['jogos']['situacao']);
        $this->assertSame(route('admin.campeonatos.edit', [$this->copa, 'voltar' => 'central']), $sub13['proximo']['url']);

        // Sub-15: a AACJ sem convocados vem antes de gerar os jogos
        $sub15 = $porCategoria[$this->idSub15M];
        $this->assertSame(['feito', 'pendente', 'opcional', 'pendente', 'bloqueado', 'bloqueado'],
            array_column($sub15['etapas'], 'situacao'));
        $this->assertSame('Sem convocados: AACJ Masculino', $sub15['etapas']['convocacao']['detalhe']);
        $this->assertSame('Convocar', $sub15['proximo']['acao']);
        $this->assertSame(route('admin.campeonatos.gerar-jogos', [$this->copa, $this->idSub15M]), $sub15['etapas']['jogos']['url']);

        // "Precisa da sua atenção": os pendentes das duas categorias, cada um com o botão para a tela certa
        $resposta->assertSeeInOrder(['Precisa da sua atenção', 'Sub-13 Masculino · Times participantes', 'Sub-15 Masculino · Convocação da AACJ',
            'Sub-15 Masculino · Jogos da fase de grupos'])
            ->assertSee('href="' . e(route('admin.campeonatos.convocados', [$this->copa, $aacj, 'categoria' => $this->idSub15M])) . '"', false);
    }

    public function test_visao_geral_acompanha_publicacao_placares_e_encerramento(): void
    {
        $jogos = [];
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $jogos[] = $this->jogo('GRUPOS', $casa, $fora, 1, 0);
        }
        $etapas = fn () => collect($this->comoAdmin()->get(route('admin.campeonatos.show', $this->copa))->viewData('categorias'))
            ->firstWhere(fn ($c) => (int) $c['categoria']->id_categoria === $this->idSub15M)['etapas'];

        // Um sem placar e outro em rascunho: pendente, com os dois motivos
        $jogos[0]->update(['placar_time_casa_jogos' => null, 'placar_time_visitante_jogos' => null]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $jogos[1]->id_evento)->update(['data_publicacao_evento_calendario' => null]);
        $this->assertSame(['pendente', '1 em rascunho (atletas ainda não avisados) · 1 já aconteceu(ram) sem placar'],
            [$etapas()['resultados']['situacao'], $etapas()['resultados']['detalhe']]);
        $this->assertSame('andamento', $etapas()['campeao']['situacao']);

        // Tudo publicado e com placar: falta encerrar a categoria (pontos corridos)
        $jogos[0]->update(['placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 0]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $jogos[1]->id_evento)->update(['data_publicacao_evento_calendario' => now()]);
        $this->assertSame('feito', $etapas()['resultados']['situacao']);
        $this->assertSame(['pendente', 'Encerrar categoria'], [$etapas()['campeao']['situacao'], $etapas()['campeao']['acao']]);

        // Encerrar volta para a aba Mata-mata e campeão, no bloco da categoria; a Visão geral mostra o campeão
        $this->comoAdmin()->patch(route('admin.campeonatos.grupos.encerramento', [$this->copa, $this->idSub15M]))
            ->assertRedirect(route('admin.campeonatos.mata-mata', $this->copa) . '#categoria-' . $this->idSub15M);
        $this->assertSame(['feito', 'Campeão: Alfa'], [$etapas()['campeao']['situacao'], $etapas()['campeao']['detalhe']]);
        $this->comoAdmin()->get(route('admin.campeonatos.mata-mata', $this->copa))->assertSee('Campeão: Alfa')->assertSee('Reabrir');
    }

    public function test_mata_mata_mostra_os_jogos_por_fase_e_o_proximo_passo(): void
    {
        Campeonato::find($this->copa)->definirClassificados($this->idSub15M, 4);
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $this->jogo('GRUPOS', $casa, $fora, 1, 0);
        }
        $campeao = fn () => collect($this->comoAdmin()->get(route('admin.campeonatos.show', $this->copa))->viewData('categorias'))
            ->firstWhere(fn ($c) => (int) $c['categoria']->id_categoria === $this->idSub15M)['etapas']['campeao'];

        // Fase de grupos terminada e "passam 4": o próximo passo é montar o mata-mata
        $this->assertSame(['pendente', route('admin.campeonatos.proxima-fase', [$this->copa, $this->idSub15M])],
            [$campeao()['situacao'], $campeao()['url']]);
        $this->comoAdmin()->get(route('admin.campeonatos.mata-mata', $this->copa))->assertOk()
            ->assertSee('Nenhum jogo de mata-mata nesta categoria.')->assertSee('Passam 4 de cada grupo (chave)')
            ->assertDontSee('Encerrar categoria');

        // Semifinais e final: listadas por fase, com o campeão; o "Montar próxima fase" saiu da aba Grupos
        $this->jogo('SEMIFINAL', 'Alfa', 'Delta', 2, 0);
        $semi = $this->jogo('SEMIFINAL', 'Beta', 'Gama', 1, 1);

        // Semifinal empatada sem prorrogação e pênaltis: não há vencedor (o montador recusaria a final)
        $this->assertSame(['pendente', '1 confronto(s) do mata-mata sem vencedor: lance a prorrogação e os pênaltis'],
            [$campeao()['situacao'], $campeao()['detalhe']]);
        $this->comoAdmin()->get(route('admin.campeonatos.mata-mata', $this->copa))->assertSee('Sem vencedor: lance a prorrogação e os pênaltis');

        $semi->update(['placar_time_visitante_jogos' => 0]);
        $this->assertSame(['pendente', 'Montar próxima fase'], [$campeao()['situacao'], $campeao()['acao']]);   // semis decididas, sem final
        $this->jogo('FINAL', 'Alfa', 'Beta', 3, 1);
        $this->assertSame(['feito', 'Campeão: Alfa'], [$campeao()['situacao'], $campeao()['detalhe']]);

        $this->comoAdmin()->get(route('admin.campeonatos.mata-mata', $this->copa))->assertOk()
            ->assertSeeInOrder(['Sub-15 Masculino', 'Montar próxima fase', 'Campeão: Alfa', 'Semifinal', 'Alfa', '2 × 0', 'Delta', 'Final', '3 × 1']);
        $this->comoAdmin()->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()
            ->assertDontSee('Montar próxima fase')->assertDontSee('Campeão: Alfa')
            ->assertSee('Gerar jogos (todos contra todos)');
    }

    public function test_participantes_com_grupo_jogos_e_sem_jogo(): void
    {
        $grupo = DB::table('tbl_campeonato_grupo')->insertGetId(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => 'Chave A']);
        DB::table('tbl_campeonato_time')->whereIn('id_time', [$this->t['Alfa'], $this->t['Beta']])->update(['id_campeonato_grupo' => $grupo]);
        $this->jogo('GRUPOS', 'Alfa', 'Beta', 1, 0);

        $html = $this->comoAdmin()->get(route('admin.campeonatos.participantes', $this->copa))->assertOk()
            ->assertSee('Nenhum time nesta categoria ainda.')   // Sub-13
            ->getContent();
        $linha = function (string $nome) use ($html) {
            preg_match('#<tr data-id-time="' . $this->t[$nome] . '" data-id-categoria="' . $this->idSub15M . '">(.*?)</tr>#s', $html, $achou);

            return $achou[1];
        };
        $this->assertStringContainsString('Chave A', $linha('Alfa'));
        $this->assertStringNotContainsString('Sem jogo', $linha('Alfa'));
        $this->assertStringContainsString('Sem grupo', $linha('Gama'));   // a categoria tem grupos: falta o do Gama
        $this->assertStringContainsString('Sem jogo', $linha('Gama'));
        $this->assertStringContainsString('Sem atletas na AACJ', $linha('Gama'));
    }

    public function test_editar_pela_central_volta_para_ela(): void
    {
        $this->comoAdmin()->get(route('admin.campeonatos.edit', [$this->copa, 'voltar' => 'central']))->assertOk()
            ->assertSee('<input type="hidden" name="voltar" value="central">', false)
            ->assertSee('<a href="' . route('admin.campeonatos.show', $this->copa) . '" class="btn-filter-toggle">', false);
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $this->copa))->assertOk()
            ->assertDontSee('name="voltar"', false);

        $dados = ['nome_campeonato' => 'Copa Central', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'categorias' => [$this->idSub15M], 'participantes' => []];
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [...$dados, 'voltar' => 'central'])
            ->assertRedirect(route('admin.campeonatos.show', $this->copa));
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), $dados)
            ->assertRedirect(route('admin.campeonatos.index'));
    }

    // ---------- auxiliares ----------

    private function comoAdmin()
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function timeExterno(string $nome, string $tipo = 'EXTERNO'): int
    {
        $id = DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
        DB::table('tbl_time_categoria')->insert(['id_time' => $id, 'id_categoria' => $this->idSub15M]);

        return $id;
    }

    // Jogo entre externos (nasce publicado), uma semana atrás
    private function jogo(string $fase, string $casa, string $fora, ?int $golsCasa, ?int $golsFora): Jogo
    {
        [, $jogo] = Jogo::criarComEvento($this->admin->id_usuario, [
            'id_campeonato' => $this->copa, 'fase_jogo' => $fase, 'id_time_casa' => $this->t[$casa], 'id_time_visitante' => $this->t[$fora],
            'placar_time_casa_jogos' => $golsCasa, 'placar_time_visitante_jogos' => $golsFora,
        ], [
            'titulo_evento_calendario' => "{$casa} x {$fora}", 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idSub15M,
            'data_evento_calendario' => today()->subDays(7)->toDateString(), 'horario_inicio_evento_calendario' => '10:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        return $jogo->load('evento');
    }
}
