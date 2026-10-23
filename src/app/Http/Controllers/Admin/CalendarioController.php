<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ConfirmaConflitos;
use App\Http\Controllers\Admin\Concerns\ListaPorMes;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\AtletaAfastamento;
use App\Models\Categoria;
use App\Models\EventoAtleta;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\Time;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CalendarioController extends Controller
{
    use ConfirmaConflitos, ListaPorMes;

    public function index(Request $request)
    {
        // Lista de eventos por mês (?mes=AAAA-MM; padrão: mês atual). Com os treinos gerados pela grade,
        // a lista inteira ficaria longa demais
        $mes = $this->mesDaLista($request->query('mes'));

        // Filtros da URL (ramo do menu, tipo, origem e situação), que convivem com o mês. Sem situação, a
        // lista abre sem os ocultos; o aviso mostra quantos ficaram de fora
        $filtros = $this->filtrosDaLista($request);
        $doMesFiltrado = fn () => EventoCalendario::doMes($mes)
            ->doRamo($filtros['ramo'])
            ->when($filtros['tipo'], fn ($q, $tipo) => $q->where('tipo_evento_calendario', $tipo))
            ->when($filtros['origem'] === 'grade', fn ($q) => $q->whereNotNull('id_grade_treino'))
            ->when($filtros['origem'] === 'manual', fn ($q) => $q->whereNull('id_grade_treino'));

        $eventos = $doMesFiltrado()
            ->with(['categoria', 'responsavel', 'historico.usuario', 'jogo'])
            ->comAlteracao()
            ->comInscritosAtivos()
            ->daSituacao($filtros['situacao'])
            ->orderBy('data_evento_calendario', 'desc')
            ->get()
            ->filter(fn ($ev) => $filtros['situacao'] === '' || $ev->situacao === $filtros['situacao'])
            ->values();
        $ocultosForaDaLista = $filtros['situacao'] === ''
            ? $doMesFiltrado()->where('status_evento_calendario', 'INATIVO')->count()
            : 0;
        $mesesLista = $this->mesesDaLista($mes);
        $grades  = GradeTreino::with('categoria')->ordenada()->get();
        $categorias = Categoria::ativas()->get();

        // Categorias inativadas que algum evento ainda usa: aparecem no select da edição
        // como "(inativa)", para editar só o título não tirar a categoria do evento
        $categoriasInativasEmUso = Categoria::where('status_categoria', '<>', 'ATIVO')
            ->whereIn('id_categoria', EventoCalendario::whereNotNull('id_categoria')->select('id_categoria'))
            ->get();

        $mesesGeracao = GradeTreino::mesesPermitidos();

        // Formulário "Novo evento individual" (só na lista de Individuais): atletas ativos pela categoria atual
        $atletasParaIndividual = $filtros['ramo'] === 'individuais' ? Atleta::ativosPorCategoria() : collect();

        return view('admin.calendario.index', compact(
            'eventos', 'grades', 'categorias', 'categoriasInativasEmUso', 'mes', 'mesesLista', 'mesesGeracao',
            'filtros', 'ocultosForaDaLista', 'atletasParaIndividual',
        ));
    }

    // Filtros da lista lidos da URL; valor fora da lista vira vazio (sem filtro)
    private function filtrosDaLista(Request $request): array
    {
        $valido = fn (string $campo, array $permitidos) => in_array($request->query($campo), $permitidos, true)
            ? $request->query($campo)
            : '';

        return [
            'ramo'     => $valido('ramo', array_keys(EventoCalendario::RAMOS)),
            'tipo'     => $valido('tipo', array_keys(EventoCalendario::TIPOS)),
            'origem'   => $valido('origem', ['grade', 'manual']),
            'situacao' => $valido('situacao', array_keys(EventoCalendario::SITUACOES)),
        ];
    }

    /**
     * Meses do select da lista: do primeiro ao último mês com evento, sempre incluindo o mês atual, os
     * meses que podem ser gerados e o mês aberto. Do mais novo para o mais antigo (como a lista).
     */
    private function mesesDaLista(Carbon $aberto): array
    {
        return $this->mesesEntre([
            EventoCalendario::min('data_evento_calendario'), EventoCalendario::max('data_evento_calendario'),
            now(), now()->addMonthsNoOverflow(GradeTreino::MESES_A_FRENTE), $aberto,
        ]);
    }

    // Lista de eventos aberta no mês do evento (depois de criar ou editar)
    private function listaNoMesDo(EventoCalendario $evento)
    {
        return redirect()->route('admin.calendario.index', ['mes' => $evento->data_evento_calendario->format('Y-m')]);
    }

    /**
     * Depois de cancelar ou ocultar: veio da lista do calendário, volta para ela no mês do evento;
     * com os mesmos filtros; veio de outra tela (Jogos, tela do evento), volta para ela.
     */
    private function voltarDoEvento(EventoCalendario $evento)
    {
        $anterior = url()->previous();
        $caminho  = fn (string $url) => rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($caminho($anterior) !== $caminho(route('admin.calendario.index'))) {
            return back();
        }

        parse_str((string) parse_url($anterior, PHP_URL_QUERY), $filtros);

        return redirect()->route('admin.calendario.index', [
            ...$filtros, 'mes' => $evento->data_evento_calendario->format('Y-m'),
        ]);
    }

    // ── Eventos ─────────────────────────────────────────────────────────────

    public function storeEvento(Request $request)
    {
        // Jogo nasce pela tela de Jogos (times, campeonato, elenco); o Calendário não cria evento JOGO
        $request->validate(
            ['tipo_evento_calendario' => Rule::notIn(['JOGO'])],
            ['tipo_evento_calendario.not_in' => 'Jogos são criados pela tela de Jogos, com os times e o campeonato.'],
        );

        $dados = [...$this->dadosEvento($request), 'status_evento_calendario' => 'ATIVO'];

        // Com categoria, os atletas dela serão inscritos: confere conflito antes de criar
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaCriacao($dados))) {
            return $confirmar;
        }

        // Responsável = admin logado que criou o evento (gravado só aqui). Com categoria, os atletas
        // ativos dela já são inscritos (EventoCalendario::criarPor)
        $evento = EventoCalendario::criarPor(auth('admin')->id(), $dados);

        $inscritos = $evento->inscricoes()->count();
        $mensagem  = 'Evento adicionado ao calendário.'
            . ($evento->id_categoria ? " {$inscritos} atleta(s) da categoria inscrito(s)." : '')
            . ($inscritos ? Notificacao::textoNotificados($evento->atletasNotificados) : '');

        // Sem categoria ninguém foi inscrito: abre a tela do evento, para inscrever; com categoria, a lista
        if (! $evento->id_categoria) {
            return redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario)
                ->with('sucesso', $mensagem . ' Inscreva os atletas abaixo.');
        }

        return $this->listaNoMesDo($evento)->with('sucesso', $mensagem);
    }

    /**
     * Evento individual (botão "Novo evento individual" em Individuais): o técnico descreve o que o atleta vai
     * fazer e escolhe os atletas, num formulário só. Sem categoria; tipos EventoCalendario::TIPOS_INDIVIDUAIS.
     * Inscreve só os escolhidos (origem INDIVIDUAL; cada um recebe a notificação, com a descrição resumida) e
     * o evento aparece só na agenda deles. Conflito de horário conferido antes, como nas outras inscrições.
     */
    public function storeEventoIndividual(Request $request)
    {
        $request->merge(['id_categoria' => null]);
        $request->validate([
            'tipo_evento_calendario' => ['required', Rule::in(EventoCalendario::TIPOS_INDIVIDUAIS)],
            'atletas'                => 'required|array|min:1',
            'atletas.*'              => ['integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
        ], [
            'tipo_evento_calendario.in' => 'Evento individual: escolha Avaliação, Reunião ou Evento.',
            'atletas.required'          => 'Escolha pelo menos um atleta.',
            'atletas.min'               => 'Escolha pelo menos um atleta.',
            'atletas.*.exists'          => 'Escolha só atletas ativos.',
        ]);

        $dados = [...$this->dadosEvento($request), 'id_categoria' => null, 'status_evento_calendario' => 'ATIVO'];
        $ids   = collect($request->input('atletas'))->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($confirmar = $this->confirmarConflitos($request, (new EventoCalendario($dados))->conflitosPara($ids))) {
            return $confirmar;
        }

        $idUsuario = auth('admin')->id();
        [$evento, $inscritos] = DB::transaction(function () use ($dados, $ids, $idUsuario) {
            $evento = EventoCalendario::criarPor($idUsuario, $dados);
            $inscritos = 0;
            foreach ($ids as $idAtleta) {
                $inscritos += $evento->inscrever($idAtleta, 'INDIVIDUAL', $idUsuario) ? 1 : 0; // afastado fica de fora
            }

            return [$evento, $inscritos];
        });

        return redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario)
            ->with('sucesso', "Evento individual criado. {$inscritos} atleta(s) inscrito(s)."
                . Notificacao::textoNotificados($evento->atletasNotificados));
    }

    // Tela do evento: dados e inscritos (só atletas ATIVO aparecem; CLAUDE.md, seção 4)
    public function showEvento($id)
    {
        $evento = EventoCalendario::with(['categoria', 'responsavel'])->comAlteracao()->findOrFail($id);

        $inscricoes = $evento->inscricoes()
            ->with(['atleta.categoriasAtivas', 'atleta.posicoes', 'usuario'])
            ->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))
            ->get()
            ->sortBy(fn ($i) => $i->atleta->nome_atleta, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $inscritosInativos = $evento->inscricoes()
            ->whereHas('atleta', fn ($q) => $q->where('status_atleta', '<>', 'ATIVO'))
            ->count();

        // Atletas ativos ainda não inscritos, agrupados pela categoria atual (para o select)
        $disponiveis = Atleta::with('categoriasAtivas')
            ->where('status_atleta', 'ATIVO')
            ->whereNotIn('id_atleta', $evento->inscricoes()->select('id_atleta'))
            ->orderBy('nome_atleta')
            ->get()
            ->groupBy(fn ($atleta) => $atleta->categoriasAtivas->first()?->rotulo ?? 'Sem categoria')
            ->sortKeys();

        $categorias = Categoria::ativas()->get();

        // Atletas da categoria do evento que entraram depois e ainda não estão inscritos (no jogo, nenhum)
        $faltantesDaCategoria = count($evento->idsFaltantesDaCategoria());

        // Evento de jogo: escalação por time (mandante/visitante internos), por qual time cada atleta joga
        // (convocação no campeonato, elenco no amistoso), a camisa da convocação e quantos ainda não estão
        // inscritos (ao lado do "Preencher pela convocação/pelo elenco")
        $jogo    = $evento->jogo()->with(['timeCasa', 'timeVisitante', 'campeonato', 'evento.categoria'])->first();
        $jogo?->setRelation('evento', $evento);
        $elencos = $jogo?->atletasPorTime() ?? [];
        $camisas = $jogo?->camisas() ?? [];
        $faltantesDoElenco = $jogo ? count($jogo->idsFaltantesDoElenco()) : 0;

        // Amistoso: caixa "Escolher atletas" com os ativos ainda não inscritos, por categoria (Fase G, Etapa 3)
        $idsInscritos = $evento->inscricoes()->pluck('id_atleta')->map(fn ($idAtleta) => (int) $idAtleta)->all();
        $gruposDoAmistoso = $jogo?->escolheAtletas()
            ? Atleta::ativosPorCategoria()
                ->map(fn ($grupo) => $grupo->reject(fn ($atleta) => in_array((int) $atleta->id_atleta, $idsInscritos, true))->values())
                ->filter(fn ($grupo) => $grupo->isNotEmpty())
            : collect();

        // Jogo de campeonato: inscritos que passam da idade (de antes da regra, ou o campeonato mudou), com
        // aviso e selo na lista, e os do select que não podem entrar ([id_atleta => "Nome (16 anos)"])
        $inscritosAcimaDaIdade = $jogo?->acimaDaIdade($inscricoes->pluck('id_atleta')->all()) ?? [];

        // Treino: inscritos fora do turno deles (o oposto ao da escola), com o selo na lista ([id_atleta => motivo])
        $turnoDoTreino = $evento->turnoDoTreino();
        $inscritosForaDoTurno = $turnoDoTreino
            ? $inscricoes->mapWithKeys(fn ($i) => [$i->id_atleta => $i->atleta->foraDoTurno($turnoDoTreino)])->filter()->all()
            : [];
        $disponiveisAcimaDaIdade = $jogo?->acimaDaIdade($disponiveis->flatten()->pluck('id_atleta')->all()) ?? [];

        // Afastados na data do evento: desabilitados no select e na caixa do amistoso ([id_atleta => "afastado até 10/11"])
        $disponiveisAfastados = collect(AtletaAfastamento::naData($evento->data_evento_calendario))
            ->map(fn (AtletaAfastamento $a) => $a->textoAte())->all();

        // Notificações enviadas sobre o evento, da mais nova para a mais antiga (só no admin)
        // Leitura pelos responsáveis (Fase 9): quantos leram, de quantos o atleta tem
        $notificacoes = $evento->notificacoes()
            ->with(['atleta' => fn ($q) => $q->withCount('responsaveis'), 'usuario'])
            ->withCount('leiturasDosResponsaveis')
            ->get();

        // Cabeçalho: linha de caminho, "Voltar" pela origem (jogo: lista de Jogos; outros: Calendário no mês
        // do evento) e os formulários de edição (do jogo ou do evento) já preenchidos
        $evento->setRelation('jogo', $jogo);
        $caminho = $this->caminhoDoEvento($evento);
        $voltar  = $jogo
            ? route('admin.jogos.index')
            : route('admin.calendario.index', ['mes' => $evento->data_evento_calendario->format('Y-m')]);

        if ($jogo) {
            $jogo->setRelation('evento', $evento);
            $campeonatosDoJogo = JogosController::campeonatosDoFormulario();
            $timesDoJogo       = Time::orderBy('nome_time')->get();
            $sumula            = $this->dadosDaSumula($jogo);
        } else {
            $evento->load('historico.usuario');
            $categoriasInativasEmUso = $evento->categoria && $evento->categoria->status_categoria !== 'ATIVO'
                ? collect([$evento->categoria])
                : collect();
        }

        return view('admin.calendario.evento', compact(
            'evento', 'inscricoes', 'inscritosInativos', 'disponiveis', 'categorias', 'faltantesDaCategoria', 'jogo', 'elencos', 'camisas', 'gruposDoAmistoso',
            'faltantesDoElenco', 'notificacoes', 'caminho', 'voltar', 'inscritosAcimaDaIdade', 'disponiveisAcimaDaIdade', 'inscritosForaDoTurno', 'turnoDoTreino',
            'disponiveisAfastados',
        ) + [
            'campeonatosDoJogo'       => $campeonatosDoJogo ?? collect(),
            'timesDoJogo'             => $timesDoJogo ?? collect(),
            'categoriasInativasEmUso' => $categoriasInativasEmUso ?? collect(),
            'sumula'                  => $sumula ?? null,
        ]);
    }

    /**
     * Bloco "Súmula" da tela do jogo (Etapa S1): os gols e cartões lançados, a situação de cada time da AACJ e os
     * escalados de cada um (para os selects). null = jogo sem time da AACJ (dois externos: ninguém para lançar).
     */
    private function dadosDaSumula(Jogo $jogo): ?array
    {
        $internos = $jogo->timesEscalaveis();
        if ($internos->isEmpty()) {
            return null;
        }

        $escalados = $internos->mapWithKeys(fn (Time $time) => [$time->id_time => [
            'nome'    => $time->nome_time,
            'atletas' => $jogo->escaladosDoTime($time->id_time)->sortBy('nome_atleta', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn ($a) => ['id' => (int) $a->id_atleta, 'nome' => $a->nome_atleta, 'camisa' => $a->camisa_convocacao])->values()->all(),
        ]]);
        $idsEscalados = $escalados->flatMap(fn ($t) => array_column($t['atletas'], 'id'))->all();

        return [
            'gols'          => $jogo->gols()->with(['time', 'atleta', 'assistente', 'usuario'])
                ->orderBy('periodo_jogo_gol')->orderByRaw('minuto_jogo_gol IS NULL')->orderBy('minuto_jogo_gol')->orderBy('id_jogo_gol')->get(),
            'cartoes'       => $jogo->cartoes()->with(['atleta', 'usuario'])->orderBy('id_cartao')->get(),
            'resumo'        => $jogo->resumoDaSumula(),
            'escalados'     => $escalados->all(),
            'id_escalados'  => $idsEscalados,
        ];
    }

    /**
     * Linha de caminho da tela do evento: [rótulo, url] por parte; a última (o evento) sem link.
     * Eventos › ramo (EventoCalendario::urlDoRamo) › campeonato do jogo (lista de Jogos filtrada) › título.
     * Evento sem ramo (JOGO antigo sem tbl_jogos): Eventos › título.
     */
    private function caminhoDoEvento(EventoCalendario $evento): array
    {
        $caminho = [['Eventos', route('admin.calendario.index')]];

        if ($ramo = $evento->ramo()) {
            $caminho[] = [EventoCalendario::RAMOS[$ramo], EventoCalendario::urlDoRamo($ramo)];
        }

        if ($campeonato = $evento->jogo?->campeonato) {
            $caminho[] = [$campeonato->nome_campeonato, route('admin.jogos.index', ['campeonato' => $campeonato->id_campeonato])];
        }

        $caminho[] = [$evento->titulo_evento_calendario, null];

        return $caminho;
    }

    // Escala (ou tira da escalação) um inscrito num dos times do jogo
    public function escalarAtleta(Request $request, $id, $idAtleta)
    {
        $evento     = EventoCalendario::findOrFail($id);
        $jogo       = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();
        $inscricao  = $evento->inscricoes()->where('id_atleta', $idAtleta)->firstOrFail();

        if (! $jogo) {
            return back()->with('erro', 'Este evento não é um jogo: não tem escalação.');
        }

        $request->validate(['id_time' => 'nullable|integer']);
        $idTime = $request->filled('id_time') ? (int) $request->id_time : null;

        if ($erro = $jogo->erroDeEscalacao($idTime)) {
            return back()->with('erro', $erro);
        }

        // Inscrito de antes (ou o campeonato mudou) que passa da idade: não é escalado; tirar do time pode
        if ($idTime !== null && ($erro = $jogo->erroDeIdade((int) $idAtleta))) {
            return back()->with('erro', $erro);
        }

        // Mesmo time: nada muda (não limpa o titular/reserva)
        if (($inscricao->id_time === null ? null : (int) $inscricao->id_time) !== $idTime) {
            $inscricao->update(EventoAtleta::aoMudarDeTime($idTime));
        }
        $nome = $inscricao->atleta->nome_atleta;

        if ($idTime !== null) {
            $this->avisarForaDaCategoria($evento, [(int) $idAtleta]);
        }

        return back()->with('sucesso', $idTime
            ? "{$nome} escalado(a) no {$jogo->timesEscalaveis()->firstWhere('id_time', $idTime)->nome_time}."
            : "{$nome} ficou sem time.");
    }

    /**
     * "Salvar escalação" de um time do jogo (Fase D, Etapa D2): posição no jogo e titular/reserva de cada
     * escalado nele (escalacao[id_atleta][posicao|titular]; vazio = a definir). A posição sugerida (a principal
     * do atleta, pré-selecionada na tela) só é gravada aqui. Atleta que não está escalado neste time é ignorado.
     * Sem aviso ao atleta (por enquanto, só no admin). Avisos depois de salvar: sem goleiro titular, mais
     * titulares que o campo.
     */
    public function salvarEscalacao(Request $request, $id, $idTime)
    {
        $evento = EventoCalendario::findOrFail($id);
        $jogo   = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();
        $time   = $jogo?->timesEscalaveis()->firstWhere('id_time', (int) $idTime);

        if (! $time) {
            return back()->with('erro', 'Este time não é um time da AACJ neste jogo: não tem escalação.');
        }

        $request->validate([
            'escalacao'           => 'required|array',
            'escalacao.*.posicao' => ['nullable', Rule::in(array_keys(Atleta::POSICOES))],
            'escalacao.*.titular' => ['nullable', Rule::in(array_keys(EventoAtleta::TITULARIDADES))],
        ], [
            'escalacao.*.posicao.in' => 'Posição inválida: escolha uma da lista.',
            'escalacao.*.titular.in' => 'Escolha Titular, Reserva ou A definir.',
        ]);

        $inscricoes = $evento->inscricoes()->where('id_time', $time->id_time)
            ->whereIn('id_atleta', array_keys($request->input('escalacao')))
            ->get();

        DB::transaction(function () use ($inscricoes, $request) {
            foreach ($inscricoes as $inscricao) {
                $linha = $request->input("escalacao.{$inscricao->id_atleta}");
                $inscricao->update([
                    'posicao_evento_atleta' => $linha['posicao'] ?? null,
                    'titular_evento_atleta' => $linha['titular'] ?? null,
                ]);
            }
        });

        $resumo = Jogo::resumoDaEscalacao($evento->inscricoes()->where('id_time', $time->id_time)
            ->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))->get());

        return back()
            ->with('sucesso', "Escalação do {$time->nome_time} salva: {$resumo['titulares']} titular(es), "
                . "{$resumo['reservas']} reserva(s), {$resumo['a_definir']} a definir.")
            ->with('aviso', $resumo['avisos'] ? implode(' ', $resumo['avisos']) : null);
    }

    /**
     * "Preencher pela convocação", só no jogo de campeonato (Jogo::inscreverElenco): inscreve quem falta dos
     * convocados do campeonato (origem CONVOCACAO) pelos times internos do jogo, com alerta de conflito, já
     * escalados, e escala quem está inscrito sem time. Quem já tem time não muda; ninguém sai. Atleta fora da
     * categoria do jogo gera aviso; quem passa da idade do campeonato fica de fora (a mensagem diz quem).
     * Amistoso: recusa (os atletas são escolhidos no jogo, "Escolher atletas").
     */
    public function preencherPeloElenco(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);
        $jogo   = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();

        if (! $jogo || $jogo->timesEscalaveis()->isEmpty()) {
            return back()->with('erro', 'Este jogo não tem time interno para escalar.');
        }

        // Amistoso não tem lista automática (Fase G, Etapa 3)
        if ($jogo->escolheAtletas()) {
            return back()->with('erro', 'No amistoso, escolha os atletas em "Escolher atletas", nesta tela.');
        }

        $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($jogo->idsFaltantesDoElenco()))) {
            return $confirmar;
        }

        $elenco    = $jogo->inscreverElenco(auth('admin')->id());
        $inscritos = $elenco['inscritos_com_time'] + $elenco['inscritos_sem_time'];

        $mensagem = "Escalação pela convocação: {$elenco['escalados']} inscrito(s) escalado(s), {$elenco['inscritos_com_time']} atleta(s) inscrito(s) e escalado(s).";
        if ($inscritos > 0) {
            $mensagem .= Notificacao::textoNotificados($evento->atletasNotificados);
        }
        $mensagem .= $jogo->textoAcimaDaIdade($elenco['acima_da_idade']);

        $this->avisarForaDaCategoria($evento, $elenco['ids_mexidos']);

        return back()->with('sucesso', $mensagem);
    }

    /**
     * Amistoso (Fase G, Etapa 3): "Escolher atletas" inscreve os marcados (origem INDIVIDUAL) já escalados no
     * time da AACJ do jogo, com alerta de conflito; quem já está inscrito é ignorado. Amistoso não tem limite de
     * idade; outra categoria/sexo só avisa. Rascunho não avisa ninguém (o aviso sai ao publicar).
     */
    public function escolherAtletas(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);
        $jogo   = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();
        $time   = $jogo?->escolheAtletas() ? $jogo->timeDaAcj() : null;

        if (! $time) {
            return back()->with('erro', 'Escolher atletas é só para amistoso com um time da AACJ.');
        }

        $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados

        $request->validate([
            'atletas'   => 'required|array|min:1',
            'atletas.*' => ['integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
        ], [
            'atletas.required' => 'Escolha pelo menos um atleta.',
            'atletas.*.exists' => 'Escolha só atletas ativos.',
        ]);

        $jaInscritos = $evento->inscricoes()->pluck('id_atleta')->map(fn ($idAtleta) => (int) $idAtleta)->all();
        $novos = array_values(array_diff(array_unique(array_map('intval', $request->input('atletas'))), $jaInscritos));

        if (! $novos) {
            return back()->with('sucesso', 'Os atletas escolhidos já estavam inscritos.');
        }

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($novos))) {
            return $confirmar;
        }

        $entraram = DB::transaction(fn () => collect($novos)
            ->filter(fn ($idAtleta) => $evento->inscrever($idAtleta, 'INDIVIDUAL', auth('admin')->id(), $time->id_time))
            ->values()->all());

        $this->avisarForaDaCategoria($evento, $entraram);

        return back()->with('sucesso', count($entraram) . " atleta(s) inscrito(s) e escalado(s) no {$time->nome_time}."
            . Notificacao::textoNotificados($evento->atletasNotificados));
    }

    // Aviso informativo (não bloqueia) para atletas fora da categoria/sexo do evento, na próxima tela
    private function avisarForaDaCategoria(EventoCalendario $evento, array $idsAtletas): void
    {
        if ($avisos = $evento->avisosForaDaCategoria($idsAtletas)) {
            session()->flash('avisos_categoria', $avisos);
        }
    }

    // Treino fora do turno do atleta (o oposto ao da escola): só avisa, na próxima tela (Fase A, Etapa 3)
    private function avisarForaDoTurno(EventoCalendario $evento, array $idsAtletas): void
    {
        if ($avisos = $evento->avisosForaDoTurno($idsAtletas)) {
            session()->flash('avisos_turno', $avisos);
        }
    }

    // "Atualizar inscritos pela categoria": só acrescenta quem falta (não remove ninguém)
    public function atualizarInscritosPelaCategoria($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        if (! $evento->id_categoria) {
            return back()->with('erro', 'Este evento não tem categoria.');
        }

        // No jogo, quem joga são os convocados do campeonato (ou o elenco dos times, no amistoso), não a categoria
        if ($evento->ehJogo()) {
            return back()->with('erro', $evento->jogo->pelaConvocacao()
                ? 'No jogo de campeonato, os inscritos vêm da convocação: use "Preencher pela convocação".'
                : 'No jogo, os inscritos vêm do elenco dos times: use "Preencher pelo elenco".');
        }

        $faltantes = $evento->idsFaltantesDaCategoria();
        if ($confirmar = $this->confirmarConflitos(request(), $evento->conflitosPara($faltantes))) {
            return $confirmar;
        }

        $novos = $evento->inscreverCategoria($evento->id_categoria, 'CATEGORIA', auth('admin')->id());
        $this->avisarForaDoTurno($evento, $faltantes);

        return back()->with('sucesso', "{$novos} atleta(s) da categoria inscrito(s)."
            . ($novos ? Notificacao::textoNotificados($evento->atletasNotificados) : ''));
    }

    // Inscrição individual: um atleta ativo escolhido no select (num jogo, pode já escolher o time)
    public function inscreverAtleta(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $request->validate([
            'id_atleta' => ['required', 'integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
            'id_time'   => 'nullable|integer',
        ], [
            'id_atleta.required' => 'Escolha um atleta.',
            'id_atleta.exists'   => 'Escolha um atleta ativo.',
        ]);

        // Afastado na data do evento: indisponível para tudo (16/10/2026); para inscrever, encerrar o afastamento antes
        if ($afastamento = $evento->afastamentoDe((int) $request->id_atleta)) {
            return back()->withInput()->with('erro', Atleta::whereKey((int) $request->id_atleta)->value('nome_atleta')
                . " está {$afastamento->textoAte()}. Encerre o afastamento antes de inscrever.");
        }

        $idTime = $request->filled('id_time') ? (int) $request->id_time : null;
        $jogo   = $evento->jogo()->with(['timeCasa', 'timeVisitante', 'campeonato', 'evento.categoria'])->first();
        $jogo?->setRelation('evento', $evento);

        // Amistoso: todo inscrito joga pelo time da AACJ do jogo (Fase G, Etapa 3)
        if ($idTime === null && $jogo?->escolheAtletas()) {
            $idTime = $jogo->timeDaAcj()?->id_time;
        }

        if ($idTime !== null) {
            $erro = $jogo ? $jogo->erroDeEscalacao($idTime) : 'Este evento não é um jogo: não tem escalação.';

            if ($erro) {
                return back()->with('erro', $erro)->withInput();
            }
        }

        // Jogo de campeonato: quem passa da idade não entra (o mais novo pode jogar acima)
        if ($jogo && ($erro = $jogo->erroDeIdade((int) $request->id_atleta))) {
            return back()->with('erro', $erro)->withInput();
        }

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara([(int) $request->id_atleta]))) {
            return $confirmar;
        }

        $inscreveu = $evento->inscrever((int) $request->id_atleta, 'INDIVIDUAL', auth('admin')->id(), $idTime);

        // Em jogo, inscrever alguém de outra categoria/sexo gera aviso (não bloqueia)
        if ($inscreveu && $evento->tipo_evento_calendario === 'JOGO') {
            $this->avisarForaDaCategoria($evento, [(int) $request->id_atleta]);
        }
        if ($inscreveu) {
            $this->avisarForaDoTurno($evento, [(int) $request->id_atleta]);
        }

        return back()->with('sucesso', match (true) {
            ! $inscreveu      => 'O atleta já estava inscrito.',
            $idTime !== null  => 'Atleta inscrito e escalado.',
            default           => 'Atleta inscrito.',
        } . ($inscreveu ? Notificacao::textoNotificados($evento->atletasNotificados) : ''));
    }

    /**
     * "Adicionar todos de uma categoria": para eventos de várias categorias (ex.: avaliação física).
     * Pode ser usado várias vezes; quem já está inscrito é ignorado. Origem INDIVIDUAL: foi uma
     * escolha do admin, e a sincronização pela categoria do evento (Etapa 2) não mexe nessas.
     */
    public function inscreverCategoriaNoEvento(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $request->validate([
            'id_categoria' => ['required', 'integer', Rule::exists('tbl_categoria', 'id_categoria')->where('status_categoria', 'ATIVO')],
        ], [
            'id_categoria.required' => 'Escolha uma categoria.',
            'id_categoria.exists'   => 'Escolha uma categoria ativa.',
        ]);

        $categoria = Categoria::find($request->id_categoria);

        // Só quem ainda não está inscrito entra; o conflito é conferido para esses
        $inscritos = $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        $entrariam = array_values(array_diff(Atleta::idsAtivosNaCategoria($categoria->id_categoria), $inscritos));

        // Jogo de campeonato: quem passa da idade fica de fora (a mensagem diz quem)
        $jogo = $evento->jogo()->with(['campeonato', 'evento.categoria'])->first();
        $jogo?->setRelation('evento', $evento);
        $acima     = $jogo?->acimaDaIdade($entrariam) ?? [];
        $entrariam = array_values(array_diff($entrariam, array_keys($acima)));

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($entrariam))) {
            return $confirmar;
        }

        $novos = $evento->inscreverCategoria($categoria->id_categoria, 'INDIVIDUAL', auth('admin')->id(), array_keys($acima));
        $this->avisarForaDoTurno($evento, $entrariam);

        return back()->with('sucesso', "{$categoria->rotulo}: {$novos} atleta(s) inscrito(s)"
            . ($novos === 0 ? ' (todos já estavam inscritos ou não há atletas ativos).' : '.' . Notificacao::textoNotificados($evento->atletasNotificados))
            . ($jogo?->textoAcimaDaIdade($acima, 'da categoria') ?? ''));
    }

    public function removerInscricao($id, $idAtleta)
    {
        $evento  = EventoCalendario::findOrFail($id);
        $removeu = $evento->removerInscricao((int) $idAtleta, auth('admin')->id());

        return back()->with('sucesso', 'Inscrição removida.'
            . ($removeu ? Notificacao::textoNotificados($evento->atletasNotificados) : ''));
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        // Jogo (com tbl_jogos) é editado pelo formulário do jogo: times, campeonato e elenco andam juntos
        if ($evento->ehJogo()) {
            return redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario)
                ->with('erro', 'Este evento é um jogo: edite pelo botão "Editar" da tela do jogo.');
        }

        // Só o evento JOGO antigo (sem tbl_jogos) continua JOGO; nenhum outro vira JOGO por aqui
        if ($evento->tipo_evento_calendario !== 'JOGO') {
            $request->validate(
                ['tipo_evento_calendario' => Rule::notIn(['JOGO'])],
                ['tipo_evento_calendario.not_in' => 'Jogos são criados pela tela de Jogos, com os times e o campeonato.'],
            );
        }

        $dados  = $this->dadosEvento($request, $evento);

        // Mudou data, horário ou categoria: confere conflito dos atletas que ficarão inscritos
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaEdicao($evento, $dados))) {
            return $confirmar;
        }

        // Nem o status (só pelas ações de cancelar e ocultar) nem o responsável mudam pela edição.
        // O que mudar em data, horário, local, título, tipo ou categoria vai para o histórico; mudou de
        // categoria, as inscrições automáticas acompanham (concluído não muda); os atletas são avisados.
        $mensagem = 'Evento atualizado.' . $this->salvarEdicaoDoEvento($evento, $dados);

        // Editado pela tela do evento (voltar=evento): volta para ela. Pela lista: no mês da data nova (se a
        // data mudou, o evento saiu do mês que estava aberto)
        if ($request->input('voltar') === 'evento') {
            return redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario)->with('sucesso', $mensagem);
        }

        return $this->listaNoMesDo($evento)->with('sucesso', $mensagem);
    }

    /**
     * Valida e devolve os campos editáveis do evento.
     * Categoria: só ativa; na edição, a categoria atual é aceita mesmo que tenha sido inativada depois.
     */
    private function dadosEvento(Request $request, ?EventoCalendario $evento = null): array
    {
        $idAtual = $evento?->id_categoria;

        $request->validate([
            'titulo_evento_calendario'          => 'required|string|max:255',
            'tipo_evento_calendario'            => ['required', Rule::in(array_keys(EventoCalendario::TIPOS))],
            'id_categoria'                      => ['nullable', 'integer', Rule::exists('tbl_categoria', 'id_categoria')
                ->where(fn ($q) => $q->where('status_categoria', 'ATIVO')->orWhere('id_categoria', $idAtual))],
            'data_evento_calendario'            => 'required|date',
            'horario_inicio_evento_calendario'  => 'nullable|date_format:H:i,H:i:s',
            'horario_fim_evento_calendario'     => 'nullable|date_format:H:i,H:i:s',
            'local_evento_calendario'           => 'nullable|string|max:255',
            'subtipo_evento_calendario'         => 'nullable|string|max:60',
            'descricao_evento_calendario'       => 'nullable|string',
        ], [
            'id_categoria.exists' => 'Escolha uma categoria ativa, ou deixe sem categoria para evento individual.',
        ]);

        return $request->only([
            'titulo_evento_calendario', 'tipo_evento_calendario', 'id_categoria',
            'data_evento_calendario', 'horario_inicio_evento_calendario',
            'horario_fim_evento_calendario', 'local_evento_calendario',
            'subtipo_evento_calendario', 'descricao_evento_calendario',
        ]);
    }

    // Cancelar <-> reativar. Cancelado continua visível (com o selo); oculto precisa ser mostrado antes.
    public function cancelarEvento($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        if ($evento->estaOculto()) {
            return back()->with('erro', 'Este evento está oculto. Mostre o evento antes de cancelar ou reativar.');
        }

        // Avisa os inscritos: CANCELAMENTO ou REATIVACAO (concluído não avisa)
        $novo = $evento->estaCancelado() ? 'ATIVO' : 'CANCELADO';
        $evento->mudarStatus($novo, auth('admin')->id());

        return $this->voltarDoEvento($evento)->with('sucesso', ($novo === 'CANCELADO' ? 'Evento cancelado.' : 'Evento reativado.')
            . Notificacao::textoNotificados($evento->atletasNotificados)
            . $this->avisoDeConflitoAoReativar($evento));
    }

    // Evento que volta a ficar ativo pode passar a conflitar: avisa sem bloquear (já está feito).
    // Conflito real vai na mensagem; aviso fraco (sem horário) vai no aviso informativo.
    private function avisoDeConflitoAoReativar(EventoCalendario $evento): string
    {
        if ($evento->status_evento_calendario !== 'ATIVO') {
            return '';
        }

        $ids = $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        [$fracos, $fortes] = $evento->conflitosPara($ids)->partition(fn ($c) => $c['fraco']);

        if ($fracos->isNotEmpty()) {
            session()->flash('avisos_mesmo_dia', $fracos->map(fn ($c) => EventoCalendario::descreverConflito($c))->values()->all());
        }

        return $fortes->isEmpty() ? '' : ' Atenção, conflito de horário: '
            . $fortes->map(fn ($c) => EventoCalendario::descreverConflito($c))->implode(' ');
    }

    // Ocultar <-> mostrar. Oculto (INATIVO) some do site e faz o papel da exclusão, sem perder o registro.
    // Mostrar devolve o status de antes de ocultar (pelo histórico): cancelado volta como cancelado.
    public function ocultarEvento($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        // Ocultar evento ativo avisa como CANCELAMENTO; mostrar de volta como ativo, como REATIVACAO.
        // Entre cancelado e oculto não avisa.
        $novo = $evento->estaOculto() ? $evento->statusAntesDeOcultar() : 'INATIVO';
        $evento->mudarStatus($novo, auth('admin')->id());

        $mensagem = match ($novo) {
            'INATIVO'   => 'Evento ocultado.',
            'CANCELADO' => 'Evento visível de novo (continua cancelado).',
            default     => 'Evento visível de novo.',
        };

        return $this->voltarDoEvento($evento)->with('sucesso', $mensagem
            . Notificacao::textoNotificados($evento->atletasNotificados)
            . $this->avisoDeConflitoAoReativar($evento));
    }

    // ── Geração da agenda pela grade (Fase 7) ───────────────────────────────

    // Prévia: o que será gerado no mês escolhido, por linha da grade (nada é gravado aqui)
    public function previaGeracao(Request $request)
    {
        if (! $mes = $this->mesDeGeracao($request)) {
            return $this->mesNaoPermitido();
        }

        $previa = GradeTreino::previaDoMes($mes, $this->datasMarcadas($request));
        $mesesGeracao = GradeTreino::mesesPermitidos();

        return view('admin.calendario.gerar', compact('previa', 'mes', 'mesesGeracao'));
    }

    /**
     * Gera de verdade: recalcula a prévia na hora (não confia na tela) e grava tudo ou nada.
     * Conflito real sem confirmar_conflito=1: nada é gravado e a prévia volta com o alerta (como nas
     * outras telas). Aviso fraco não bloqueia: depois de gerar, vai no aviso azul da lista.
     */
    public function gerarAgenda(Request $request)
    {
        if (! $mes = $this->mesDeGeracao($request)) {
            return $this->mesNaoPermitido();
        }

        $marcadas = $this->datasMarcadas($request);
        // Volta para a prévia com as mesmas datas marcadas
        $previaComEscolha = ['mes' => $mes] + ($marcadas === null ? [] : ['escolha' => 1, 'marcadas' => $marcadas]);

        try {
            $resultado = GradeTreino::gerarMes($mes, auth('admin')->id(), $request->boolean('confirmar_conflito'), $marcadas);
        } catch (UniqueConstraintViolationException $e) {
            // Outro admin gerou o mesmo mês ao mesmo tempo: nada foi gravado (o lote foi desfeito)
            return redirect()->route('admin.calendario.grade.previa', ['mes' => $mes])
                ->with('erro', 'Outro usuário gerou eventos deste mês ao mesmo tempo. Nada foi gravado; confira a prévia e gere de novo.');
        }

        $totais = $resultado['previa']['totais'];

        if (! $resultado['gerado']) {
            return redirect()->route('admin.calendario.grade.previa', $previaComEscolha)
                ->with('erro', "Nada foi gerado: o lote tem {$totais['conflitos_reais']} conflito(s) de horário. Confira e use \"Confirmar mesmo assim e gerar\".");
        }

        $mensagem = $totais['eventos'] === 0
            ? ($totais['desmarcadas'] > 0 ? 'Nenhuma data marcada: nada foi gerado.' : 'Nada novo para gerar neste mês.')
            : "{$totais['eventos']} evento(s) gerado(s), {$totais['inscricoes']} inscrição(ões)."
                . Notificacao::textoNotificados($resultado['notificados']);
        if ($totais['conflitos_reais'] > 0) {
            $mensagem .= " O lote tinha {$totais['conflitos_reais']} conflito(s) de horário, confirmado(s).";
        }
        if ($totais['existentes'] > 0) {
            $mensagem .= " {$totais['existentes']} já existia(m) e não foi(ram) recriado(s).";
        }
        if ($totais['puladas'] > 0) {
            $mensagem .= " {$totais['puladas']} de hoje já tinha(m) passado e não foi(ram) gerado(s).";
        }
        if ($totais['desmarcadas'] > 0 && $totais['eventos'] > 0) {
            $mensagem .=" {$totais['desmarcadas']} data(s) desmarcada(s) não foi(ram) gerada(s).";
        }

        // Aviso fraco (mesmo dia, sem horário de início): só informa, no aviso azul da lista
        $fracos = $resultado['previa']['conflitos'][EventoCalendario::CONFLITO_FRACO];
        if ($fracos->isNotEmpty()) {
            session()->flash('avisos_mesmo_dia', $fracos->map(fn ($g) => EventoCalendario::descreverGrupoDeConflito($g))->all());
        }

        return redirect()->route('admin.calendario.index', ['mes' => $mes])->with('sucesso', $mensagem);
    }

    // Mês da geração (AAAA-MM), só entre os permitidos (mês atual e os seguintes); null se não for
    private function mesDeGeracao(Request $request): ?string
    {
        $mes = (string) $request->input('mes');

        return array_key_exists($mes, GradeTreino::mesesPermitidos()) ? $mes : null;
    }

    /**
     * Datas marcadas na prévia ("idGrade|AAAA-MM-DD", GradeTreino::chaveDaData), ou null = todas.
     * escolha=1 diz que houve escolha (com todas desmarcadas, nenhum "marcadas[]" chega); valor fora do
     * formato é ignorado.
     */
    private function datasMarcadas(Request $request): ?array
    {
        if (! $request->boolean('escolha')) {
            return null;
        }

        return array_values(array_filter((array) $request->input('marcadas', []),
            fn ($chave) => is_string($chave) && preg_match('/^\d+\|\d{4}-\d{2}-\d{2}$/', $chave)));
    }

    private function mesNaoPermitido()
    {
        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])
            ->with('erro', 'Escolha um mês entre o atual e os ' . GradeTreino::MESES_A_FRENTE . ' seguintes para gerar a agenda.');
    }

    // ── Grade de Treinos ────────────────────────────────────────────────────

    /**
     * Novo horário: os dias marcados viram uma linha cada, com os mesmos dados (a grade tem um dia por linha;
     * na edição, cada linha muda sozinha). Tudo ou nada.
     */
    public function storeGrade(Request $request)
    {
        $request->validate([
            'dias_semana_grade_treino'   => ['required', 'array'],
            'dias_semana_grade_treino.*' => ['distinct', Rule::in(array_keys(GradeTreino::DIAS_SEMANA))],
        ], [
            'dias_semana_grade_treino.required' => 'Marque pelo menos um dia da semana.',
        ]);

        $dados = $this->dadosGrade($request);
        $dias  = array_values(array_intersect(array_keys(GradeTreino::DIAS_SEMANA), $request->dias_semana_grade_treino));

        DB::transaction(function () use ($dados, $dias) {
            foreach ($dias as $dia) {
                GradeTreino::create([...$dados, 'dia_semana_grade_treino' => $dia, 'status_grade_treino' => 'ATIVO']);
            }
        });

        $mensagem = count($dias) === 1
            ? 'Horário adicionado à grade.'
            : count($dias) . ' horários adicionados à grade (um por dia: '
                . implode(', ', array_map(fn ($dia) => GradeTreino::DIAS_SEMANA[$dia], $dias)) . ').';

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', $mensagem);
    }

    // Campos da grade que mudam os eventos gerados (observação e ordem não mudam)
    private const CAMPOS_GRADE_DO_EVENTO = [
        'dia_semana_grade_treino', 'horario_inicio_grade_treino', 'horario_fim_grade_treino',
        'local_grade_treino', 'id_categoria', 'categoria_grade_treino', 'tipo_grade_treino',
    ];

    public function updateGrade(Request $request, $id)
    {
        $grade = GradeTreino::findOrFail($id);

        $request->validate([
            'dia_semana_grade_treino' => ['required', Rule::in(array_keys(GradeTreino::DIAS_SEMANA))],
        ]);

        $antes = $this->camposDoEvento($grade);
        $grade->update([...$this->dadosGrade($request, $grade), 'dia_semana_grade_treino' => $request->dia_semana_grade_treino]);

        // Mudou algo que o evento copia: os eventos futuros já gerados NÃO acompanham (só avisa)
        $mensagem = 'Horário atualizado.';
        if ($antes !== $this->camposDoEvento($grade) && ($futuros = $grade->contarEventosFuturosAtivos()) > 0) {
            $mensagem .= " Atenção: {$futuros} evento(s) futuro(s) já gerado(s) por este horário não foram alterados;"
                . ' edite-os na lista de eventos (filtro Origem: Grade).';
        }

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', $mensagem);
    }

    // Campos que o evento gerado copia, comparáveis ("08:00" e "08:00:00" são o mesmo horário)
    private function camposDoEvento(GradeTreino $grade): array
    {
        $valores = [];
        foreach (self::CAMPOS_GRADE_DO_EVENTO as $campo) {
            $valor = $grade->getAttribute($campo);
            $valores[$campo] = match (true) {
                $valor === null || $valor === ''    => null,
                str_starts_with($campo, 'horario_') => substr((string) $valor, 0, 5),
                default                             => (string) $valor,
            };
        }

        return $valores;
    }

    /**
     * Valida e monta os dados de um horário da grade, menos o dia (vários no store, um só no update).
     * Com categoria: o rótulo (categoria_grade_treino) vem do nome da categoria.
     * Sem categoria ("Geral", ex.: Integrado, Treino Livre): o rótulo é obrigatório.
     * Tipo e local são NOT NULL no banco, por isso obrigatórios aqui.
     */
    private function dadosGrade(Request $request, ?GradeTreino $atual = null): array
    {
        // JOGO saiu da escolha (o jogo nasce na tela de Jogos): só a linha que já é JOGO pode continuar com ele
        $tipos = $atual?->tipo_grade_treino === 'JOGO' ? GradeTreino::TIPOS : GradeTreino::TIPOS_PARA_ESCOLHER;

        $request->validate([
            'id_categoria'                   => ['nullable', 'integer', Rule::exists('tbl_categoria', 'id_categoria')->where('status_categoria', 'ATIVO')],
            'categoria_grade_treino'         => 'required_without:id_categoria|nullable|string|max:60',
            'tipo_grade_treino'              => ['required', Rule::in($tipos)],
            'horario_inicio_grade_treino'    => 'required|date_format:H:i,H:i:s',
            'horario_fim_grade_treino'       => 'required|date_format:H:i,H:i:s',
            'horario_obs_grade_treino'       => 'nullable|string|max:60',
            'local_grade_treino'             => 'required|string|max:255',
            'ordem_grade_treino'             => 'nullable|integer|min:0',
        ], [
            'categoria_grade_treino.required_without' => 'Escolha uma categoria ou, para um item geral, informe o nome (ex.: Integrado).',
            'id_categoria.exists'                     => 'Escolha uma categoria ativa.',
        ]);

        $categoria = $request->filled('id_categoria') ? Categoria::find($request->id_categoria) : null;

        return [
            ...$request->only([
                'tipo_grade_treino',
                'horario_inicio_grade_treino', 'horario_fim_grade_treino',
                'horario_obs_grade_treino', 'local_grade_treino',
            ]),
            'id_categoria'           => $categoria?->id_categoria,
            'categoria_grade_treino' => $categoria?->nome_categoria ?? $request->categoria_grade_treino,
            // A tela não tem mais o campo Ordem (a lista ordena pelo dia e pelo horário; a ordem só desempata):
            // sem ele, a edição mantém a ordem gravada e o novo horário fica com o padrão do banco
            ...($request->filled('ordem_grade_treino') ? ['ordem_grade_treino' => (int) $request->ordem_grade_treino] : []),
        ];
    }

    public function toggleStatusGrade($id)
    {
        $grade = GradeTreino::findOrFail($id);
        $novo = strtoupper($grade->status_grade_treino) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $grade->update(['status_grade_treino' => $novo]);

        // Inativar não cancela os eventos futuros já gerados: só avisa
        $mensagem = "Horário {$novo} com sucesso.";
        if ($novo === 'INATIVO' && ($futuros = $grade->contarEventosFuturosAtivos()) > 0) {
            $mensagem .= " Atenção: {$futuros} evento(s) futuro(s) já gerado(s) por este horário continuam na agenda;"
                . ' cancele-os à mão se não forem acontecer.';
        }

        return back()->with('sucesso', $mensagem);
    }

    public function destroyGrade($id)
    {
        $grade = GradeTreino::findOrFail($id);

        // Linha que já gerou eventos fica (a FK não deixa excluir e os eventos guardam a origem): só inativar
        if ($grade->eventos()->exists()) {
            return redirect()->route('admin.calendario.index', ['tab' => 'grade'])
                ->with('erro', 'Este horário já gerou eventos na agenda e não pode ser excluído. Use "Inativar" para tirá-lo da grade.');
        }

        $grade->delete();

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário removido.');
    }
}
