<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampeonatosController extends Controller
{
    /**
     * Lista de campeonatos. ?campeonato=ID (o nome do campeonato no menu) mostra só aquele, com o aviso e
     * o "Ver todos"; ID inexistente ou inválido é ignorado (lista todos).
     */
    public function index(Request $request)
    {
        $idFiltro = (string) $request->query('campeonato', '');
        $campeonatoFiltrado = ctype_digit($idFiltro) ? Campeonato::find((int) $idFiltro, ['id_campeonato', 'nome_campeonato']) : null;

        $campeonatos = Campeonato::with(['categoria', 'categorias', 'times'])
            ->when($campeonatoFiltrado, fn ($q) => $q->whereKey($campeonatoFiltrado->id_campeonato))
            ->orderBy('data_inicio_campeonato', 'desc')
            ->get();
        $categorias  = $this->categorias();
        $times       = Time::orderBy('nome_time')->get();

        // Participantes de cada categoria, para o modal de edição
        $participantes = $campeonatos->mapWithKeys(fn ($camp) => [$camp->id_campeonato => $camp->participantesPorCategoria()]);

        return view('admin.campeonatos.index', compact('campeonatos', 'categorias', 'times', 'campeonatoFiltrado', 'participantes'));
    }

    public function create()
    {
        $categorias = $this->categorias();
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.create', compact('categorias', 'times'));
    }

    public function store(Request $request)
    {
        $this->validar($request);

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'local_evento', 'organizador_campeonato',
            'descricao_campeonato', 'id_categoria',
        ]);

        if ($request->hasFile('logo_evento')) {
            $dados['logo_evento'] = $request->file('logo_evento')->getClientOriginalName();
            $request->file('logo_evento')->move(public_path('futebol/images/campeonatos'), $dados['logo_evento']);
        }

        if ($request->hasFile('banner_evento')) {
            $dados['banner_evento'] = $request->file('banner_evento')->getClientOriginalName();
            $request->file('banner_evento')->move(public_path('futebol/images/campeonatos'), $dados['banner_evento']);
        }

        $dados['status_campeonato'] = 'ATIVO';
        $idsCategorias = $this->categoriasDoPedido($request);

        DB::transaction(function () use ($dados, $request, $idsCategorias) {
            $campeonato = Campeonato::create($dados);
            $campeonato->gravarCategoriasEParticipantes($idsCategorias, $this->participantesDoPedido($request, $idsCategorias));
        });

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato criado com sucesso.');
    }

    public function edit($id)
    {
        $campeonato = Campeonato::with('categorias')->findOrFail($id);
        $categorias = $this->categorias();
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.edit', compact('campeonato', 'categorias', 'times'));
    }

    public function update(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);

        $this->validar($request, $campeonato);

        $idsCategorias = $this->categoriasDoPedido($request);
        $participantes = $this->participantesDoPedido($request, $idsCategorias);

        // Time com convocados não sai de todas as categorias (a convocação ficaria apontando para quem não joga)
        if ($saem = $campeonato->timesQueSaemComConvocados(array_merge([], ...array_values($participantes)))) {
            return back()->withInput()->withErrors(['participantes' => 'Não é possível tirar do campeonato time com atletas convocados: '
                . implode(', ', $saem) . '. Remova a convocação do time antes.']);
        }

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'local_evento', 'organizador_campeonato',
            'descricao_campeonato', 'id_categoria',
        ]);

        if ($request->hasFile('logo_evento')) {
            $dados['logo_evento'] = $request->file('logo_evento')->getClientOriginalName();
            $request->file('logo_evento')->move(public_path('futebol/images/campeonatos'), $dados['logo_evento']);
        }

        if ($request->hasFile('banner_evento')) {
            $dados['banner_evento'] = $request->file('banner_evento')->getClientOriginalName();
            $request->file('banner_evento')->move(public_path('futebol/images/campeonatos'), $dados['banner_evento']);
        }

        // A categoria dos jogos acompanha a do campeonato (Campeonato::atualizarCategoriaDosJogos)
        $jogosAtualizados = DB::transaction(function () use ($campeonato, $dados, $idsCategorias, $participantes) {
            $campeonato->update($dados);
            $campeonato->gravarCategoriasEParticipantes($idsCategorias, $participantes);

            return $campeonato->wasChanged('id_categoria') ? $campeonato->atualizarCategoriaDosJogos(auth('admin')->id()) : 0;
        });

        $mensagem = 'Campeonato atualizado com sucesso.'
            . ($jogosAtualizados ? " Categoria atualizada em {$jogosAtualizados} jogo(s)." : '');

        return redirect()->route('admin.campeonatos.index')->with('sucesso', $mensagem);
    }

    public function toggleStatus($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $novo = strtoupper($campeonato->status_campeonato) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $campeonato->update(['status_campeonato' => $novo]);

        return back()->with('sucesso', "Campeonato {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $campeonato = Campeonato::findOrFail($id);

        // Campeonato com jogos não é excluído (mensagem em vez do erro de FK)
        if ($motivo = $campeonato->motivoParaNaoExcluir()) {
            return redirect()->route('admin.campeonatos.index')->with('erro', $motivo);
        }

        DB::transaction(function () use ($campeonato) {
            $campeonato->apagarCategoriasEParticipantes();
            $campeonato->delete();
        });

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato removido com sucesso.');
    }

    /**
     * Tipo pela lista Campeonato::TIPOS (o valor chega em maiúsculas; na edição, o tipo antigo fora da
     * lista continua aceito, para editar outros campos sem perder o tipo).
     */
    private function validar(Request $request, ?Campeonato $campeonato = null): void
    {
        $request->merge(['tipo_campeonato' => mb_strtoupper(trim((string) $request->input('tipo_campeonato')))]);
        $tipos = array_keys(Campeonato::TIPOS);
        if ($campeonato?->tipo_campeonato) {
            $tipos[] = $campeonato->tipo_campeonato;
        }

        $request->validate([
            'nome_campeonato'        => 'required|string|max:255',
            'tipo_campeonato'        => ['required', Rule::in($tipos)],
            'data_inicio_campeonato' => 'required|date',
            'data_fim_campeonato'    => 'required|date|after_or_equal:data_inicio_campeonato',
            'local_evento'           => 'nullable|string|max:255',
            'organizador_campeonato' => 'nullable|string|max:255',
            'descricao_campeonato'   => 'nullable|string',
            'id_categoria'           => 'required|integer|exists:tbl_categoria,id_categoria',
            'categorias'             => 'nullable|array',
            'categorias.*'           => 'integer|exists:tbl_categoria,id_categoria',
            'logo_evento'            => 'nullable|image|max:2048',
            'banner_evento'          => 'nullable|image|max:4096',
            'participantes'          => 'nullable|array',
            'participantes.*'        => 'array',
            'participantes.*.*'      => 'integer|exists:tbl_time,id_time',
        ], [
            'id_categoria.required' => 'Escolha a categoria principal do campeonato.',
        ]);
    }

    // Categorias marcadas, sempre com a principal (ela vale para a idade, a convocação e os jogos até a Fase H, Etapa H3)
    private function categoriasDoPedido(Request $request): array
    {
        return array_values(array_unique(array_map('intval', [$request->input('id_categoria'), ...(array) $request->input('categorias', [])])));
    }

    // Participantes de cada categoria marcada: [id_categoria => [id_time]]; o bloco de categoria desmarcada é ignorado
    private function participantesDoPedido(Request $request, array $idsCategorias): array
    {
        $pedido = (array) $request->input('participantes', []);

        return collect($idsCategorias)
            ->mapWithKeys(fn ($idCategoria) => [$idCategoria => array_values(array_unique(array_map('intval', (array) ($pedido[$idCategoria] ?? []))))])
            ->all();
    }

    // Categorias dos selects (com o sexo no rótulo), na ordem dos outros formulários; inclui as inativas,
    // para a categoria atual de um campeonato antigo continuar aparecendo
    private function categorias()
    {
        return Categoria::orderBy('sexo_categoria', 'desc')->orderBy('idade_min_categoria')->get();
    }
}
