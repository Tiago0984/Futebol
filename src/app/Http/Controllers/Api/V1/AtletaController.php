<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppDoAtleta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AtletaController extends Controller
{
    // GET /api/v1/atleta - dados do atleta autenticado (identificado pelo token)
    // Os mesmos dados que o responsável vê do filho (App\Services\AppDoAtleta::dados)
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => (new AppDoAtleta($request->user()))->dados(),
        ]);
    }

    // PUT/PATCH /api/v1/atleta - atualiza apenas os dados que o próprio atleta pode alterar
    // Nome, CPF, RG, matrícula e status continuam sendo responsabilidade da secretaria (painel admin)
    public function update(Request $request)
    {
        $atleta = $request->user();

        // "sometimes" permite enviar só alguns campos (atualização parcial)
        $dados = $request->validate([
            'email_atleta'           => ['sometimes', 'required', 'email', 'max:255', Rule::unique('tbl_atletas', 'email_atleta')->ignore($atleta->id_atleta, 'id_atleta')],
            'telefone_atleta'        => 'sometimes|nullable|string|max:20',
            'escola_atleta'          => 'sometimes|required|string|max:100',
            'serie_atleta'           => 'sometimes|nullable|string|max:20',
            'sala_atleta'            => 'sometimes|nullable|string|max:255',
            'periodo_escolar_atleta' => 'sometimes|nullable|string|max:20',
            'peso_atleta'            => 'sometimes|nullable|numeric|min:0|max:999',
            'altura_atleta'          => 'sometimes|nullable|numeric|min:0|max:9.99',
            'descricao_atleta'       => 'sometimes|nullable|string|max:1000',
        ]);

        $atleta->update($dados);

        return response()->json([
            'success' => true,
            'message' => 'Dados atualizados com sucesso.',
            'data'    => $atleta->fresh(),
        ]);
    }

    // PUT /api/v1/atleta/senha - confere a senha atual e grava a nova com Hash::make()
    public function updateSenha(Request $request)
    {
        $atleta = $request->user();

        $dados = $request->validate([
            'senha_atual' => 'required|string',
            'nova_senha'  => 'required|string|min:8|confirmed|different:senha_atual',
        ]);

        if (!Hash::check($dados['senha_atual'], $atleta->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta.',
            ], 422);
        }

        $atleta->update(['password' => Hash::make($dados['nova_senha'])]);

        // Encerra as sessões de outros aparelhos, mantendo o token atual
        $atleta->tokens()
            ->where('id', '!=', $atleta->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso.',
        ]);
    }
}
