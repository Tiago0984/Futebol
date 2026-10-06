<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // POST /api/v1/auth/login - valida e-mail/senha do atleta e devolve um token
    public function login(Request $request)
    {
        // 1. Validar e-mail e senha recebidos
        $dados = $request->validate([
            'email'       => 'required|email',
            'senha'       => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        // 2. Localizar o atleta pelo e-mail
        $atleta = Atleta::where('email_atleta', $dados['email'])->first();

        // 3. Comparar a senha informada com o hash salvo em password
        if (!$atleta || !$atleta->password || !Hash::check($dados['senha'], $atleta->password)) {
            return response()->json([
                'success' => false,
                'message' => 'E-mail ou senha inválidos.',
            ], 401);
        }

        // 4. Confirmar que o atleta está ATIVO
        if (strtoupper($atleta->status_atleta) !== 'ATIVO') {
            return response()->json([
                'success' => false,
                'message' => 'Cadastro não está ativo. Procure a secretaria da escolinha.',
            ], 403);
        }

        // 5. Gerar um token usando createToken(), válido por 30 dias (expires_at; o Sanctum recusa depois)
        $nomeToken = $dados['device_name'] ?? 'app';

        $token = $atleta
            ->createToken($nomeToken, ['*'], now()->addDays(Atleta::VALIDADE_TOKEN_DIAS))
            ->plainTextToken;

        // 6. Retornar o token e os dados básicos do atleta em JSON
        return response()->json([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'data' => [
                'token'  => $token,
                'atleta' => [
                    'id_atleta'               => $atleta->id_atleta,
                    'nome_atleta'             => $atleta->nome_atleta,
                    'email_atleta'            => $atleta->email_atleta,
                    'numero_matricula_atleta' => $atleta->numero_matricula_atleta,
                    'foto_atleta'             => $atleta->foto_atleta,
                ],
            ],
        ]);
    }

    // POST /api/v1/auth/logout - invalida apenas o token usado nesta requisição
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
