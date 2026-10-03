<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatriculasController extends Controller
{
    public function index()
    {
        $matriculas = Atleta::with(['responsaveis', 'endereco', 'autorizacoes'])
            ->whereIn('status_atleta', ['PENDENTE', 'pendente'])
            ->orderBy('nome_atleta')
            ->get();

        // Categoria sugerida de cada matrícula, para o botão "Aprovar" da lista
        $sugeridas = $matriculas->mapWithKeys(fn ($atleta) => [
            $atleta->id_atleta => Categoria::sugeridaPara($atleta->data_nasc_atleta, $atleta->sexo_atleta),
        ]);

        return view('admin.matriculas.index', compact('matriculas', 'sugeridas'));
    }

    public function show($id)
    {
        $atleta = Atleta::with(['responsaveis.endereco', 'endereco', 'autorizacoes'])
            ->findOrFail($id);

        // Só as categorias do sexo do atleta; a sugerida (regra do ano) já vem selecionada
        $categorias = Categoria::ativas()->where('sexo_categoria', $atleta->sexo_atleta)->get();
        $sugerida   = Categoria::sugeridaPara($atleta->data_nasc_atleta, $atleta->sexo_atleta);

        return view('admin.matriculas.show', compact('atleta', 'categorias', 'sugerida'));
    }

    // Aprova a matrícula e grava a categoria (a sugerida, ou outra acima da idade com motivo)
    public function aprovar(Request $request, $id)
    {
        $atleta = Atleta::findOrFail($id);

        $request->validate([
            'id_categoria'     => 'required|integer|exists:tbl_categoria,id_categoria',
            'motivo_categoria' => 'nullable|string|max:500',
        ], [
            'id_categoria.required' => 'Escolha a categoria do atleta para aprovar a matrícula.',
        ]);

        $erro = Categoria::findOrFail($request->id_categoria)
            ->erroParaAtleta($atleta->data_nasc_atleta, $atleta->sexo_atleta, $request->motivo_categoria);

        if ($erro) {
            return back()->withErrors(['id_categoria' => $erro])->withInput();
        }

        $matricula = $atleta->numero_matricula_atleta ?: $this->gerarNumeroMatricula();

        DB::transaction(function () use ($atleta, $matricula, $request) {
            $atleta->update([
                'status_atleta'           => 'ATIVO',
                'numero_matricula_atleta' => $matricula,
            ]);

            $atleta->trocarCategoria((int) $request->id_categoria, $request->motivo_categoria);
        });

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
        $rejeitadas = Atleta::with(['responsaveis', 'endereco', 'autorizacoes'])
            ->whereIn('status_atleta', ['REJEITADO', 'rejeitado'])
            ->orderBy('nome_atleta')
            ->get();

        return view('admin.matriculas.rejeitadas', compact('rejeitadas'));
    }

    public function reativar($id)
    {
        $atleta = Atleta::findOrFail($id);
        $atleta->update(['status_atleta' => 'PENDENTE']);

        return redirect()->route('admin.matriculas.index')
            ->with('sucesso', "Matrícula de {$atleta->nome_atleta} reativada. Agora é possível aprovar na seção de pendentes.");
    }

    public function deletar($id)
    {
        $atleta = Atleta::findOrFail($id);
        $nome = $atleta->nome_atleta;

        if (!$atleta->excluirComDependencias()) {
            return redirect()->route('admin.matriculas.rejeitadas')
                ->with('erro', "{$nome} possui cartões registrados em jogos e não pode ser excluído definitivamente. O cadastro continua em Matrículas Rejeitadas para preservar o histórico das partidas.");
        }

        return redirect()->route('admin.matriculas.rejeitadas')
            ->with('sucesso', "Cadastro de {$nome} excluído permanentemente.");
    }
}
