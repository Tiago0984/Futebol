<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>API – Escolinha de Futebol</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6f5; color: #1d2b24; line-height: 1.5; }
        header { background: #0f5132; color: #fff; padding: 32px 16px; }
        header h1 { margin: 0 0 4px; font-size: 1.8rem; }
        header p { margin: 0; opacity: .85; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        h2 { margin: 32px 0 12px; font-size: 1.25rem; border-bottom: 2px solid #0f5132; padding-bottom: 4px; }
        .card { background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .rota { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 4px; }
        .metodo { font-weight: 700; font-size: .75rem; padding: 2px 8px; border-radius: 4px; color: #fff; }
        .GET { background: #198754; } .POST { background: #0d6efd; } .PUT { background: #fd7e14; } .PATCH { background: #6f42c1; }
        .acesso { font-size: .75rem; padding: 2px 8px; border-radius: 4px; background: #e9ecef; }
        .acesso.token { background: #fff3cd; }
        code { font-family: ui-monospace, Consolas, monospace; font-size: .9rem; word-break: break-all; }
        pre { background: #1d2b24; color: #d1e7dd; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: .85rem; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; font-size: .9rem; }
    </style>
</head>

<body>

<header>
    <h1>API – Escolinha de Futebol</h1>

    <p>
        Documentação da API utilizada pelo aplicativo. Base: <code>{{ url('/api/v1') }}</code>
    </p>
</header>

<main>

    <h2>Padrão das respostas</h2>
    <div class="card">
        <p>Todas as respostas são JSON com o campo <code>success</code>. Em caso de sucesso os dados vêm em <code>data</code>; em caso de erro vem uma <code>message</code>.</p>
        <p>Envie sempre o cabeçalho <code>Accept: application/json</code>.</p>
    </div>

    <h2>Rotas públicas</h2>

    @php
        $publicas = [
            ['GET', '/status', 'Verifica se a API está online'],
            ['GET', '/banners', 'Banners ativos da home, na ordem de exibição'],
            ['GET', '/categorias', 'Categorias ativas (Sub-9 a Sub-17, masculino e feminino)'],
            ['GET', '/categorias/{id}/times', 'Times ativos de uma categoria'],
            ['GET', '/noticias', 'Notícias ativas. Filtro opcional: ?categoria=Campeonatos'],
            ['GET', '/noticias/{id}', 'Notícia completa'],
            ['GET', '/campeonatos', 'Campeonatos ativos'],
            ['GET', '/campeonatos/{id}', 'Detalhes do campeonato com times e jogos'],
            ['POST', '/auth/login', 'Login do atleta (limite de 5 tentativas por minuto)'],
        ];

        $protegidas = [
            ['GET', '/atleta', 'Dados do atleta logado'],
            ['PUT', '/atleta', 'Atualiza dados do perfil'],
            ['PATCH', '/atleta', 'Atualização parcial do perfil'],
            ['PUT', '/atleta/senha', 'Altera a senha'],
            ['POST', '/auth/logout', 'Invalida o token atual'],
        ];
    @endphp

    @foreach ($publicas as [$metodo, $rota, $descricao])
        <div class="card">
            <div class="rota">
                <span class="metodo {{ $metodo }}">{{ $metodo }}</span>
                <code>/api/v1{{ $rota }}</code>
                <span class="acesso">Público</span>
            </div>
            <div>{{ $descricao }}</div>
        </div>
    @endforeach

    <h2>Autenticação</h2>
    <div class="card">
        <p>O atleta faz login com e-mail e senha e recebe um token. Só atletas com status <code>ATIVO</code> conseguem entrar.</p>
<pre>POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "atleta@exemplo.com",
  "senha": "********",
  "device_name": "celular-do-atleta"
}</pre>
        <p>Nas rotas protegidas, envie o token no cabeçalho:</p>
<pre>Authorization: Bearer SEU_TOKEN</pre>
    </div>

    <h2>Rotas com token</h2>

    @foreach ($protegidas as [$metodo, $rota, $descricao])
        <div class="card">
            <div class="rota">
                <span class="metodo {{ $metodo }}">{{ $metodo }}</span>
                <code>/api/v1{{ $rota }}</code>
                <span class="acesso token">Token</span>
            </div>
            <div>{{ $descricao }}</div>
        </div>
    @endforeach

    <h2>Campos editáveis pelo atleta</h2>
    <div class="card">
        <p><code>email_atleta</code>, <code>telefone_atleta</code>, <code>escola_atleta</code>, <code>serie_atleta</code>, <code>sala_atleta</code>, <code>periodo_escolar_atleta</code>, <code>peso_atleta</code>, <code>altura_atleta</code>, <code>descricao_atleta</code>.</p>
        <p>Nome, CPF, RG, matrícula e status só podem ser alterados pela secretaria no painel.</p>
        <p>Troca de senha:</p>
<pre>{
  "senha_atual": "********",
  "nova_senha": "********",
  "nova_senha_confirmation": "********"
}</pre>
    </div>

    <h2>Códigos de resposta</h2>
    <table>
        <tr><th>Código</th><th>Significado</th></tr>
        <tr><td>200</td><td>Sucesso</td></tr>
        <tr><td>401</td><td>Sem token, token inválido ou e-mail/senha incorretos</td></tr>
        <tr><td>403</td><td>Atleta sem cadastro ativo</td></tr>
        <tr><td>404</td><td>Registro não encontrado</td></tr>
        <tr><td>422</td><td>Dados inválidos (veja o campo <code>errors</code>)</td></tr>
        <tr><td>429</td><td>Muitas tentativas de login – aguarde 1 minuto</td></tr>
    </table>

</main>

</body>
</html>
