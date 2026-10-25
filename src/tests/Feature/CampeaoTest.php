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
 * Fase I, Etapa I4 (dono do projeto, 21/10/2026): o campeão de cada categoria, calculado. Com final: o vencedor
 * dela (vice e 3º lugar). Pontos corridos: o 1º da tabela única com todos os jogos publicados e com placar; empate
 * em tudo no topo = "a definir (sorteio)". Mostrado no site (página, lista e home), no admin e na API.
 */
class CampeaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $copa;
    private array $t = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->copa     = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => 'Copa Campeã', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => today()->subMonth()->toDateString(), 'data_fim_campeonato' => today()->addMonth()->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        foreach (['Alfa', 'Beta', 'Gama', 'Delta'] as $nome) {
            $this->t[$nome] = DB::table('tbl_time')->insertGetId([
                'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO', 'status_time' => 'ATIVO',
            ]);
        }
        $this->participantes($this->copa, array_values($this->t), $this->idSub15M);
    }

    public function test_pontos_corridos_o_primeiro_com_todos_os_jogos_jogados(): void
    {
        $ultimo = null;
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $ultimo = $this->jogo('GRUPOS', $casa, $fora, 1, 0);
        }

        // Falta um placar: ainda não há campeão
        $ultimo->update(['placar_time_casa_jogos' => null, 'placar_time_visitante_jogos' => null]);
        $this->assertNull($this->campeao());
        $this->get(route('campeonato.show', $this->copa))->assertOk()->assertDontSee('camp-campeao-card', false);

        // Jogo com placar mas em rascunho: continua pendente
        $ultimo->update(['placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 0]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $ultimo->id_evento)->update(['data_publicacao_evento_calendario' => null]);
        $this->assertNull($this->campeao());

        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $ultimo->id_evento)->update(['data_publicacao_evento_calendario' => now()]);

        // Todos jogados, mas pode vir um returno: só com a categoria encerrada pelo admin
        $this->assertNull($this->campeao());
        $this->get(route('campeonato.show', $this->copa))->assertOk()->assertDontSee('camp-campeao-card', false);
        $this->actingAs($this->admin, 'admin')->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()->assertSee('Encerrar categoria');
        $this->encerramento()->assertSessionHas('sucesso');

        $campeao = $this->campeao();
        $this->assertSame(['campeao', 'tabela', 'Alfa', '1º na tabela (pontos corridos)'],
            [$campeao['situacao'], $campeao['modo'], $campeao['campeao']['nome'], $campeao['como']]);

        // Site: destaque na página, selo na tabela e o nome na lista e na home
        $this->get(route('campeonato.show', $this->copa))->assertOk()
            ->assertSee('Campeão: Alfa')->assertSee('1º na tabela (pontos corridos)')->assertSee('camp-campeao-tag', false);
        $this->get(route('campeonato'))->assertOk()->assertSee('Campeão: <strong>Alfa</strong>', false);
        $this->get(route('home'))->assertOk()->assertSee('Campeão: <strong>Alfa</strong>', false);

        // Admin e API
        $this->actingAs($this->admin, 'admin')->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()->assertSee('Campeão: Alfa');
        $this->getJson("/api/v1/campeonatos/{$this->copa}")->assertOk()
            ->assertJsonPath('data.classificacao.0.campeao.situacao', 'campeao')
            ->assertJsonPath('data.classificacao.0.campeao.modo', 'tabela')
            ->assertJsonPath('data.classificacao.0.campeao.campeao.nome', 'Alfa')
            ->assertJsonPath('data.classificacao.0.campeao.vice', null);
    }

    public function test_encerrar_reabrir_e_jogo_novo_depois_de_encerrar(): void
    {
        $jogo = $this->jogo('GRUPOS', 'Alfa', 'Beta', null, null);

        // Jogo sem placar: recusa
        $this->encerramento()->assertSessionHas('erro', 'Não dá para encerrar: Ainda há 1 jogo(s) da fase de grupos sem placar ou em rascunho.');
        $this->assertNull($this->encerradaEm());

        $jogo->update(['placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 0]);
        $this->encerramento();
        $this->assertNotNull($this->encerradaEm());
        $this->assertSame('Alfa', $this->campeao()['campeao']['nome']);

        // Um returno cadastrado depois: o campeão some até o placar, e o admin vê o motivo
        $returno = $this->jogo('GRUPOS', 'Beta', 'Alfa', null, null);
        $this->assertNull($this->campeao());
        $this->actingAs($this->admin, 'admin')->get(route('admin.campeonatos.grupos', $this->copa))
            ->assertSee('O campeão não aparece: Ainda há 1 jogo(s)');
        $returno->update(['placar_time_casa_jogos' => 0, 'placar_time_visitante_jogos' => 0]);
        $this->assertSame('Alfa', $this->campeao()['campeao']['nome']);

        // Reabrir tira o campeão
        $this->encerramento('reabrir')->assertSessionHas('sucesso');
        $this->assertNull($this->encerradaEm());
        $this->assertNull($this->campeao());
    }

    public function test_com_classificados_por_grupo_o_campeao_sai_da_final(): void
    {
        $this->jogo('GRUPOS', 'Alfa', 'Beta', 1, 0);
        Campeonato::find($this->copa)->definirClassificados($this->idSub15M, 2);

        $this->encerramento()->assertSessionHas('erro', fn ($erro) => str_contains($erro, 'ainda vem o mata-mata'));
        $this->assertNull($this->encerradaEm());
        $this->actingAs($this->admin, 'admin')->get(route('admin.campeonatos.grupos', $this->copa))->assertDontSee('Encerrar categoria');

        // Mesmo encerrada antes de definir os classificados, a tabela não declara campeão
        Campeonato::find($this->copa)->definirEncerramento($this->idSub15M, true);
        $this->assertNull($this->campeao());
    }

    public function test_empate_em_tudo_no_primeiro_lugar_fica_a_definir(): void
    {
        $this->jogo('GRUPOS', 'Alfa', 'Beta', 1, 1);
        $this->encerramento();

        $campeao = $this->campeao();
        $this->assertSame(['sorteio', ['Alfa', 'Beta']], [$campeao['situacao'], $campeao['empatados']]);
        $this->assertSame('a definir (sorteio entre Alfa e Beta)', Campeonato::textoDoCampeao($campeao));

        $this->get(route('campeonato.show', $this->copa))->assertOk()->assertSee('Campeão a definir')->assertSee('Sorteio entre Alfa e Beta');
        $this->actingAs($this->admin, 'admin')->get(route('admin.campeonatos.grupos', $this->copa))->assertSee('sorteio entre Alfa e Beta');
    }

    public function test_com_final_o_vencedor_dela_com_vice_e_terceiro(): void
    {
        $this->jogo('GRUPOS', 'Alfa', 'Beta', 3, 0);
        $this->jogo('SEMIFINAL', 'Alfa', 'Delta', 2, 0);
        $this->jogo('SEMIFINAL', 'Beta', 'Gama', 0, 1);

        // Mata-mata sem final: em andamento (a tabela completa não vale)
        $this->assertNull($this->campeao());

        $final = $this->jogo('FINAL', 'Alfa', 'Gama', null, null);
        $this->assertNull($this->campeao(), 'final sem placar');

        $final->update(['placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 1, 'prorrogacao_time_casa_jogos' => 0,
            'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 5]);
        $this->jogo('TERCEIRO', 'Beta', 'Delta', 2, 0);

        $campeao = $this->campeao();
        $this->assertSame(['campeao', 'final', 'Gama', 'Alfa', 'Beta', 'Final: Gama vence nos pênaltis (4 × 5)'],
            [$campeao['situacao'], $campeao['modo'], $campeao['campeao']['nome'], $campeao['vice']['nome'], $campeao['terceiro']['nome'], $campeao['como']]);

        $this->get(route('campeonato.show', $this->copa))->assertOk()
            ->assertSee('Campeão: Gama')->assertSee('Vice: Alfa')->assertSee('3º lugar: Beta')->assertDontSee('camp-campeao-tag', false);

        // Final em rascunho não vale
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $final->id_evento)->update(['data_publicacao_evento_calendario' => null]);
        $this->assertNull($this->campeao());
    }

    public function test_grupos_sem_mata_mata_nao_tem_campeao(): void
    {
        foreach (['Grupo A' => ['Alfa', 'Beta'], 'Grupo B' => ['Gama', 'Delta']] as $nome => $times) {
            $id = DB::table('tbl_campeonato_grupo')->insertGetId(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => $nome]);
            DB::table('tbl_campeonato_time')->whereIn('id_time', array_map(fn ($n) => $this->t[$n], $times))->update(['id_campeonato_grupo' => $id]);
        }
        $this->jogo('GRUPOS', 'Alfa', 'Beta', 1, 0);
        $this->jogo('GRUPOS', 'Gama', 'Delta', 1, 0);

        $this->encerramento()->assertSessionHas('erro', 'Não dá para encerrar: Com mais de um grupo (chave), o campeão sai do mata-mata.');
        $this->assertNull($this->campeao());
    }

    private function encerramento(?string $acao = null)
    {
        return $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.campeonatos.grupos.encerramento', [$this->copa, $this->idSub15M]), array_filter(['acao' => $acao]));
    }

    private function encerradaEm(): ?string
    {
        return DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->copa)->where('id_categoria', $this->idSub15M)
            ->value('data_encerramento_campeonato_categoria');
    }

    // ---------- auxiliares ----------

    private function campeao(): ?array
    {
        return Campeonato::find($this->copa)->campeoes()[$this->idSub15M] ?? null;
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
