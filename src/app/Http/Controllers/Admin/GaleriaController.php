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

        $categorias = Galeria::select('categoria_galeria')
            ->distinct() // garante que só traga categorias únicas
            ->orderBy('categoria_galeria')
            ->pluck('categoria_galeria'); // pluck para obter apenas os valores da coluna

        $proximaOrdem = ($fotos->max('ordem_galeria') ?? 0) + 1;

        return view('admin.galeria.index', compact('fotos', 'categorias', 'proximaOrdem'));
    }

    // 2. Salva os dados enviados pelo modal de criação
    public function store(Request $request)
    {
        $request->validate([
            'titulo_galeria'         => 'required|string|max:255',
            'categoria_galeria'      => 'required|string',
            'nova_categoria_galeria' => 'required_if:categoria_galeria,__nova__|nullable|string|max:60',
            'ordem_galeria'          => 'required|integer|min:1',
            'status_galeria'         => 'required|in:ATIVO,INATIVO',
            'foto_galeria'           => 'required|image|max:4096',
        ]);

        $dados = $request->only(['titulo_galeria', 'ordem_galeria', 'status_galeria']);

        $dados['categoria_galeria'] = $request->input('categoria_galeria') === '__nova__' // se for nova categoria, usa o campo de nova categoria, senão usa a selecionada
            ? strtoupper(trim($request->input('nova_categoria_galeria')))
            : $request->input('categoria_galeria');


        $arquivo = $request->file('foto_galeria');
        $nomeArquivo = time() . '_' . $arquivo->getClientOriginalName(); // prefixa o nome do arquivo com timestamp para evitar conflitos se o nome for igual.
        $arquivo->move(public_path('futebol/images/galeria'), $nomeArquivo); // move o arquivo para a pasta pública e salva o nome no banco
        $dados['foto_galeria'] = $nomeArquivo;

        Galeria::create($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto adicionada com sucesso.');
    }

    // 3. Processa a atualização dos dados vindos do modal de edição
    public function update(Request $request, $id)
    {
        $foto = Galeria::findOrFail($id);

        $request->validate([
            'titulo_galeria'         => 'required|string|max:255',
            'categoria_galeria'      => 'required|string',
            'nova_categoria_galeria' => 'required_if:categoria_galeria,__nova__|nullable|string|max:60',
            'ordem_galeria'          => 'required|integer|min:1',
            'status_galeria'         => 'required|in:ATIVO,INATIVO',
            'foto_galeria'           => 'nullable|image|max:4096',
        ]);

        $dados = $request->only(['titulo_galeria', 'ordem_galeria', 'status_galeria']);

        $dados['categoria_galeria'] = $request->input('categoria_galeria') === '__nova__'
            ? strtoupper(trim($request->input('nova_categoria_galeria')))
            : $request->input('categoria_galeria');

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

        if ($foto->status_galeria === 'ATIVO') {
            $foto->status_galeria = 'INATIVO';
            $mensagem = 'Foto inativada com sucesso!';
        } else {
            $foto->status_galeria = 'ATIVO';
            $mensagem = 'Foto reativada com sucesso!';
        }

        $foto->save();

        return redirect()->route('admin.galeria.index')->with('sucesso', $mensagem);
    }
}
