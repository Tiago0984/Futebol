<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriasController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.categorias.index', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_categoria'      => ['required', 'string', 'max:50', $this->nomeUnicoPorSexo($request)],
            'idade_min_categoria' => 'required|integer|min:1',
            'idade_max_categoria' => 'required|integer|min:1|gte:idade_min_categoria',
            'sexo_categoria'      => ['required', Rule::in(array_keys(Categoria::SEXOS))],
        ], $this->mensagens());

        Categoria::create([
            ...$request->only(['nome_categoria', 'idade_min_categoria', 'idade_max_categoria', 'sexo_categoria']),
            'status_categoria' => 'ATIVO',
        ]);

        return redirect()->route('admin.categorias.index')->with('sucesso', 'Categoria criada com sucesso.');
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nome_categoria'      => ['required', 'string', 'max:50', $this->nomeUnicoPorSexo($request, $categoria->id_categoria)],
            'idade_min_categoria' => 'required|integer|min:1',
            'idade_max_categoria' => 'required|integer|min:1|gte:idade_min_categoria',
            'sexo_categoria'      => ['required', Rule::in(array_keys(Categoria::SEXOS))],
        ], $this->mensagens());

        $categoria->update($request->only([
            'nome_categoria',
            'idade_min_categoria',
            'idade_max_categoria',
            'sexo_categoria',
        ]));

        return redirect()->route('admin.categorias.index')->with('sucesso', 'Categoria atualizada com sucesso.');
    }

    public function toggleStatus($id)
    {
        $categoria = Categoria::findOrFail($id);
        $novo = strtoupper($categoria->status_categoria) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $categoria->update(['status_categoria' => $novo]);

        return back()->with('sucesso', "Categoria {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);

        // Categoria em uso não é excluída (mensagem em vez do erro de FK)
        if ($motivo = $categoria->motivoParaNaoExcluir()) {
            return redirect()->route('admin.categorias.index')->with('erro', $motivo);
        }

        $categoria->delete();

        return redirect()->route('admin.categorias.index')->with('sucesso', 'Categoria removida com sucesso.');
    }

    // Mesmo nome pode existir uma vez por sexo (Sub-13 M e Sub-13 F); espelha o índice único do banco
    private function nomeUnicoPorSexo(Request $request, ?int $ignorarId = null)
    {
        return Rule::unique('tbl_categoria', 'nome_categoria')
            ->where('sexo_categoria', $request->input('sexo_categoria'))
            ->ignore($ignorarId, 'id_categoria');
    }

    private function mensagens(): array
    {
        return [
            'nome_categoria.unique' => 'Já existe uma categoria com esse nome para esse sexo.',
        ];
    }
}
