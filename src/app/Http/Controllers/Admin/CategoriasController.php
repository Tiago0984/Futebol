<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriasController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.categorias.index', compact('categorias'));
    }

    public function create()
    {
        return view('admin.categorias.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_categoria'      => 'required|string|max:100',
            'idade_min_categoria' => 'required|integer|min:1',
            'idade_max_categoria' => 'required|integer|min:1|gte:idade_min_categoria',
            'sexo_categoria'      => 'required|in:M,F,Misto',
        ]);

        Categoria::create([
            ...$request->only(['nome_categoria', 'idade_min_categoria', 'idade_max_categoria', 'sexo_categoria']),
            'status_categoria' => 'ATIVO',
        ]);

        return redirect()->route('admin.categorias.index')->with('sucesso', 'Categoria criada com sucesso.');
    }

    public function edit($id)
    {
        $categoria = Categoria::findOrFail($id);

        return view('admin.categorias.edit', compact('categoria'));
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nome_categoria'      => 'required|string|max:100',
            'idade_min_categoria' => 'required|integer|min:1',
            'idade_max_categoria' => 'required|integer|min:1|gte:idade_min_categoria',
            'sexo_categoria'      => 'required|in:M,F,Misto',
        ]);

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
        $categoria->delete();

        return redirect()->route('admin.categorias.index')->with('sucesso', 'Categoria removida com sucesso.');
    }
}
