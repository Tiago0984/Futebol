<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Senha do App — Escolinha de Futebol AACJ</title>
    <link rel="stylesheet" href="{{ asset('coderatech/css/estilo.css') }}">
    <style>
        .campo-senha { margin-bottom: 14px; }
        .campo-senha label { display: block; font-size: 13px; color: #666; margin-bottom: 4px; }
        .campo-senha input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; }
        .erro-senha { color: #c00; font-size: 13px; margin-top: 4px; }
    </style>
</head>

<body>

    <div class="header">
        <h1>ESCOLINHA DE FUTEBOL AACJ</h1>
        <p>Senha do app — perfil {{ $perfil === 'responsavel' ? 'responsável' : 'atleta' }}</p>
    </div>

    @if($definida ?? false)
        <div class="sucesso">
            <h2>✅ Senha definida!</h2>
            <p>Agora é só abrir o app, escolher o perfil <strong>{{ $perfil === 'responsavel' ? 'responsável' : 'atleta' }}</strong>
                e entrar com o seu e-mail e a senha nova.</p>
        </div>

    @elseif(! $linkValido)
        <div class="ja-assinado">
            <h2>ℹ️ Link inválido ou vencido</h2>
            <p>Este link não vale mais: ele dura 24 horas e só pode ser usado uma vez.<br>
                Para receber outro, abra o app e use <strong>"Esqueci minha senha"</strong>.</p>
        </div>

    @else
        <div class="card">
            <h2>🔒 Defina sua senha</h2>
            <form action="{{ route('senha.salvar', $perfil) }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="campo-senha">
                    <label>E-mail</label>
                    <input type="email" value="{{ $email }}" disabled>
                </div>
                <div class="campo-senha">
                    <label for="senha">Nova senha (mínimo de 8 caracteres)</label>
                    <input type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password">
                    @error('senha')<div class="erro-senha">{{ $message }}</div>@enderror
                </div>
                <div class="campo-senha">
                    <label for="senha_confirmation">Repita a senha</label>
                    <input type="password" id="senha_confirmation" name="senha_confirmation" required minlength="8" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-assinar">SALVAR SENHA</button>
            </form>
        </div>

        <div class="aviso">
            O link vale por 24 horas. Se ele vencer, abra o app e use "Esqueci minha senha" para receber outro.
        </div>
    @endif

</body>

</html>
