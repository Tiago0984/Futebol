<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase D, Etapa D2: posição no jogo e titular/reserva (por jogo) na escalação de cada time da AACJ. A tela
 * sugere a posição principal do atleta (pré-selecionada, só gravada no "Salvar escalação"), divide o time em
 * Titulares, Reservas e A definir, conta os titulares (5 futsal, 7 society, 11 campo) e avisa sem goleiro titular
 * e acima de 11. Trocar de time limpa o titular/reserva; ficar sem time limpa os dois. Sem aviso ao atleta.
 */
class JogoEscalacaoPosicaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub13M;
    private int $acj;
    private int $acjB;
    private int $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->acj      = $this->time('AACJ Sub-13', 'INTERNO');
        $this->acjB     = $this->time('AACJ Sub-13 B', 'INTERNO');
        $this->rival    = $this->time('Rival FC', 'EXTERNO');
    }

    public function test_tela_sugere_a_principal_e_salvar_grava_posicao_e_titular(): void
    {
        $ana  = $this->atleta('Ana', ['VOLANTE', 'MEIA_CENTRAL']);
        $bia  = $this->atleta('Bia', []);
        $jogo = $this->amistosoCom([$ana, $bia]);

        // Sugerida: pré-selecionada, mas nada gravado
        $this->tela($jogo)
            ->assertSee('<option value="VOLANTE" selected>VOL · Volante (sugerida)</option>', false)
            ->assertSee('Salvar escalação')
            ->assertSee('A definir (2)');
        $this->assertNull($this->linha($jogo, $ana)->posicao_evento_atleta);

        $this->salvar($jogo, $this->acj, [
            $ana => ['posicao' => 'VOLANTE', 'titular' => 'TITULAR'],
            $bia => ['posicao' => 'GOLEIRO', 'titular' => 'RESERVA'],
        ])->assertSessionHas('sucesso', 'Escalação do AACJ Sub-13 salva: 1 titular(es), 1 reserva(s), 0 a definir.')
          ->assertSessionHas('aviso', 'Sem goleiro titular.');

        $this->assertSame(['VOLANTE', 'TITULAR'], $this->valores($jogo, $ana));
        $this->assertSame(['GOLEIRO', 'RESERVA'], $this->valores($jogo, $bia));
        $this->assertSame(0, Notificacao::count()); // só no admin

        // Gravada: sem "(sugerida)"; grupos Titulares e Reservas
        $this->tela($jogo)
            ->assertSee('<option value="VOLANTE" selected>VOL · Volante</option>', false)
            ->assertDontSee('(sugerida)')
            ->assertSee('Titulares (1)')
            ->assertSee('Reservas (1)');

        // Vazio volta a "a definir"
        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => '', 'titular' => '']])->assertSessionHasNoErrors();
        $this->assertSame([null, null], $this->valores($jogo, $ana));
    }

    public function test_valores_invalidos_e_time_que_nao_e_da_aacj_sao_recusados(): void
    {
        $ana  = $this->atleta('Ana', []);
        $jogo = $this->amistosoCom([$ana]);

        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => 'MEIA', 'titular' => 'TITULAR']])
            ->assertSessionHasErrors('escalacao.' . $ana . '.posicao');
        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => 'VOLANTE', 'titular' => 'CAPITAO']])
            ->assertSessionHasErrors('escalacao.' . $ana . '.titular');
        $this->salvar($jogo, $this->rival, [$ana => ['posicao' => 'VOLANTE', 'titular' => 'TITULAR']])
            ->assertSessionHas('erro', 'Este time não é um time da AACJ neste jogo: não tem escalação.');
        $this->assertSame([null, null], $this->valores($jogo, $ana));
    }

    public function test_atleta_de_outro_time_ou_de_fora_do_jogo_e_ignorado(): void
    {
        $ana  = $this->atleta('Ana', []);
        $ivo  = $this->atleta('Ivo', []); // não está no jogo
        $jogo = $this->amistosoCom([$ana]);
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->update(['id_time' => null]);

        $this->salvar($jogo, $this->acj, [
            $ana => ['posicao' => 'VOLANTE', 'titular' => 'TITULAR'],
            $ivo => ['posicao' => 'GOLEIRO', 'titular' => 'TITULAR'],
        ])->assertSessionHas('sucesso', 'Escalação do AACJ Sub-13 salva: 0 titular(es), 0 reserva(s), 0 a definir.');

        $this->assertSame([null, null], $this->valores($jogo, $ana));
        $this->assertSame(0, DB::table('tbl_evento_atleta')->where('id_atleta', $ivo)->count());
    }

    public function test_trocar_de_time_limpa_o_titular_e_sem_time_limpa_os_dois(): void
    {
        $ana  = $this->atleta('Ana', []);
        $jogo = $this->campeonatoCom([$ana]);
        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => 'VOLANTE', 'titular' => 'TITULAR']]);

        // O mesmo time de novo: nada muda
        $this->escalar($jogo, $ana, $this->acj);
        $this->assertSame(['VOLANTE', 'TITULAR'], $this->valores($jogo, $ana));

        $this->escalar($jogo, $ana, $this->acjB);
        $this->assertSame(['VOLANTE', null], $this->valores($jogo, $ana));

        $this->salvar($jogo, $this->acjB, [$ana => ['posicao' => 'VOLANTE', 'titular' => 'RESERVA']]);
        $this->escalar($jogo, $ana, null);
        $this->assertSame([null, null], $this->valores($jogo, $ana));
    }

    public function test_troca_do_time_da_aacj_no_amistoso_limpa_o_titular(): void
    {
        $ana  = $this->atleta('Ana', []);
        $jogo = $this->amistosoCom([$ana]);
        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => 'ZAGUEIRO', 'titular' => 'TITULAR']]);

        $jogo->update(['id_time_casa' => $this->acjB]);
        $jogo->refresh()->escalarNoTimeDaAcj();

        $this->assertSame(['ZAGUEIRO', null], $this->valores($jogo, $ana));
        $this->assertSame($this->acjB, (int) $this->linha($jogo, $ana)->id_time);
    }

    public function test_contador_de_titulares_por_modalidade_e_acima_de_onze(): void
    {
        $vazia = fn (array $titulares) => collect($titulares)->map(fn ($posicao) => (object) [
            'titular_evento_atleta' => 'TITULAR', 'posicao_evento_atleta' => $posicao,
        ]);

        $cinco = Jogo::resumoDaEscalacao($vazia(['GOLEIRO', 'ZAGUEIRO', 'VOLANTE', 'MEIA_CENTRAL', 'CENTROAVANTE']));
        $this->assertSame('futsal', $cinco['modalidade']);
        $this->assertSame([], $cinco['avisos']);

        $this->assertSame('society', Jogo::resumoDaEscalacao($vazia(['GOLEIRO', ...array_fill(0, 6, 'ZAGUEIRO')]))['modalidade']);
        $this->assertSame('campo', Jogo::resumoDaEscalacao($vazia(['GOLEIRO', ...array_fill(0, 10, 'ZAGUEIRO')]))['modalidade']);

        $doze = Jogo::resumoDaEscalacao($vazia(['GOLEIRO', ...array_fill(0, 11, 'ZAGUEIRO')]));
        $this->assertNull($doze['modalidade']);
        $this->assertSame(['Mais de 11 titulares (o número do campo).'], $doze['avisos']);

        $this->assertSame(['Sem goleiro titular.'], Jogo::resumoDaEscalacao($vazia(['ZAGUEIRO']))['avisos']);
    }

    public function test_tela_ordena_pela_posicao_e_jogadores_do_time_mostram_posicao_e_titular(): void
    {
        $ataque = $this->atleta('Aldo', ['CENTROAVANTE']);
        $gol    = $this->atleta('Zeca', ['GOLEIRO']);
        $jogo   = $this->amistosoCom([$ataque, $gol]);

        // Sem nada gravado: ordem pela posição sugerida (goleiro antes do ataque, mesmo com o nome depois)
        $html = $this->tela($jogo)->getContent();
        $this->assertLessThan(strpos($html, 'Aldo'), strpos($html, 'Zeca'));

        $this->salvar($jogo, $this->acj, [
            $ataque => ['posicao' => 'CENTROAVANTE', 'titular' => 'TITULAR'],
            $gol    => ['posicao' => 'GOLEIRO', 'titular' => 'RESERVA'],
        ]);

        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.times.show', [$jogo->id_jogo, $this->acj]))
            ->assertOk()
            ->assertSee('CA · Centroavante')
            ->assertSee('GOL · Goleiro')
            ->assertSee('Titular')
            ->assertSee('Reserva')
            ->getContent();
        $this->assertLessThan(strpos($html, 'Zeca'), strpos($html, 'Aldo')); // titular antes do reserva
    }

    // ---------- apoio ----------

    private function atleta(string $nome, array $posicoes): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idSub13M);
        Atleta::find($id)->definirPosicoes($posicoes);

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

    // Jogo de campeonato AACJ x AACJ B (os dois escaláveis), com os atletas convocados pela AACJ
    private function campeonatoCom(array $atletas): Jogo
    {
        $campeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub13M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'TORNEIO',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Arena', 'status_campeonato' => 'ATIVO',
        ]);
        $this->participantes($campeonato, [$this->acj, $this->acjB]);
        foreach ($atletas as $i => $atleta) {
            $this->convocacaoDireta($campeonato, $this->acj, $atleta, $i + 1);
        }

        $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), [
            'id_campeonato' => $campeonato, 'id_categoria' => $this->idSub13M, 'fase_jogo' => 'GRUPOS',
            'id_time_casa' => $this->acj, 'id_time_visitante' => $this->acjB,
            'data_evento_calendario' => now()->addDays(10)->toDateString(), 'horario_inicio_evento_calendario' => '19:00',
            'local_evento_calendario' => 'Campo A',
        ])->assertSessionHasNoErrors();

        return Jogo::latest('id_jogo')->first();
    }

    private function salvar(Jogo $jogo, int $idTime, array $escalacao)
    {
        return $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.calendario.eventos.escalacao.salvar', [$jogo->id_evento, $idTime]), ['escalacao' => $escalacao]);
    }

    private function escalar(Jogo $jogo, int $idAtleta, ?int $idTime)
    {
        return $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.calendario.eventos.inscricoes.time', [$jogo->id_evento, $idAtleta]), ['id_time' => $idTime])
            ->assertSessionHas('sucesso');
    }

    private function tela(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk();
    }

    private function linha(Jogo $jogo, int $idAtleta): object
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->where('id_atleta', $idAtleta)->first();
    }

    private function valores(Jogo $jogo, int $idAtleta): array
    {
        $linha = $this->linha($jogo, $idAtleta);

        return [$linha->posicao_evento_atleta, $linha->titular_evento_atleta];
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub13M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
