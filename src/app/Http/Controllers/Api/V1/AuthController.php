<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResponsavelComAtletaAtivo;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Services\RecuperaSenhaPorCodigo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        // Serializa a emissão de sessões com a troca de senha e a revogação dos tokens da conta.
        return DB::transaction(fn () => $this->loginDaConta($dados, $perfil));
    }

    private function loginDaConta(array $dados, string $perfil)
    {
        // 2. Localizar o atleta ou o responsável pelo e-mail
        $model = self::MODELS[$perfil];
        $usuario = $model::where($model::COLUNA_EMAIL, trim($dados['email']))->lockForUpdate()->first();

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

    // POST /api/v1/auth/esqueci-senha - envia um código de 4 dígitos para o e-mail do perfil
    // A resposta é sempre a mesma: não revela se o e-mail está cadastrado
    public function esqueciSenha(Request $request, RecuperaSenhaPorCodigo $recuperacao)
    {
        $dados = $request->validate([
            'email'  => 'required|string|email|max:255',
            'perfil' => 'nullable|in:atleta,responsavel',
        ]);
        $recuperacao->solicitar($dados['email'], $dados['perfil'] ?? 'atleta');

        return response()->json([
            'success' => true,
            'message' => 'Se o e-mail estiver cadastrado, enviaremos um código de verificação.',
        ]);
    }

    public function validarCodigo(Request $request, RecuperaSenhaPorCodigo $recuperacao)
    {
        $erro = 'Código inválido ou expirado.';
        $dados = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'perfil' => ['nullable', 'in:atleta,responsavel'],
            'codigo' => ['bail', 'required', 'string', 'regex:/^[0-9]{4}$/D'],
        ], [
            'codigo.required' => $erro, 'codigo.string' => $erro, 'codigo.regex' => $erro,
        ]);

        $token = $recuperacao->validar($dados['email'], $dados['perfil'] ?? 'atleta', $dados['codigo']);
        if (! $token) {
            throw ValidationException::withMessages(['codigo' => $erro]);
        }

        return response()->json(['success' => true, 'data' => ['reset_token' => $token]]);
    }

    public function redefinirSenha(Request $request, RecuperaSenhaPorCodigo $recuperacao)
    {
        $erroToken = 'Token de redefinição inválido ou expirado.';
        $dados = $request->validate([
            'reset_token' => ['bail', 'required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'nova_senha' => ['bail', 'required', 'string', 'min:8', 'confirmed'],
        ], [
            'reset_token.required' => $erroToken, 'reset_token.string' => $erroToken, 'reset_token.regex' => $erroToken,
            'nova_senha.required' => 'Informe a nova senha.',
            'nova_senha.string' => 'A nova senha deve ser um texto.',
            'nova_senha.min' => 'A nova senha precisa ter pelo menos 8 caracteres.',
            'nova_senha.confirmed' => 'A confirmação não é igual à nova senha.',
        ]);

        if (! $recuperacao->redefinir($dados['reset_token'], $dados['nova_senha'])) {
            throw ValidationException::withMessages(['reset_token' => $erroToken]);
        }

        return response()->json(['success' => true, 'message' => 'Senha redefinida com sucesso. Faça login novamente.']);
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
            'foto_url'                => $atleta->urlFoto(),
        ];
    }
}
