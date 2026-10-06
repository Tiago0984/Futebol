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
        h3 { margin: 0 0 8px; font-size: 1rem; }
        .card { background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .rota { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 4px; }
        .metodo { font-weight: 700; font-size: .75rem; padding: 2px 8px; border-radius: 4px; color: #fff; }
        .GET { background: #198754; } .POST { background: #0d6efd; } .PUT { background: #fd7e14; } .PATCH { background: #6f42c1; }
        .acesso { font-size: .75rem; padding: 2px 8px; border-radius: 4px; background: #e9ecef; }
        .acesso.atleta { background: #fff3cd; } .acesso.responsavel { background: #cfe2ff; } .acesso.qualquer { background: #e2d9f3; }
        code { font-family: ui-monospace, Consolas, monospace; font-size: .9rem; word-break: break-all; }
        pre { background: #1d2b24; color: #d1e7dd; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: .85rem; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; font-size: .9rem; vertical-align: top; }
        .tabela { overflow-x: auto; }
        nav ul { margin: 0; padding-left: 20px; columns: 2; }
        @media (max-width: 600px) { nav ul { columns: 1; } }
        .nota { font-size: .9rem; color: #495057; }
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

    <nav class="card">
        <h3>Conteúdo</h3>
        <ul>
            <li><a href="#padrao">Padrão das respostas, datas e paginação</a></li>
            <li><a href="#perfis">Perfis e token</a></li>
            <li><a href="#publicas">Rotas públicas</a></li>
            <li><a href="#autenticacao">Login, logout e senha</a></li>
            <li><a href="#atleta">Área do atleta</a></li>
            <li><a href="#responsavel">Área do responsável</a></li>
            <li><a href="#agenda">Agenda</a></li>
            <li><a href="#avisos">Avisos (notificações)</a></li>
            <li><a href="#erros">Erros e limites</a></li>
            <li><a href="#rotas">Todas as rotas</a></li>
        </ul>
    </nav>

    {{-- ================================================================== --}}
    <h2 id="padrao">Padrão das respostas</h2>
    <div class="card">
        <p>Todas as respostas são JSON. As respostas da escolinha têm o campo <code>success</code>: em caso de sucesso os dados vêm em <code>data</code>; em caso de erro vem uma <code>message</code> em português.</p>
<pre>{ "success": true,  "data": { ... } }
{ "success": false, "message": "Atleta não encontrado." }</pre>
        <p>Envie sempre o cabeçalho <code>Accept: application/json</code>. Os erros gerados pelo próprio Laravel (401 sem token, 422 de validação, 429) vêm só com <code>message</code> (e <code>errors</code> no 422), sem <code>success</code>; veja <a href="#erros">Erros e limites</a>.</p>
    </div>

    <div class="card">
        <h3>Datas e horários</h3>
        <p>Datas no formato ISO 8601 com o fuso de Brasília e sem milissegundos: <code>2026-10-12T18:00:00-03:00</code>. Quem converte a data (<code>new Date(...)</code>) acerta o instante; quem só recorta o texto lê a hora local.</p>
        <p>Data sem hora (dia do evento, data de nascimento) vem com a meia-noite: <code>2026-10-12T00:00:00-03:00</code>.</p>
        <p>Horários soltos vêm sem segundos, como texto: <code>"18:00"</code>. Sem horário definido: <code>null</code>.</p>
    </div>

    <div class="card">
        <h3>Paginação</h3>
        <p>As listas paginadas (agenda e avisos) recebem <code>?page=N</code> (padrão 1) e têm <strong>20 itens por página</strong>. Junto com a lista vem o bloco <code>paginacao</code>:</p>
<pre>"paginacao": { "pagina_atual": 1, "ultima_pagina": 3, "por_pagina": 20, "total": 47 }</pre>
        <p>Página além da última devolve a lista vazia, com a mesma <code>paginacao</code>.</p>
    </div>

    {{-- ================================================================== --}}
    <h2 id="perfis">Perfis e token</h2>
    <div class="card">
        <p>O app tem <strong>dois perfis de login</strong>, cada um com o próprio e-mail e senha:</p>
        <ul>
            <li><strong>atleta</strong> (padrão): vê e edita os próprios dados, vê a sua agenda e os seus avisos. Só entra com o cadastro <code>ATIVO</code>.</li>
            <li><strong>responsavel</strong>: vê os próprios dados e, de cada filho <code>ATIVO</code>, tudo o que o atleta vê, em modo leitura. Escolhe o filho pela lista de <code>/responsavel/atletas</code>. Só entra com pelo menos um filho <code>ATIVO</code>.</li>
        </ul>
        <p>O mesmo e-mail pode ser de um atleta e de um responsável; o campo <code>perfil</code> do login diz qual conta usar.</p>
        <p><strong>Token:</strong> o login devolve um token que vale <strong>30 dias</strong>. Envie em todas as rotas protegidas:</p>
<pre>Authorization: Bearer SEU_TOKEN</pre>
        <p>Depois de 30 dias, ou depois do logout, o token responde <code>401</code>: faça login de novo. O token também deixa de valer quando o atleta é inativado (ou o responsável fica sem filho ativo) e quando a senha é definida pelo link "Defina sua senha".</p>
        <p><strong>Cada token só entra na área do seu perfil:</strong> o token do atleta recebe <code>403</code> nas rotas <code>/responsavel...</code> e o do responsável recebe <code>403</code> nas rotas do atleta.</p>
    </div>

    {{-- ================================================================== --}}
    <h2 id="publicas">Rotas públicas</h2>

    @php
        $publicas = [
            ['GET', '/status', 'Verifica se a API está online'],
            ['GET', '/banners', 'Banners ativos da home, na ordem de exibição'],
            ['GET', '/categorias', 'Categorias ativas (Sub-9 a Sub-17, masculino e feminino)'],
            ['GET', '/categorias/{id}/times', 'Times ativos de uma categoria'],
            ['GET', '/noticias', 'Notícias ativas. Filtro opcional: ?categoria=Campeonatos'],
            ['GET', '/noticias/{id}', 'Notícia completa'],
            ['GET', '/campeonatos', 'Campeonatos ativos'],
            ['GET', '/campeonatos/{id}', 'Detalhes do campeonato com times e jogos (data_jogo com fuso)'],
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

    {{-- ================================================================== --}}
    <h2 id="autenticacao">Login, logout e senha</h2>

    <div class="card">
        <div class="rota"><span class="metodo POST">POST</span><code>/api/v1/auth/login</code><span class="acesso">Público</span></div>
        <p>Login de um dos perfis. <code>perfil</code> é <code>atleta</code> (padrão, quando não é enviado) ou <code>responsavel</code>. <code>device_name</code> é opcional (nome do aparelho no token).</p>
<pre>POST /api/v1/auth/login
Content-Type: application/json

{ "email": "joana@exemplo.com", "senha": "********", "perfil": "atleta", "device_name": "celular-da-joana" }</pre>
        <p>Resposta do <strong>atleta</strong> (200):</p>
<pre>{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "12|AbCdEf...",
    "perfil": "atleta",
    "atleta": {
      "id_atleta": 7, "nome_atleta": "Joana Exemplo", "email_atleta": "joana@exemplo.com",
      "numero_matricula_atleta": "A100", "foto_atleta": null
    }
  }
}</pre>
        <p>Resposta do <strong>responsável</strong> (200), com os filhos <code>ATIVO</code>:</p>
<pre>{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "13|GhIjKl...",
    "perfil": "responsavel",
    "responsavel": { "id_responsavel": 5, "nome_responsavel": "Carla Exemplo", "email_responsavel": "carla@exemplo.com" },
    "atletas": [
      { "id_atleta": 7, "nome_atleta": "Joana Exemplo", "numero_matricula_atleta": "A100",
        "foto_atleta": null, "grau_parentesco_responsavel": "Mãe" }
    ]
  }
}</pre>
        <p>Erros:</p>
<pre>401 { "success": false, "message": "E-mail ou senha inválidos." }
403 { "success": false, "message": "Cadastro não está ativo. Procure a secretaria da escolinha." }            (atleta)
403 { "success": false, "message": "Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha." }
422 { "message": "...", "errors": { "perfil": ["..."] } }                                                      (campos inválidos)
429 { "message": "Too Many Attempts." }                                                                       (limite de tentativas)</pre>
        <p class="nota">Limite: 5 tentativas por minuto por IP e 5 por minuto por e-mail e perfil (errar a senha do responsável não bloqueia o atleta do mesmo e-mail).</p>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo POST">POST</span><code>/api/v1/auth/logout</code><span class="acesso qualquer">Qualquer perfil</span></div>
        <p>Invalida só o token usado na requisição.</p>
<pre>200 { "success": true, "message": "Logout realizado com sucesso." }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo POST">POST</span><code>/api/v1/auth/esqueci-senha</code><span class="acesso">Público</span></div>
        <p>Envia ao e-mail do perfil um link para definir uma nova senha. A resposta é <strong>sempre a mesma</strong>, exista ou não o e-mail (não revela quem está cadastrado). O e-mail só sai para quem pode entrar no app (atleta <code>ATIVO</code>; responsável com filho <code>ATIVO</code>), e não sai outro para a mesma conta em menos de 60 segundos.</p>
<pre>POST /api/v1/auth/esqueci-senha
{ "email": "carla@exemplo.com", "perfil": "responsavel" }

200 {
  "success": true,
  "message": "Se o e-mail estiver cadastrado neste perfil, você vai receber um link para definir uma nova senha. O link vale por 24 horas."
}</pre>
        <p class="nota">Limite: 5 pedidos por minuto por IP e 3 a cada 10 minutos por e-mail e perfil (<code>429</code> depois disso).</p>
    </div>

    <div class="card">
        <h3>Links "Defina sua senha" (página do site, não é rota da API)</h3>
        <p>O link chega por e-mail em três situações: na <strong>aprovação da matrícula</strong> (para o atleta, se tiver e-mail, e para o responsável que ainda não tem senha), quando a secretaria usa <strong>"Reenviar convite"</strong> e no <strong>"Esqueci minha senha"</strong>. Ele abre a página <code>{{ url('/senha/{perfil}/{token}') }}?email=...</code>, onde a pessoa digita a senha nova (mínimo de 8 caracteres).</p>
        <ul>
            <li>O link vale <strong>24 horas</strong> e só pode ser usado uma vez. Vencido, a página orienta a pedir outro pelo "Esqueci minha senha" do app.</li>
            <li>Ao definir a senha, as sessões do app daquele perfil são encerradas (os tokens antigos deixam de valer). O outro perfil, mesmo com o mesmo e-mail, continua conectado.</li>
        </ul>
    </div>

    {{-- ================================================================== --}}
    <h2 id="atleta">Área do atleta</h2>
    <p class="nota">Rotas com o token do perfil <strong>atleta</strong> e o cadastro <code>ATIVO</code>. A agenda e os avisos estão nas seções <a href="#agenda">Agenda</a> e <a href="#avisos">Avisos</a>.</p>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/atleta</code><span class="acesso atleta">Atleta</span></div>
        <p>Dados do atleta logado, com o endereço, a categoria atual e os times.</p>
<pre>200 {
  "success": true,
  "data": {
    "id_atleta": 7, "nome_atleta": "Joana Exemplo", "data_nasc_atleta": "2013-06-15T00:00:00-03:00",
    "email_atleta": "joana@exemplo.com", "numero_matricula_atleta": "A100", "status_atleta": "ATIVO",
    "escola_atleta": "...", "serie_atleta": "...", "peso_atleta": "45.00", "altura_atleta": "1.55", ...,
    "endereco": { "cep_endereco": "01000-000", "rua_endereco": "...", ... },
    "categorias": [
      { "id_categoria": 5, "nome_categoria": "Sub-13",
        "pivot": { "id_atleta": 7, "id_categoria": 5,
                   "data_inicio_categoria_atleta": "2026-02-01T09:30:00-03:00",
                   "data_fim_categoria_atleta": null,
                   "data_atualizacao_categoria_atleta": "2026-02-01T09:30:00-03:00",
                   "status_categoria_atleta": "ATIVO", "observacao_categoria_atleta": null } }
    ],
    "times": [ { "id_time": 3, "nome_time": "AACJ Sub-13", "logo_time": "...", "pivot": { ... } } ]
  }
}</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><span class="metodo PATCH">PATCH</span><code>/api/v1/atleta</code><span class="acesso atleta">Atleta</span></div>
        <p>Atualiza os dados que o atleta pode mudar (pode mandar só alguns): <code>email_atleta</code> (único), <code>telefone_atleta</code>, <code>escola_atleta</code>, <code>serie_atleta</code>, <code>sala_atleta</code>, <code>periodo_escolar_atleta</code>, <code>peso_atleta</code>, <code>altura_atleta</code>, <code>descricao_atleta</code>. Nome, CPF, RG, matrícula e status só a secretaria muda.</p>
<pre>PATCH /api/v1/atleta
{ "telefone_atleta": "(11) 90000-0001" }

200 { "success": true, "message": "Dados atualizados com sucesso.", "data": { ...atleta... } }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><code>/api/v1/atleta/senha</code><span class="acesso atleta">Atleta</span></div>
        <p>Troca a senha (mínimo de 8 caracteres) e encerra as sessões dos outros aparelhos (o token atual continua valendo).</p>
<pre>{ "senha_atual": "********", "nova_senha": "********", "nova_senha_confirmation": "********" }

200 { "success": true, "message": "Senha alterada com sucesso." }
422 { "success": false, "message": "Senha atual incorreta." }</pre>
    </div>

    {{-- ================================================================== --}}
    <h2 id="responsavel">Área do responsável</h2>
    <p class="nota">Rotas com o token do perfil <strong>responsavel</strong> e pelo menos um filho <code>ATIVO</code>. Em todas as rotas de um filho, o servidor confere que o atleta é filho do responsável logado e está <code>ATIVO</code>; senão responde <code>404</code> (o mesmo para atleta de outra família, inativado ou inexistente).</p>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/responsavel</code><span class="acesso responsavel">Responsável</span></div>
        <p>Dados do responsável logado, o endereço (só leitura) e os filhos <code>ATIVO</code>. A senha nunca vem.</p>
<pre>200 {
  "success": true,
  "data": {
    "id_responsavel": 5, "nome_responsavel": "Carla Exemplo", "email_responsavel": "carla@exemplo.com",
    "cpf_responsavel": "123.456.789-09", "rg_responsavel": "...", "telefone_responsavel": null,
    "whatsapp_responsavel": "(11) 90000-0000",
    "endereco": { "cep_endereco": "...", "rua_endereco": "...", "numero_endereco": "...", "bairro_endereco": "...",
                  "complemento_endereco": null, "cidade_endereco": "...", "estado_endereco": "SP" },
    "atletas": [ { "id_atleta": 7, "nome_atleta": "Joana Exemplo", "numero_matricula_atleta": "A100",
                   "foto_atleta": null, "grau_parentesco_responsavel": "Mãe" } ]
  }
}</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><span class="metodo PATCH">PATCH</span><code>/api/v1/responsavel</code><span class="acesso responsavel">Responsável</span></div>
        <p>Atualiza só <code>email_responsavel</code> (o login; único entre responsáveis, gravado em minúsculas), <code>telefone_responsavel</code> e <code>whatsapp_responsavel</code>. Nome, CPF, RG e endereço ficam com a secretaria. Responde com os mesmos dados do GET.</p>
<pre>PATCH /api/v1/responsavel
{ "whatsapp_responsavel": "(11) 90000-0001" }

422 { "message": "...", "errors": { "email_responsavel": ["Este e-mail já está cadastrado para outro responsável."] } }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><code>/api/v1/responsavel/senha</code><span class="acesso responsavel">Responsável</span></div>
        <p>Igual à troca de senha do atleta: <code>senha_atual</code>, <code>nova_senha</code> e <code>nova_senha_confirmation</code>; encerra os outros aparelhos.</p>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/responsavel/atletas</code><span class="acesso responsavel">Responsável</span></div>
        <p>Os filhos <code>ATIVO</code>, para escolher de qual ver a agenda e os avisos. Cada um com os avisos ainda não lidos <strong>por este responsável</strong>.</p>
<pre>200 {
  "success": true,
  "data": [
    { "id_atleta": 7, "nome_atleta": "Joana Exemplo", "numero_matricula_atleta": "A100", "foto_atleta": null,
      "grau_parentesco_responsavel": "Mãe", "notificacoes_nao_lidas": 3 }
  ]
}</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/responsavel/atletas/{idAtleta}</code><span class="acesso responsavel">Responsável</span></div>
        <p>Os dados do filho, <strong>exatamente o mesmo JSON</strong> de <code>GET /api/v1/atleta</code>. Sem edição.</p>
<pre>404 { "success": false, "message": "Atleta não encontrado." }</pre>
    </div>

    <div class="card">
        <h3>Agenda e avisos do filho</h3>
        <p>As mesmas rotas do atleta, sob <code>/api/v1/responsavel/atletas/{idAtleta}</code>, com as mesmas respostas:</p>
        <ul>
            <li><code>GET .../agenda</code></li>
            <li><code>GET .../notificacoes</code>, <code>GET .../notificacoes/nao-lidas</code></li>
            <li><code>PATCH .../notificacoes/{idNotificacao}/lida</code>, <code>PATCH .../notificacoes/lidas</code></li>
        </ul>
        <p>A leitura é <strong>de cada pessoa</strong>: o que o responsável marca como lido não muda a leitura do atleta nem a do outro responsável, e a contagem de não lidos é própria.</p>
    </div>

    {{-- ================================================================== --}}
    <h2 id="agenda">Agenda</h2>

    <div class="card">
        <div class="rota">
            <span class="metodo GET">GET</span><code>/api/v1/agenda?page=1</code><span class="acesso atleta">Atleta</span>
            <code>/api/v1/responsavel/atletas/{idAtleta}/agenda?page=1</code><span class="acesso responsavel">Responsável</span>
        </div>
        <ul>
            <li>Só os eventos em que o atleta está <strong>inscrito</strong> (treinos, jogos, avaliações, reuniões...). Eventos ocultos pela secretaria não aparecem.</li>
            <li><code>situacao</code>: <code>CONFIRMADO</code> ou <code>CANCELADO</code>. Mudanças de data, horário ou local chegam pelos <a href="#avisos">avisos</a>; a agenda já mostra os dados novos.</li>
            <li><code>proximos</code>: o que ainda não terminou (inclusive o de hoje em andamento e os cancelados), do mais perto ao mais longe, <strong>paginado</strong>. No mesmo dia, evento sem horário vem por último.</li>
            <li><code>passados</code>: os <strong>3 últimos concluídos e não cancelados</strong>, do mais recente ao mais antigo; iguais em todas as páginas.</li>
            <li>Concluído: dia anterior a hoje, ou hoje com o horário de fim já passado (sem fim, vale o início; sem nenhum dos dois, só no dia seguinte).</li>
        </ul>
<pre>200 {
  "success": true,
  "data": {
    "proximos": [
      {
        "id_evento": 44,
        "titulo": "Treino Sub-13 Masculino",
        "tipo": "TREINO",
        "tipo_label": "TREINO",
        "subtipo": null,
        "descricao": null,
        "categoria": "Sub-13 Masculino",
        "data": "2026-10-12T00:00:00-03:00",
        "horario_inicio": "18:00",
        "horario_fim": "19:30",
        "inicio": "2026-10-12T18:00:00-03:00",
        "fim": "2026-10-12T19:30:00-03:00",
        "local": "Campo AACJ",
        "situacao": "CONFIRMADO",
        "jogo": null
      },
      {
        "id_evento": 80,
        "titulo": "AACJ Sub-13 x Rival FC",
        "tipo": "JOGO",
        "tipo_label": "JOGO",
        "subtipo": null,
        "descricao": null,
        "categoria": "Sub-13 Masculino",
        "data": "2026-10-15T00:00:00-03:00",
        "horario_inicio": "10:00",
        "horario_fim": null,
        "inicio": "2026-10-15T10:00:00-03:00",
        "fim": null,
        "local": "Campo AACJ",
        "situacao": "CONFIRMADO",
        "jogo": {
          "id_jogo": 12,
          "amistoso": false,
          "campeonato": { "id_campeonato": 2, "nome_campeonato": "Copa AACJ" },
          "time_casa": { "id_time": 3, "nome_time": "AACJ Sub-13", "logo_time": "aacj.png" },
          "time_visitante": { "id_time": 9, "nome_time": "Rival FC", "logo_time": "rival.png" },
          "placar": null,
          "time_do_atleta": { "id_time": 3, "nome_time": "AACJ Sub-13", "logo_time": "aacj.png" }
        }
      }
    ],
    "paginacao": { "pagina_atual": 1, "ultima_pagina": 3, "por_pagina": 20, "total": 47 },
    "passados": [ { ...mesmo formato... } ]
  }
}</pre>
        <h3>Campos do evento</h3>
        <div class="tabela">
        <table>
            <tr><th>Campo</th><th>Descrição</th></tr>
            <tr><td><code>tipo</code> / <code>tipo_label</code></td><td><code>JOGO</code>, <code>TREINO</code>, <code>CAMPEONATO</code>, <code>EVENTO</code>, <code>REUNIAO</code>, <code>CONFRATERNIZACAO</code>, <code>AVALIACAO</code>; o rótulo vem com acento (<code>REUNIÃO</code>)</td></tr>
            <tr><td><code>categoria</code></td><td>"Sub-13 Masculino", ou <code>null</code> em evento sem categoria (ex.: Treino Integrado, exame individual)</td></tr>
            <tr><td><code>data</code></td><td>Dia do evento, com fuso (meia-noite)</td></tr>
            <tr><td><code>horario_inicio</code> / <code>horario_fim</code></td><td><code>"HH:MM"</code> ou <code>null</code> ("A definir")</td></tr>
            <tr><td><code>inicio</code> / <code>fim</code></td><td>Data e hora completas com fuso; <code>null</code> quando o horário não está definido</td></tr>
            <tr><td><code>situacao</code></td><td><code>CONFIRMADO</code> ou <code>CANCELADO</code></td></tr>
            <tr><td><code>jogo</code></td><td><code>null</code> fora de jogo. No jogo: <code>amistoso</code>; <code>campeonato</code> (<code>null</code> no amistoso); <code>time_casa</code> e <code>time_visitante</code>; <code>placar</code> <code>{ "casa": 2, "visitante": 1 }</code> ou <code>null</code> se ainda não jogado; <code>time_do_atleta</code>, o time em que o atleta está escalado, ou <code>null</code> se ainda não foi escalado</td></tr>
        </table>
        </div>
    </div>

    {{-- ================================================================== --}}
    <h2 id="avisos">Avisos (notificações)</h2>
    <p class="nota">Rotas do atleta abaixo; para o responsável, as mesmas sob <code>/api/v1/responsavel/atletas/{idAtleta}</code>, com a leitura própria dele.</p>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/notificacoes?page=1</code><span class="acesso atleta">Atleta</span></div>
        <p>Os avisos do atleta, do mais novo para o mais antigo, paginados (20 por página). <code>lida</code> e <code>data_leitura</code> são de quem está lendo.</p>
<pre>200 {
  "success": true,
  "data": {
    "notificacoes": [
      {
        "id_notificacao": 310,
        "tipo": "ALTERACAO",
        "tipo_label": "Alteração",
        "titulo": "Atividade alterada",
        "mensagem": "Treino Sub-13 Masculino · seg, 12/10 · 18:00 às 19:30 · Campo B. Mudanças: local Campo A → Campo B.",
        "id_evento": 44,
        "data": "2026-10-10T09:15:00-03:00",
        "lida": false,
        "data_leitura": null
      }
    ],
    "paginacao": { "pagina_atual": 1, "ultima_pagina": 1, "por_pagina": 20, "total": 1 }
  }
}</pre>
        <p>Tipos: <code>INSCRICAO</code>, <code>REMOCAO</code>, <code>ALTERACAO</code>, <code>CANCELAMENTO</code>, <code>REATIVACAO</code> (com <code>id_evento</code>) e <code>AGENDA</code> (resumo, como "Agenda de dezembro disponível", com <code>id_evento</code> <code>null</code>). Título e mensagem ficam como foram enviados.</p>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/notificacoes/nao-lidas</code><span class="acesso atleta">Atleta</span></div>
<pre>200 { "success": true, "data": { "nao_lidas": 3 } }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PATCH">PATCH</span><code>/api/v1/notificacoes/{idNotificacao}/lida</code><span class="acesso atleta">Atleta</span></div>
        <p>Marca um aviso como lido e devolve o aviso atualizado. Marcar de novo mantém a data da primeira leitura. Aviso de outro atleta, ou inexistente: 404.</p>
<pre>200 { "success": true, "data": { "id_notificacao": 310, ..., "lida": true, "data_leitura": "2026-10-10T12:00:00-03:00" } }
404 { "success": false, "message": "Notificação não encontrada." }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PATCH">PATCH</span><code>/api/v1/notificacoes/lidas</code><span class="acesso atleta">Atleta</span></div>
        <p>Marca todos os avisos não lidos como lidos; devolve quantos mudaram.</p>
<pre>200 { "success": true, "data": { "marcadas": 3 } }</pre>
    </div>

    {{-- ================================================================== --}}
    <h2 id="erros">Erros e limites</h2>
    <div class="tabela">
    <table>
        <tr><th>Código</th><th>Quando</th><th>Corpo</th></tr>
        <tr><td>200</td><td>Sucesso</td><td><code>{ "success": true, ... }</code></td></tr>
        <tr><td>401</td><td>Login com e-mail ou senha errados</td><td><code>{ "success": false, "message": "E-mail ou senha inválidos." }</code></td></tr>
        <tr><td>401</td><td>Rota protegida sem token, com token inválido, vencido (30 dias) ou já encerrado</td><td><code>{ "message": "Unauthenticated." }</code></td></tr>
        <tr><td>403</td><td>Atleta sem cadastro ativo; responsável sem filho ativo (os tokens dele são apagados)</td><td><code>{ "success": false, "message": "..." }</code></td></tr>
        <tr><td>403</td><td>Token de um perfil na área do outro</td><td><code>{ "success": false, "message": "Esta área é do perfil responsável. Entre no app como responsável." }</code></td></tr>
        <tr><td>404</td><td>Filho que não é do responsável, inativado ou inexistente; aviso que não é daquele atleta</td><td><code>{ "success": false, "message": "Atleta não encontrado." }</code> / <code>"Notificação não encontrada."</code></td></tr>
        <tr><td>405</td><td>Método não aceito na rota (ex.: <code>PATCH</code> nos dados do filho)</td><td>Padrão do Laravel</td></tr>
        <tr><td>422</td><td>Dados inválidos. As mensagens de validação padrão vêm em inglês; mostre uma mensagem própria no app</td><td><code>{ "message": "...", "errors": { "campo": ["..."] } }</code></td></tr>
        <tr><td>429</td><td>Limite de tentativas (login e esqueci-senha); aguarde e tente de novo</td><td><code>{ "message": "Too Many Attempts." }</code></td></tr>
    </table>
    </div>

    <div class="card" style="margin-top:12px">
        <h3>Limites de tentativa</h3>
        <ul>
            <li><code>POST /auth/login</code>: 5 por minuto por IP e 5 por minuto por e-mail e perfil.</li>
            <li><code>POST /auth/esqueci-senha</code>: 5 por minuto por IP e 3 a cada 10 minutos por e-mail e perfil.</li>
        </ul>
    </div>

    {{-- ================================================================== --}}
    <h2 id="rotas">Todas as rotas</h2>

    @php
        $todas = [
            ['POST',  '/auth/login', 'Público'],
            ['POST',  '/auth/esqueci-senha', 'Público'],
            ['POST',  '/auth/logout', 'Qualquer perfil'],
            ['GET',   '/atleta', 'Atleta'],
            ['PUT',   '/atleta', 'Atleta'],
            ['PATCH', '/atleta', 'Atleta'],
            ['PUT',   '/atleta/senha', 'Atleta'],
            ['GET',   '/agenda', 'Atleta'],
            ['GET',   '/notificacoes', 'Atleta'],
            ['GET',   '/notificacoes/nao-lidas', 'Atleta'],
            ['PATCH', '/notificacoes/{idNotificacao}/lida', 'Atleta'],
            ['PATCH', '/notificacoes/lidas', 'Atleta'],
            ['GET',   '/responsavel', 'Responsável'],
            ['PUT',   '/responsavel', 'Responsável'],
            ['PATCH', '/responsavel', 'Responsável'],
            ['PUT',   '/responsavel/senha', 'Responsável'],
            ['GET',   '/responsavel/atletas', 'Responsável'],
            ['GET',   '/responsavel/atletas/{idAtleta}', 'Responsável'],
            ['GET',   '/responsavel/atletas/{idAtleta}/agenda', 'Responsável'],
            ['GET',   '/responsavel/atletas/{idAtleta}/notificacoes', 'Responsável'],
            ['GET',   '/responsavel/atletas/{idAtleta}/notificacoes/nao-lidas', 'Responsável'],
            ['PATCH', '/responsavel/atletas/{idAtleta}/notificacoes/{idNotificacao}/lida', 'Responsável'],
            ['PATCH', '/responsavel/atletas/{idAtleta}/notificacoes/lidas', 'Responsável'],
        ];
    @endphp

    <div class="tabela">
    <table>
        <tr><th>Método</th><th>Rota</th><th>Acesso</th></tr>
        @foreach ($publicas as [$metodo, $rota])
            <tr><td><span class="metodo {{ $metodo }}">{{ $metodo }}</span></td><td><code>/api/v1{{ $rota }}</code></td><td>Público</td></tr>
        @endforeach
        @foreach ($todas as [$metodo, $rota, $acesso])
            <tr><td><span class="metodo {{ $metodo }}">{{ $metodo }}</span></td><td><code>/api/v1{{ $rota }}</code></td><td>{{ $acesso }}</td></tr>
        @endforeach
    </table>
    </div>

</main>

</body>
</html>
