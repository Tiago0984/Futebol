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
        .ficha th { width: 160px; white-space: nowrap; }
        .ficha th, .ficha td { border-bottom: 1px solid #eef1ef; }
        .param { font-size: .85rem; color: #495057; }
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

    <div class="card">
        <h3>Identificação da API</h3>
        <table class="ficha">
            <tr><th>Nome</th><td>API da Escolinha de Futebol AACJ (Associação Atlética Cohab Jucelino e Adjacência)</td></tr>
            <tr><th>Uso</th><td>Aplicativo do atleta e do responsável: dados, agenda de treinos e jogos, avisos; dados públicos do site</td></tr>
            <tr><th>Versão</th><td><strong>v1</strong> (todas as rotas começam com <code>/api/v1</code>)</td></tr>
            <tr><th>URL base</th><td><code>{{ url('/api/v1') }}</code></td></tr>
            <tr><th>Formato</th><td>JSON (envie <code>Accept: application/json</code>; corpo das requisições em <code>application/json</code>)</td></tr>
            <tr><th>Autenticação</th><td>Token Bearer (Laravel Sanctum), obtido em <code>POST /api/v1/auth/login</code> e enviado em <code>Authorization: Bearer SEU_TOKEN</code>; vale 30 dias. Rotas marcadas como <strong>Público</strong> não pedem token.</td></tr>
        </table>
    </div>

    <nav class="card">
        <h3>Conteúdo</h3>
        <ul>
            <li><a href="#padrao">Padrão das respostas, datas e paginação</a></li>
            <li><a href="#perfis">Perfis e token</a></li>
            <li><a href="#publicas">Rotas públicas</a></li>
            <li><a href="#campeonatos">Campeonatos (categorias, fase e classificação)</a></li>
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
        <p>Todas as respostas são JSON e têm o campo <code>success</code>: em caso de sucesso os dados vêm em <code>data</code>; em caso de erro (qualquer um: 401, 403, 404, 405, 422, 429, 500) vem uma <code>message</code> em português, que o app pode mostrar como está.</p>
<pre>{ "success": true,  "data": { ... } }
{ "success": false, "message": "Atleta não encontrado." }</pre>
        <p>No <code>422</code> (dados inválidos) vem também <code>errors</code>, com as mensagens de cada campo; <code>message</code> é a primeira delas. Envie o cabeçalho <code>Accept: application/json</code>. Veja <a href="#erros">Erros e limites</a>.</p>
    </div>

    <div class="card">
        <h3>Imagens</h3>
        <p>Os campos de imagem trazem o nome do arquivo (<code>logo_time</code>, <code>logo_evento</code>, <code>banner_evento</code>, <code>foto_banner</code>, <code>foto_noticia</code>, <code>foto_atleta</code>) e, ao lado, o <strong>endereço completo</strong> para mostrar no app: <code>logo_url</code>, <code>banner_url</code> ou <code>foto_url</code>. Sem imagem: <code>null</code>.</p>
<pre>"logo_time": "aacj.png",
"logo_url": "https://coderatech.tipi5.smpsistema.com.br/AACJ/futebol/images/team/aacj.png"</pre>
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
            ['GET', '/status', 'Verifica se a API está online', '—'],
            ['GET', '/banners', 'Banners ativos da home, na ordem de exibição', '—'],
            ['GET', '/categorias', 'Categorias ativas (Sub-9 a Sub-17, masculino e feminino)', '—'],
            ['GET', '/categorias/{id}/times', 'Times ativos de uma categoria', '{id}: id da categoria'],
            ['GET', '/noticias', 'Notícias ativas. Filtro opcional: ?categoria=Campeonatos', 'categoria? (filtro pela categoria da notícia)'],
            ['GET', '/noticias/{id}', 'Notícia completa', '{id}: id da notícia'],
            ['GET', '/campeonatos', 'Campeonatos ativos, com as categorias de cada um (ver "Campeonatos")', '—'],
            ['GET', '/campeonatos/{id}', 'Detalhes do campeonato: categorias, times, jogos (com fase e categoria; data_jogo com fuso) e a classificação por categoria e grupo (ver "Campeonatos")', '{id}: id do campeonato'],
        ];
    @endphp

    @foreach ($publicas as [$metodo, $rota, $descricao, $parametros])
        <div class="card">
            <div class="rota">
                <span class="metodo {{ $metodo }}">{{ $metodo }}</span>
                <code>/api/v1{{ $rota }}</code>
                <span class="acesso">Público</span>
            </div>
            <div>{{ $descricao }}</div>
            <div class="param">Parâmetros: <code>{{ $parametros }}</code></div>
        </div>
    @endforeach

    {{-- ================================================================== --}}
    <h2 id="campeonatos">Campeonatos</h2>
    <p class="nota">Um campeonato pode ter várias categorias (ex.: Sub-11, Sub-15 e Sub-17). Cada jogo é de uma categoria e de uma fase. A classificação é por categoria e grupo (chave) e conta só a fase de grupos.</p>

    <div class="card">
        <div class="tabela">
        <table>
            <tr><th>Campo</th><th>Descrição</th></tr>
            <tr><td><code>categorias</code></td><td>As categorias do campeonato: <code>[{ "id_categoria": 2, "nome_categoria": "Sub-15", "sexo_categoria": "M", "rotulo": "Sub-15 Masculino" }]</code>. O campo antigo <code>categoria</code> continua (uma delas), para não quebrar o app; use <code>categorias</code></td></tr>
            <tr><td><code>local_evento</code>, <code>organizador_campeonato</code>, <code>logo_evento</code> / <code>logo_url</code>, <code>banner_evento</code> / <code>banner_url</code></td><td>Opcionais: podem vir <code>null</code>. <code>local_evento</code> é só o local principal; cada jogo tem o seu, em <code>jogos[].local_jogo</code> (os jogos de um campeonato podem ser em locais diferentes)</td></tr>
            <tr><td><code>jogos[].fase_jogo</code> / <code>jogos[].fase</code></td><td>A fase do jogo: <code>GRUPOS</code>, <code>REPESCAGEM</code>, <code>DEZESSEIS_AVOS</code>, <code>OITAVAS</code>, <code>QUARTAS</code>, <code>SEMIFINAL</code>, <code>TERCEIRO</code>, <code>FINAL</code>; <code>fase</code> é o rótulo ("Fase de grupos", "Disputa de 3º lugar")</td></tr>
            <tr><td><code>jogos[].rodada_jogo</code></td><td>A rodada na fase de grupos (<code>null</code> sem rodada e no mata-mata)</td></tr>
            <tr><td><code>jogos[].placar_time_casa_jogos</code> / <code>_visitante_</code></td><td>Placar do <strong>tempo normal</strong> (<code>null</code> = ainda não jogado)</td></tr>
            <tr><td><code>jogos[].prorrogacao_time_casa_jogos</code> / <code>_visitante_</code>, <code>jogos[].penaltis_time_casa_jogos</code> / <code>_visitante_</code></td><td>Só no mata-mata: os gols da prorrogação (jogo único empatado) e a disputa de pênaltis; <code>null</code> quando não houve</td></tr>
            <tr><td><code>jogos[].id_jogo_ida</code></td><td>No jogo de volta do mata-mata, o <code>id_jogo</code> da ida (na ida e no jogo único, <code>null</code>). Empate no agregado vai direto para os pênaltis, sem gol fora</td></tr>
            <tr><td><code>jogos[].confronto</code></td><td>Mata-mata decidido: <code>{ "id_time": 9, "como": "pênaltis", "agregado": [3, 3], "texto": "Botafogo passa nos pênaltis (4 × 3), depois do agregado 3 × 3" }</code>. <code>como</code>: <code>tempo normal</code>, <code>prorrogação</code>, <code>agregado</code> ou <code>pênaltis</code>; <code>agregado</code> só na volta, na ordem mandante × visitante deste jogo. <code>null</code> fora do mata-mata, na ida (decide na volta) e enquanto não há vencedor</td></tr>
            <tr><td><code>jogos[].id_categoria</code></td><td>A categoria do jogo (uma das <code>categorias</code>)</td></tr>
            <tr><td><code>classificacao</code></td><td>Só em <code>/campeonatos/{id}</code>: uma entrada por categoria, com <code>id_categoria</code>, <code>categoria</code> (rótulo), <code>classificados</code> (quantos passam de cada grupo, ou <code>null</code>) e <code>grupos</code></td></tr>
            <tr><td><code>classificacao[].campeao</code></td><td>O campeão da categoria, ou <code>null</code> enquanto não há: <code>situacao</code> (<code>campeao</code> ou <code>sorteio</code>), <code>modo</code> (<code>final</code>: o vencedor da final; <code>tabela</code>: pontos corridos, o 1º com todos os jogos da fase de grupos publicados e com placar), <code>como</code> (texto), <code>campeao</code>, <code>vice</code> e <code>terceiro</code> (<code>{ id_time, nome, logo, logo_url }</code> ou <code>null</code>; vice e 3º só com final) e <code>empatados</code> (nomes, quando o 1º lugar está empatado em tudo e o campeão sai por sorteio)</td></tr>
            <tr><td><code>classificacao[].grupos[]</code></td><td><code>grupo</code> (nome, ou <code>null</code> na categoria sem grupos; "Sem grupo" para quem não está em nenhum), <code>classificados</code> e <code>tabela</code>: <code>posicao</code>, <code>id_time</code>, <code>time</code>, <code>logo</code>, <code>logo_url</code>, <code>pontos</code>, <code>jogos</code>, <code>vitorias</code>, <code>empates</code>, <code>derrotas</code>, <code>gols_marcados</code>, <code>gols_sofridos</code>, <code>saldo</code>, <code>classificado</code> (true para os primeiros que passam) e <code>empate_sorteio</code> (true quando o time está empatado com outro em todos os critérios: se continuar assim no fim, a ordem sai por sorteio)</td></tr>
            <tr><td>Ordem da tabela</td><td>Como no Brasileirão: pontos (vitória 3, empate 1), vitórias, saldo de gols, gols marcados e, com só dois times empatados nisso tudo, o confronto direto (pontos nos jogos entre eles). Sem cartões. Empate em tudo: <code>empate_sorteio</code> e, enquanto isso, ordem alfabética</td></tr>
        </table>
        </div>
<pre>"classificacao": [
  {
    "id_categoria": 2, "categoria": "Sub-15 Masculino", "classificados": 2,
    "grupos": [
      {
        "grupo": "Grupo 01", "classificados": 2,
        "tabela": [
          { "posicao": 1, "id_time": 3, "time": "AACJ Sub-15", "logo": "aacj.png",
            "logo_url": "https://.../futebol/images/team/aacj.png", "pontos": 6, "jogos": 2, "vitorias": 2,
            "empates": 0, "derrotas": 0, "gols_marcados": 5, "gols_sofridos": 1, "saldo": 4, "classificado": true }
        ]
      }
    ]
  }
]</pre>
    </div>

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
      "numero_matricula_atleta": "A100", "foto_atleta": null, "foto_url": null
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
        "foto_atleta": null, "foto_url": null, "grau_parentesco_responsavel": "Mãe" }
    ]
  }
}</pre>
        <p>Erros:</p>
<pre>401 { "success": false, "message": "E-mail ou senha inválidos." }
403 { "success": false, "message": "Cadastro não está ativo. Procure a secretaria da escolinha." }            (atleta)
403 { "success": false, "message": "Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha." }
422 { "success": false, "message": "O valor de perfil não é válido.", "errors": { "perfil": [...] } }   (campos inválidos)
429 { "success": false, "message": "Muitas tentativas. Aguarde um pouco e tente de novo." }                     (limite de tentativas)</pre>
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
        <p>Dados do atleta logado, com o endereço e a categoria atual. O atleta é da AACJ, na categoria dele: <code>times</code> vem sempre vazio (mantido para não quebrar o app); o time de cada jogo vem na agenda, em <code>time_do_atleta</code>. Posições: <code>posicoes</code> (até 3, a principal primeiro; <code>posicao</code> é o código fixo, <code>sigla</code> e <code>nome</code> são para mostrar) e <code>posicao_atleta</code> com o código da principal (<code>null</code> sem posição). Códigos: {{ implode(', ', array_keys(\App\Models\Atleta::POSICOES)) }}. Responsáveis: <code>responsaveis</code>, só com <code>nome</code> e <code>grau_parentesco</code> (lista vazia quando não há; CPF e contatos do responsável não vêm).</p>
<pre>200 {
  "success": true,
  "data": {
    "id_atleta": 7, "nome_atleta": "Joana Exemplo", "data_nasc_atleta": "2013-06-15T00:00:00-03:00",
    "email_atleta": "joana@exemplo.com", "numero_matricula_atleta": "A100", "status_atleta": "ATIVO",
    "escola_atleta": "...", "serie_atleta": "...", "peso_atleta": "45.00", "altura_atleta": "1.55", ...,
    "posicao_atleta": "VOLANTE", "foto_url": "https://.../futebol/images/our-teams/joana.jpg",
    "endereco": { "cep_endereco": "01000-000", "rua_endereco": "...", ... },
    "categorias": [
      { "id_categoria": 5, "nome_categoria": "Sub-13",
        "pivot": { "id_atleta": 7, "id_categoria": 5,
                   "data_inicio_categoria_atleta": "2026-02-01T09:30:00-03:00",
                   "data_fim_categoria_atleta": null,
                   "data_atualizacao_categoria_atleta": "2026-02-01T09:30:00-03:00",
                   "status_categoria_atleta": "ATIVO", "observacao_categoria_atleta": null } }
    ],
    "posicoes": [
      { "posicao": "VOLANTE", "sigla": "VOL", "nome": "Volante", "ordem_atleta_posicao": 1 },
      { "posicao": "MEIA_CENTRAL", "sigla": "MC", "nome": "Meia-central", "ordem_atleta_posicao": 2 }
    ],
    "responsaveis": [
      { "nome": "Carla Exemplo", "grau_parentesco": "Mãe" }
    ],
    "times": []
  }
}</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><span class="metodo PATCH">PATCH</span><code>/api/v1/atleta</code><span class="acesso atleta">Atleta</span></div>
        <p>Atualiza os dados que o atleta pode mudar (pode mandar só alguns; campos fora desta lista são ignorados): <code>nome_atleta</code>, <code>cpf_atleta</code>, <code>data_nasc_atleta</code>, <code>email_atleta</code> (único), <code>telefone_atleta</code>, <code>escola_atleta</code>, <code>serie_atleta</code>, <code>sala_atleta</code>, <code>periodo_escolar_atleta</code>, <code>turno_treino_atleta</code>, <code>peso_atleta</code>, <code>altura_atleta</code>, <code>descricao_atleta</code>. RG, matrícula, status e o <strong>ano</strong> de nascimento só a secretaria muda. A resposta traz o atleta no mesmo formato de <code>GET /api/v1/atleta</code>.</p>
        <p><strong>Nome, CPF e data de nascimento</strong> ficam registrados para a secretaria (o painel mostra o antes e o depois):</p>
        <ul>
            <li><code>nome_atleta</code>: de 3 a 100 caracteres.</li>
            <li><code>cpf_atleta</code>: com ou sem pontuação (<code>194.727.789-84</code> ou <code>19472778984</code>); volta sempre como <code>000.000.000-00</code>. Os dígitos verificadores são conferidos e o CPF não pode ser de outro atleta ("Este CPF já está cadastrado para outro atleta.").</li>
            <li><code>data_nasc_atleta</code>: <code>AAAA-MM-DD</code>, uma data que existe, anterior a hoje e de quem tem de 9 a 17 anos no ano. <strong>Só o dia e o mês</strong> podem mudar: outro ano dá 422 com "Para mudar o ano de nascimento, procure a secretaria da escolinha." (o ano decide a categoria e a idade nos campeonatos).</li>
        </ul>
        <p><code>periodo_escolar_atleta</code> (quando vier, não pode ser vazio): <code>MANHA</code>, <code>TARDE</code>, <code>NOITE</code> ou <code>INTEGRAL</code>. O treino é no turno oposto ao da escola (integral treina à tarde). Com <code>NOITE</code>, mande junto <code>turno_treino_atleta</code>: <code>MANHA</code>, <code>TARDE</code> ou <code>AMBOS</code>; nos outros períodos ele volta como <code>null</code>.</p>
<pre>PATCH /api/v1/atleta
{ "nome_atleta": "Joana Exemplo da Silva", "cpf_atleta": "19472778984", "data_nasc_atleta": "2014-05-20" }

200 { "success": true, "message": "Dados atualizados com sucesso.", "data": { ...o mesmo de GET /api/v1/atleta... } }

422 { "success": false, "message": "Para mudar o ano de nascimento, procure a secretaria da escolinha.",
      "errors": { "data_nasc_atleta": ["Para mudar o ano de nascimento, procure a secretaria da escolinha."] } }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><code>/api/v1/atleta/senha</code><span class="acesso atleta">Atleta</span></div>
        <p>Troca a senha (mínimo de 8 caracteres) e encerra as sessões dos outros aparelhos (o token atual continua valendo).</p>
<pre>{ "senha_atual": "********", "nova_senha": "********", "nova_senha_confirmation": "********" }

200 { "success": true, "message": "Senha alterada com sucesso." }
422 { "success": false, "message": "Senha atual incorreta." }</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo GET">GET</span><code>/api/v1/atleta/estatisticas</code><span class="acesso atleta">Atleta</span></div>
        <p>Os números do atleta desde que entrou na escolinha, no <code>total</code> e <code>por_time</code> (cada time em que jogou ou foi convocado, do que tem mais partidas para o que tem menos), e os da <code>categoria_atual</code> dele:</p>
        <ul>
            <li><code>partidas</code>: jogos (campeonato ou amistoso) em que ele foi <strong>escalado num time</strong>, publicados, não cancelados e já realizados. Inscrito sem time não conta.</li>
            <li><code>convocacoes</code>: convocações para campeonatos (uma por campeonato e categoria).</li>
            <li><code>cartoes_amarelos</code>, <code>cartoes_vermelhos</code>: no time em que ele estava escalado naquele jogo.</li>
            <li><code>gols</code>, <code>assistencias</code>: <strong><code>null</code> por enquanto</strong> (o painel ainda não registra quem fez cada gol). <code>null</code> = não registrado, diferente de <code>0</code>: mostre "—". Quando passarem a ser registrados, viram números, sem mudar o formato.</li>
            <li><code>categoria_atual</code>: os mesmos números, só os da categoria em que o atleta está hoje (a mesma de <code>GET /atleta</code>; uma só). Contam os jogos <strong>daquela categoria</strong> (a categoria do jogo; no amistoso sem categoria, a do time em que ele jogou) e as convocações nela: o Sub-13 que sobe para jogar no Sub-15 tem essas partidas no <code>total</code>, não na <code>categoria_atual</code>. <code>nome_categoria</code> é o nome curto ("Sub-13") e <code>categoria</code> traz o sexo ("Sub-13 Masculino"). Sem categoria: <code>null</code>.</li>
        </ul>
<pre>200 {
  "success": true,
  "data": {
    "total": { "partidas": 10, "convocacoes": 5, "cartoes_amarelos": 1, "cartoes_vermelhos": 0, "gols": null, "assistencias": null },
    "por_time": [
      { "id_time": 3, "nome_time": "AACJ Sub-17 A", "logo_url": "https://.../images/times/sub17.png", "categoria": "Sub-17 Masculino",
        "partidas": 10, "convocacoes": 5, "cartoes_amarelos": 1, "cartoes_vermelhos": 0, "gols": null, "assistencias": null }
    ],
    "categoria_atual": { "id_categoria": 9, "nome_categoria": "Sub-17", "categoria": "Sub-17 Masculino",
      "partidas": 10, "convocacoes": 5, "cartoes_amarelos": 1, "cartoes_vermelhos": 0, "gols": null, "assistencias": null }
  }
}</pre>
        <p>Atleta que ainda não jogou: tudo <code>0</code> no <code>total</code> e <code>por_time</code> vazio. O responsável tem a mesma rota sob o filho: <code>GET /api/v1/responsavel/atletas/{idAtleta}/estatisticas</code>.</p>
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
                   "foto_atleta": null, "foto_url": null, "grau_parentesco_responsavel": "Mãe" } ]
  }
}</pre>
    </div>

    <div class="card">
        <div class="rota"><span class="metodo PUT">PUT</span><span class="metodo PATCH">PATCH</span><code>/api/v1/responsavel</code><span class="acesso responsavel">Responsável</span></div>
        <p>Atualiza só <code>email_responsavel</code> (o login; único entre responsáveis, gravado em minúsculas), <code>telefone_responsavel</code> e <code>whatsapp_responsavel</code>. Nome, CPF, RG e endereço ficam com a secretaria. Responde com os mesmos dados do GET.</p>
<pre>PATCH /api/v1/responsavel
{ "whatsapp_responsavel": "(11) 90000-0001" }

422 { "success": false, "message": "Este e-mail já está cadastrado para outro responsável.",
      "errors": { "email_responsavel": ["Este e-mail já está cadastrado para outro responsável."] } }</pre>
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
    { "id_atleta": 7, "nome_atleta": "Joana Exemplo", "numero_matricula_atleta": "A100", "foto_atleta": null, "foto_url": null,
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
        <h3>Agenda, avisos e estatísticas do filho</h3>
        <p>As mesmas rotas do atleta, sob <code>/api/v1/responsavel/atletas/{idAtleta}</code>, com as mesmas respostas:</p>
        <ul>
            <li><code>GET .../estatisticas</code></li>
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
            <li>Só os eventos em que o atleta está <strong>inscrito</strong> (treinos, jogos, avaliações, reuniões...). Eventos ocultos pela secretaria e jogos ainda em rascunho (sendo montados, antes de publicar) não aparecem.</li>
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
          "time_casa": { "id_time": 3, "nome_time": "AACJ Sub-13", "logo_time": "aacj.png", "logo_url": "https://.../futebol/images/team/aacj.png" },
          "time_visitante": { "id_time": 9, "nome_time": "Rival FC", "logo_time": "rival.png", "logo_url": "https://.../futebol/images/team/rival.png" },
          "placar": null,
          "time_do_atleta": { "id_time": 3, "nome_time": "AACJ Sub-13", "logo_time": "aacj.png", "logo_url": "https://.../futebol/images/team/aacj.png" },
          "horario_chegada": "09:00",
          "chegada": "2026-10-15T09:00:00-03:00"
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
            <tr><td><code>jogo</code></td><td><code>null</code> fora de jogo. No jogo: <code>amistoso</code>; <code>campeonato</code> (<code>null</code> no amistoso); <code>time_casa</code> e <code>time_visitante</code>; <code>placar</code> <code>{ "casa": 2, "visitante": 1 }</code> ou <code>null</code> se ainda não jogado; <code>time_do_atleta</code>, o time em que o atleta está escalado, ou <code>null</code> se ainda não foi escalado; <code>horario_chegada</code> (<code>"09:00"</code>) e <code>chegada</code> (data e hora), a hora em que o atleta deve chegar, 1 hora antes do início, ou <code>null</code> se o jogo ainda não tem horário. Mostre como "Chegar às 09:00"</td></tr>
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
        <p>Tipos: <code>INSCRICAO</code>, <code>REMOCAO</code>, <code>ALTERACAO</code>, <code>CANCELAMENTO</code>, <code>REATIVACAO</code> (com <code>id_evento</code>), <code>AGENDA</code> (resumo, como "Agenda de dezembro disponível", com <code>id_evento</code> <code>null</code>) e <code>AVISO</code> (recado escrito pelo admin, também com <code>id_evento</code> <code>null</code>). Título e mensagem ficam como foram enviados.</p>
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
        <tr><td>401</td><td>Rota protegida sem token, com token inválido, vencido (30 dias) ou já encerrado</td><td><code>{ "success": false, "message": "Faça login para continuar: o token não foi enviado, é inválido ou venceu." }</code></td></tr>
        <tr><td>403</td><td>Atleta sem cadastro ativo; responsável sem filho ativo (os tokens dele são apagados)</td><td><code>{ "success": false, "message": "..." }</code></td></tr>
        <tr><td>403</td><td>Token de um perfil na área do outro</td><td><code>{ "success": false, "message": "Esta área é do perfil responsável. Entre no app como responsável." }</code></td></tr>
        <tr><td>404</td><td>Filho que não é do responsável, inativado ou inexistente; aviso que não é daquele atleta</td><td><code>{ "success": false, "message": "Atleta não encontrado." }</code> / <code>"Notificação não encontrada."</code></td></tr>
        <tr><td>404</td><td>Endereço que não existe na API</td><td><code>{ "success": false, "message": "Endereço não encontrado." }</code></td></tr>
        <tr><td>405</td><td>Método não aceito na rota (ex.: <code>PATCH</code> nos dados do filho)</td><td><code>{ "success": false, "message": "Método não aceito nesta rota." }</code></td></tr>
        <tr><td>422</td><td>Dados inválidos, com a mensagem de cada campo em português</td><td><code>{ "success": false, "message": "O campo e-mail é obrigatório.", "errors": { "email": ["O campo e-mail é obrigatório."] } }</code></td></tr>
        <tr><td>429</td><td>Limite de requisições (abaixo); aguarde os segundos do cabeçalho <code>Retry-After</code> e tente de novo</td><td><code>{ "success": false, "message": "Muitas tentativas. Aguarde um pouco e tente de novo." }</code></td></tr>
        <tr><td>500</td><td>Erro interno no servidor; tente de novo em instantes</td><td><code>{ "success": false, "message": "Erro interno. Tente de novo em instantes." }</code></td></tr>
    </table>
    </div>

    <div class="card" style="margin-top:12px">
        <h3>Limites de requisições</h3>
        <ul>
            <li><strong>Todas as rotas:</strong> 120 por minuto por conta logada (atleta ou responsável) e 60 por minuto por IP sem login. As respostas trazem os cabeçalhos <code>X-RateLimit-Limit</code> e <code>X-RateLimit-Remaining</code>. Guarde no app o que muda pouco (campeonatos, notícias, banners) em vez de buscar a cada tela.</li>
            <li><code>POST /auth/login</code>: 5 por minuto por IP e 5 por minuto por e-mail e perfil.</li>
            <li><code>POST /auth/esqueci-senha</code>: 5 por minuto por IP e 3 a cada 10 minutos por e-mail e perfil.</li>
        </ul>
    </div>

    {{-- ================================================================== --}}
    <h2 id="rotas">Todas as rotas</h2>

    @php
        $todas = [
            ['POST',  '/auth/login', 'Público', 'email, senha, perfil?, device_name?'],
            ['POST',  '/auth/esqueci-senha', 'Público', 'email, perfil?'],
            ['POST',  '/auth/logout', 'Qualquer perfil', '—'],
            ['GET',   '/atleta', 'Atleta', '—'],
            ['PUT',   '/atleta', 'Atleta', $camposDoAtleta = 'nome_atleta?, cpf_atleta?, data_nasc_atleta?, email_atleta?, telefone_atleta?, escola_atleta?, serie_atleta?, sala_atleta?, periodo_escolar_atleta?, turno_treino_atleta?, peso_atleta?, altura_atleta?, descricao_atleta?'],
            ['PATCH', '/atleta', 'Atleta', $camposDoAtleta],
            ['PUT',   '/atleta/senha', 'Atleta', $camposDaSenha = 'senha_atual, nova_senha, nova_senha_confirmation'],
            ['GET',   '/atleta/estatisticas', 'Atleta', '—'],
            ['GET',   '/agenda', 'Atleta', 'page?'],
            ['GET',   '/notificacoes', 'Atleta', 'page?'],
            ['GET',   '/notificacoes/nao-lidas', 'Atleta', '—'],
            ['PATCH', '/notificacoes/{idNotificacao}/lida', 'Atleta', '{idNotificacao}'],
            ['PATCH', '/notificacoes/lidas', 'Atleta', '—'],
            ['GET',   '/responsavel', 'Responsável', '—'],
            ['PUT',   '/responsavel', 'Responsável', $camposDoResponsavel = 'email_responsavel?, telefone_responsavel?, whatsapp_responsavel?'],
            ['PATCH', '/responsavel', 'Responsável', $camposDoResponsavel],
            ['PUT',   '/responsavel/senha', 'Responsável', $camposDaSenha],
            ['GET',   '/responsavel/atletas', 'Responsável', '—'],
            ['GET',   '/responsavel/atletas/{idAtleta}', 'Responsável', '{idAtleta}'],
            ['GET',   '/responsavel/atletas/{idAtleta}/estatisticas', 'Responsável', '{idAtleta}'],
            ['GET',   '/responsavel/atletas/{idAtleta}/agenda', 'Responsável', '{idAtleta}, page?'],
            ['GET',   '/responsavel/atletas/{idAtleta}/notificacoes', 'Responsável', '{idAtleta}, page?'],
            ['GET',   '/responsavel/atletas/{idAtleta}/notificacoes/nao-lidas', 'Responsável', '{idAtleta}'],
            ['PATCH', '/responsavel/atletas/{idAtleta}/notificacoes/{idNotificacao}/lida', 'Responsável', '{idAtleta}, {idNotificacao}'],
            ['PATCH', '/responsavel/atletas/{idAtleta}/notificacoes/lidas', 'Responsável', '{idAtleta}'],
        ];
    @endphp

    <p class="nota">
        <strong>Parâmetros:</strong> <code>{nome}</code> vai no caminho da URL; <code>?</code> no fim do nome = opcional (os outros são obrigatórios);
        nas rotas <code>GET</code> os parâmetros vão na query string (ex.: <code>?page=2</code>) e nas demais no corpo JSON; <code>—</code> = sem parâmetros.
        <strong>Acesso:</strong> as rotas que não são <strong>Público</strong> exigem <code>Authorization: Bearer SEU_TOKEN</code> do perfil indicado.
    </p>

    <div class="tabela">
    <table>
        <tr><th>Método</th><th>Rota</th><th>Acesso</th><th>Parâmetros</th></tr>
        @foreach ($publicas as [$metodo, $rota, $descricao, $parametros])
            <tr><td><span class="metodo {{ $metodo }}">{{ $metodo }}</span></td><td><code>/api/v1{{ $rota }}</code></td><td>Público</td><td class="param"><code>{{ $parametros }}</code></td></tr>
        @endforeach
        @foreach ($todas as [$metodo, $rota, $acesso, $parametros])
            <tr><td><span class="metodo {{ $metodo }}">{{ $metodo }}</span></td><td><code>/api/v1{{ $rota }}</code></td><td>{{ $acesso }}</td><td class="param"><code>{{ $parametros }}</code></td></tr>
        @endforeach
    </table>
    </div>

</main>

</body>
</html>
