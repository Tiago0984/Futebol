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
    public function index()
    {
        $campeonatos = Campeonato::with(['categoria', 'times'])->orderBy('data_inicio_campeonato', 'desc')->get();
        $categorias  = $this->categorias();
        $times       = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.index', compact('campeonatos', 'categorias', 'times'));
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
        $campeonato = Campeonato::create($dados);

        if ($request->filled('times')) {
            $campeonato->times()->sync($request->times);
        }

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato criado com sucesso.');
    }

    public function edit($id)
    {
        $campeonato = Campeonato::with('times')->findOrFail($id);
        $categorias = $this->categorias();
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.edit', compact('campeonato', 'categorias', 'times'));
    }

    public function update(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);

        $this->validar($request, $campeonato);

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

        $campeonato->update($dados);
        $campeonato->times()->sync($request->times ?? []);

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato atualizado com sucesso.');
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
            $campeonato->times()->detach();
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
            'id_categoria'           => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'logo_evento'            => 'nullable|image|max:2048',
            'banner_evento'          => 'nullable|image|max:4096',
            'times'                  => 'nullable|array',
            'times.*'                => 'integer|exists:tbl_time,id_time',
        ]);
    }

    // Categorias dos selects (com o sexo no rótulo), na ordem dos outros formulários; inclui as inativas,
    // para a categoria atual de um campeonato antigo continuar aparecendo
    private function categorias()
    {
        return Categoria::orderBy('sexo_categoria', 'desc')->orderBy('idade_min_categoria')->get();
    }
}
