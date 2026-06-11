<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Autorizacao;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    // Exibe a página de assinatura
    public function show($token)
    {
        $autorizacao = Autorizacao::where('token_assinatura', $token)->firstOrFail();
        $atleta      = $autorizacao->atleta;
        $responsavel = $autorizacao->responsavel;

        // Busca o grau de parentesco na tabela pivot
        $grauParentesco = $atleta->responsaveis()
            ->where('tbl_responsavel.id_responsavel', $responsavel->id_responsavel)
            ->first()
            ->pivot
            ->grau_parentesco_responsavel ?? '—';

        return view('site.cadastro.assinar', compact('autorizacao', 'atleta', 'responsavel', 'grauParentesco'));
    }

    // Salva a assinatura
    public function store(Request $request, $token)
    {
        $autorizacao = Autorizacao::where('token_assinatura', $token)
            ->where('status_autorizacao', 'PENDENTE')
            ->firstOrFail();

        $request->validate([
            'assinatura_img' => 'required|string',
        ]);

        // Salva a imagem base64 como arquivo PNG
        $imagemBase64 = $request->assinatura_img;
        $imagemBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imagemBase64);
        $imagemBinaria = base64_decode($imagemBase64);

        $nomeArquivo = 'futebol/images/Assinaturas/assinatura_' . $autorizacao->id_responsavel . '_' . time() . '.png';
        file_put_contents(public_path($nomeArquivo), $imagemBinaria);

        // Atualiza o responsável com a assinatura e aceite
        $autorizacao->responsavel->update([
            'assinatura_responsavel' => $nomeArquivo,
            'aceite_responsavel'     => 'S',
        ]);

        // Atualiza a autorização para ASSINADO
        $autorizacao->update([
            'status_autorizacao'          => 'ASSINADO',
            'data_assinatura_autorizacao' => now(),
        ]);

        return redirect()->route('assinar.show', $token)
            ->with('assinado', true)
            ->with('nome_responsavel', $autorizacao->responsavel->nome_responsavel)
            ->with('nome_atleta', $autorizacao->atleta->nome_atleta);
    }
}
