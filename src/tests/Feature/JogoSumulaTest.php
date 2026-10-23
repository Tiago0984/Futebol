<?php

namespace Tests\Feature;

use App\Models\Cartao;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\JogoGol;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Súmula do jogo (Etapa S1, 22/10/2026): gols dos times da AACJ (autor e assistência entre os escalados, gol contra),
 * cartões, sem passar do placar, e o placar e os times travados enquanto houver súmula. Só no admin, sem aviso.
 */
class JogoSumulaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub13M;
    private int $acj;
    private int $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->acj      = $this->time('AACJ Sub-13', 'INTERNO');
        $this->rival    = $this->time('Rival FC', 'EXTERNO');
    }

    public function test_sem_placar_a_tela_pede_o_placar_e_nao_lanca(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->amistosoCom([$ana]);

        $this->tela($jogo)->assertSee('Súmula')->assertSee('Lance o placar do jogo');
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $ana])->assertSessionHasErrorsIn('sumula', 'id_time');
        $this->assertSame(0, JogoGol::count());
    }

    public function test_lanca_gol_com_autor_e_assistencia_ate_o_placar(): void
    {
        [$ana, $bia] = [$this->atleta('Ana'), $this->atleta('Bia')];
        $jogo = $this->amistosoCom([$ana, $bia]);
        $this->placar($jogo, 2, 1)->assertSessionHasNoErrors();

        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $ana, 'id_atleta_assistencia' => $bia, 'minuto_jogo_gol' => 12])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Gol lançado na súmula. Falta(m) 1 gol(s) do time sem autor lançado.');

        $gol = JogoGol::sole();
        $this->assertSame([$ana, $bia, 12, $this->admin->id_usuario], [$gol->id_atleta, $gol->id_atleta_assistencia, $gol->minuto_jogo_gol, $gol->id_usuario]);
        $this->tela($jogo)->assertSee('Ana')->assertSee('1 lançado(s)')->assertSee('falta(m) 1');

        // Segundo gol sem autor identificado; o terceiro passa do placar
        $this->gol($jogo, ['id_time' => $this->acj])->assertSessionHasNoErrors()->assertSessionHas('sucesso', 'Gol lançado na súmula.');
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $bia])->assertSessionHasErrorsIn('sumula', 'id_time');
        $this->assertSame(2, JogoGol::count());
        $this->assertSame(0, Notificacao::count()); // só no admin
    }

    public function test_regras_do_autor_da_assistencia_do_adversario_e_da_prorrogacao(): void
    {
        [$ana, $bia] = [$this->atleta('Ana'), $this->atleta('Bia')];
        $fora = $this->atleta('Fora do jogo');
        $jogo = $this->amistosoCom([$ana, $bia]);
        $this->placar($jogo, 3, 3);

        $this->gol($jogo, ['id_time' => $this->rival])->assertSessionHasErrorsIn('sumula', 'id_time');                            // gol do adversário
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $fora])->assertSessionHasErrorsIn('sumula', 'id_atleta');       // não escalado
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $ana, 'id_atleta_assistencia' => $ana])
            ->assertSessionHasErrorsIn('sumula', 'id_atleta_assistencia');                                                          // assistência do autor
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta_assistencia' => $bia])->assertSessionHasErrorsIn('sumula', 'id_atleta_assistencia');
        $this->gol($jogo, ['id_time' => $this->acj, 'periodo_jogo_gol' => 'PRORROGACAO'])->assertSessionHasErrorsIn('sumula', 'periodo_jogo_gol');
        $this->gol($jogo, ['id_time' => $this->acj, 'minuto_jogo_gol' => 200])->assertSessionHasErrorsIn('sumula', 'minuto_jogo_gol');
        $this->assertSame(0, JogoGol::count());
    }

    public function test_gol_contra_do_adversario_e_de_atleta_da_aacj(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->amistosoCom([$ana]);
        $this->placar($jogo, 1, 1);

        // O adversário fez contra: gol da AACJ, sem autor
        $this->gol($jogo, ['id_time' => $this->acj, 'tipo_jogo_gol' => 'CONTRA'])->assertSessionHasNoErrors();
        // A Ana fez contra: gol do Rival, com ela de autora (não conta como gol dela)
        $this->gol($jogo, ['id_time' => $this->rival, 'tipo_jogo_gol' => 'CONTRA', 'id_atleta' => $ana])->assertSessionHasNoErrors();
        // No gol contra, o autor joga pelo time que sofreu o gol, e não há assistência
        $this->gol($jogo, ['id_time' => $this->acj, 'tipo_jogo_gol' => 'CONTRA', 'id_atleta' => $ana])->assertSessionHasErrorsIn('sumula', 'id_atleta');

        $contra = JogoGol::where('id_time', $this->rival)->sole();
        $this->assertSame($ana, $contra->id_atleta);
        $this->assertFalse($contra->contaParaOAutor());
        $this->tela($jogo)->assertSee('Gol contra')->assertSee('Adversário');
    }

    public function test_placar_e_times_nao_ficam_abaixo_da_sumula(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->amistosoCom([$ana]);
        $this->placar($jogo, 2, 0);
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $ana]);
        $this->gol($jogo, ['id_time' => $this->acj, 'id_atleta' => $ana]);

        $this->placar($jogo, 1, 0)->assertSessionHasErrors('placar_time_casa_jogos');
        $this->placar($jogo, null, null)->assertSessionHasErrors('placar_time_casa_jogos');
        $this->placar($jogo, 3, 0)->assertSessionHasNoErrors(); // subir pode

        $jogo->refresh();
        $outro = $this->time('Outro FC', 'EXTERNO');
        $this->assertStringContainsString('antes de trocar os times', $jogo->erroDaSumulaNaEdicao([
            'id_time_casa' => $this->acj, 'id_time_visitante' => $outro, 'placar_time_casa_jogos' => 3, 'placar_time_visitante_jogos' => 0,
        ]));

        // Removido o gol, o placar pode descer até o que sobrou
        $this->actingAs($this->admin, 'admin')->delete(route('admin.jogos.gols.destroy', [$jogo->id_jogo, JogoGol::first()->id_jogo_gol]))
            ->assertSessionHas('sucesso', 'Gol removido da súmula.');
        $this->placar($jogo, 1, 0)->assertSessionHasNoErrors();
    }

    public function test_cartoes_so_de_escalado_com_limite_de_amarelos_e_vermelho(): void
    {
        $ana  = $this->atleta('Ana');
        $fora = $this->atleta('Fora do jogo');
        $jogo = $this->amistosoCom([$ana]);
        $this->placar($jogo, 0, 0);

        $cartao = fn (int $idAtleta, string $tipo) => $this->actingAs($this->admin, 'admin')
            ->post(route('admin.jogos.cartoes.store', $jogo->id_jogo), ['id_atleta' => $idAtleta, 'tipo_cartao' => $tipo]);

        $cartao($ana, 'AMARELO')->assertSessionHasNoErrors()->assertSessionHas('sucesso', 'Cartão lançado na súmula.');
        $cartao($ana, 'AMARELO')->assertSessionHasNoErrors();
        $cartao($ana, 'AMARELO')->assertSessionHasErrorsIn('sumula', 'id_atleta');
        $cartao($ana, 'VERMELHO')->assertSessionHasNoErrors();
        $cartao($ana, 'VERMELHO')->assertSessionHasErrorsIn('sumula', 'id_atleta');
        $cartao($fora, 'AMARELO')->assertSessionHasErrorsIn('sumula', 'id_atleta');

        $this->assertSame(3, Cartao::count());
        $this->assertSame([$this->admin->id_usuario], Cartao::pluck('id_usuario')->unique()->values()->all());
        $this->tela($jogo)->assertSee('Amarelo')->assertSee('Vermelho');

        $this->actingAs($this->admin, 'admin')->delete(route('admin.jogos.cartoes.destroy', [$jogo->id_jogo, Cartao::first()->id_cartao]))
            ->assertSessionHas('sucesso', 'Cartão removido da súmula.');
        $this->assertSame(2, Cartao::count());
    }

    public function test_jogo_sem_time_da_aacj_nao_tem_sumula(): void
    {
        // Dois externos (num campeonato, nasce assim): ninguém da AACJ para lançar
        $evento = EventoCalendario::criarPor(null, inscreverCategoria: false, dados: [
            'titulo_evento_calendario' => 'Rival FC x Outro FC', 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => now()->subDay()->toDateString(), 'local_evento_calendario' => 'Arena',
        ]);
        $jogo = Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_time_casa' => $this->rival, 'id_time_visitante' => $this->time('Outro FC', 'EXTERNO'),
            'placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 0,
        ]);

        $this->tela($jogo)->assertDontSee('id="sumula"', false);
        $this->gol($jogo, ['id_time' => $this->rival])->assertSessionHasErrorsIn('sumula', 'id_time');
    }

    // ── Ajudantes ───────────────────────────────────────────────────────────

    private function gol(Jogo $jogo, array $dados)
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.gols.store', $jogo->id_jogo),
            $dados + ['tipo_jogo_gol' => 'NORMAL', 'periodo_jogo_gol' => 'TEMPO_NORMAL']);
    }

    private function placar(Jogo $jogo, ?int $casa, ?int $visitante)
    {
        return $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.placar', $jogo->id_jogo), [
            'placar_time_casa_jogos' => $casa, 'placar_time_visitante_jogos' => $visitante,
        ]);
    }

    private function atleta(string $nome): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idSub13M);

        return $id;
    }

    // Amistoso AACJ x Rival com os atletas escolhidos (todos escalados no time da AACJ)
    private function amistosoCom(array $atletas): Jogo
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), [
            'id_campeonato' => 'AMISTOSO', 'id_categoria' => $this->idSub13M,
            'id_time_casa' => $this->acj, 'id_time_visitante' => $this->rival,
            'data_evento_calendario' => now()->addDays(10)->toDateString(), 'horario_inicio_evento_calendario' => '19:00',
            'local_evento_calendario' => 'Campo A',
        ])->assertSessionHasNoErrors();
        $jogo = Jogo::latest('id_jogo')->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.calendario.eventos.escalacao.escolher', $jogo->id_evento), ['atletas' => $atletas])
            ->assertSessionHasNoErrors();

        return $jogo;
    }

    private function tela(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk();
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub13M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
