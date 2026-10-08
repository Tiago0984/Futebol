<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Jogo inscreve o elenco (tbl_atleta_time) dos times internos que jogam, não a categoria inteira
 * (CLAUDE.md, seção 4, "Jogos"). Origem ELENCO, já com o time; quem está nos dois elencos entra sem time.
 * Troca de time: sai quem veio pelo elenco do time que saiu, entra o elenco do novo, as INDIVIDUAL ficam.
 */
class JogoElencoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private string $dia;
    private int $idSub11M;
    private int $azul;
    private int $verde;
    private int $preto;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->admin()->create();
        $this->dia       = now()->addDays(10)->toDateString();
        $this->idSub11M  = $this->idCategoria('Sub-11', 'M');
        $this->azul      = $this->time('Time Azul', 'INTERNO');
        $this->verde     = $this->time('Time Verde', 'INTERNO');
        $this->preto     = $this->time('Time Preto', 'INTERNO');
        $this->visitante = $this->time('Time Visitante', 'EXTERNO');
    }

    // ---------- criar ----------

    public function test_criar_inscreve_o_elenco_dos_times_internos_ja_com_o_time(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $bia  = $this->atleta('Bia', [$this->verde]);
        $caio = $this->atleta('Caio', [$this->azul, $this->verde]);  // nos dois: entra sem time
        $this->atleta('Davi', [$this->preto]);                        // time que não joga
        $this->atleta('Eva');                                         // da categoria, sem elenco
        $this->atleta('Fabio', [$this->azul], 'INATIVO');

        $this->postJogo($this->azul, $this->verde)
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. 3 atleta(s) do elenco inscrito(s). 1 atleta(s) estão nos elencos dos dois times:'
                . ' escolha o time de cada um na tela do jogo. Os atletas serão avisados ao publicar.')
            ->assertSessionMissing('aviso');

        $jogo = Jogo::sole();
        $this->assertSame([
            $ana  => ['ELENCO', $this->azul],
            $bia  => ['ELENCO', $this->verde],
            $caio => ['ELENCO', null],
        ], $this->inscricoesDo($jogo));
        $this->assertSame(0, Notificacao::count()); // rascunho: ninguém avisado ainda

        // Ao publicar, cada um recebe uma INSCRICAO só, com o time da escalação (Caio, sem time, sem a frase)
        $this->publicar($jogo)->assertSessionHas('sucesso', 'Jogo publicado. 3 atleta(s) notificado(s).');
        $this->assertSame([$ana => 'INSCRICAO', $bia => 'INSCRICAO', $caio => 'INSCRICAO'], $this->notificacoesPorAtleta());
        $mensagens = Notificacao::pluck('mensagem_notificacao', 'id_atleta');
        $this->assertStringEndsWith('. Você joga pelo Time Azul.', $mensagens[$ana]);
        $this->assertStringEndsWith('. Você joga pelo Time Verde.', $mensagens[$bia]);
        $this->assertStringNotContainsString('Você joga', $mensagens[$caio]);
    }

    public function test_time_interno_sem_elenco_fica_sem_inscritos_daquele_lado_e_avisa(): void
    {
        $ana = $this->atleta('Ana', [$this->azul]);
        $this->atleta('Fabio', [$this->preto], 'INATIVO'); // o Preto só tem atleta inativo

        $this->postJogo($this->azul, $this->preto)
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. 1 atleta(s) do elenco inscrito(s). Os atletas serão avisados ao publicar.')
            ->assertSessionHas('aviso', 'Sem elenco cadastrado: Time Preto. Nenhum atleta foi inscrito por esse time;'
                . ' cadastre o elenco e use "Preencher pelo elenco" na tela do jogo, ou inscreva à mão.');

        $this->assertSame([$ana => ['ELENCO', $this->azul]], $this->inscricoesDo(Jogo::sole()));
    }

    public function test_conflito_na_criacao_confere_so_o_elenco(): void
    {
        $ana = $this->atleta('Ana', [$this->azul]);
        $eva = $this->atleta('Eva'); // da categoria, fora do elenco: não entra, não conflita
        $treino = $this->treinoNoHorarioDoJogo();
        $treino->inscrever($ana, 'INDIVIDUAL', null);
        $treino->inscrever($eva, 'INDIVIDUAL', null);

        $this->postJogo($this->azul, $this->visitante)
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1 && str_contains($p['fortes'][0], 'Ana'));
        $this->assertSame(0, Jogo::count());

        $this->postJogo($this->azul, $this->visitante, ['confirmar_conflito' => 1])->assertSessionHas('sucesso');
        $this->assertSame(1, Jogo::count());
    }

    // ---------- trocar time ----------

    public function test_trocar_time_tira_o_elenco_do_que_saiu_e_inscreve_o_do_novo(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $bia  = $this->atleta('Bia', [$this->verde]);
        $caio = $this->atleta('Caio', [$this->azul, $this->verde]);
        $eva  = $this->atleta('Eva');
        $jogo = $this->criarJogo($this->azul, $this->visitante);
        // Convidada pelo admin, escalada no Azul: é INDIVIDUAL, fica (sem time)
        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento),
            ['id_atleta' => $eva, 'id_time' => $this->azul]);
        Notificacao::query()->delete();

        $this->putJogo($jogo, ['id_time_casa' => $this->verde])
            ->assertSessionHas('sucesso', 'Jogo atualizado. Inscrições pelo elenco: 1 atleta(s) inscrito(s), 1 removido(s).'
                . ' As inscrições individuais foram mantidas. 1 atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos.'
                . ' 2 atleta(s) notificado(s).')
            ->assertSessionMissing('aviso');

        // Caio era do Azul e também é do Verde: fica, e é escalado no Verde
        $this->assertSame([
            $bia  => ['ELENCO', $this->verde],
            $caio => ['ELENCO', $this->verde],
            $eva  => ['INDIVIDUAL', null],
        ], $this->inscricoesDo($jogo));
        $this->assertSame([$ana => 'REMOCAO', $bia => 'INSCRICAO'], $this->notificacoesPorAtleta());
    }

    public function test_trocar_time_e_horario_juntos_cada_um_recebe_uma_so(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $bia  = $this->atleta('Bia', [$this->verde]);
        $caio = $this->atleta('Caio', [$this->azul, $this->verde]);
        $jogo = $this->criarJogo($this->azul, $this->visitante);
        Notificacao::query()->delete();

        $this->putJogo($jogo, ['id_time_casa' => $this->verde, 'horario_inicio_evento_calendario' => '20:00'])
            ->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, ' 3 atleta(s) notificado(s).'));

        // Quem sai: só REMOCAO; quem entra: só INSCRICAO (já com o horário novo); quem fica: só ALTERACAO
        $this->assertSame([$ana => 'REMOCAO', $bia => 'INSCRICAO', $caio => 'ALTERACAO'], $this->notificacoesPorAtleta());
        $this->assertStringContainsString('· 20:00 ·', Notificacao::where('id_atleta', $bia)->value('mensagem_notificacao'));
    }

    public function test_jogo_concluido_que_troca_de_time_nao_muda_as_inscricoes(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $this->atleta('Bia', [$this->verde]);
        $jogo = $this->criarJogo($this->azul, $this->visitante);
        $passado = now()->subWeek()->toDateString();
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $jogo->id_evento)->update(['data_evento_calendario' => $passado]);
        Notificacao::query()->delete();

        $this->putJogo($jogo, ['id_time_casa' => $this->verde, 'data_evento_calendario' => $passado])
            ->assertSessionHas('sucesso', 'Jogo atualizado. 1 atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos.');

        $this->assertSame([$ana => ['ELENCO', null]], $this->inscricoesDo($jogo));
        $this->assertSame(0, Notificacao::count());
    }

    public function test_inverter_o_mando_nao_troca_ninguem(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $jogo = $this->criarJogo($this->azul, $this->visitante);

        $this->putJogo($jogo, ['id_time_casa' => $this->visitante, 'id_time_visitante' => $this->azul])
            ->assertSessionHas('sucesso', 'Jogo atualizado.');

        $this->assertSame([$ana => ['ELENCO', $this->azul]], $this->inscricoesDo($jogo));
    }

    public function test_trocar_time_com_conflito_pede_confirmacao(): void
    {
        $this->atleta('Ana', [$this->azul]);
        $bia  = $this->atleta('Bia', [$this->verde]);
        $jogo = $this->criarJogo($this->azul, $this->visitante);
        $this->treinoNoHorarioDoJogo()->inscrever($bia, 'INDIVIDUAL', null);

        $this->putJogo($jogo, ['id_time_casa' => $this->verde])
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1 && str_contains($p['fortes'][0], 'Bia'));
        $this->assertSame($this->azul, (int) $jogo->fresh()->id_time_casa); // nada foi salvo

        $this->putJogo($jogo, ['id_time_casa' => $this->verde, 'confirmar_conflito' => 1])->assertSessionHas('sucesso');
        $this->assertSame($this->verde, (int) $jogo->fresh()->id_time_casa);
    }

    // ---------- fora das regras da categoria ----------

    public function test_mover_inscricoes_do_atleta_ignora_os_jogos(): void
    {
        $idSub13 = $this->idCategoria('Sub-13', 'M');
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        $gil = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        $this->colocarNaCategoria($gil, $idSub13);

        // Jogo antigo da Sub-13 com o Gil pela categoria (como antes da regra do elenco): não sai
        $jogoSub13 = $this->criarJogo($this->azul, $this->visitante, ['id_campeonato' => 'AMISTOSO', 'id_categoria' => $idSub13]);
        $jogoSub13->evento->inscrever($gil, 'CATEGORIA', null);
        // Jogo da Sub-15: não entra
        $this->criarJogo($this->verde, $this->visitante, ['id_campeonato' => 'AMISTOSO', 'id_categoria' => $idSub15]);
        $treinoSub15 = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino Sub-15', 'tipo_evento_calendario' => 'TREINO', 'id_categoria' => $idSub15,
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '08:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        $movimento = Atleta::find($gil)->eventosParaMoverInscricoes($idSub13, $idSub15);

        $this->assertSame([], $movimento['sair']->pluck('id_evento_calendario')->all());
        $this->assertSame([$treinoSub15->id_evento_calendario], $movimento['entrar']->pluck('id_evento_calendario')->all());
    }

    public function test_evento_jogo_sem_tbl_jogos_continua_pela_categoria(): void
    {
        $ana = $this->atleta('Ana', [$this->azul]);
        $eva = $this->atleta('Eva');

        // Evento JOGO antigo: o Calendário não cria mais (Fase 10), então nasce direto pelo model
        EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Jogo combinado', 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idSub11M,
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '10:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        $evento = EventoCalendario::sole();
        $this->assertFalse($evento->ehJogo());
        $this->assertEqualsCanonicalizing([$ana, $eva], $evento->inscricoes()->where('origem_evento_atleta', 'CATEGORIA')->pluck('id_atleta')->all());
    }

    // ---------- tela do jogo ----------

    public function test_tela_do_jogo_mostra_quem_falta_do_elenco_e_quem_esta_fora_dele(): void
    {
        $this->atleta('Ana', [$this->azul]);
        $eva  = $this->atleta('Eva');
        $jogo = $this->criarJogo($this->azul, $this->visitante);
        $hugo = $this->atleta('Hugo', [$this->azul]); // entrou no elenco depois do jogo criado
        $jogo->evento->inscrever($eva, 'INDIVIDUAL', null);
        $tela = route('admin.calendario.eventos.show', $jogo->id_evento);

        $this->actingAs($this->admin, 'admin')->get($tela)->assertOk()
            ->assertSee('<strong>1</strong> atleta(s) do elenco ainda não está inscrito', false)
            ->assertSee('Fora do elenco')
            ->assertDontSee('Atualizar inscritos pela categoria');

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Escalação pelo elenco: 0 inscrito(s) escalado(s), 1 atleta(s) inscrito(s) e escalado(s). 1 atleta(s) notificado(s).');
        $this->assertStringEndsWith('. Você joga pelo Time Azul.', Notificacao::where('id_atleta', $hugo)->value('mensagem_notificacao'));
        $this->assertSame(['ELENCO', $this->azul], $this->inscricoesDo($jogo)[$hugo]);

        $this->actingAs($this->admin, 'admin')->get($tela)->assertSee('Todo o elenco ativo está inscrito');
    }

    // ---------- rascunho e publicação (Fase 10, Etapa 4) ----------

    public function test_rascunho_nao_avisa_troca_de_time_edicao_preencher_nem_status(): void
    {
        $this->atleta('Ana', [$this->azul]);
        $this->atleta('Bia', [$this->verde]);
        $this->postJogo($this->azul, $this->visitante);
        $jogo = Jogo::with('evento')->sole();
        $this->atleta('Hugo', [$this->verde]); // entra no elenco depois

        $this->putJogo($jogo, ['id_time_casa' => $this->verde, 'horario_inicio_evento_calendario' => '20:00'])
            ->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, ' 0 atleta(s) notificado(s).'));
        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento));
        $this->actingAs($this->admin, 'admin')->patch(route('admin.calendario.eventos.cancelar', $jogo->id_evento));
        $this->actingAs($this->admin, 'admin')->patch(route('admin.calendario.eventos.cancelar', $jogo->id_evento)); // reativa

        $this->assertSame(2, $jogo->evento->inscricoes()->count()); // Bia e Hugo, do Verde
        $this->assertSame(0, Notificacao::count());
        $this->assertFalse($jogo->evento->fresh()->estaPublicado());
    }

    public function test_publicar_avisa_cada_um_uma_vez_com_a_escalacao_feita_no_rascunho(): void
    {
        $ana  = $this->atleta('Ana', [$this->azul]);
        $caio = $this->atleta('Caio', [$this->azul, $this->verde]); // nos dois: entra sem time
        $this->postJogo($this->azul, $this->verde);
        $jogo = Jogo::with('evento')->sole();

        // O admin escala o Caio no Verde ainda no rascunho: o aviso já sai com o time certo
        $this->actingAs($this->admin, 'admin')->patch(route('admin.calendario.eventos.inscricoes.time', [$jogo->id_evento, $caio]),
            ['id_time' => $this->verde]);

        $this->publicar($jogo)
            ->assertRedirect(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Jogo publicado. 2 atleta(s) notificado(s).');

        $this->assertSame([$ana => 'INSCRICAO', $caio => 'INSCRICAO'], $this->notificacoesPorAtleta());
        $this->assertStringEndsWith('. Você joga pelo Time Verde.', Notificacao::where('id_atleta', $caio)->value('mensagem_notificacao'));
        $this->assertSame($this->admin->id_usuario, Notificacao::where('id_atleta', $ana)->value('id_usuario'));
        $this->assertTrue($jogo->evento->fresh()->estaPublicado());
        $this->assertSame('data_publicacao_evento_calendario', $jogo->evento->historico()->sole()->campo_evento_historico);

        // De novo: não avisa ninguém
        $this->publicar($jogo)->assertSessionHas('aviso', 'Este jogo já estava publicado. Nenhum atleta foi avisado de novo.');
        $this->assertSame(2, Notificacao::count());
    }

    public function test_publicar_rascunho_concluido_ou_cancelado_nao_avisa(): void
    {
        $this->atleta('Ana', [$this->azul]);
        $semAviso = 'Jogo publicado. Nenhum atleta foi avisado: o jogo já aconteceu, está cancelado ou oculto.';

        $this->postJogo($this->azul, $this->visitante, ['data_evento_calendario' => now()->subWeek()->toDateString()]);
        $this->publicar(Jogo::latest('id_jogo')->first())->assertSessionHas('sucesso', $semAviso);

        $this->postJogo($this->azul, $this->visitante);
        $cancelado = Jogo::latest('id_jogo')->first();
        $this->actingAs($this->admin, 'admin')->patch(route('admin.calendario.eventos.cancelar', $cancelado->id_evento));
        $this->publicar($cancelado)->assertSessionHas('sucesso', $semAviso);

        $this->assertSame(0, Notificacao::count());
        $this->assertTrue($cancelado->evento->fresh()->estaPublicado());
    }

    public function test_tela_e_lista_mostram_o_rascunho_ate_publicar(): void
    {
        $this->atleta('Ana', [$this->azul]);
        $this->postJogo($this->azul, $this->visitante);
        $jogo = Jogo::sole();
        $tela = route('admin.calendario.eventos.show', $jogo->id_evento);

        $this->actingAs($this->admin, 'admin')->get($tela)->assertOk()
            ->assertSee('id="faixaRascunho"', false)
            ->assertSee('Publicar e avisar os atletas');
        $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.index'))->assertOk()->assertSee('>Rascunho</span>', false);
        // Jogos dos amistosos (cartões por jogo): aviso no topo, selo e "Revisar e publicar" levando à tela do jogo
        $this->actingAs($this->admin, 'admin')->get(route('admin.amistosos.times'))->assertOk()
            ->assertSee('id="avisoRascunhos"', false)
            ->assertSee('1 amistoso ainda não publicado')
            ->assertSee('<a href="' . $tela . '" class="btn btn-warning btn-sm ms-auto js-publicar-jogo">', false);

        $this->publicar($jogo);

        $this->actingAs($this->admin, 'admin')->get(route('admin.amistosos.times'))->assertOk()
            ->assertDontSee('id="avisoRascunhos"', false)
            ->assertDontSee('js-publicar-jogo');

        $this->actingAs($this->admin, 'admin')->get($tela)->assertOk()
            ->assertDontSee('id="faixaRascunho"', false)
            ->assertDontSee('Publicar e avisar os atletas');
        $this->actingAs($this->admin, 'admin')->get(route('admin.jogos.index'))->assertOk()->assertDontSee('>Rascunho</span>', false);
    }

    public function test_rascunho_conta_no_conflito_de_horario(): void
    {
        $ana = $this->atleta('Ana', [$this->azul]);
        $this->postJogo($this->azul, $this->visitante); // rascunho, 19:00, com a Ana

        $conflitos = $this->treinoNoHorarioDoJogo()->conflitosPara([$ana]);

        $this->assertCount(1, $conflitos);
        $this->assertFalse($conflitos->first()['fraco']); // conflito real
        $this->assertSame(Jogo::sole()->id_evento, $conflitos->first()['evento']->id_evento_calendario);
    }

    public function test_eventos_que_nao_sao_jogo_continuam_avisando_na_hora(): void
    {
        $ana = $this->atleta('Ana');

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.store'), [
            'titulo_evento_calendario'         => 'Treino extra',
            'tipo_evento_calendario'           => 'TREINO',
            'id_categoria'                     => $this->idSub11M,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '08:00',
            'status_evento_calendario'         => 'ATIVO',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(EventoCalendario::sole()->estaPublicado());
        $this->assertSame([$ana => 'INSCRICAO'], $this->notificacoesPorAtleta());
    }

    // ---------- helpers ----------

    // Atleta da Sub-11 M (a categoria dos times e do campeonato), no elenco dos times indicados
    private function atleta(string $nome, array $elencos = [], string $status = 'ATIVO'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(11), 'M', $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idSub11M);

        foreach ($elencos as $idTime) {
            DB::table('tbl_atleta_time')->insert([
                'id_time' => $idTime, 'id_atleta' => $id, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => '',
            ]);
        }

        return $id;
    }

    private function dadosJogo(int $casa, int $visitante, array $extra = []): array
    {
        return array_merge([
            'id_campeonato'                    => 'AMISTOSO',
            'id_categoria'                     => $this->idSub11M,
            'id_time_casa'                     => $casa,
            'id_time_visitante'                => $visitante,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '19:00',
            'horario_fim_evento_calendario'    => '',
            'local_evento_calendario'          => 'Campo A',
            'placar_time_casa_jogos'           => '',
            'placar_time_visitante_jogos'      => '',
        ], $extra);
    }

    private function postJogo(int $casa, int $visitante, array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dadosJogo($casa, $visitante, $extra));
    }

    // Cria o jogo pela tela e já publica (os testes de troca de time e da tela olham o jogo publicado)
    private function criarJogo(int $casa, int $visitante, array $extra = []): Jogo
    {
        $this->postJogo($casa, $visitante, $extra)->assertSessionHasNoErrors()->assertSessionHas('sucesso');
        $jogo = Jogo::latest('id_jogo')->first();
        $this->publicar($jogo)->assertSessionHas('sucesso');

        return $jogo->load('evento');
    }

    private function publicar(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.publicar', $jogo->id_jogo));
    }

    // Edita o jogo mantendo o resto como está
    private function putJogo(Jogo $jogo, array $extra)
    {
        return $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo),
            $this->dadosJogo((int) $jogo->id_time_casa, (int) $jogo->id_time_visitante, $extra));
    }

    // Treino no mesmo dia, 19:30 às 20:30 (sobrepõe o jogo das 19:00)
    private function treinoNoHorarioDoJogo(): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $this->dia,
            'horario_inicio_evento_calendario' => '19:30', 'horario_fim_evento_calendario' => '20:30', 'status_evento_calendario' => 'ATIVO',
        ]);
    }

    // Inscrições do jogo: [id_atleta => [origem, id_time]]
    private function inscricoesDo(Jogo $jogo): array
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->orderBy('id_atleta')->get()
            ->mapWithKeys(fn ($i) => [(int) $i->id_atleta => [$i->origem_evento_atleta, $i->id_time === null ? null : (int) $i->id_time]])
            ->all();
    }

    private function notificacoesPorAtleta(): array
    {
        return Notificacao::orderBy('id_atleta')->pluck('tipo_notificacao', 'id_atleta')->all();
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
