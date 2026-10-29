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
 * tela, jogo só pela tela de Jogos), times do campeonato, sugestões, tipo do campeonato
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

        // Times com o Sub-11 marcado no cadastro (tbl_time_categoria, 27/10/2026)
        $time = function (string $nome, string $tipo) {
            $id = DB::table('tbl_time')->insertGetId([
                'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
            ]);
            DB::table('tbl_time_categoria')->insert(['id_time' => $id, 'id_categoria' => $this->idSub11M]);

            return $id;
        };
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
        // A lista abre no mês atual: pede o mês do jogo, que pode cair no seguinte.
        $jogo = $this->jogo($this->copa);
        $resposta = $this->comoAdmin()->get(route('admin.calendario.index', ['mes' => substr((string) $jogo->evento->data_evento_calendario, 0, 7)]))->assertOk();

        preg_match('#<div class="modal fade" id="modalCriarEvento".*?</form>#s', $resposta->getContent(), $criar);
        $this->assertStringNotContainsString('<option value="JOGO"', $criar[0]);
        $resposta->assertSee('href="' . route('admin.calendario.eventos.show', $jogo->id_evento) . '#editar"', false);
        // Modal de edição: a opção JOGO existe escondida (o JS libera só para o evento JOGO antigo)
        $this->assertMatchesRegularExpression('#<option value="JOGO" class="js-tipo-jogo"\s+hidden disabled#', $resposta->getContent());
    }

    // ---------- 6 e 7: times do campeonato e aviso de categoria ----------

    public function test_jogo_de_campeonato_aceita_so_os_participantes(): void
    {
        $this->participantes($this->copa, [$this->azul, $this->visitante]);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_time_casa' => $this->verde]))
            ->assertSessionHasErrors(['id_time_casa' => 'O mandante não participa do Sub-11 Masculino do campeonato Copa Escola. Inclua o time nos participantes dessa categoria, ou escolha outro.']);
        $this->assertSame(0, Jogo::count());

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())->assertSessionHasNoErrors();
        // Amistoso: qualquer time
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => 'AMISTOSO', 'id_time_casa' => $this->verde]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Jogo::count());

        // O select sabe as categorias do campeonato e os participantes de cada uma (Fase H, Etapa H3)
        $this->comoAdmin()->get(route('admin.jogos.index'))
            ->assertSee('data-categorias="[' . $this->idSub11M . ']"', false)
            ->assertSee('data-participantes="' . e(json_encode([$this->idSub11M => [$this->azul, $this->visitante]])) . '"', false)
            ->assertDontSee('Os atletas ativos da categoria são inscritos no jogo.');
    }

    public function test_campeonato_sem_participantes_aceita_qualquer_time_e_edicao_mantem_os_times_antigos(): void
    {
        $jogo = $this->jogo($this->copa, $this->verde);
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_time_casa' => $this->verde]))->assertSessionHasNoErrors();

        // Participantes cadastrados depois: o jogo antigo com o Verde continua editável
        $this->participantes($this->copa, [$this->azul]);
        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['id_time_casa' => $this->verde, 'local_evento_calendario' => 'Campo C']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Campo C', $jogo->evento->fresh()->local_evento_calendario);
    }

    public function test_criar_jogo_avisa_atleta_fora_da_categoria(): void
    {
        // Da idade da Copa (Sub-11), mas do feminino: convocada, entra e gera o aviso (quem passa da idade nem entra)
        $idSub11F = $this->idCategoria('Sub-11', 'F');
        $ana = $this->criarAtleta($this->nascidoComIdade(11), 'F', 'ATIVO');
        $this->colocarNaCategoria($ana, $idSub11F);
        $this->participantes($this->copa, [$this->azul, $this->visitante]);
        $this->convocacaoDireta($this->copa, $this->azul, $ana, 7);

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

    // Desde a L2 (30/10/2026), o Local é o select do cadastro de locais (sem a lista de textos usados)
    public function test_select_de_local_e_sugestoes_de_subtipo(): void
    {
        DB::table('tbl_local')->insert([
            'nome_local' => 'Campo da Grade', 'rua_local' => 'Rua A', 'numero_local' => '1', 'bairro_local' => 'Centro',
            'cidade_local' => 'São Paulo', 'estado_local' => 'SP',
        ]);

        $treino = $this->evento('TREINO', 'Treino');
        $paginas = [
            route('admin.calendario.index'), route('admin.calendario.index', ['tab' => 'grade']), route('admin.jogos.index'),
            route('admin.campeonatos.index'), route('admin.campeonatos.create'), route('admin.campeonatos.edit', $this->copa),
            route('admin.calendario.eventos.show', $treino->id_evento_calendario),
        ];
        foreach ($paginas as $pagina) {
            $this->comoAdmin()->get($pagina)->assertOk()
                ->assertSee('class="form-select js-select-local"', false)
                ->assertSee('>Campo da Grade</option>', false)
                ->assertSee('js-novo-local', false)
                ->assertDontSee('list="locaisUsados"', false);
        }

        $this->comoAdmin()->get(route('admin.calendario.index'))
            ->assertSee('<option value="Exame médico"></option>', false)
            ->assertSee('<option value="Avaliação física"></option>', false)
            ->assertSee('class="form-select js-tipo-evento"', false)
            ->assertSee('js-subtipo', false);
    }

    // ---------- 11: tipo e categoria no formulário do campeonato ----------

    public function test_editar_campeonato_abre_com_o_tipo_e_a_categoria_atuais(): void
    {
        $this->participantes($this->copa, []); // a categoria na lista do campeonato

        // Modal da lista: o valor gravado (maiúsculas) existe como opção e vai no botão
        $this->comoAdmin()->get(route('admin.campeonatos.index'))
            ->assertSee('<option value="MATA-MATA">Mata-mata</option>', false)
            ->assertSee('data-tipo="MATA-MATA"', false)
            ->assertSee('data-categorias="' . e(json_encode([$this->idSub11M])) . '"', false);

        // Página de edição: já selecionados
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $this->copa))
            ->assertSee('<option value="MATA-MATA" selected>Mata-mata</option>', false)
            ->assertSee('id="p_cat_' . $this->idSub11M . '" checked', false);

        // Gravar: o tipo vai em maiúsculas; fora da lista é recusado
        $dados = [
            'nome_campeonato' => 'Copa Escola', 'tipo_campeonato' => 'Pontos Corridos',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01', 'categorias' => [$this->idSub11M],
        ];
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), $dados)->assertSessionHasNoErrors();
        $this->assertSame('PONTOS CORRIDOS', DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->value('tipo_campeonato'));

        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [...$dados, 'tipo_campeonato' => 'Inventado'])
            ->assertSessionHasErrors('tipo_campeonato');
    }

    public function test_categoria_com_jogos_nao_sai_do_campeonato_e_os_jogos_mantem_a_categoria(): void
    {
        // Cada jogo tem a categoria dele (Fase H, Etapa H3): trocar as categorias do campeonato não regrava os jogos
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->participantes($this->copa, []); // o Sub-11 na lista do campeonato
        $futuro = $this->jogo($this->copa);
        $oculto = $this->jogo($this->copa, $this->verde);
        foreach ([$futuro, $oculto] as $jogo) {
            $jogo->evento->update(['id_categoria' => $this->idSub11M]);
        }
        $oculto->evento->update(['status_evento_calendario' => 'INATIVO']);

        $dados = [
            'nome_campeonato' => 'Copa Escola', 'tipo_campeonato' => 'MATA-MATA',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01',
        ];

        // Tirar o Sub-11 (que tem jogos, inclusive o oculto): recusado
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [...$dados, 'categorias' => [$idSub15M]])
            ->assertSessionHasErrors(['categorias' => 'Não é possível tirar do campeonato categoria com jogos: Sub-11 Masculino (2 jogo(s)). '
                . 'Mude a categoria desses jogos antes (ou mantenha a categoria).']);
        $this->assertDatabaseMissing('tbl_campeonato_categoria', ['id_campeonato' => $this->copa, 'id_categoria' => $idSub15M]);

        // Acrescentar o Sub-15: os jogos continuam no Sub-11, sem histórico
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [...$dados, 'categorias' => [$this->idSub11M, $idSub15M]])
            ->assertSessionHas('sucesso', 'Campeonato atualizado com sucesso.');
        foreach ([$futuro, $oculto] as $jogo) {
            $this->assertSame($this->idSub11M, (int) $jogo->evento->fresh()->id_categoria);
        }
        $this->assertSame(0, DB::table('tbl_evento_historico')->where('campo_evento_historico', 'id_categoria')->count());
    }

    public function test_cartao_do_time_avisa_quando_a_categoria_do_time_e_outra_que_a_do_jogo(): void
    {
        $jogo = $this->jogo($this->copa);
        $jogo->evento->update(['id_categoria' => $this->idSub11M]);

        // Mesma categoria: só o rótulo
        $this->comoAdmin()->get(route('admin.campeonatos.times', $this->copa))
            ->assertSee('Sub-11 Masculino')
            ->assertDontSee('js-categoria-do-time');

        // Jogo Sub-15, times só com o Sub-11 no cadastro (jogo de antes de 27/10/2026, quando o time passou a ter as
        // categorias marcadas): a do jogo em destaque e as do cadastro embaixo, nos cartões e na tela do jogo
        $jogo->evento->update(['id_categoria' => $this->idCategoria('Sub-15', 'M')]);
        $destaque = '<span class="escal-team-cat escal-team-cat-joga js-categoria-do-time">Sub-15 Masculino</span>';
        $this->comoAdmin()->get(route('admin.campeonatos.times', $this->copa))
            ->assertSee($destaque, false)
            ->assertSee('Cadastro do time: Sub-11 Masculino')
            ->assertDontSee('Time: Sub-11 Masculino');
        $this->comoAdmin()->get(route('admin.campeonatos.jogos'))->assertSee($destaque, false)->assertSee('Cadastro do time: Sub-11 Masculino');

        $jogo = Jogo::where('id_campeonato', $this->copa)->first();
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSee('Cadastro do time: Sub-11 Masculino');
    }

    public function test_tipo_antigo_fora_da_lista_continua_aceito_na_edicao(): void
    {
        DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->update(['tipo_campeonato' => 'REGIONAL']);

        $this->comoAdmin()->get(route('admin.campeonatos.edit', $this->copa))
            ->assertSee('<option value="REGIONAL" selected>REGIONAL</option>', false);
        $this->comoAdmin()->put(route('admin.campeonatos.update', $this->copa), [
            'nome_campeonato' => 'Copa Escola', 'tipo_campeonato' => 'REGIONAL',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01', 'categorias' => [$this->idSub11M],
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
}
