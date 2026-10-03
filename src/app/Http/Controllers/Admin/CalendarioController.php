<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarioController extends Controller
{
    public function index()
    {
        $eventos = EventoCalendario::with(['categoria', 'responsavel', 'historico.usuario'])
            ->comAlteracao()
            ->withCount(['inscricoes as inscritos_ativos' => fn ($q) => $q->whereHas('atleta', fn ($a) => $a->where('status_atleta', 'ATIVO'))])
            ->orderBy('data_evento_calendario', 'desc')
            ->get();
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
        // Responsável = admin logado que criou o evento (gravado só aqui). Com categoria, os atletas
        // ativos dela já são inscritos (EventoCalendario::criarPor)
        $evento = EventoCalendario::criarPor(auth('admin')->id(), [
            ...$this->dadosEvento($request),
            'status_evento_calendario' => 'ATIVO',
        ]);

        $inscritos = $evento->inscricoes()->count();
        $mensagem  = 'Evento adicionado ao calendário.'
            . ($evento->id_categoria ? " {$inscritos} atleta(s) da categoria inscrito(s)." : '');

        return redirect()->route('admin.calendario.index')->with('sucesso', $mensagem);
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

        // Atletas da categoria do evento que entraram depois e ainda não estão inscritos
        $faltantesDaCategoria = count($evento->idsFaltantesDaCategoria());

        return view('admin.calendario.evento', compact(
            'evento', 'inscricoes', 'inscritosInativos', 'disponiveis', 'categorias', 'faltantesDaCategoria'
        ));
    }

    // "Atualizar inscritos pela categoria": só acrescenta quem falta (não remove ninguém)
    public function atualizarInscritosPelaCategoria($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        if (! $evento->id_categoria) {
            return back()->with('erro', 'Este evento não tem categoria.');
        }

        $novos = $evento->inscreverCategoria($evento->id_categoria, 'CATEGORIA', auth('admin')->id());

        return back()->with('sucesso', "{$novos} atleta(s) da categoria inscrito(s).");
    }

    // Inscrição individual: um atleta ativo escolhido no select
    public function inscreverAtleta(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $request->validate([
            'id_atleta' => ['required', 'integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
        ], [
            'id_atleta.required' => 'Escolha um atleta.',
            'id_atleta.exists'   => 'Escolha um atleta ativo.',
        ]);

        $inscreveu = $evento->inscrever((int) $request->id_atleta, 'INDIVIDUAL', auth('admin')->id());

        return back()->with('sucesso', $inscreveu ? 'Atleta inscrito.' : 'O atleta já estava inscrito.');
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
        $novos     = $evento->inscreverCategoria($categoria->id_categoria, 'INDIVIDUAL', auth('admin')->id());

        return back()->with('sucesso', "{$categoria->rotulo}: {$novos} atleta(s) inscrito(s)"
            . ($novos === 0 ? ' (todos já estavam inscritos ou não há atletas ativos).' : '.'));
    }

    public function removerInscricao($id, $idAtleta)
    {
        $evento = EventoCalendario::findOrFail($id);
        $evento->removerInscricao((int) $idAtleta);

        return back()->with('sucesso', 'Inscrição removida.');
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $categoriaAntes = $evento->id_categoria;

        // Nem o status (só pelas ações de cancelar e ocultar) nem o responsável mudam pela edição.
        // O que mudar em data, horário, local, título, tipo ou categoria vai para o histórico.
        $evento->atualizarComHistorico($this->dadosEvento($request, $evento), auth('admin')->id());

        $mensagem = 'Evento atualizado.';

        // Mudou de categoria (ou ficou sem): as inscrições automáticas acompanham; as individuais ficam.
        // Evento concluído não muda nada.
        if ((int) $categoriaAntes !== (int) $evento->id_categoria && ! $evento->estaConcluido()) {
            ['entraram' => $entraram, 'sairam' => $sairam] = $evento->sincronizarInscricoesPelaCategoria(auth('admin')->id());
            $mensagem .= " Inscrições pela categoria: {$entraram} atleta(s) inscrito(s), {$sairam} removido(s)."
                . ' As inscrições individuais foram mantidas.';
        }

        return redirect()->route('admin.calendario.index')->with('sucesso', $mensagem);
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
        $evento->mudarStatus($novo, auth('admin')->id());

        return back()->with('sucesso', $novo === 'CANCELADO' ? 'Evento cancelado.' : 'Evento reativado.');
    }

    // Ocultar <-> mostrar. Oculto (INATIVO) some do site e faz o papel da exclusão, sem perder o registro.
    // Mostrar devolve o status de antes de ocultar (pelo histórico): cancelado volta como cancelado.
    public function ocultarEvento($id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $novo = $evento->estaOculto() ? $evento->statusAntesDeOcultar() : 'INATIVO';
        $evento->mudarStatus($novo, auth('admin')->id());

        $mensagem = match ($novo) {
            'INATIVO'   => 'Evento ocultado.',
            'CANCELADO' => 'Evento visível de novo (continua cancelado).',
            default     => 'Evento visível de novo.',
        };

        return back()->with('sucesso', $mensagem);
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
