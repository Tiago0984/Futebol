<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Meu perfil (10/11/2026; o "Perfil" do menu do usuário apontava para "#"): quem está logado muda o nome, o e-mail e
 * a foto, e troca a senha informando a atual. O nível de acesso só aparece (fora do $fillable de User: ninguém se
 * promove a administrador por aqui) e o cargo não é mudado por aqui (dono do projeto, 10/11/2026: o cargo é da
 * comissão técnica e só o administrador o define). A foto é tratada como a do atleta: quadrada, até 512 px, JPG, com nome
 * gerado em futebol/images/usuarios; a antiga gerada é apagada (as do projeto, em dash/assets/img/user, ficam).
 */
class PerfilController extends Controller
{
    public const PASTA = 'usuarios';

    public function show()
    {
        return view('admin.perfil.index', ['usuario' => Auth::guard('admin')->user()]);
    }

    public function update(Request $request)
    {
        $usuario = Auth::guard('admin')->user();

        $dados = $request->validate([
            'nome_usuario'  => 'required|string|max:255',
            'email_usuario' => ['required', 'email', 'max:255', Rule::unique('tbl_usuarios', 'email_usuario')->ignore($usuario->id_usuario, 'id_usuario')],
            'foto_usuario'  => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'remover_foto'  => 'nullable|boolean',
        ], [
            'nome_usuario.required'  => 'Informe o seu nome.',
            'email_usuario.required' => 'Informe o seu e-mail.',
            'email_usuario.email'    => 'Informe um e-mail válido.',
            'email_usuario.unique'   => 'Este e-mail já é de outro usuário do painel.',
            'foto_usuario.image'     => 'Envie uma imagem (JPG, PNG ou WebP).',
            ...TratadorDeImagem::mensagensDoEnvio(['foto_usuario']),
        ]);

        $antiga = $usuario->foto_usuario;
        // Só nome e e-mail: o cargo e o nível são definidos pelo administrador (um cargo_usuario enviado é ignorado)
        $usuario->fill(collect($dados)->only(['nome_usuario', 'email_usuario'])->all());

        if ($request->hasFile('foto_usuario')) {
            $usuario->foto_usuario = TratadorDeImagem::foto($request->file('foto_usuario')->getRealPath(), 'imagens', self::PASTA, 'foto-usuario');
        } elseif ($request->boolean('remover_foto')) {
            $usuario->foto_usuario = null;
        }

        $usuario->save();

        if ($antiga !== $usuario->foto_usuario) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA, $antiga, fn ($arquivo) => User::where('foto_usuario', $arquivo)->exists());
        }

        return redirect()->route('admin.perfil')->with('sucesso', 'Seus dados foram atualizados.');
    }

    // Senha nova com a atual conferida (bag "senha", para os erros aparecerem no bloco da senha)
    public function senha(Request $request)
    {
        $request->validateWithBag('senha', [
            'senha_atual' => 'required|current_password:admin',
            'nova_senha'  => 'required|string|min:8|confirmed|different:senha_atual',
        ], [
            'senha_atual.required'         => 'Informe a sua senha atual.',
            'senha_atual.current_password' => 'A senha atual não confere.',
            'nova_senha.required'          => 'Informe a nova senha.',
            'nova_senha.min'               => 'A nova senha precisa ter pelo menos 8 caracteres.',
            'nova_senha.confirmed'         => 'A confirmação não é igual à nova senha.',
            'nova_senha.different'         => 'A nova senha precisa ser diferente da atual.',
        ]);

        $usuario = Auth::guard('admin')->user();
        $usuario->senha_usuario = $request->input('nova_senha'); // o cast "hashed" do model grava o hash
        $usuario->save();

        // Sessão nova, como num login (a anterior, se alguém a tivesse, deixa de valer)
        $request->session()->regenerate();

        return redirect()->route('admin.perfil')->with('sucesso', 'Sua senha foi trocada.');
    }
}
