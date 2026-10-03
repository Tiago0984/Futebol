<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarioController extends Controller
{
    public function index()
    {
        $eventos = EventoCalendario::with(['categoria', 'responsavel'])->orderBy('data_evento_calendario', 'desc')->get();
        $grades  = GradeTreino::with('categoria')->ordenada()->get();
        $categorias = Categoria::ativas()->get();

        // Categorias inativadas que algum evento ainda usa: aparecem no select da edição
        // como "(inativa)", para editar só o título não tirar a categoria do evento
        $categoriasInativasEmUso = Categoria::where('status_categoria', '<>', 'ATIVO')
            ->whereIn('id_categoria', EventoCalendario::whereNotNull('id_categoria')->select('id_categoria'))
            ->get();

        return view('admin.calendario.index', compact('eventos', 'grades', 'categorias', 'categoriasInativasEmUso'));
    }

    // ── Eventos ─────────────────────────────────────────────────────────────

    public function storeEvento(Request $request)
    {
        // Responsável = admin logado que criou o evento (gravado só aqui)
        EventoCalendario::criarPor(auth('admin')->id(), [
            ...$this->dadosEvento($request),
            'status_evento_calendario' => 'ATIVO',
        ]);

        return redirect()->route('admin.calendario.index')->with('sucesso', 'Evento adicionado ao calendário.');
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        // Nem o status (só pelas ações de cancelar e ocultar) nem o responsável mudam pela edição
        $evento->update($this->dadosEvento($request, $evento));

        return redirect()->route('admin.calendario.index')->with('sucesso', 'Evento atualizado.');
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

        $novo = $evento->estaCancelado() ? 'ATIVO' : 'CANCELADO';
        $evento->update(['status_evento_calendario' => $novo]);

        return back()->with('sucesso', $novo === 'CANCELADO' ? 'Evento cancelado.' : 'Evento reativado.');
    }

    // Ocultar <-> mostrar. Oculto (INATIVO) some do site e faz o papel da exclusão, sem perder o registro.
    // Mostrar de novo volta para ATIVO (um evento cancelado e depois ocultado volta ativo).
    public function ocultarEvento($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $novo = $evento->estaOculto() ? 'ATIVO' : 'INATIVO';
        $evento->update(['status_evento_calendario' => $novo]);

        return back()->with('sucesso', $novo === 'INATIVO' ? 'Evento ocultado.' : 'Evento visível de novo.');
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

    public function updateGrade(Request $request, $id)
    {
        $grade = GradeTreino::findOrFail($id);

        $grade->update($this->dadosGrade($request));

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário atualizado.');
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

        return back()->with('sucesso', "Horário {$novo} com sucesso.");
    }

    public function destroyGrade($id)
    {
        GradeTreino::findOrFail($id)->delete();

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário removido.');
    }
}
