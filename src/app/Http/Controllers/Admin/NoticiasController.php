<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    public function index()
    {
        $noticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->get();

        return view('admin.noticias.index', compact('noticias'));
    }

    public function create()
    {
        return view('admin.noticias.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo_noticia'          => 'required|string|max:255',
            'conteudo_noticia'        => 'required|string',
            'categoria_noticia'       => 'required|string|max:100',
            'autor_noticia'           => 'required|string|max:100',
            'data_publicacao_noticia' => 'required|date',
            'foto_noticia'            => 'nullable|image|max:2048',
        ]);

        $dados = $request->only([
            'titulo_noticia',
            'conteudo_noticia',
            'categoria_noticia',
            'autor_noticia',
            'data_publicacao_noticia',
        ]);

        if ($request->hasFile('foto_noticia')) {
            $dados['foto_noticia'] = $request->file('foto_noticia')->store('noticias', 'public');
        }

        Noticia::create($dados);

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia criada com sucesso.');
    }

    public function edit($id)
    {
        $noticia = Noticia::findOrFail($id);

        return view('admin.noticias.edit', compact('noticia'));
    }

    public function update(Request $request, $id)
    {
        $noticia = Noticia::findOrFail($id);

        $request->validate([
            'titulo_noticia'          => 'required|string|max:255',
            'conteudo_noticia'        => 'required|string',
            'categoria_noticia'       => 'required|string|max:100',
            'autor_noticia'           => 'required|string|max:100',
            'data_publicacao_noticia' => 'required|date',
            'foto_noticia'            => 'nullable|image|max:2048',
        ]);

        $dados = $request->only([
            'titulo_noticia',
            'conteudo_noticia',
            'categoria_noticia',
            'autor_noticia',
            'data_publicacao_noticia',
        ]);

        if ($request->hasFile('foto_noticia')) {
            $dados['foto_noticia'] = $request->file('foto_noticia')->store('noticias', 'public');
        }

        $noticia->update($dados);

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia atualizada com sucesso.');
    }

    public function destroy($id)
    {
        $noticia = Noticia::findOrFail($id);
        $noticia->delete();

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia removida com sucesso.');
    }
}
