<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ConfirmaConflitos;
use App\Http\Controllers\Admin\Concerns\ListaPorMes;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\Notificacao;
use App\Models\Time;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        return view('admin.calendario.index', compact(
            'eventos', 'grades', 'categorias', 'categoriasInativasEmUso', 'mes', 'mesesLista', 'mesesGeracao',
            'filtros', 'ocultosForaDaLista',
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

    // Tela do evento: dados e inscritos (só atletas ATIVO aparecem; CLAUDE.md, seção 4)
    public function showEvento($id)
    {
        $evento = EventoCalendario::with(['categoria', 'responsavel'])->comAlteracao()->findOrFail($id);

        $inscricoes = $evento->inscricoes()
            ->with(['atleta.categoriasAtivas', 'usuario'])
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

        // Evento de jogo: escalação por time (mandante/visitante internos), elenco de cada atleta e quantos
        // do elenco ainda não estão inscritos (ao lado do "Preencher pelo elenco")
        $jogo    = $evento->jogo()->with(['timeCasa', 'timeVisitante', 'campeonato'])->first();
        $elencos = $jogo?->elencosDoJogo() ?? [];
        $faltantesDoElenco = $jogo ? count($jogo->idsFaltantesDoElenco()) : 0;

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
        } else {
            $evento->load('historico.usuario');
            $categoriasInativasEmUso = $evento->categoria && $evento->categoria->status_categoria !== 'ATIVO'
                ? collect([$evento->categoria])
                : collect();
        }

        return view('admin.calendario.evento', compact(
            'evento', 'inscricoes', 'inscritosInativos', 'disponiveis', 'categorias', 'faltantesDaCategoria', 'jogo', 'elencos',
            'faltantesDoElenco', 'notificacoes', 'caminho', 'voltar',
        ) + [
            'campeonatosDoJogo'       => $campeonatosDoJogo ?? collect(),
            'timesDoJogo'             => $timesDoJogo ?? collect(),
            'categoriasInativasEmUso' => $categoriasInativasEmUso ?? collect(),
        ]);
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

        $inscricao->update(['id_time' => $idTime]);
        $nome = $inscricao->atleta->nome_atleta;

        if ($idTime !== null) {
            $this->avisarForaDaCategoria($evento, [(int) $idAtleta]);
        }

        return back()->with('sucesso', $idTime
            ? "{$nome} escalado(a) no {$jogo->timesEscalaveis()->firstWhere('id_time', $idTime)->nome_time}."
            : "{$nome} ficou sem time.");
    }

    /**
     * "Preencher pelo elenco" (Jogo::inscreverElenco): inscreve quem falta do elenco (tbl_atleta_time) dos
     * times internos do jogo (origem ELENCO, com alerta de conflito) e escala quem está inscrito sem time.
     * Quem é de um time só entra escalado nele; quem está nos dois elencos entra SEM time, para o admin
     * escolher na lista. Quem já tem time não muda; ninguém sai. Atleta fora da categoria do jogo gera aviso.
     */
    public function preencherPeloElenco(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);
        $jogo   = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();

        if (! $jogo || $jogo->timesEscalaveis()->isEmpty()) {
            return back()->with('erro', 'Este jogo não tem time interno para escalar.');
        }

        $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($jogo->idsFaltantesDoElenco()))) {
            return $confirmar;
        }

        $elenco    = $jogo->inscreverElenco(auth('admin')->id());
        $inscritos = $elenco['inscritos_com_time'] + $elenco['inscritos_sem_time'];

        $mensagem = "Escalação pelo elenco: {$elenco['escalados']} inscrito(s) escalado(s), {$elenco['inscritos_com_time']} atleta(s) inscrito(s) e escalado(s).";
        if ($elenco['nos_dois']) {
            $mensagem .= " {$elenco['nos_dois']} atleta(s) estão nos elencos dos dois times"
                . ($elenco['inscritos_sem_time'] ? " ({$elenco['inscritos_sem_time']} inscrito(s) agora, sem time)" : '')
                . ': escolha o time de cada um na lista.';
        }
        if ($inscritos > 0) {
            $mensagem .= Notificacao::textoNotificados($evento->atletasNotificados);
        }

        $this->avisarForaDaCategoria($evento, $elenco['ids_mexidos']);

        return back()->with('sucesso', $mensagem);
    }

    // Aviso informativo (não bloqueia) para atletas fora da categoria/sexo do evento, na próxima tela
    private function avisarForaDaCategoria(EventoCalendario $evento, array $idsAtletas): void
    {
        if ($avisos = $evento->avisosForaDaCategoria($idsAtletas)) {
            session()->flash('avisos_categoria', $avisos);
        }
    }

    // "Atualizar inscritos pela categoria": só acrescenta quem falta (não remove ninguém)
    public function atualizarInscritosPelaCategoria($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        if (! $evento->id_categoria) {
            return back()->with('erro', 'Este evento não tem categoria.');
        }

        // No jogo, quem joga é o elenco dos times, não a categoria
        if ($evento->ehJogo()) {
            return back()->with('erro', 'No jogo, os inscritos vêm do elenco dos times: use "Preencher pelo elenco".');
        }

        if ($confirmar = $this->confirmarConflitos(request(), $evento->conflitosPara($evento->idsFaltantesDaCategoria()))) {
            return $confirmar;
        }

        $novos = $evento->inscreverCategoria($evento->id_categoria, 'CATEGORIA', auth('admin')->id());

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

        $idTime = $request->filled('id_time') ? (int) $request->id_time : null;
        if ($idTime !== null) {
            $jogo = $evento->jogo()->with(['timeCasa', 'timeVisitante'])->first();
            $erro = $jogo ? $jogo->erroDeEscalacao($idTime) : 'Este evento não é um jogo: não tem escalação.';

            if ($erro) {
                return back()->with('erro', $erro)->withInput();
            }
        }

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara([(int) $request->id_atleta]))) {
            return $confirmar;
        }

        $inscreveu = $evento->inscrever((int) $request->id_atleta, 'INDIVIDUAL', auth('admin')->id(), $idTime);

        // Em jogo, inscrever alguém de outra categoria/sexo gera aviso (não bloqueia)
        if ($inscreveu && $evento->tipo_evento_calendario === 'JOGO') {
            $this->avisarForaDaCategoria($evento, [(int) $request->id_atleta]);
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

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($entrariam))) {
            return $confirmar;
        }

        $novos = $evento->inscreverCategoria($categoria->id_categoria, 'INDIVIDUAL', auth('admin')->id());

        return back()->with('sucesso', "{$categoria->rotulo}: {$novos} atleta(s) inscrito(s)"
            . ($novos === 0 ? ' (todos já estavam inscritos ou não há atletas ativos).' : '.' . Notificacao::textoNotificados($evento->atletasNotificados)));
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

        $previa = GradeTreino::previaDoMes($mes);
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

        try {
            $resultado = GradeTreino::gerarMes($mes, auth('admin')->id(), $request->boolean('confirmar_conflito'));
        } catch (UniqueConstraintViolationException $e) {
            // Outro admin gerou o mesmo mês ao mesmo tempo: nada foi gravado (o lote foi desfeito)
            return redirect()->route('admin.calendario.grade.previa', ['mes' => $mes])
                ->with('erro', 'Outro usuário gerou eventos deste mês ao mesmo tempo. Nada foi gravado; confira a prévia e gere de novo.');
        }

        $totais = $resultado['previa']['totais'];

        if (! $resultado['gerado']) {
            return redirect()->route('admin.calendario.grade.previa', ['mes' => $mes])
                ->with('erro', "Nada foi gerado: o lote tem {$totais['conflitos_reais']} conflito(s) de horário. Confira e use \"Confirmar mesmo assim e gerar\".");
        }

        $mensagem = $totais['eventos'] === 0
            ? 'Nada novo para gerar neste mês.'
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

    private function mesNaoPermitido()
    {
        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])
            ->with('erro', 'Escolha um mês entre o atual e os ' . GradeTreino::MESES_A_FRENTE . ' seguintes para gerar a agenda.');
    }

    // ── Grade de Treinos ────────────────────────────────────────────────────

    public function storeGrade(Request $request)
    {
        GradeTreino::create([
            ...$this->dadosGrade($request),
            'status_grade_treino' => 'ATIVO',
        ]);

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário adicionado à grade.');
    }

    // Campos da grade que mudam os eventos gerados (observação e ordem não mudam)
    private const CAMPOS_GRADE_DO_EVENTO = [
        'dia_semana_grade_treino', 'horario_inicio_grade_treino', 'horario_fim_grade_treino',
        'local_grade_treino', 'id_categoria', 'categoria_grade_treino', 'tipo_grade_treino',
    ];

    public function updateGrade(Request $request, $id)
    {
        $grade = GradeTreino::findOrFail($id);

        $antes = $this->camposDoEvento($grade);
        $grade->update($this->dadosGrade($request));

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
     * Valida e monta os dados de um horário da grade.
     * Com categoria: o rótulo (categoria_grade_treino) vem do nome da categoria.
     * Sem categoria ("Geral", ex.: Integrado, Treino Livre): o rótulo é obrigatório.
     * Tipo e local são NOT NULL no banco, por isso obrigatórios aqui.
     */
    private function dadosGrade(Request $request): array
    {
        $request->validate([
            'dia_semana_grade_treino'        => ['required', Rule::in(array_keys(GradeTreino::DIAS_SEMANA))],
            'id_categoria'                   => ['nullable', 'integer', Rule::exists('tbl_categoria', 'id_categoria')->where('status_categoria', 'ATIVO')],
            'categoria_grade_treino'         => 'required_without:id_categoria|nullable|string|max:60',
            'tipo_grade_treino'              => ['required', Rule::in(GradeTreino::TIPOS)],
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
                'dia_semana_grade_treino', 'tipo_grade_treino',
                'horario_inicio_grade_treino', 'horario_fim_grade_treino',
                'horario_obs_grade_treino', 'local_grade_treino',
            ]),
            'id_categoria'           => $categoria?->id_categoria,
            'categoria_grade_treino' => $categoria?->nome_categoria ?? $request->categoria_grade_treino,
            'ordem_grade_treino'     => $request->input('ordem_grade_treino') ?? 0,
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
