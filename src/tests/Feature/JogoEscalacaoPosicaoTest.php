<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\EventoAtleta;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\User;
use App\Services\Escalacao;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase D, Etapa D2: posição no jogo e titular/reserva (por jogo) na escalação de cada time da AACJ. Desde
 * 03/11/2026, a tela é um campo (Escalacao::doJogo reconhece a formação pelas posições gravadas), nunca mais de 11
 * titulares (o servidor recusa) e quem está fora do campo é reserva; avisa sem goleiro titular. Trocar de time
 * limpa o titular/reserva; ficar sem time limpa os dois. Sem aviso ao atleta.
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

    public function test_tela_mostra_o_campo_e_salvar_grava_posicao_e_titular(): void
    {
        $ana  = $this->atleta('Ana', ['VOLANTE', 'MEIA_CENTRAL']);
        $bia  = $this->atleta('Bia', []);
        $jogo = $this->amistosoCom([$ana, $bia]);

        // Campo (03/11/2026): formação, os dois na lista e nada gravado até salvar
        $this->tela($jogo)
            ->assertSee('js-campo-dados', false)
            ->assertSee('<option value="4-3-3" selected>4-3-3</option>', false)
            ->assertSee('data-id="' . $ana . '"', false)
            ->assertSee('data-id="' . $bia . '"', false)
            ->assertSee('Sugerir pelo cadastro')
            ->assertSee('Salvar escalação');
        $this->assertNull($this->linha($jogo, $ana)->posicao_evento_atleta);

        // O que o JS manda: o titular com a posição da vaga; quem está fora do campo, reserva sem posição
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.calendario.eventos.escalacao.salvar', [$jogo->id_evento, $this->acj]), [
                'formacao'  => '4-4-2',
                'escalacao' => [
                    $ana => ['posicao' => 'VOLANTE', 'titular' => 'TITULAR'],
                    $bia => ['posicao' => '', 'titular' => 'RESERVA'],
                ],
            ])
            ->assertSessionHas('sucesso', 'Escalação do AACJ Sub-13 salva: 1 titular(es), 1 reserva(s), 0 a definir.')
            ->assertSessionHas('aviso', 'Sem goleiro titular.');

        $this->assertSame(['VOLANTE', 'TITULAR'], $this->valores($jogo, $ana));
        $this->assertSame([null, 'RESERVA'], $this->valores($jogo, $bia));
        $this->assertSame(0, Notificacao::count()); // só no admin

        // Reaberta logo depois: a formação escolhida (o volante cabe nas quatro; no empate, a que foi salva)
        $this->tela($jogo)->assertSee('<option value="4-4-2" selected>4-4-2</option>', false);

        // Vazio volta a "a definir"
        $this->salvar($jogo, $this->acj, [$ana => ['posicao' => '', 'titular' => '']])->assertSessionHasNoErrors();
        $this->assertSame([null, null], $this->valores($jogo, $ana));
    }

    public function test_no_maximo_onze_titulares(): void
    {
        $atletas = array_map(fn ($n) => $this->atleta("Atleta {$n}", ['ZAGUEIRO']), range(1, 12));
        $jogo    = $this->amistosoCom($atletas);
        $todos   = fn (string $titular) => collect($atletas)->mapWithKeys(fn ($id) => [$id => ['posicao' => 'ZAGUEIRO', 'titular' => $titular]])->all();

        $this->salvar($jogo, $this->acj, $todos('TITULAR'))
            ->assertSessionHas('erro', 'O AACJ Sub-13 ficaria com 12 titulares: o máximo é 11. Deixe os outros como reserva.');
        $this->assertSame(0, DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->whereNotNull('titular_evento_atleta')->count());

        // Com 11 titulares e um reserva, salva
        $escalacao = $todos('TITULAR');
        $escalacao[$atletas[11]]['titular'] = 'RESERVA';
        $this->salvar($jogo, $this->acj, $escalacao)
            ->assertSessionHas('sucesso', 'Escalação do AACJ Sub-13 salva: 11 titular(es), 1 reserva(s), 0 a definir.');
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

    public function test_campo_reconhece_a_formacao_pelas_posicoes_gravadas(): void
    {
        $inscricoes = fn (array $titulares) => collect($titulares)->values()->map(fn ($posicao, $i) => (new EventoAtleta)->forceFill([
            'id_atleta' => $i + 1, 'titular_evento_atleta' => 'TITULAR', 'posicao_evento_atleta' => $posicao,
        ]));

        // As posições de um 3-5-2 completo: a formação volta e cada um na vaga da posição dele
        $tres = ['GOLEIRO', 'ZAGUEIRO', 'LIBERO', 'ZAGUEIRO', 'MEIA_ESQUERDA', 'VOLANTE', 'MEIA_OFENSIVO', 'VOLANTE',
            'MEIA_DIREITA', 'CENTROAVANTE', 'SEGUNDO_ATACANTE'];
        $campo = Escalacao::doJogo($inscricoes($tres));
        $this->assertSame('3-5-2', $campo['formacao']);
        $this->assertSame(range(1, 11), $campo['vagas']);
        $this->assertSame([], $campo['sem_vaga']);

        // Só o goleiro: empata em todas; vale a escolhida na tela (ou a primeira)
        $this->assertSame('4-3-3', Escalacao::doJogo($inscricoes(['GOLEIRO']))['formacao']);
        $this->assertSame('4-2-3-1', Escalacao::doJogo($inscricoes(['GOLEIRO']), '4-2-3-1')['formacao']);

        // Doze titulares gravados antes do limite: um fica sem vaga (vira reserva ao salvar)
        $this->assertCount(1, Escalacao::doJogo($inscricoes(['GOLEIRO', ...array_fill(0, 11, 'ZAGUEIRO')]))['sem_vaga']);

        $this->assertSame(['Sem goleiro titular.'], Jogo::resumoDaEscalacao($inscricoes(['ZAGUEIRO']))['avisos']);
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
