<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use Illuminate\Support\Facades\DB;

class MatriculasController extends Controller
{
    public function index()
    {
        $matriculas = Atleta::with(['responsaveis', 'endereco'])
            ->whereIn('status_atleta', ['PENDENTE', 'pendente'])
            ->orderBy('nome_atleta')
            ->get();

        return view('admin.matriculas.index', compact('matriculas'));
    }

    public function show($id)
    {
        $atleta = Atleta::with(['responsaveis.endereco', 'endereco', 'autorizacoes'])
            ->findOrFail($id);

        return view('admin.matriculas.show', compact('atleta'));
    }

    public function aprovar($id)
    {
        $atleta = Atleta::findOrFail($id);

        $matricula = $atleta->numero_matricula_atleta ?: $this->gerarNumeroMatricula();

        $atleta->update([
            'status_atleta'          => 'ATIVO',
            'numero_matricula_atleta' => $matricula,
        ]);

        return redirect()->route('admin.matriculas.index')
            ->with('sucesso', "Matrícula de {$atleta->nome_atleta} aprovada. Número: {$matricula}");
    }

    private function gerarNumeroMatricula(): string
    {
        $maxNumero = Atleta::whereRaw("numero_matricula_atleta REGEXP '^A[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(numero_matricula_atleta, 2) AS UNSIGNED)) as max_num')
            ->value('max_num');

        $proximo = ($maxNumero ?? 0) + 1;

        return 'A' . str_pad($proximo, 3, '0', STR_PAD_LEFT);
    }

    public function rejeitar($id)
    {
        $atleta = Atleta::findOrFail($id);
        $atleta->update(['status_atleta' => 'REJEITADO']);

        return redirect()->route('admin.matriculas.index')
            ->with('sucesso', "Matrícula de {$atleta->nome_atleta} foi rejeitada.");
    }

    public function rejeitadas()
    {
        $rejeitadas = Atleta::with(['responsaveis', 'endereco'])
            ->whereIn('status_atleta', ['REJEITADO', 'rejeitado'])
            ->orderBy('nome_atleta')
            ->get();

        return view('admin.matriculas.rejeitadas', compact('rejeitadas'));
    }

    public function deletar($id)
    {
        $atleta = Atleta::with(['responsaveis', 'autorizacoes'])->findOrFail($id);
        $nome = $atleta->nome_atleta;

        DB::transaction(function () use ($atleta) {
            $atleta->autorizacoes()->delete();
            $atleta->responsaveis()->detach();
            $atleta->categorias()->detach();
            $atleta->times()->detach();
            $atleta->delete();
        });

        return redirect()->route('admin.matriculas.rejeitadas')
            ->with('sucesso', "Cadastro de {$nome} excluído permanentemente.");
    }
}
