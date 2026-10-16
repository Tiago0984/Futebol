<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase G, Etapa 2: o jogo de campeonato inscreve os convocados do campeonato (origem CONVOCACAO) no lugar do
 * elenco fixo; o amistoso continua pelo elenco (JogoElencoTest). "Preencher pela convocação", "Não convocado",
 * camisa da convocação na tela do jogo e os avisos quando a convocação muda depois que os jogos existem.
 */
class JogoConvocacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private string $dia;
    private int $idSub15M;
    private int $liga;
    private int $azul;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->dia      = now()->addDays(10)->toDateString();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->liga = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Liga', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'MATA-MATA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Campo A',
        ]);
        $this->azul      = $this->time('Time Azul', 'INTERNO');
        $this->verde     = $this->time('Time Verde', 'INTERNO');
        $this->visitante = $this->time('Time Visitante', 'EXTERNO');

        foreach ([$this->azul, $this->verde, $this->visitante] as $idTime) {
            $this->participantes($this->liga, [$idTime]);
        }
    }

    // ---------- criar ----------

    public function test_criar_jogo_de_campeonato_inscreve_os_convocados_ja_escalados_e_nao_o_elenco(): void
    {
        $ana  = $this->atleta('Ana');
        $bia  = $this->atleta('Bia');
        $caio = $this->atleta('Caio');
        $this->convocar($this->azul, [$ana => 10, $bia => 7]);

        $this->postJogo()
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. 2 atleta(s) da convocação inscrito(s). Os atletas serão avisados ao publicar.')
            ->assertSessionMissing('aviso');

        $this->assertSame([$ana => ['CONVOCACAO', $this->azul], $bia => ['CONVOCACAO', $this->azul]], $this->inscricoes(Jogo::sole()));
    }

    public function test_time_sem_convocados_avisa_ao_criar(): void
    {
        $this->postJogo(['id_time_casa' => $this->verde])
            ->assertSessionHas('aviso', 'Sem convocados: Time Verde. Nenhum atleta foi inscrito por esse time; convoque os atletas'
                . ' (Jogos do campeonato › Convocação) e use "Preencher pela convocação" na tela do jogo, ou inscreva à mão.');
    }

    public function test_criar_jogo_confere_conflito_pelos_convocados(): void
    {
        $ana = $this->atleta('Ana');
        $this->convocar($this->azul, [$ana => 10]);
        $treino = \App\Models\EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $this->dia,
            'horario_inicio_evento_calendario' => '13:30', 'horario_fim_evento_calendario' => '14:30', 'status_evento_calendario' => 'ATIVO',
        ]);
        $treino->inscrever($ana, 'INDIVIDUAL', null, notificar: false);

        $this->postJogo()->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1);
        $this->assertSame(0, Jogo::count());
    }

    // ---------- preencher pela convocação ----------

    public function test_preencher_pela_convocacao_inscreve_quem_foi_convocado_depois(): void
    {
        $ana = $this->atleta('Ana');
        $this->convocar($this->azul, [$ana => 10]);
        $jogo = $this->criarJogo();

        // Convocado depois: a tela de Convocados lembra do jogo futuro; o jogo não recebe sozinho
        $bia = $this->atleta('Bia');
        $this->convocar($this->azul, [$bia => 7])
            ->assertSessionHas('sucesso', '1 atleta(s) convocado(s) pelo Time Azul. O Time Azul já tem 1 jogo(s) futuro(s) neste campeonato:'
                . ' para inscrever os novos, use "Preencher pela convocação" na tela de cada jogo.');
        $this->assertSame([$ana], array_keys($this->inscricoes($jogo)));

        $this->tela($jogo)
            ->assertSee('1</strong> convocado(s) ainda não está inscrito', false)
            ->assertSee('Preencher pela convocação')
            ->assertDontSee('Preencher pelo elenco');

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Escalação pela convocação: 0 inscrito(s) escalado(s), 1 atleta(s) inscrito(s) e escalado(s). 1 atleta(s) notificado(s).');

        $this->assertSame(['CONVOCACAO', $this->azul], $this->inscricoes($jogo)[$bia]);
        $this->tela($jogo)->assertSee('Todos os convocados estão inscritos');
    }

    public function test_atualizar_pela_categoria_no_jogo_de_campeonato_aponta_a_convocacao(): void
    {
        $jogo = $this->criarJogo();

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.inscricoes.atualizar', $jogo->id_evento))
            ->assertSessionHas('erro', 'No jogo de campeonato, os inscritos vêm da convocação: use "Preencher pela convocação".');
    }

    // ---------- trocar time ----------

    public function test_trocar_time_tira_a_convocacao_do_time_que_saiu_e_inscreve_a_do_novo(): void
    {
        $ana = $this->atleta('Ana');
        $bia = $this->atleta('Bia');
        $rui = $this->atleta('Rui');
        $this->convocar($this->azul, [$ana => 10]);
        $this->convocar($this->verde, [$bia => 10]); // a mesma camisa em outro time pode
        $jogo = $this->criarJogo();
        $this->inscreverEscalado($jogo, $rui, $this->azul); // individual: fica, sem time

        $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['id_time_casa' => $this->verde]))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, 'Inscrições pela convocação: 1 atleta(s) inscrito(s), 1 removido(s).'));

        $this->assertSame([
            $bia => ['CONVOCACAO', $this->verde],
            $rui => ['INDIVIDUAL', null],
        ], $this->inscricoes($jogo));
        $this->assertSame(['REMOCAO'], \App\Models\Notificacao::where('id_atleta', $ana)->where('tipo_notificacao', 'REMOCAO')->pluck('tipo_notificacao')->all());
    }

    public function test_inscricao_elenco_antiga_no_jogo_de_campeonato_so_sai_se_convocada_pelo_time_que_saiu(): void
    {
        // Jogo de antes da Etapa 2: inscrito pelo elenco, sem convocação ("ficam como estão")
        $caio = $this->atleta('Caio');
        $jogo = $this->criarJogo();
        DB::table('tbl_evento_atleta')->insert([
            'id_evento_calendario' => $jogo->id_evento, 'id_atleta' => $caio, 'origem_evento_atleta' => 'ELENCO', 'id_time' => $this->azul,
        ]);

        $this->assertSame([], $jogo->idsQueSaemNaTroca([$this->azul], [$this->verde, $this->visitante]));
    }

    // ---------- tela do jogo ----------

    public function test_tela_do_jogo_mostra_camisa_convocado_e_nao_convocado(): void
    {
        $ana = $this->atleta('Ana');
        $rui = $this->atleta('Rui');
        $this->convocar($this->azul, [$ana => 10]);
        $jogo = $this->criarJogo();
        $this->inscreverEscalado($jogo, $rui, $this->azul); // escalado no Azul sem estar convocado

        $html = $this->tela($jogo)->getContent();
        $this->assertStringContainsString('title="Camisa na convocação do campeonato">10</span>', $html);
        $this->assertStringContainsString('Convocado: Time Azul', $html);
        $this->assertStringContainsString('Não convocado', $html);
        $this->assertStringNotContainsString('Fora do elenco', $html);
        $this->assertStringContainsString('title="Entrou automaticamente por estar convocado pelo time neste campeonato.', $html);

        // Jogadores do time no jogo (só leitura): camisa da convocação e a coluna Convocação
        $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.times.show', [$jogo->id_jogo, $this->azul]))->assertOk()
            ->assertSeeInOrder(['Convocação', 'Ana', '10', 'Convocado', 'Rui', 'Não convocado'])
            ->assertDontSee('Status no elenco');
    }

    public function test_inscritos_do_time_ficam_na_ordem_da_camisa(): void
    {
        $ana = $this->atleta('Ana Teste');
        $bia = $this->atleta('Bia Teste');
        $rui = $this->atleta('Rui Teste');
        $this->convocar($this->azul, [$ana => 10, $bia => 7]);
        $jogo = $this->criarJogo();
        $this->inscreverEscalado($jogo, $rui, $this->azul); // sem camisa: no fim

        // Pelo nome seria Ana, Bia, Rui; pela camisa, Bia (7), Ana (10) e Rui (sem camisa)
        $this->tela($jogo)->assertSeeInOrder(['Bia Teste', 'Ana Teste', 'Rui Teste']);
        $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.times.show', [$jogo->id_jogo, $this->azul]))
            ->assertSeeInOrder(['Bia Teste', 'Ana Teste', 'Rui Teste']);
    }

    // ---------- convocação mexida depois ----------

    public function test_remover_da_convocacao_mantem_a_inscricao_e_avisa_os_jogos_futuros(): void
    {
        $ana = $this->atleta('Ana');
        $this->convocar($this->azul, [$ana => 10]);
        $jogo = $this->criarJogo();

        $this->actingAs($this->admin, 'admin')->delete(route('admin.campeonatos.convocados.remover', [$this->liga, $this->azul, $ana]))
            ->assertSessionHas('aviso', 'Ana saiu da convocação do Time Azul. Continua inscrito em 1 jogo(s) futuro(s) do time neste campeonato'
                . ' (marcado como "Não convocado" na tela do jogo); remova a inscrição na tela de cada jogo, se for o caso.');

        $this->assertSame([$ana => ['CONVOCACAO', $this->azul]], $this->inscricoes($jogo));
        $this->tela($jogo)->assertSee('Não convocado');
    }

    public function test_amistoso_nao_usa_a_convocacao_nem_o_elenco(): void
    {
        $caio = $this->atleta('Caio');
        $this->convocar($this->azul, [$this->atleta('Ana') => 10]); // convocação não vale no amistoso

        $this->postJogo(['id_campeonato' => 'AMISTOSO'])
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. Escolha os atletas do Time Azul em "Escolher atletas", nesta tela.'
                . ' Os atletas serão avisados ao publicar.');

        $jogo = Jogo::sole();
        $this->assertSame([], $this->inscricoes($jogo)); // os atletas são escolhidos no jogo (JogoAmistosoTest)
        $this->tela($jogo)->assertSee('Escolher atletas')->assertDontSee('Preencher pela')->assertDontSee('Camisa na convocação');
    }

    // ---------- ajudantes ----------

    private function atleta(string $nome): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idSub15M);

        return $id;
    }

    // Convoca pela tela (POST da caixa "Convocar atletas"): [id_atleta => camisa]
    private function convocar(int $idTime, array $camisas)
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.campeonatos.convocados.convocar', [$this->liga, $idTime]), [
            'atletas' => array_keys($camisas),
            'camisas' => $camisas,
        ])->assertSessionHasNoErrors();
    }

    private function inscreverEscalado(Jogo $jogo, int $idAtleta, int $idTime): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento),
            ['id_atleta' => $idAtleta, 'id_time' => $idTime])->assertSessionHasNoErrors();
    }

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato'                    => $this->liga,
            'id_time_casa'                     => $this->azul,
            'id_time_visitante'                => $this->visitante,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '13:00',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }

    private function postJogo(array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dadosJogo($extra));
    }

    // Cria o jogo da Liga (Azul x Visitante) e publica (a partir daí inscrição e remoção avisam)
    private function criarJogo(): Jogo
    {
        $this->postJogo()->assertSessionHasNoErrors();
        $jogo = Jogo::latest('id_jogo')->first();
        $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.publicar', $jogo->id_jogo))->assertSessionHas('sucesso');

        return $jogo->load('evento');
    }

    private function tela(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk();
    }

    // Inscrições do jogo: [id_atleta => [origem, id_time]]
    private function inscricoes(Jogo $jogo): array
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->orderBy('id_atleta')->get()
            ->mapWithKeys(fn ($i) => [(int) $i->id_atleta => [$i->origem_evento_atleta, $i->id_time === null ? null : (int) $i->id_time]])
            ->all();
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
    }
}
