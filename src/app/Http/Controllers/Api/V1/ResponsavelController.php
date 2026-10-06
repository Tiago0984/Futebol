<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Responsavel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Área do responsável no app (Fase 9). Nome, CPF e RG ficam com a secretaria (painel admin), como no
 * atleta; o endereço também (o app só mostra). O responsável edita e-mail (login, único entre
 * responsáveis), telefone e WhatsApp.
 */
class ResponsavelController extends Controller
{
    // GET /api/v1/responsavel - dados do responsável autenticado e os filhos ATIVO
    public function show(Request $request)
    {
        $responsavel = $request->user()->load('endereco');

        return response()->json([
            'success' => true,
            'data'    => self::dadosBasicos($responsavel) + [
                'cpf_responsavel'      => $responsavel->cpf_responsavel,
                'rg_responsavel'       => $responsavel->rg_responsavel,
                'telefone_responsavel' => $responsavel->telefone_responsavel,
                'whatsapp_responsavel' => $responsavel->whatsapp_responsavel,
                'endereco'             => $responsavel->endereco?->only([
                    'cep_endereco', 'rua_endereco', 'numero_endereco', 'bairro_endereco',
                    'complemento_endereco', 'cidade_endereco', 'estado_endereco',
                ]),
                'atletas'              => self::atletasDoResponsavel($responsavel),
            ],
        ]);
    }

    // PUT/PATCH /api/v1/responsavel - atualiza só e-mail, telefone e WhatsApp ("sometimes": envio parcial)
    public function update(Request $request)
    {
        $responsavel = $request->user();

        // Normaliza antes do unique (o model grava em minúsculas e sem espaços)
        if ($request->has('email_responsavel')) {
            $request->merge(['email_responsavel' => Responsavel::normalizarEmail($request->input('email_responsavel'))]);
        }

        $dados = $request->validate([
            'email_responsavel'    => ['sometimes', 'required', 'email', 'max:150',
                Rule::unique('tbl_responsavel', 'email_responsavel')->ignore($responsavel->id_responsavel, 'id_responsavel')],
            'telefone_responsavel' => 'sometimes|nullable|string|max:20',
            'whatsapp_responsavel' => 'sometimes|required|string|max:20',
        ], [
            'email_responsavel.unique' => 'Este e-mail já está cadastrado para outro responsável.',
        ]);

        $responsavel->update($dados);

        return $this->show($request);
    }

    // PUT /api/v1/responsavel/senha - confere a senha atual e grava a nova com Hash::make()
    public function updateSenha(Request $request)
    {
        $responsavel = $request->user();

        $dados = $request->validate([
            'senha_atual' => 'required|string',
            'nova_senha'  => 'required|string|min:8|confirmed|different:senha_atual',
        ]);

        if (!Hash::check($dados['senha_atual'], $responsavel->senha_responsavel)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta.',
            ], 422);
        }

        $responsavel->forceFill(['senha_responsavel' => Hash::make($dados['nova_senha'])])->save();

        // Encerra as sessões de outros aparelhos, mantendo o token atual
        $responsavel->tokens()
            ->where('id', '!=', $responsavel->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso.',
        ]);
    }

    // Também usados na resposta do login
    public static function dadosBasicos(Responsavel $responsavel): array
    {
        return [
            'id_responsavel'    => $responsavel->id_responsavel,
            'nome_responsavel'  => $responsavel->nome_responsavel,
            'email_responsavel' => $responsavel->email_responsavel,
        ];
    }

    public static function atletasDoResponsavel(Responsavel $responsavel): array
    {
        return $responsavel->atletasAtivos()->orderBy('nome_atleta')->get()
            ->map(fn ($atleta) => [
                'id_atleta'                   => $atleta->id_atleta,
                'nome_atleta'                 => $atleta->nome_atleta,
                'numero_matricula_atleta'     => $atleta->numero_matricula_atleta,
                'foto_atleta'                 => $atleta->foto_atleta,
                'grau_parentesco_responsavel' => $atleta->pivot->grau_parentesco_responsavel,
            ])->all();
    }
}
