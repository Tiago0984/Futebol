<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // Exibe a tela de login
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dash');
        }

        return view('admin.auth.loggin');
    }

    // Processa o login
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login    = $request->input('login');
        $password = $request->input('password');

        // Tenta logar por e-mail
        $credenciais = ['email_usuario' => $login, 'password' => $password];

        if (Auth::guard('admin')->attempt($credenciais, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dash'));
        }

        return back()->withErrors([
            'login' => 'Credenciais incorretas.',
        ])->onlyInput('login');
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
