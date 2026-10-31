<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaleConosco;
use Illuminate\Http\Request;

class FaleConoscoController extends Controller
{
    public function index(Request $request)
    {
        $dados = $request->validate(['leitura' => ['nullable', 'in:lidas,nao_lidas']]);
        $leitura = $dados['leitura'] ?? '';
        $mensagens = FaleConosco::query()
            ->when($leitura === 'lidas', fn ($q) => $q->whereNotNull('data_leitura_fale_conosco'))
            ->when($leitura === 'nao_lidas', fn ($q) => $q->whereNull('data_leitura_fale_conosco'))
            ->orderByDesc('data_fale_conosco')->orderByDesc('id_fale_conosco')
            ->paginate(20)->withQueryString();

        return view('admin.fale-conosco.index', compact('mensagens', 'leitura'));
    }

    public function show(int $id)
    {
        $mensagem = FaleConosco::findOrFail($id);

        return view('admin.fale-conosco.show', compact('mensagem'));
    }

    public function leitura(Request $request, int $id)
    {
        $dados = $request->validate(['lida' => ['required', 'boolean']]);
        $mensagem = FaleConosco::findOrFail($id);
        // Repetir "marcar lida" preserva a primeira data de leitura.
        if ($dados['lida']) {
            FaleConosco::whereKey($id)->whereNull('data_leitura_fale_conosco')
                ->update(['data_leitura_fale_conosco' => now()]);
        } else {
            $mensagem->data_leitura_fale_conosco = null;
            $mensagem->save();
        }

        return redirect()->route('admin.fale-conosco.show', $id)
            ->with('sucesso', $dados['lida'] ? 'Mensagem marcada como lida.' : 'Mensagem marcada como não lida.');
    }
}
