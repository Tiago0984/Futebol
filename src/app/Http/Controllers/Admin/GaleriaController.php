<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Galeria;
use Illuminate\Http\Request;

class GaleriaController extends Controller
{
    // 1. Carrega a listagem e os modais estruturados nela
    public function index()
    {
        $fotos = Galeria::orderBy('ordem_galeria')->get();

        return view('admin.galeria.index', compact('fotos'));
    }

    // 2. Salva os dados enviados pelo modal de criação
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

        $arquivo = $request->file('foto_galeria');
        $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
        $arquivo->move(public_path('futebol/images/galeria'), $nomeArquivo);
        $dados['foto_galeria'] = $nomeArquivo;

        Galeria::create($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto adicionada com sucesso.');
    }

    // 3. Processa a atualização dos dados vindos do modal de edição
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
            $arquivo = $request->file('foto_galeria');
            $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName();
            $arquivo->move(public_path('futebol/images/galeria'), $nomeArquivo);
            $dados['foto_galeria'] = $nomeArquivo;
        }

        $foto->update($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto atualizada com sucesso.');
    }

    // 4. Alterna o status entre ativo e inativo
    public function destroy($id)
    {
        $foto = Galeria::findOrFail($id);

        if (strtolower($foto->status_galeria) === 'ativo') {
            $foto->status_galeria = 'inativo';
            $mensagem = 'Foto inativada com sucesso!';
        } else {
            $foto->status_galeria = 'ativo';
            $mensagem = 'Foto reativada com sucesso!';
        }

        $foto->save();

        return redirect()->route('admin.galeria.index')->with('sucesso', $mensagem);
    }
}
