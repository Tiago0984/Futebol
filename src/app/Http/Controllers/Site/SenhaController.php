<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Página do link "Defina sua senha" / "Esqueci minha senha" do app (Fase 9). O link leva o perfil, o token
 * e o e-mail. Link vencido, já usado ou com e-mail que não existe: a mesma mensagem (não revela quem
 * está cadastrado) e a orientação de pedir outro pelo "Esqueci minha senha" do app.
 */
class SenhaController extends Controller
{
    private const MODELS = [
        'atleta'      => Atleta::class,
        'responsavel' => Responsavel::class,
    ];

    // GET /senha/{perfil}/{token}?email=...
    public function show(Request $request, string $perfil, string $token)
    {
        $email = (string) $request->query('email');
        $model = self::MODELS[$perfil];
        $usuario = $model::porEmail($email);

        return view('site.senha.definir', [
            'perfil'      => $perfil,
            'token'       => $token,
            'email'       => $email,
            'linkValido'  => $usuario && $model::broker()->tokenExists($usuario, $token),
        ]);
    }

    // POST /senha/{perfil}
    public function store(Request $request, string $perfil)
    {
        $dados = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'senha' => 'required|string|min:8|confirmed',
        ], [
            'senha.min'       => 'A senha precisa ter pelo menos 8 caracteres.',
            'senha.confirmed' => 'A confirmação não é igual à senha.',
        ]);
        $model = self::MODELS[$perfil];

        // O broker confere o token (e a validade de 24 horas), chama o callback e apaga o token do link
        $resultado = $model::broker()->reset(
            [$model::COLUNA_EMAIL => $dados['email'], 'token' => $dados['token'], 'password' => $dados['senha']],
            fn ($usuario, $senha) => $usuario->definirSenha($senha),
        );

        if ($resultado !== Password::PASSWORD_RESET) {
            return redirect()->route('senha.definir', ['perfil' => $perfil, 'token' => $dados['token'], 'email' => $dados['email']]);
        }

        return redirect()->route('senha.definida')->with('senha_definida', $perfil);
    }

    // GET /senha/definida
    public function definida(Request $request)
    {
        if (! $perfil = $request->session()->get('senha_definida')) {
            return redirect()->route('home');
        }

        return view('site.senha.definir', ['perfil' => $perfil, 'definida' => true]);
    }
}
