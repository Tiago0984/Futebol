<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscricao;
use Illuminate\Http\Request;

class InscricoesController extends Controller
{
    public function index()
    {
        $inscricoes = Inscricao::with(['atleta', 'categoria'])
                               ->orderBy('data_inscricao', 'desc')
                               ->get();

        return view('admin.inscricoes.index', compact('inscricoes'));
    }

    public function show($id)
    {
        $inscricao = Inscricao::with(['atleta.endereco', 'atleta.responsaveis', 'categoria'])
                              ->findOrFail($id);

        return view('admin.inscricoes.show', compact('inscricao'));
    }

    public function destroy($id)
    {
        $inscricao = Inscricao::findOrFail($id);
        $inscricao->delete();

        return redirect()->route('admin.inscricoes.index')->with('sucesso', 'Inscrição removida com sucesso.');
    }
}
