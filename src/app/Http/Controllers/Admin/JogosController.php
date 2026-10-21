<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ConfirmaConflitos;
use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Jogo = evento JOGO + times, campeonato e placar (CLAUDE.md, seção 4, "Jogos").
 * Criar e editar gravam o evento e o jogo juntos; data, horário e local ficam no evento, com histórico,
 * inscrição pela categoria e alerta de conflito. Sem exclusão: cancelar e ocultar são ações do evento.
 */
class JogosController extends Controller
{
    use ConfirmaConflitos;

    // Valor do select de campeonato para jogo sem campeonato
    public const AMISTOSO = 'AMISTOSO';

    /**
     * Lista de jogos com os filtros da URL: campeonato (id ou "amistoso"), time (mandante ou visitante),
     * situação e jogo (id_jogo: o jogo do campeonato no menu). Sem situação, abre sem os ocultos (o filtro
     * "Oculto" mostra só eles); o aviso mostra quantos ficaram de fora. O jogo pedido por ?jogo=ID aparece
     * mesmo oculto (com o aviso da situação); jogo inexistente é ignorado.
     */
    public function index(Request $request)
    {
        $campeonatos = self::campeonatosDoFormulario();
        $times       = Time::orderBy('nome_time')->get();
        $filtros     = $this->filtrosDaLista($request, $campeonatos, $times);

        $jogoFiltrado = $filtros['jogo'] ? Jogo::with(['evento', 'timeCasa', 'timeVisitante'])->find($filtros['jogo']) : null;
        $filtros['jogo'] = $jogoFiltrado ? (string) $jogoFiltrado->id_jogo : '';
        // Ocultos fora por padrão, menos o jogo pedido pelo id
        $situacaoDaConsulta = $jogoFiltrado && $filtros['situacao'] === '' ? null : $filtros['situacao'];

        // Campeonato e time convivem (ex.: jogos do Time Azul na Copa Escola, pelo menu)
        $doCampeonato = fn () => Jogo::query()
            ->when($filtros['campeonato'] === 'amistoso', fn ($q) => $q->whereNull('id_campeonato'))
            ->when(ctype_digit($filtros['campeonato']), fn ($q) => $q->where('id_campeonato', (int) $filtros['campeonato']))
            ->when($filtros['time'], fn ($q, $idTime) => $q->where(fn ($t) => $t
                ->where('id_time_casa', $idTime)->orWhere('id_time_visitante', $idTime)))
            ->when($jogoFiltrado, fn ($q) => $q->whereKey($jogoFiltrado->id_jogo));

        $jogos = $doCampeonato()
            ->with(['evento' => fn ($q) => $q->comAlteracao()->comInscritosAtivos(), 'evento.categoria', 'timeCasa', 'timeVisitante', 'campeonato'])
            ->whereHas('evento', fn ($q) => $situacaoDaConsulta === null ? $q : $q->daSituacao($situacaoDaConsulta))
            ->get()
            ->filter(fn ($jogo) => $filtros['situacao'] === '' || $jogo->evento->situacao === $filtros['situacao'])
            ->sortByDesc(fn ($jogo) => $jogo->evento?->dataHoraDoJogo())
            ->values();
        $ocultosForaDaLista = $filtros['situacao'] === '' && ! $jogoFiltrado
            ? $doCampeonato()->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', 'INATIVO'))->count()
            : 0;

        $categorias  = Categoria::ativas()->get();

        return view('admin.jogos.index', compact('jogos', 'campeonatos', 'times', 'categorias', 'filtros', 'ocultosForaDaLista', 'jogoFiltrado'));
    }

    // Campeonatos do select do formulário do jogo, com as categorias e os participantes de cada uma
    // (participantes_por_categoria: [id_categoria => [id_time]]; o select de categoria mostra só as do campeonato
    // e o de times, só os participantes da categoria escolhida: Fase H, Etapa H3). Usado aqui e na tela do jogo
    // (CalendarioController::showEvento)
    public static function campeonatosDoFormulario()
    {
        $campeonatos = Campeonato::with('categorias')->orderBy('nome_campeonato')->get();
        $participantes = DB::table('tbl_campeonato_time')->orderBy('id_time')->get(['id_campeonato', 'id_categoria', 'id_time'])
            ->groupBy('id_campeonato');

        return $campeonatos->each(fn ($camp) => $camp->setAttribute('participantes_por_categoria',
            ($participantes[$camp->id_campeonato] ?? collect())->groupBy('id_categoria')
                ->map(fn ($linhas) => $linhas->pluck('id_time')->map(fn ($id) => (int) $id)->values())->all()));
    }

    // Listas do modal de edição do jogo (admin.jogos.modals.edit) nas telas que não são a lista de Jogos
    public static function dadosDoFormulario(): array
    {
        return [
            'campeonatos' => self::campeonatosDoFormulario(),
            'times'       => Time::orderBy('nome_time')->get(),
            'categorias'  => Categoria::ativas()->get(),
        ];
    }

    // Filtros da lista lidos da URL; valor fora da lista vira vazio (sem filtro)
    private function filtrosDaLista(Request $request, $campeonatos, $times): array
    {
        $campeonato = (string) $request->query('campeonato', '');
        $time       = (string) $request->query('time', '');
        $situacao   = (string) $request->query('situacao', '');
        $jogo       = (string) $request->query('jogo', '');
        $idsValidos = $campeonatos->pluck('id_campeonato')->map(fn ($id) => (string) $id)->all();
        $idsTimes   = $times->pluck('id_time')->map(fn ($id) => (string) $id)->all();

        return [
            'campeonato' => in_array($campeonato, ['amistoso', ...$idsValidos], true) ? $campeonato : '',
            'time'       => in_array($time, $idsTimes, true) ? $time : '',
            'situacao'   => array_key_exists($situacao, EventoCalendario::SITUACOES) ? $situacao : '',
            'jogo'       => ctype_digit($jogo) ? $jogo : '', // conferido no index (o jogo precisa existir)
        ];
    }

    public function store(Request $request)
    {
        $dadosJogo   = $this->dadosJogo($request);
        $dadosEvento = [...$this->dadosEvento($request, $dadosJogo), 'status_evento_calendario' => 'ATIVO'];

        // Sem local, o jogo de campeonato usa o local do campeonato
        if (blank($dadosEvento['local_evento_calendario']) && $dadosJogo['id_campeonato']) {
            $dadosEvento['local_evento_calendario'] = Campeonato::find($dadosJogo['id_campeonato'])?->local_evento;
        }

        // Os convocados do campeonato (ou o elenco, no amistoso) ativos dos times internos serão inscritos:
        // confere conflito antes de criar
        $times = [$dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']];
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaCriacaoDoJogo($dadosEvento, $times, $dadosJogo['id_campeonato']))) {
            return $confirmar;
        }

        // Jogo sem time da AACJ (dois externos, num campeonato) nasce publicado: não há ninguém a avisar e ele
        // precisa contar na classificação (Fase H, Etapa H3)
        $semAcj = ! Jogo::idsInternos($times);

        [$evento, $jogo, $elenco] = DB::transaction(function () use ($dadosJogo, $dadosEvento, $semAcj) {
            // Responsável = admin logado. A categoria do evento é a do jogo: no campeonato, vale para a idade e a
            // convocação (Fase H, Etapa H3); no amistoso, só exibição.
            // Nasce rascunho (Fase 10, Etapa 4): inscritos e escalados, ninguém avisado até publicar
            $evento = EventoCalendario::criarPor(auth('admin')->id(), $dadosEvento, inscreverCategoria: false, rascunho: ! $semAcj);

            $jogo = Jogo::create([...$dadosJogo, 'id_evento' => $evento->id_evento_calendario]);
            $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados
            $evento->setRelation('jogo', $jogo);   // o aviso de categoria sabe que é jogo de campeonato

            return [$evento, $jogo, $jogo->inscreverElenco(auth('admin')->id())];
        });

        // Campeonato: os convocados entraram; amistoso: ninguém ainda (escolher na tela do jogo)
        $inscritos = $elenco['inscritos_com_time'] + $elenco['inscritos_sem_time'];
        $mensagem  = $semAcj
            ? 'Jogo registrado e publicado (sem time da AACJ: não há atletas a inscrever nem a avisar).'
            : 'Jogo registrado como rascunho.'
                . ($jogo->escolheAtletas()
                    ? ' Escolha os atletas do ' . $jogo->timeDaAcj()?->nome_time . ' em "Escolher atletas", nesta tela.'
                    : " {$inscritos} atleta(s) da convocação inscrito(s).")
                . $jogo->textoAcimaDaIdade($elenco['acima_da_idade'])
                . ' Os atletas serão avisados ao publicar.';

        // Inscrito de outra categoria/sexo: só avisa, como na tela do jogo (CLAUDE.md, seção 8, pergunta 14)
        if ($avisos = $evento->avisosForaDaCategoria($elenco['ids_mexidos'])) {
            session()->flash('avisos_categoria', $avisos);
        }

        // Abre a tela do jogo (inscritos, escalação e ações), sem voltar à lista
        return $this->comAvisoSemElenco(
            redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario)->with('sucesso', $mensagem), $jogo,
        );
    }

    /**
     * Jogadores de um time num jogo (cartão do time nas telas de times por jogo), só leitura: os atletas
     * ativos inscritos e escalados por aquele time naquele jogo. Voltar e linha de caminho levam à tela de
     * times de onde se veio (Amistosos ou o campeonato). A escalação é feita na tela do evento.
     */
    public function escaladosDoTime($id, $timeId)
    {
        $jogo = Jogo::with(['evento', 'campeonato', 'timeCasa.categoria', 'timeVisitante.categoria'])->findOrFail($id);
        $time = collect([$jogo->timeCasa, $jogo->timeVisitante])->first(fn ($t) => (string) $t?->id_time === (string) $timeId);
        abort_unless($time, 404);

        $voltar = $jogo->ehAmistoso()
            ? route('admin.amistosos.times')
            : route('admin.campeonatos.times', $jogo->id_campeonato);

        if (strtoupper($time->tipo_time) === 'EXTERNO') {
            return redirect($voltar)->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        $escalados = $jogo->escaladosDoTime($time->id_time);
        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ...($jogo->ehAmistoso()
                ? [['Amistosos', EventoCalendario::urlDoRamo('amistosos')]]
                : [['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
                   [$jogo->campeonato->nome_campeonato, route('admin.campeonatos.index', ['campeonato' => $jogo->id_campeonato])]]),
            ['Jogos', $voltar], // a tela de jogos do campeonato ou dos amistosos
            [$time->nome_time, null],
        ];

        return view('admin.jogos.escalados', compact('jogo', 'time', 'escalados', 'voltar', 'caminho'));
    }

    /**
     * Placar rápido, pela tela do jogo (sem o formulário inteiro): os dois ou nenhum (vazio = ainda não
     * jogado). Placar não entra no histórico do evento nem avisa os atletas.
     */
    public function placar(Request $request, $id)
    {
        $jogo = Jogo::findOrFail($id);

        $request->validate([
            'placar_time_casa_jogos'      => 'nullable|integer|min:0|max:99|required_with:placar_time_visitante_jogos',
            'placar_time_visitante_jogos' => 'nullable|integer|min:0|max:99|required_with:placar_time_casa_jogos',
        ], [
            'placar_time_casa_jogos.required_with'      => 'Preencha os dois placares, ou deixe os dois vazios (jogo ainda não jogado).',
            'placar_time_visitante_jogos.required_with' => 'Preencha os dois placares, ou deixe os dois vazios (jogo ainda não jogado).',
        ]);

        $jogo->update([
            'placar_time_casa_jogos'      => $request->filled('placar_time_casa_jogos') ? (int) $request->placar_time_casa_jogos : null,
            'placar_time_visitante_jogos' => $request->filled('placar_time_visitante_jogos') ? (int) $request->placar_time_visitante_jogos : null,
        ]);

        return redirect()->route('admin.calendario.eventos.show', $jogo->id_evento)
            ->with('sucesso', $jogo->temPlacar()
                ? "Placar salvo: {$jogo->placar_time_casa_jogos} × {$jogo->placar_time_visitante_jogos}."
                : 'Placar apagado (jogo ainda não jogado).');
    }

    /**
     * "Publicar e avisar os atletas" (Fase 10, Etapa 4), na tela do jogo: o rascunho passa a valer e cada
     * inscrito ativo recebe um aviso só, com o time. Jogo que já aconteceu, cancelado ou oculto é publicado
     * sem avisar. Sem "despublicar": depois de publicado, usar cancelar ou ocultar.
     * Rascunho com inscrito ativo que passa da idade do campeonato não é publicado (remover antes).
     */
    public function publicar($id)
    {
        $jogo   = Jogo::with(['evento.categoria', 'campeonato'])->findOrFail($id);
        $evento = $jogo->evento;
        $tela   = redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario);

        if (! $evento->estaPublicado()) {
            $ativos = $evento->inscricoes()->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))->pluck('id_atleta')->all();

            if ($acima = $jogo->acimaDaIdade($ativos)) {
                return $tela->with('erro', 'Jogo não publicado: ' . implode(', ', $acima) . ' ' . (count($acima) === 1 ? 'passa' : 'passam')
                    . " da idade do campeonato ({$jogo->limiteDeIdade()}). Remova a inscrição antes de publicar.");
            }
        }

        if (! $evento->publicar(auth('admin')->id())) {
            return $tela->with('aviso', 'Este jogo já estava publicado. Nenhum atleta foi avisado de novo.');
        }

        return $tela->with('sucesso', 'Jogo publicado.' . ($evento->avisaAtletas()
            ? Notificacao::textoNotificados($evento->atletasNotificados)
            : ' Nenhum atleta foi avisado: o jogo já aconteceu, está cancelado ou oculto.'));
    }

    public function update(Request $request, $id)
    {
        $jogo   = Jogo::with('evento')->findOrFail($id);
        $evento = $jogo->evento;

        $dadosJogo   = $this->dadosJogo($request, $evento);
        $dadosEvento = $this->dadosEvento($request, $dadosJogo);
        $timesAntes  = [(int) $jogo->id_time_casa, (int) $jogo->id_time_visitante];
        $timesNovos  = [$dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']];

        // Mudou data, horário ou times: confere conflito dos atletas que ficarão inscritos
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaEdicao($evento, $dadosEvento, $timesNovos))) {
            return $confirmar;
        }

        // Evento e jogo juntos: o que mudar em título, categoria, data, horário ou local vai para o histórico
        // do evento; times trocados, as inscrições acompanham a convocação (ou o elenco, no amistoso); os
        // atletas são avisados
        $mensagem = 'Jogo atualizado.' . $this->salvarEdicaoDoEvento($evento, $dadosEvento,
            fn () => $this->gravarJogoETrocarTimes($jogo, $dadosJogo, $timesAntes));

        // Editado pela tela do jogo (voltar=evento): volta para ela; pelos Jogos do campeonato ou dos amistosos
        // (voltar=pagina), volta para a página de onde veio; pela lista, volta à lista
        $resposta = (match ($request->input('voltar')) {
            'evento' => redirect()->route('admin.calendario.eventos.show', $evento->id_evento_calendario),
            'pagina' => back(fallback: route('admin.jogos.index')),
            default  => redirect()->route('admin.jogos.index'),
        })->with('sucesso', $mensagem);

        return $this->timesMudaram($timesAntes, $timesNovos) ? $this->comAvisoSemElenco($resposta, $jogo) : $resposta;
    }

    /**
     * Grava o jogo (dentro da transação da edição). Times trocados: no campeonato, sai quem veio pela
     * convocação do time que saiu, entra a do time novo e as INDIVIDUAL ficam (sem time, se estavam no que
     * saiu); no amistoso, os escolhidos passam para o time da AACJ que ficou. Jogo de campeonato concluído não
     * muda as inscrições: só tira da escalação quem estava num time que saiu.
     * Devolve o trecho da mensagem e quem entrou (para salvarEdicaoDoEvento), ou null se os times não mudaram.
     */
    private function gravarJogoETrocarTimes(Jogo $jogo, array $dadosJogo, array $timesAntes): ?array
    {
        $jogo->update($dadosJogo);
        $jogo->load(['timeCasa', 'timeVisitante', 'campeonato']);
        $jogo->evento->unsetRelation('categoria'); // a categoria do jogo pode ter mudado (idade e convocação)
        $timesAgora = [(int) $jogo->id_time_casa, (int) $jogo->id_time_visitante];

        if (! $this->timesMudaram($timesAntes, $timesAgora)) {
            return null;
        }

        $foraDaEscalacao = fn (int $n) => $n ? " {$n} atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos." : '';

        // Amistoso: ninguém sai nem entra; os escolhidos passam para o time da AACJ que ficou no jogo
        if ($jogo->escolheAtletas()) {
            $passaram = $jogo->escalarNoTimeDaAcj();

            return ['mensagem' => $passaram ? " {$passaram} atleta(s) passaram para o {$jogo->timeDaAcj()->nome_time}." : '',
                'ids_entraram' => [], 'mexeu' => false];
        }

        if ($jogo->evento->estaConcluido()) {
            return ['mensagem' => $foraDaEscalacao($jogo->limparEscalacaoForaDosTimes()), 'ids_entraram' => [], 'mexeu' => false];
        }

        $troca    = $jogo->sincronizarPeloElenco(array_values(array_diff($timesAntes, $timesAgora)), auth('admin')->id());
        $entraram = $troca['inscritos_com_time'] + $troca['inscritos_sem_time'];

        return [
            'mensagem'     => " Inscrições pela convocação: {$entraram} atleta(s) inscrito(s), {$troca['sairam']} removido(s)."
                . ' As inscrições individuais foram mantidas.' . $foraDaEscalacao($troca['sem_time'])
                . $jogo->textoAcimaDaIdade($troca['acima_da_idade']),
            'ids_entraram' => $troca['ids_entraram'],
            'mexeu'        => $entraram + $troca['sairam'] > 0,
        ];
    }

    // Jogo de campeonato com time interno sem atleta ativo convocado: o jogo fica sem inscritos daquele lado;
    // avisa o admin (amistoso: nunca, os atletas são escolhidos no jogo)
    private function comAvisoSemElenco($resposta, Jogo $jogo)
    {
        $semElenco = $jogo->timesSemElenco();

        if ($semElenco->isEmpty()) {
            return $resposta;
        }

        $nomes  = $semElenco->pluck('nome_time')->implode(', ');
        $porQue = '. Nenhum atleta foi inscrito por ' . ($semElenco->count() === 1 ? 'esse time' : 'esses times');

        return $resposta->with('aviso', "Sem convocados: {$nomes}{$porQue}; convoque os atletas (Jogos do campeonato › Convocação) e use "
            . '"Preencher pela convocação" na tela do jogo, ou inscreva à mão.');
    }

    // Campos do jogo: campeonato (vazio = amistoso), categoria, times e placar (os dois ou nenhum)
    private function dadosJogo(Request $request, ?EventoCalendario $evento = null): array
    {
        $idCategoriaAtual = $evento?->id_categoria;
        $amistoso = $request->id_campeonato === self::AMISTOSO;

        // Jogo de campeonato: categoria obrigatória, uma das do campeonato (Fase H, Etapa H3); com uma categoria só,
        // ela vale sem precisar escolher. Amistoso: uma categoria ativa (ou a que ele já tem) ou nenhuma
        $categoriasDoCampeonato = $amistoso ? [] : DB::table('tbl_campeonato_categoria')
            ->where('id_campeonato', (int) $request->id_campeonato)->pluck('id_categoria')->map(fn ($id) => (int) $id)->all();
        // Por segurança, campeonato sem a lista de categorias usa a de referência (tbl_campeonato.id_categoria)
        if (! $amistoso && ! $categoriasDoCampeonato && ($referencia = Campeonato::whereKey((int) $request->id_campeonato)->value('id_categoria'))) {
            $categoriasDoCampeonato = [(int) $referencia];
        }
        if (! $amistoso && ! $request->filled('id_categoria') && count($categoriasDoCampeonato) === 1) {
            $request->merge(['id_categoria' => $categoriasDoCampeonato[0]]);
        }
        // Fase (Fase H, Etapa H4): só no jogo de campeonato; sem ela, a fase de grupos
        if (! $amistoso && ! $request->filled('fase_jogo')) {
            $request->merge(['fase_jogo' => Jogo::FASE_PADRAO]);
        }
        $regraCategoria = $amistoso
            ? ['nullable', 'integer', Rule::exists('tbl_categoria', 'id_categoria')
                ->where(fn ($q) => $q->where('status_categoria', 'ATIVO')->orWhere('id_categoria', $idCategoriaAtual))]
            : ['required', 'integer', Rule::in($categoriasDoCampeonato)];

        $request->validate([
            // Escolha obrigatória: um campeonato ou AMISTOSO (assim ninguém cria amistoso por esquecimento)
            'id_campeonato'                    => ['required', Rule::when($request->id_campeonato !== self::AMISTOSO,
                ['integer', 'exists:tbl_campeonato,id_campeonato'])],
            'id_time_casa'                     => 'required|integer|exists:tbl_time,id_time',
            'id_time_visitante'                => 'required|integer|exists:tbl_time,id_time|different:id_time_casa',
            'placar_time_casa_jogos'           => 'nullable|integer|min:0|required_with:placar_time_visitante_jogos',
            'placar_time_visitante_jogos'      => 'nullable|integer|min:0|required_with:placar_time_casa_jogos',
            'id_categoria'                     => $regraCategoria,
            'fase_jogo'                        => ['nullable', Rule::in(array_keys(Jogo::FASES))],
            'data_evento_calendario'           => 'required|date',
            'horario_inicio_evento_calendario' => 'nullable|date_format:H:i,H:i:s',
            'horario_fim_evento_calendario'    => 'nullable|date_format:H:i,H:i:s',
            'local_evento_calendario'          => 'nullable|string|max:255',
        ], [
            'id_campeonato.required'               => 'Escolha o campeonato do jogo, ou "Amistoso".',
            'id_time_visitante.different'          => 'O visitante precisa ser diferente do mandante.',
            'placar_time_casa_jogos.required_with' => 'Preencha os dois placares, ou deixe os dois vazios (jogo ainda não jogado).',
            'placar_time_visitante_jogos.required_with' => 'Preencha os dois placares, ou deixe os dois vazios (jogo ainda não jogado).',
            'id_categoria.exists'                  => 'Escolha uma categoria ativa para o amistoso, ou deixe sem categoria.',
            'id_categoria.required'                => 'Escolha a categoria do jogo (uma das categorias do campeonato).',
            'id_categoria.in'                      => 'A categoria do jogo precisa ser uma das categorias do campeonato.',
            'fase_jogo.in'                         => 'Escolha uma fase da lista.',
        ]);

        $this->conferirParticipantes($request, $evento?->jogo);

        return [
            'id_campeonato'               => $request->id_campeonato === self::AMISTOSO ? null : (int) $request->id_campeonato,
            'fase_jogo'                   => $request->id_campeonato === self::AMISTOSO ? null : $request->fase_jogo,
            'id_time_casa'                => (int) $request->id_time_casa,
            'id_time_visitante'           => (int) $request->id_time_visitante,
            'placar_time_casa_jogos'      => $request->filled('placar_time_casa_jogos') ? (int) $request->placar_time_casa_jogos : null,
            'placar_time_visitante_jogos' => $request->filled('placar_time_visitante_jogos') ? (int) $request->placar_time_visitante_jogos : null,
        ];
    }

    /**
     * Jogo de campeonato: mandante e visitante precisam estar entre os participantes da categoria do jogo
     * (Fase H, Etapa H3; o select já mostra só eles). Categoria sem participantes cadastrados aceita qualquer
     * time; na edição, os times que o jogo já tem continuam aceitos (jogos antigos). Amistoso: um time da AACJ
     * contra um externo (Fase G, Etapa 3; vale também ao editar os amistosos antigos de AACJ contra AACJ).
     */
    private function conferirParticipantes(Request $request, ?Jogo $jogoAtual): void
    {
        if ($request->id_campeonato === self::AMISTOSO) {
            if ($erro = Jogo::erroDosTimesDoAmistoso((int) $request->id_time_casa, (int) $request->id_time_visitante)) {
                throw ValidationException::withMessages(['id_time_visitante' => $erro]);
            }

            return;
        }

        $campeonato    = Campeonato::find((int) $request->id_campeonato);

        // Campeonato inativo (21/10/2026): não recebe jogo novo; o jogo que já é dele continua editável
        if (! $campeonato->estaAtivo() &&(int) $jogoAtual?->id_campeonato !== (int) $campeonato->id_campeonato) {
            throw ValidationException::withMessages(['id_campeonato' =>
                "O campeonato {$campeonato->nome_campeonato} está inativo. Reative-o (Campeonatos) antes de pôr jogos nele."]);
        }

        $categoria     = Categoria::find((int) $request->id_categoria);
        $participantes = $campeonato->participantesPorCategoria()[(int) $request->id_categoria] ?? [];
        if (! $participantes) {
            return;
        }

        $aceitos = [...$participantes, (int) $jogoAtual?->id_time_casa, (int) $jogoAtual?->id_time_visitante];
        $erros   = [];
        foreach (['id_time_casa' => 'O mandante', 'id_time_visitante' => 'O visitante'] as $campo => $quem) {
            if (! in_array((int) $request->input($campo), $aceitos, true)) {
                $erros[$campo] = "{$quem} não participa do {$categoria->rotulo} do campeonato {$campeonato->nome_campeonato}. "
                    . 'Inclua o time nos participantes dessa categoria, ou escolha outro.';
            }
        }

        if ($erros) {
            throw ValidationException::withMessages($erros);
        }
    }

    // Campos do evento do jogo. Título gerado pelos times; categoria escolhida (a do jogo, no campeonato; só
    // exibição, no amistoso)
    private function dadosEvento(Request $request, array $dadosJogo): array
    {
        $idCategoria = $request->filled('id_categoria') ? (int) $request->id_categoria : null;

        return [
            'titulo_evento_calendario'         => Jogo::tituloPara($dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']),
            'tipo_evento_calendario'           => 'JOGO',
            'id_categoria'                     => $idCategoria,
            'data_evento_calendario'           => $request->data_evento_calendario,
            'horario_inicio_evento_calendario' => $request->horario_inicio_evento_calendario,
            'horario_fim_evento_calendario'    => $request->horario_fim_evento_calendario,
            'local_evento_calendario'          => $request->local_evento_calendario,
        ];
    }
}
