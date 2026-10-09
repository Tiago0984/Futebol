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
 * Fase 10, Etapa 2: fluxo de Eventos sem ida e volta (tela do evento com caminho e ações, criar abre a
 * tela, jogo só pela tela de Jogos), times do campeonato, sugestões, elenco editável, tipo do campeonato
 * e composers do site limitados.
 */
class EventoFluxoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub11M;
    private int $copa;
    private int $azul;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub11M = $this->idCategoria('Sub-11', 'M');
        $this->copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa Escola', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'MATA-MATA',
            'data_inicio_campeonato' => now()->subWeek()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Estádio da Copa', 'status_campeonato' => 'ATIVO',
        ]);

        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
        $this->azul      = $time('Time Azul', 'INTERNO');
        $this->verde     = $time('Time Verde', 'INTERNO');
        $this->visitante = $time('Time Visitante', 'EXTERNO');
    }

    // ---------- 1 a 3: caminho, ações e voltar na tela do evento ----------

    public function test_caminho_do_jogo_de_campeonato_e_dos_outros_ramos(): void
    {
        $jogo = $this->jogo($this->copa);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk()
            ->assertSeeInOrder([
                '<a href="' . route('admin.calendario.index') . '">Eventos</a>',
                '<a href="' . e(route('admin.calendario.index', ['ramo' => 'campeonatos'])) . '">Campeonatos</a>',
                '<a href="' . e(route('admin.jogos.index', ['campeonato' => $this->copa])) . '">Copa Escola</a>',
                '<li class="breadcrumb-item active" aria-current="page">Time Azul x Time Visitante</li>',
            ], false);

        $amistoso = $this->jogo(null);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $amistoso->id_evento))
            ->assertSee('<a href="' . e(route('admin.jogos.index', ['campeonato' => 'amistoso'])) . '">Amistosos</a>', false)
            ->assertDontSee('Copa Escola</a>', false);

        $treino = $this->evento('TREINO', 'Treino Sub-11');
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $treino->id_evento_calendario))
            ->assertSeeInOrder([
                '>Eventos</a>',
                '<a href="' . e(route('admin.calendario.index', ['ramo' => 'treinos'])) . '">Treinos</a>',
                'aria-current="page">Treino Sub-11</li>',
            ], false);
    }

    public function test_acoes_do_cabecalho_seguem_o_status(): void
    {
        $treino = $this->evento('TREINO', 'Treino Sub-11');
        $url = route('admin.calendario.eventos.show', $treino->id_evento_calendario);

        $this->comoAdmin()->get($url)
            ->assertSee('data-bs-target="#modalEditarEvento"', false)
            ->assertSee('<i class="bi bi-x-circle"></i> Cancelar', false)
            ->assertSee('<i class="bi bi-eye-slash"></i> Ocultar', false)
            ->assertDontSee('id="modalPlacar"', false);

        // Cancelar pela tela volta para ela, que passa a oferecer Reativar
        $this->comoAdmin()->from($url)->patch(route('admin.calendario.eventos.cancelar', $treino->id_evento_calendario))
            ->assertRedirect($url);
        $this->comoAdmin()->get($url)->assertSee('<i class="bi bi-check-circle"></i> Reativar', false);

        // Oculto: só Mostrar (cancelar/reativar some até mostrar)
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $treino->id_evento_calendario)->update(['status_evento_calendario' => 'INATIVO']);
        $this->comoAdmin()->get($url)
            ->assertSee('<i class="bi bi-eye"></i> Mostrar', false)
            ->assertDontSee('<i class="bi bi-check-circle"></i> Reativar', false);
    }

    public function test_voltar_segue_a_origem(): void
    {
        $jogo   = $this->jogo($this->copa);
        $treino = $this->evento('TREINO', 'Treino Sub-11', ['data_evento_calendario' => '2027-02-10']);

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSee('href="' . route('admin.jogos.index') . '" class="btn btn-secondary btn-sm" id="btnVoltar"', false)
            ->assertSee('Voltar aos jogos');
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $treino->id_evento_calendario))
            ->assertSee('href="' . e(route('admin.calendario.index', ['mes' => '2027-02'])) . '" class="btn btn-secondary btn-sm" id="btnVoltar"', false)
            ->assertSee('Voltar ao calendário');
    }

    public function test_editar_pela_tela_do_evento_volta_para_ela(): void
    {
        $treino = $this->evento('TREINO', 'Treino Sub-11');
        $show   = route('admin.calendario.eventos.show', $treino->id_evento_calendario);

        // O formulário já abre preenchido, com voltar=evento
        $this->comoAdmin()->get($show)
            ->assertSee('action="' . route('admin.calendario.eventos.update', $treino->id_evento_calendario) . '"', false)
            ->assertSee('<input type="hidden" name="voltar" value="evento">', false)
            ->assertSee('value="Treino Sub-11"', false);

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $treino->id_evento_calendario), [
            'titulo_evento_calendario' => 'Treino Renomeado', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => $treino->data_evento_calendario->toDateString(), 'voltar' => 'evento',
        ])->assertRedirect($show)->assertSessionHas('sucesso');
        $this->assertSame('Treino Renomeado', $treino->fresh()->titulo_evento_calendario);
    }

    public function test_jogo_edita_pelo_formulario_do_jogo_e_placar_rapido(): void
    {
        $jogo = $this->jogo($this->copa);
        $show = route('admin.calendario.eventos.show', $jogo->id_evento);

        $this->comoAdmin()->get($show)
            ->assertSee('data-bs-target="#modalEditarJogo"', false)
            ->assertSee('action="' . route('admin.jogos.update', $jogo->id_jogo) . '"', false)
            ->assertSee('id="modalPlacar"', false)
            ->assertDontSee('id="modalEditarEvento"', false);

        // Editar o jogo pela tela volta para ela
        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['voltar' => 'evento', 'local_evento_calendario' => 'Campo B']))
            ->assertRedirect($show);
        $this->assertSame('Campo B', $jogo->evento->fresh()->local_evento_calendario);

        // Placar: os dois ou nenhum
        $this->comoAdmin()->from($show)->patch(route('admin.jogos.placar', $jogo->id_jogo), ['placar_time_casa_jogos' => 3])
            ->assertSessionHasErrors('placar_time_visitante_jogos');
        $this->comoAdmin()->patch(route('admin.jogos.placar', $jogo->id_jogo), ['placar_time_casa_jogos' => 3, 'placar_time_visitante_jogos' => 1])
            ->assertRedirect($show)->assertSessionHas('sucesso', 'Placar salvo: 3 × 1.');
        $this->assertSame([3, 1], [$jogo->fresh()->placar_time_casa_jogos, $jogo->fresh()->placar_time_visitante_jogos]);

        $this->comoAdmin()->patch(route('admin.jogos.placar', $jogo->id_jogo), ['placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => ''])
            ->assertSessionHas('sucesso', 'Placar apagado (jogo ainda não jogado).');
        $this->assertFalse($jogo->fresh()->temPlacar());
    }

    // ---------- 4: criar abre a tela ----------

    public function test_criar_jogo_e_evento_sem_categoria_abrem_a_tela(): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())
            ->assertRedirect(route('admin.calendario.eventos.show', Jogo::sole()->id_evento));

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), [
            'titulo_evento_calendario' => 'Exame', 'tipo_evento_calendario' => 'AVALIACAO', 'data_evento_calendario' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('admin.calendario.eventos.show', EventoCalendario::where('titulo_evento_calendario', 'Exame')->value('id_evento_calendario')))
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário. Inscreva os atletas abaixo.');

        // Com categoria, os atletas já entram: volta para a lista no mês
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'id_categoria' => $this->idSub11M,
            'data_evento_calendario' => '2027-03-05',
        ])->assertRedirect(route('admin.calendario.index', ['mes' => '2027-03']));
    }

    // ---------- 5: Calendário não cria nem edita jogo ----------

    public function test_calendario_recusa_editar_jogo_e_criar_jogo_mas_edita_o_jogo_antigo(): void
    {
        $jogo = $this->jogo($this->copa);
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $jogo->id_evento), [
            'titulo_evento_calendario' => 'Outro', 'tipo_evento_calendario' => 'JOGO', 'data_evento_calendario' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSessionHas('erro', 'Este evento é um jogo: edite pelo botão "Editar" da tela do jogo.');
        $this->assertSame('Time Azul x Time Visitante', $jogo->evento->fresh()->titulo_evento_calendario);

        // JOGO antigo (sem tbl_jogos) continua editável e continua JOGO
        $antigo = $this->evento('JOGO', 'Jogo antigo');
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $antigo->id_evento_calendario), [
            'titulo_evento_calendario' => 'Jogo antigo editado', 'tipo_evento_calendario' => 'JOGO', 'data_evento_calendario' => now()->addDay()->toDateString(),
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');
        $this->assertSame('Jogo antigo editado', $antigo->fresh()->titulo_evento_calendario);

        // Nenhum outro vira JOGO pelo Calendário
        $treino = $this->evento('TREINO', 'Treino');
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $treino->id_evento_calendario), [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'JOGO', 'data_evento_calendario' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('tipo_evento_calendario');
        $this->assertSame('TREINO', $treino->fresh()->tipo_evento_calendario);
    }

    public function test_lista_do_calendario_sem_jogo_no_criar_e_jogo_editado_pela_tela_dele(): void
    {
        $jogo = $this->jogo($this->copa);
        $resposta = $this->comoAdmin()->get(route('admin.calendario.index'))->assertOk();

        preg_match('#<div class="modal fade" id="modalCriarEvento".*?</form>#s', $resposta->getContent(), $criar);
        $this->assertStringNotContainsString('<option value="JOGO"', $criar[0]);
        $resposta->assertSee('href="' . route('admin.calendario.eventos.show', $jogo->id_evento) . '#editar"', false);
        // Modal de edição: a opção JOGO existe escondida (o JS libera só para o evento JOGO antigo)
        $this->assertMatchesRegularExpression('#<option value="JOGO" class="js-tipo-jogo"\s+hidden disabled#', $resposta->getContent());
    }

    // ---------- 6 e 7: times do campeonato e aviso de categoria ----------

    public function test_jogo_de_campeonato_aceita_so_os_participantes(): void
    {
        DB::table('tbl_campeonato_time')->insert([
            ['id_campeonato' => $this->copa, 'id_time' => $this->azul],
            ['id_campeonato' => $this->copa, 'id_time' => $this->visitante],
        ]);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_time_casa' => $this->verde]))
            ->assertSessionHasErrors(['id_time_casa' => 'O mandante não participa do campeonato Copa Escola. Inclua o time nos participantes do campeonato, ou escolha outro.']);
        $this->assertSame(0, Jogo::count());

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())->assertSessionHasNoErrors();
        // Amistoso: qualquer time
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => 'AMISTOSO', 'id_time_casa' => $this->verde]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Jogo::count());

        // O select sabe os participantes; o texto de ajuda fala do elenco, não da categoria
        $this->comoAdmin()->get(route('admin.jogos.index'))
            ->assertSee('data-times="[' . $this->azul . ',' . $this->visitante . ']"', false)
            ->assertSee('Só exibição: quem joga é o elenco ativo dos times internos.')
            ->assertDontSee('Os atletas ativos da categoria são inscritos no jogo.');
    }

    public function test_campeonato_sem_participantes_aceita_qualquer_time_e_edicao_mantem_os_times_antigos(): void
    {
        $jogo = $this->jogo($this->copa, $this->verde);
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_time_casa' => $this->verde]))->assertSessionHasNoErrors();

        // Participantes cadastrados depois: o jogo antigo com o Verde continua editável
        DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $this->copa, 'id_time' => $this->azul]);
        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['id_time_casa' => $this->verde, 'local_evento_calendario' => 'Campo C']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Campo C', $jogo->evento->fresh()->local_evento_calendario);
    }

    public function test_criar_jogo_avisa_atleta_fora_da_categoria(): void
    {
        $idSub15F = $this->idCategoria('Sub-15', 'F');
        $ana = $this->criarAtleta($this->nascidoComIdade(15), 'F', 'ATIVO');
        $this->colocarNaCategoria($ana, $idSub15F);
        DB::table('tbl_atleta_time')->insert(['id_time' => $this->azul, 'id_atleta' => $ana, 'camisa_atleta_time' => 7, 'posicao_atleta_time' => '']);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())
            ->assertSessionHas('avisos_categoria');

        $this->comoAdmin()->followingRedirects()->post(route('admin.jogos.store'), $this->dadosJogo(['data_evento_calendario' => now()->addDays(9)->toDateString()]))
            ->assertSee('Fora da categoria do jogo');
    }

    // ---------- 8: atalhos para os jogos ----------

    public function test_campeonatos_linkam_os_jogos_e_a_lista_dos_amistosos_marca_amistosos(): void
    {
        // O botão "Jogos" da linha abre a tela de jogos já filtrada pelo campeonato
        $this->comoAdmin()->get(route('admin.campeonatos.index'))
            ->assertSee('href="' . route('admin.campeonatos.times', $this->copa) . '"', false);

        // A lista de Jogos dos amistosos marca o item Amistosos (que abre a tela de jogos dos amistosos)
        $barra = $this->comoAdmin()->get(route('admin.jogos.index', ['campeonato' => 'amistoso']))->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('admin.amistosos.times'), '#') . '"\s+class="nav-link active"#', $barra);
    }

    // ---------- 9: sugestões ----------

    public function test_sugestoes_de_local_e_de_subtipo(): void
    {
        $this->evento('REUNIAO', 'Reunião', ['local_evento_calendario' => 'Sala 7']);
        DB::table('tbl_grade_treino')->insert([
            'dia_semana_grade_treino' => 'sexta', 'categoria_grade_treino' => 'Integrado', 'tipo_grade_treino' => 'TREINO',
            'horario_inicio_grade_treino' => '08:00', 'local_grade_treino' => 'Campo da Grade', 'status_grade_treino' => 'ATIVO',
        ]);
        $this->assertSame(['Campo da Grade', 'Estádio da Copa', 'Sala 7'], EventoCalendario::locaisUsados());

        $treino = $this->evento('TREINO', 'Treino');
        $paginas = [
            route('admin.calendario.index'), route('admin.calendario.index', ['tab' => 'grade']), route('admin.jogos.index'),
            route('admin.campeonatos.index'), route('admin.campeonatos.create'), route('admin.campeonatos.edit', $this->copa),
            route('admin.calendario.eventos.show', $treino->id_evento_calendario),
        ];
        foreach ($paginas as $pagina) {
            $this->comoAdmin()->get($pagina)->assertOk()
                ->assertSee('<datalist id="locaisUsados">', false)
                ->assertSee('<option value="Campo da Grade"></option>', false)
                ->assertSee('list="locaisUsados"', false);
        }

        $this->comoAdmin()->get(route('admin.calendario.index'))
            ->assertSee('<option value="Exame médico"></option>', false)
            ->assertSee('<option value="Avaliação física"></option>', false)
            ->assertSee('class="form-select js-tipo-evento"', false)
            ->assertSee('js-subtipo', false);
    }

    // ---------- 10: elenco editável ----------

    public function test_adicionar_e_remover_do_elenco(): void
    {
        $rui = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $inativo = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'INATIVO');
        $elenco = route('admin.times.elenco', $this->azul);

        $this->comoAdmin()->get($elenco)->assertOk()
            ->assertSee('<option value="' . $rui . '"', false)
            ->assertDontSee('<option value="' . $inativo . '"', false);

        $this->comoAdmin()->post(route('admin.times.elenco.adicionar', $this->azul), ['id_atleta' => $rui, 'camisa_atleta_time' => 9])
            ->assertRedirect($elenco)->assertSessionHas('sucesso');
        $this->assertDatabaseHas('tbl_atleta_time', ['id_time' => $this->azul, 'id_atleta' => $rui, 'camisa_atleta_time' => 9, 'status_atleta_time' => 'TITULAR']);

        $this->comoAdmin()->from($elenco)->post(route('admin.times.elenco.adicionar', $this->azul), ['id_atleta' => $rui])
            ->assertSessionHas('erro');
        $this->comoAdmin()->from($elenco)->post(route('admin.times.elenco.adicionar', $this->azul), ['id_atleta' => $inativo])
            ->assertSessionHasErrors(['id_atleta' => 'Escolha um atleta ativo.']);
        $this->assertSame(1, DB::table('tbl_atleta_time')->count());

        // Externo não tem elenco
        $this->comoAdmin()->post(route('admin.times.elenco.adicionar', $this->visitante), ['id_atleta' => $rui])
            ->assertRedirect(route('admin.times.index'))->assertSessionHas('erro');

        // Sem jogos: sai com sucesso
        $this->comoAdmin()->get($elenco)->assertSee(route('admin.times.elenco.remover', [$this->azul, $rui]), false);
        $this->comoAdmin()->delete(route('admin.times.elenco.remover', [$this->azul, $rui]))
            ->assertRedirect($elenco)->assertSessionHas('sucesso', "{$this->nomeAtleta($rui)} saiu do elenco do Time Azul.");
        $this->assertSame(0, DB::table('tbl_atleta_time')->count());
    }

    public function test_remover_do_elenco_mantem_a_inscricao_e_avisa_os_jogos_futuros(): void
    {
        $rui = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        DB::table('tbl_atleta_time')->insert(['id_time' => $this->azul, 'id_atleta' => $rui, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => '']);

        $futuro1 = $this->jogo($this->copa);
        $futuro2 = $this->jogo(null, $this->visitante, $this->azul); // o Azul como visitante
        $passado = $this->jogo($this->copa, $this->azul, $this->visitante, now()->subWeek()->toDateString());
        $outro   = $this->jogo($this->copa, $this->verde);                 // jogo de outro time
        foreach ([$futuro1, $futuro2, $passado, $outro] as $jogo) {
            $jogo->evento->inscrever($rui, 'INDIVIDUAL', null, notificar: false);
        }

        $this->comoAdmin()->delete(route('admin.times.elenco.remover', [$this->azul, $rui]))
            ->assertSessionHas('aviso', "{$this->nomeAtleta($rui)} saiu do elenco do Time Azul. Continua inscrito em 2 jogo(s) futuro(s) do time "
                . '(marcado como "Fora do elenco" na tela do jogo); remova a inscrição na tela de cada jogo, se for o caso.');

        $this->assertSame(4, DB::table('tbl_evento_atleta')->where('id_atleta', $rui)->count());
        $this->comoAdmin()->get(route('admin.times.elenco', $this->azul))->assertSee('Continua inscrito em 2 jogo(s)');
    }

    // ---------- 11: tipo e categoria no formulário do campeonato ----------

    public function test_editar_campeonato_abre_com_o_tipo_e_a_categoria_atuais(): void
    {
        // Modal da lista: o valor gravado (maiúsculas) existe como opção e vai no botão
        $this->comoAdmin()->get(route('admin.campeonatos.index'))
            ->assertSee('<option value="MATA-MATA">Mata-mata</option>', false)
            ->assertSee('data-tipo="MATA-MATA"', false)
            ->assertSee('<option value="' . $this->idSub11M . '">Sub-11 Masculino</option>', false);

        // Página de edição: já selecionados
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $this->copa))
            ->assertSee('<option value="MATA-MATA" selected>Mata-mata</option>', false)
            ->assertSeeInOrder(['<option value="' . $this->idSub11M . '"', 'selected', 'Sub-11 Masculino'], false);

        // Gravar: o tipo vai em maiúsculas; fora da lista é recusado
        $dados = [
            'nome_campeonato' => 'Copa Escola', 'tipo_campeonato' => 'Pontos Corridos',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01',
        ];
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), $dados)->assertSessionHasNoErrors();
        $this->assertSame('PONTOS CORRIDOS', DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->value('tipo_campeonato'));

        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [...$dados, 'tipo_campeonato' => 'Inventado'])
            ->assertSessionHasErrors('tipo_campeonato');
    }

    public function test_tipo_antigo_fora_da_lista_continua_aceito_na_edicao(): void
    {
        DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->update(['tipo_campeonato' => 'REGIONAL']);

        $this->comoAdmin()->get(route('admin.campeonatos.edit', $this->copa))
            ->assertSee('<option value="REGIONAL" selected>REGIONAL</option>', false);
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [
            'nome_campeonato' => 'Copa Escola', 'tipo_campeonato' => 'REGIONAL',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01',
        ])->assertSessionHasNoErrors();
    }

    // ---------- 12: composers do site só nas views do site ----------

    public function test_admin_nao_consulta_os_dados_do_site_e_o_site_continua_com_eles(): void
    {
        DB::table('tbl_noticias')->insert([
            'titulo_noticia' => 'Notícia Recente XYZ', 'conteudo_noticia' => 'Texto', 'foto_noticia' => 'foto.jpg',
            'categoria_noticia' => 'GERAL', 'data_publicacao_noticia' => now(), 'autor_noticia' => 'Equipe', 'status_noticia' => 'ATIVO',
        ]);

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) { $consultas[] = $q->sql; });
        $this->comoAdmin()->get(route('admin.calendario.index'))->assertOk();

        $this->assertEmpty(array_filter($consultas, fn ($sql) => str_contains($sql, 'tbl_noticias')));
        $this->assertEmpty(array_filter($consultas, fn ($sql) => str_contains($sql, 'select * from `tbl_campeonato` order by `nome_campeonato`')));
        // O contador de matrículas roda uma vez (barra e header usam o mesmo valor)
        $this->assertCount(1, array_filter($consultas, fn ($sql) => str_contains($sql, 'from `tbl_atletas` where `status_atleta` in')));

        // Site: notícias no header e campeonatos no menu
        $this->get('/')->assertOk()->assertSee('Notícia Recente XYZ')->assertSee('Copa Escola');
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function evento(string $tipo, string $titulo, array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor($this->admin->id_usuario, array_merge([
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => $tipo,
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => '10:00:00',
            'status_evento_calendario' => 'ATIVO',
        ], $extra), inscreverCategoria: false);
    }

    private function jogo(?int $idCampeonato, ?int $casa = null, ?int $visitante = null, ?string $data = null): Jogo
    {
        $casa ??= $this->azul;
        $visitante ??= $this->visitante;
        $evento = $this->evento('JOGO', Jogo::tituloPara($casa, $visitante), ['data_evento_calendario' => $data ?? now()->addWeek()->toDateString()]);

        $jogo = Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $idCampeonato,
            'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
        ]);
        $jogo->setRelation('evento', $evento);

        return $jogo;
    }

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato' => $this->copa, 'id_time_casa' => $this->azul, 'id_time_visitante' => $this->visitante,
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => '19:00',
        ], $extra);
    }

    private function nomeAtleta(int $id): string
    {
        return DB::table('tbl_atletas')->where('id_atleta', $id)->value('nome_atleta');
    }
}
