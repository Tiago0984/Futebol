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
     * Lista de jogos com os filtros da URL: campeonato (id ou "amistoso") e situação. Sem situação, abre
     * sem os ocultos (o filtro "Oculto" mostra só eles); o aviso mostra quantos ficaram de fora.
     */
    public function index(Request $request)
    {
        $campeonatos = Campeonato::with('categoria')->orderBy('nome_campeonato')->get();
        $filtros     = $this->filtrosDaLista($request, $campeonatos);

        $doCampeonato = fn () => Jogo::query()
            ->when($filtros['campeonato'] === 'amistoso', fn ($q) => $q->whereNull('id_campeonato'))
            ->when(ctype_digit($filtros['campeonato']), fn ($q) => $q->where('id_campeonato', (int) $filtros['campeonato']));

        $jogos = $doCampeonato()
            ->with(['evento' => fn ($q) => $q->comAlteracao()->comInscritosAtivos(), 'evento.categoria', 'timeCasa', 'timeVisitante', 'campeonato'])
            ->whereHas('evento', fn ($q) => $q->daSituacao($filtros['situacao']))
            ->get()
            ->filter(fn ($jogo) => $filtros['situacao'] === '' || $jogo->evento->situacao === $filtros['situacao'])
            ->sortByDesc(fn ($jogo) => $jogo->evento?->dataHoraDoJogo())
            ->values();
        $ocultosForaDaLista = $filtros['situacao'] === ''
            ? $doCampeonato()->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', 'INATIVO'))->count()
            : 0;

        $times       = Time::orderBy('nome_time')->get();
        $categorias  = Categoria::ativas()->get();

        return view('admin.jogos.index', compact('jogos', 'campeonatos', 'times', 'categorias', 'filtros', 'ocultosForaDaLista'));
    }

    // Filtros da lista lidos da URL; valor fora da lista vira vazio (sem filtro)
    private function filtrosDaLista(Request $request, $campeonatos): array
    {
        $campeonato = (string) $request->query('campeonato', '');
        $situacao   = (string) $request->query('situacao', '');
        $idsValidos = $campeonatos->pluck('id_campeonato')->map(fn ($id) => (string) $id)->all();

        return [
            'campeonato' => in_array($campeonato, ['amistoso', ...$idsValidos], true) ? $campeonato : '',
            'situacao'   => array_key_exists($situacao, EventoCalendario::SITUACOES) ? $situacao : '',
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

        // O elenco ativo dos times internos será inscrito: confere conflito antes de criar
        $times = [$dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']];
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaCriacaoDoJogo($dadosEvento, $times))) {
            return $confirmar;
        }

        [$evento, $jogo, $elenco] = DB::transaction(function () use ($dadosJogo, $dadosEvento) {
            // Responsável = admin logado. A categoria fica no evento só para exibição: quem joga é o elenco
            $evento = EventoCalendario::criarPor(auth('admin')->id(), $dadosEvento, inscreverCategoria: false);

            $jogo = Jogo::create([...$dadosJogo, 'id_evento' => $evento->id_evento_calendario]);
            $jogo->setRelation('evento', $evento); // a mesma instância conta os notificados

            return [$evento, $jogo, $jogo->inscreverElenco(auth('admin')->id())];
        });

        $inscritos = $elenco['inscritos_com_time'] + $elenco['inscritos_sem_time'];
        $mensagem  = 'Jogo registrado.'
            . ($jogo->timesEscalaveis()->isNotEmpty() ? " {$inscritos} atleta(s) do elenco inscrito(s)." : '')
            . ($elenco['nos_dois'] ? " {$elenco['nos_dois']} atleta(s) estão nos elencos dos dois times: escolha o time de cada um na tela do jogo." : '')
            . ($inscritos ? Notificacao::textoNotificados($evento->atletasNotificados) : '');

        return $this->comAvisoSemElenco(redirect()->route('admin.jogos.index')->with('sucesso', $mensagem), $jogo);
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
        // do evento; times trocados, as inscrições acompanham o elenco; os atletas são avisados
        $mensagem = 'Jogo atualizado.' . $this->salvarEdicaoDoEvento($evento, $dadosEvento,
            fn () => $this->gravarJogoETrocarTimes($jogo, $dadosJogo, $timesAntes));

        $resposta = redirect()->route('admin.jogos.index')->with('sucesso', $mensagem);

        return $this->timesMudaram($timesAntes, $timesNovos) ? $this->comAvisoSemElenco($resposta, $jogo) : $resposta;
    }

    /**
     * Grava o jogo (dentro da transação da edição). Times trocados: sai quem veio pelo elenco do time que
     * saiu, entra o elenco do time novo e as INDIVIDUAL ficam (sem time, se estavam no que saiu). Jogo
     * concluído não muda as inscrições: só tira da escalação quem estava num time que saiu.
     * Devolve o trecho da mensagem e quem entrou (para salvarEdicaoDoEvento), ou null se os times não mudaram.
     */
    private function gravarJogoETrocarTimes(Jogo $jogo, array $dadosJogo, array $timesAntes): ?array
    {
        $jogo->update($dadosJogo);
        $jogo->load(['timeCasa', 'timeVisitante']);
        $timesAgora = [(int) $jogo->id_time_casa, (int) $jogo->id_time_visitante];

        if (! $this->timesMudaram($timesAntes, $timesAgora)) {
            return null;
        }

        $foraDaEscalacao = fn (int $n) => $n ? " {$n} atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos." : '';

        if ($jogo->evento->estaConcluido()) {
            return ['mensagem' => $foraDaEscalacao($jogo->limparEscalacaoForaDosTimes()), 'ids_entraram' => [], 'mexeu' => false];
        }

        $troca    = $jogo->sincronizarPeloElenco(array_values(array_diff($timesAntes, $timesAgora)), auth('admin')->id());
        $entraram = $troca['inscritos_com_time'] + $troca['inscritos_sem_time'];

        return [
            'mensagem'     => " Inscrições pelo elenco: {$entraram} atleta(s) inscrito(s), {$troca['sairam']} removido(s)."
                . ' As inscrições individuais foram mantidas.' . $foraDaEscalacao($troca['sem_time']),
            'ids_entraram' => $troca['ids_entraram'],
            'mexeu'        => $entraram + $troca['sairam'] > 0,
        ];
    }

    // Time interno sem atleta ativo no elenco: o jogo fica sem inscritos daquele lado; avisa o admin
    private function comAvisoSemElenco($resposta, Jogo $jogo)
    {
        $semElenco = $jogo->timesSemElenco();

        return $semElenco->isEmpty() ? $resposta : $resposta->with('aviso', 'Sem elenco cadastrado: '
            . $semElenco->pluck('nome_time')->implode(', ') . '. Nenhum atleta foi inscrito por '
            . ($semElenco->count() === 1 ? 'esse time' : 'esses times')
            . '; cadastre o elenco e use "Preencher pelo elenco" na tela do jogo, ou inscreva à mão.');
    }

    // Campos do jogo: campeonato (vazio = amistoso), times e placar (os dois ou nenhum)
    private function dadosJogo(Request $request, ?EventoCalendario $evento = null): array
    {
        $idCategoriaAtual = $evento?->id_categoria;

        $request->validate([
            // Escolha obrigatória: um campeonato ou AMISTOSO (assim ninguém cria amistoso por esquecimento)
            'id_campeonato'                    => ['required', Rule::when($request->id_campeonato !== self::AMISTOSO,
                ['integer', 'exists:tbl_campeonato,id_campeonato'])],
            'id_time_casa'                     => 'required|integer|exists:tbl_time,id_time',
            'id_time_visitante'                => 'required|integer|exists:tbl_time,id_time|different:id_time_casa',
            'placar_time_casa_jogos'           => 'nullable|integer|min:0|required_with:placar_time_visitante_jogos',
            'placar_time_visitante_jogos'      => 'nullable|integer|min:0|required_with:placar_time_casa_jogos',
            // Categoria só vale no amistoso (no jogo de campeonato, é a do campeonato)
            'id_categoria'                     => ['nullable', 'integer', Rule::exists('tbl_categoria', 'id_categoria')
                ->where(fn ($q) => $q->where('status_categoria', 'ATIVO')->orWhere('id_categoria', $idCategoriaAtual))],
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
        ]);

        return [
            'id_campeonato'               => $request->id_campeonato === self::AMISTOSO ? null : (int) $request->id_campeonato,
            'id_time_casa'                => (int) $request->id_time_casa,
            'id_time_visitante'           => (int) $request->id_time_visitante,
            'placar_time_casa_jogos'      => $request->filled('placar_time_casa_jogos') ? (int) $request->placar_time_casa_jogos : null,
            'placar_time_visitante_jogos' => $request->filled('placar_time_visitante_jogos') ? (int) $request->placar_time_visitante_jogos : null,
        ];
    }

    // Campos do evento do jogo. Título gerado pelos times; categoria do campeonato, ou a escolhida no amistoso
    private function dadosEvento(Request $request, array $dadosJogo): array
    {
        $idCategoria = $dadosJogo['id_campeonato']
            ? Campeonato::find($dadosJogo['id_campeonato'])->id_categoria
            : ($request->filled('id_categoria') ? (int) $request->id_categoria : null);

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
