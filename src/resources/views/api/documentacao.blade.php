{{--
    Documentação da API (06/11/2026): menu com os métodos e filtro à esquerda, texto no centro e exemplos à direita, com a
    identidade da escolinha, clara: prancheta com linhas de campo, número de camisa e cartões nos status. O conteúdo vem de App\Support\DocumentacaoApi e dos arquivos de resources/documentacao-api;
    estilos em coderatech/css/api-documentacao.css e JS em coderatech/js/api-documentacao.js.
--}}
@php
    $base       = url('/api/v1');
    $grupos     = \App\Support\DocumentacaoApi::grupos();
    $endpoints  = \App\Support\DocumentacaoApi::endpoints();
    $servidores = \App\Support\DocumentacaoApi::servidores($base);
    $textos     = ['{BASE}' => $base, '{PRODUCAO}' => \App\Support\DocumentacaoApi::PRODUCAO, '{LOCAL}' => \App\Support\DocumentacaoApi::LOCAL];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Documentação da API – Escolinha de Futebol AACJ</title>

    <link rel="shortcut icon" href="{{ asset('futebol/images/logo2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('coderatech/css/api-documentacao.css') }}?v={{ filemtime(public_path('coderatech/css/api-documentacao.css')) }}">
    <noscript><style>.nav-group.fechado .nav-itens { display: block; }</style></noscript>
</head>

<body data-base="{{ $base }}">

<header class="topbar">
    <button class="menu-toggle" type="button" aria-label="Abrir menu" id="menuToggle"><span aria-hidden="true">☰</span></button>

    <a href="#introducao" class="brand">
        <span class="brand-escudo"><img src="{{ asset('futebol/images/logo2.png') }}" alt="Logo da AACJ"></span>
        <span class="brand-texto">
            <span class="brand-nome"><b>AACJ</b> Futebol</span>
            <span class="brand-tipo">Documentação da API</span>
        </span>
    </a>
    <span class="badge-version">v1</span>

    <div class="topbar-base">
        <div class="ambientes" role="group" aria-label="Ambiente">
            @foreach ($servidores as $servidor)
                <button type="button" class="ambiente{{ $servidor['url'] === $base ? ' ativo' : '' }}" data-url="{{ $servidor['url'] }}">{{ $servidor['nome'] }}</button>
            @endforeach
        </div>
        <code class="js-base base-url">{{ $base }}</code>
    </div>

    <button type="button" class="btn-postman" id="postman"><span aria-hidden="true">⬇</span> <span class="rotulo-longo">Coleção</span> Postman</button>
</header>

<nav class="sidebar" id="sidebar" aria-label="Rotas da API">
    <div class="menu-filtros">
        <input type="search" class="search" id="search" placeholder="Buscar rota..." aria-label="Buscar rota">

        <div class="filtros" role="group" aria-label="Filtrar pelo método">
            <button type="button" class="filtro ativo" data-metodo="">Todos</button>
            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $metodo)
                <button type="button" class="filtro {{ strtolower($metodo) }}" data-metodo="{{ $metodo }}">
                    {{ $metodo }}
                    <span class="filtro-conta">{{ collect($endpoints)->filter(fn ($rota) => in_array($metodo, $rota['metodos'], true))->count() }}</span>
                </button>
            @endforeach
        </div>

        <div class="menu-acoes">
            <span id="navContador" aria-live="polite"></span>
            <button type="button" id="alternarGrupos">Expandir tudo</button>
        </div>
    </div>

    @foreach ($grupos as $grupo)
        <div class="nav-group fechado">
            <button type="button" class="nav-title" aria-expanded="false">
                <span>{{ $grupo['titulo'] }}</span>
                <span class="nav-conta">{{ count($grupo['itens']) }}</span>
                <span class="nav-seta" aria-hidden="true"></span>
            </button>
            <div class="nav-itens">
                @foreach ($grupo['itens'] as $item)
                    @php $ehGuia = $item['guia'] ?? false; @endphp
                    <a class="nav-link" href="#{{ $item['id'] }}"
                       data-metodos="{{ $ehGuia ? '' : implode(' ', $item['metodos']) }}"
                       data-busca="{{ mb_strtolower($item['titulo'] . ' ' . ($item['rota'] ?? '')) }}">
                        <span class="nav-metodos">
                            @if ($ehGuia)
                                <span class="method guia">GUIA</span>
                            @else
                                @foreach ($item['metodos'] as $metodo)
                                    <span class="method {{ strtolower($metodo) }}">{{ $metodo }}</span>
                                @endforeach
                            @endif
                        </span>
                        <span class="nav-texto">{{ $item['titulo'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <p class="nav-empty" id="navEmpty" hidden>Nenhuma rota encontrada.</p>
</nav>

<main class="content">
    @php $camisa = 0; @endphp
    @foreach ($grupos as $grupo)
        @foreach ($grupo['itens'] as $item)
            @if ($item['guia'] ?? false)
                @include('api._guia')
            @else
                @include('api._endpoint', ['camisa' => ++$camisa])
            @endif
        @endforeach
    @endforeach
</main>

<script type="application/json" id="colecaoPostman">@json(\App\Support\DocumentacaoApi::colecaoPostman($base))</script>
<script src="{{ asset('coderatech/js/api-documentacao.js') }}?v={{ filemtime(public_path('coderatech/js/api-documentacao.js')) }}"></script>

</body>
</html>
