<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Galeria;
use Illuminate\Http\Request;

class GaleriaController extends Controller
{
    public function index()
    {
        $fotos = Galeria::orderBy('ordem_galeria')->get();

        return view('admin.galeria.index', compact('fotos'));
    }

    public function create()
    {
        return view('admin.galeria.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo_galeria' => 'required|string|max:255',
            'ordem_galeria'  => 'required|integer|min:1',
            'status_galeria' => 'required|in:ativo,inativo',
            'foto_galeria'   => 'required|image|max:4096',
        ]);

        $dados = $request->only([
            'titulo_galeria',
            'ordem_galeria',
            'status_galeria',
        ]);

        $dados['foto_galeria'] = $request->file('foto_galeria')->store('galeria', 'public');

        Galeria::create($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto adicionada com sucesso.');
    }

    public function edit($id)
    {
        $foto = Galeria::findOrFail($id);

        return view('admin.galeria.edit', compact('foto'));
    }

    public function update(Request $request, $id)
    {
        $foto = Galeria::findOrFail($id);

        $request->validate([
            'titulo_galeria' => 'required|string|max:255',
            'ordem_galeria'  => 'required|integer|min:1',
            'status_galeria' => 'required|in:ativo,inativo',
            'foto_galeria'   => 'nullable|image|max:4096',
        ]);

        $dados = $request->only([
            'titulo_galeria',
            'ordem_galeria',
            'status_galeria',
        ]);

        if ($request->hasFile('foto_galeria')) {
            $dados['foto_galeria'] = $request->file('foto_galeria')->store('galeria', 'public');
        }

        $foto->update($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto atualizada com sucesso.');
    }

    public function destroy($id)
    {
        $foto = Galeria::findOrFail($id);
        $foto->delete();

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto removida com sucesso.');
    }
}
