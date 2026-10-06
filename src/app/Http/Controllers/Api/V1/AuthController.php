<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResponsavelComAtletaAtivo;
use App\Models\Atleta;
use App\Models\Responsavel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Perfis do app (Fase 9): cada um com o próprio e-mail e senha
    private const MODELS = [
        'atleta'      => Atleta::class,
        'responsavel' => Responsavel::class,
    ];

    // POST /api/v1/auth/login - valida e-mail/senha do perfil escolhido e devolve um token
    // Sem "perfil", vale atleta (o app atual continua funcionando igual)
    public function login(Request $request)
    {
        // 1. Validar e-mail, senha e perfil recebidos
        $dados = $request->validate([
            'email'       => 'required|email',
            'senha'       => 'required|string',
            'perfil'      => 'nullable|in:atleta,responsavel',
            'device_name' => 'nullable|string|max:100',
        ]);
        $perfil = $dados['perfil'] ?? 'atleta';

        // 2. Localizar o atleta ou o responsável pelo e-mail
        $usuario = self::MODELS[$perfil]::porEmail($dados['email']);

        // 3. Comparar a senha informada com o hash salvo (password no atleta, senha_responsavel no responsável)
        if (!$usuario || !$usuario->getAuthPassword() || !Hash::check($dados['senha'], $usuario->getAuthPassword())) {
            return response()->json([
                'success' => false,
                'message' => 'E-mail ou senha inválidos.',
            ], 401);
        }

        // 4. Confirmar que pode entrar: atleta ATIVO; responsável com algum filho ATIVO
        if (!$usuario->podeEntrarNoApp()) {
            return response()->json([
                'success' => false,
                'message' => $perfil === 'atleta'
                    ? 'Cadastro não está ativo. Procure a secretaria da escolinha.'
                    : ResponsavelComAtletaAtivo::MENSAGEM,
            ], 403);
        }

        // 5. Gerar um token usando createToken(), válido por 30 dias (expires_at; o Sanctum recusa depois)
        $nomeToken = $dados['device_name'] ?? 'app';

        $token = $usuario
            ->createToken($nomeToken, ['*'], now()->addDays(Atleta::VALIDADE_TOKEN_DIAS))
            ->plainTextToken;

        // 6. Retornar o token, o perfil e os dados básicos de quem entrou em JSON
        return response()->json([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'data' => ['token' => $token, 'perfil' => $perfil] + ($perfil === 'atleta'
                ? ['atleta' => $this->dadosDoAtleta($usuario)]
                : [
                    'responsavel' => ResponsavelController::dadosBasicos($usuario),
                    'atletas'     => ResponsavelController::atletasDoResponsavel($usuario),
                ]),
        ]);
    }

    // POST /api/v1/auth/esqueci-senha - envia o link de 24 horas para o e-mail do perfil
    // A resposta é sempre a mesma: não revela se o e-mail está cadastrado
    public function esqueciSenha(Request $request)
    {
        $dados = $request->validate([
            'email'  => 'required|email',
            'perfil' => 'nullable|in:atleta,responsavel',
        ]);
        $model = self::MODELS[$dados['perfil'] ?? 'atleta'];

        $usuario = $model::porEmail($dados['email']);

        // Só quem pode entrar no app; e não gera outro link para a mesma conta em menos de 60 segundos
        if ($usuario && $usuario->podeEntrarNoApp()
            && ! $model::broker()->getRepository()->recentlyCreatedToken($usuario)) {
            $usuario->enviarLinkDeSenha(convite: false); // falha no envio fica no log
        }

        return response()->json([
            'success' => true,
            'message' => 'Se o e-mail estiver cadastrado neste perfil, você vai receber um link para definir uma nova senha. O link vale por 24 horas.',
        ]);
    }

    // POST /api/v1/auth/logout - invalida apenas o token usado nesta requisição (qualquer perfil)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    private function dadosDoAtleta(Atleta $atleta): array
    {
        return [
            'id_atleta'               => $atleta->id_atleta,
            'nome_atleta'             => $atleta->nome_atleta,
            'email_atleta'            => $atleta->email_atleta,
            'numero_matricula_atleta' => $atleta->numero_matricula_atleta,
            'foto_atleta'             => $atleta->foto_atleta,
        ];
    }
}
