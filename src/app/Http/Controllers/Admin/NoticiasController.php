<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    // 1. Carrega a listagem e os modais estruturados nela
    public function index()
    {
        $noticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->get();
        return view('admin.noticias.index', compact('noticias'));
    }

    // 2. Salva os dados enviados pelo modal de criação
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
            $arquivo = $request->file('foto_noticia');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->move(public_path('futebol/images/news'), $nomeArquivo);
            $dados['foto_noticia'] = $nomeArquivo;
        }

        Noticia::create($dados);

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia criada com sucesso.');
    }

    // 3. Processa a atualização dos dados vindos do modal de edição
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
            $arquivo = $request->file('foto_noticia');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->move(public_path('futebol/images/news'), $nomeArquivo);
            $dados['foto_noticia'] = $nomeArquivo;
        }

        $noticia->update($dados);

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia atualizada com sucesso.');
    }

    // 4. Alterna o Status entre Ativo e Inativo
    public function destroy($id)
    {
        $noticia = Noticia::findOrFail($id);

        if ($noticia->status_noticia === 'ATIVO') {
            $noticia->status_noticia = 'INATIVO';
            $mensagem = 'Notícia inativada com sucesso!';
        } else {
            $noticia->status_noticia = 'ATIVO';
            $mensagem = 'Notícia reativada com sucesso!';
        }

        $noticia->save();

        return redirect()->route('admin.noticias.index')->with('sucesso', $mensagem);
    }
}
