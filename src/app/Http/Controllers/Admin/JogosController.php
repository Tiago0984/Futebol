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

    public function index()
    {
        $jogos = Jogo::with(['evento' => fn ($q) => $q->comAlteracao()->comInscritosAtivos(), 'evento.categoria', 'timeCasa', 'timeVisitante', 'campeonato'])
            ->get()
            ->sortByDesc(fn ($jogo) => $jogo->evento?->dataHoraDoJogo())
            ->values();

        $campeonatos = Campeonato::with('categoria')->orderBy('nome_campeonato')->get();
        $times       = Time::orderBy('nome_time')->get();
        $categorias  = Categoria::ativas()->get();

        return view('admin.jogos.index', compact('jogos', 'campeonatos', 'times', 'categorias'));
    }

    public function store(Request $request)
    {
        $dadosJogo   = $this->dadosJogo($request);
        $dadosEvento = [...$this->dadosEvento($request, $dadosJogo), 'status_evento_calendario' => 'ATIVO'];

        // Sem local, o jogo de campeonato usa o local do campeonato
        if (blank($dadosEvento['local_evento_calendario']) && $dadosJogo['id_campeonato']) {
            $dadosEvento['local_evento_calendario'] = Campeonato::find($dadosJogo['id_campeonato'])?->local_evento;
        }

        // Com categoria, os atletas dela serão inscritos: confere conflito antes de criar
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaCriacao($dadosEvento))) {
            return $confirmar;
        }

        $evento = DB::transaction(function () use ($dadosJogo, $dadosEvento) {
            // Responsável = admin logado; com categoria, os atletas ativos dela já são inscritos
            $evento = EventoCalendario::criarPor(auth('admin')->id(), $dadosEvento);

            Jogo::create([...$dadosJogo, 'id_evento' => $evento->id_evento_calendario]);

            return $evento;
        });

        $inscritos = $evento->inscricoes()->count();
        $mensagem  = 'Jogo registrado.'
            . ($evento->id_categoria ? " {$inscritos} atleta(s) da categoria inscrito(s)." : '')
            . ($inscritos ? Notificacao::textoNotificados($evento->atletasNotificados) : '');

        return redirect()->route('admin.jogos.index')->with('sucesso', $mensagem);
    }

    public function update(Request $request, $id)
    {
        $jogo   = Jogo::with('evento')->findOrFail($id);
        $evento = $jogo->evento;

        $dadosJogo   = $this->dadosJogo($request, $evento);
        $dadosEvento = $this->dadosEvento($request, $dadosJogo);

        // Mudou data, horário ou categoria: confere conflito dos atletas que ficarão inscritos
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaEdicao($evento, $dadosEvento))) {
            return $confirmar;
        }

        $categoriaAntes = $evento->id_categoria;

        DB::transaction(function () use ($jogo, $evento, $dadosJogo, $dadosEvento) {
            // O que mudar em título, categoria, data, horário ou local vai para o histórico do evento
            $evento->atualizarComHistorico($dadosEvento, auth('admin')->id());
            $jogo->update($dadosJogo);
        });

        // Times trocados: quem estava escalado num time que saiu do jogo fica sem time (continua inscrito)
        $foraDaEscalacao = $jogo->fresh(['timeCasa', 'timeVisitante'])->limparEscalacaoForaDosTimes();

        $mensagem = 'Jogo atualizado.' . $this->sincronizarSeMudouCategoria($evento, $categoriaAntes)
            . ($foraDaEscalacao ? " {$foraDaEscalacao} atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos." : '');

        return redirect()->route('admin.jogos.index')->with('sucesso', $mensagem);
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
