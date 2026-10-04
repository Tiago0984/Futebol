<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
        $dados = [...$this->dadosEvento($request), 'status_evento_calendario' => 'ATIVO'];

        // Com categoria, os atletas dela serão inscritos: confere conflito antes de criar
        if (! empty($dados['id_categoria'])) {
            $simulado = new EventoCalendario($dados);
            $conflitos = $simulado->conflitosPara(Atleta::idsAtivosNaCategoria((int) $dados['id_categoria']));

            if ($confirmar = $this->confirmarConflitos($request, $conflitos)) {
                return $confirmar;
            }
        }

        // Responsável = admin logado que criou o evento (gravado só aqui). Com categoria, os atletas
        // ativos dela já são inscritos (EventoCalendario::criarPor)
        $evento = EventoCalendario::criarPor(auth('admin')->id(), $dados);

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

        if ($confirmar = $this->confirmarConflitos(request(), $evento->conflitosPara($evento->idsFaltantesDaCategoria()))) {
            return $confirmar;
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

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara([(int) $request->id_atleta]))) {
            return $confirmar;
        }

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

        // Só quem ainda não está inscrito entra; o conflito é conferido para esses
        $inscritos = $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        $entrariam = array_values(array_diff(Atleta::idsAtivosNaCategoria($categoria->id_categoria), $inscritos));

        if ($confirmar = $this->confirmarConflitos($request, $evento->conflitosPara($entrariam))) {
            return $confirmar;
        }

        $novos = $evento->inscreverCategoria($categoria->id_categoria, 'INDIVIDUAL', auth('admin')->id());

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
        $dados          = $this->dadosEvento($request, $evento);

        // Mudou data, horário ou categoria: confere conflito dos atletas que ficarão inscritos
        if ($confirmar = $this->confirmarConflitos($request, $this->conflitosDaEdicao($evento, $dados))) {
            return $confirmar;
        }

        // Nem o status (só pelas ações de cancelar e ocultar) nem o responsável mudam pela edição.
        // O que mudar em data, horário, local, título, tipo ou categoria vai para o histórico.
        $evento->atualizarComHistorico($dados, auth('admin')->id());

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
     * Alerta de conflito. Conflito real (horários sobrepostos) pede confirmação: volta para a tela com a
     * lista e um formulário que reenvia os mesmos dados com confirmar_conflito=1. Aviso fraco (algum
     * evento sem horário de início) não bloqueia: segue e mostra um aviso informativo na próxima tela.
     * Com os dois tipos, pede confirmação e lista os dois, separados. Devolve null quando pode seguir.
     */
    private function confirmarConflitos(Request $request, Collection $conflitos): ?RedirectResponse
    {
        [$fracos, $fortes] = $conflitos->partition(fn ($c) => $c['fraco']);
        $descrever = fn (Collection $lista) => $lista->map(fn ($c) => EventoCalendario::descreverConflito($c))->values()->all();

        if ($fortes->isEmpty() || $request->boolean('confirmar_conflito')) {
            if ($fortes->isEmpty() && $fracos->isNotEmpty()) {
                session()->flash('avisos_mesmo_dia', $descrever($fracos));
            }

            return null;
        }

        return back()->withInput()->with('conflitos_pendentes', [
            'url'    => $request->url(),
            'metodo' => $request->method(), // PUT na edição (o _method é refeito no formulário)
            'dados'  => Arr::except($request->all(), ['_token', '_method', 'confirmar_conflito']),
            'fortes' => $descrever($fortes),
            'fracos' => $descrever($fracos),
        ]);
    }

    /**
     * Conflitos que a edição criaria: só quando muda data, horário ou categoria. Confere os atletas que
     * ficarão inscritos (com troca de categoria: individuais atuais + atletas ativos da categoria nova).
     */
    private function conflitosDaEdicao(EventoCalendario $evento, array $dados): Collection
    {
        $simulado = $evento->replicate()->fill($dados);
        $simulado->id_evento_calendario = $evento->id_evento_calendario;

        $hora = fn ($valor) => substr((string) $valor, 0, 5);
        $mudouHorario = $evento->data_evento_calendario->toDateString() !== $simulado->data_evento_calendario->toDateString()
            || $hora($evento->horario_inicio_evento_calendario) !== $hora($simulado->horario_inicio_evento_calendario)
            || $hora($evento->horario_fim_evento_calendario) !== $hora($simulado->horario_fim_evento_calendario);
        $mudouCategoria = (int) $evento->id_categoria !== (int) $simulado->id_categoria && ! $simulado->estaConcluido();

        if (! $mudouHorario && ! $mudouCategoria) {
            return collect();
        }

        $inscricoes = $evento->inscricoes()->get(['id_atleta', 'origem_evento_atleta']);
        $ids = $inscricoes->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        if ($mudouCategoria) {
            $individuais = $inscricoes->where('origem_evento_atleta', 'INDIVIDUAL')->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
            $daNova      = $simulado->id_categoria ? Atleta::idsAtivosNaCategoria((int) $simulado->id_categoria) : [];
            $ids         = array_values(array_unique([...$individuais, ...$daNova]));
        }

        return $simulado->conflitosPara($ids);
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

        return back()->with('sucesso', ($novo === 'CANCELADO' ? 'Evento cancelado.' : 'Evento reativado.')
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

        $novo = $evento->estaOculto() ? $evento->statusAntesDeOcultar() : 'INATIVO';
        $evento->mudarStatus($novo, auth('admin')->id());

        $mensagem = match ($novo) {
            'INATIVO'   => 'Evento ocultado.',
            'CANCELADO' => 'Evento visível de novo (continua cancelado).',
            default     => 'Evento visível de novo.',
        };

        return back()->with('sucesso', $mensagem . $this->avisoDeConflitoAoReativar($evento));
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
