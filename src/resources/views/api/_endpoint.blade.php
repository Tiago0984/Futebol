{{-- Uma rota da API: texto, parâmetros e campos à esquerda; requisição e respostas à direita ($item, $grupo, $base; $camisa, o número da rota) --}}
@php
    $acesso = \App\Support\DocumentacaoApi::ACESSOS[$item['acesso']];
    $respostas = collect($item['respostas'])
        ->map(fn ($json, $status) => [(string) $status, 'json', \App\Support\DocumentacaoApi::json($json)])->values()->all();
@endphp
<section class="section" id="{{ $item['id'] }}">
    <div class="doc">
        <span class="camisa" aria-hidden="true">{{ str_pad($camisa, 2, '0', STR_PAD_LEFT) }}</span>
        <div class="group-label">{{ $grupo['titulo'] }}</div>
        <h2>{{ $item['titulo'] }}</h2>

        <div class="endpoint-line">
            @foreach ($item['metodos'] as $metodo)
                <span class="method {{ strtolower($metodo) }}" data-rota="{{ $metodo }} /api/v1{{ $item['rota'] }}">{{ $metodo }}</span>
            @endforeach
            <span class="path">{{ $item['rota'] }}</span>
            <span class="auth-tag {{ $acesso['classe'] }}">{{ $acesso['rotulo'] }}</span>
        </div>

        {!! $item['doc'] !!}

        @if (! empty($item['parametros']))
            <h3>Parâmetros</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nome</th><th>Tipo</th><th>Onde</th><th>Descrição</th></tr></thead>
                    <tbody>
                        @foreach ($item['parametros'] as [$nome, $tipo, $onde, $obrigatorio, $descricao])
                            <tr>
                                <td><code>{{ $nome }}</code>@if ($obrigatorio) <span class="obrigatorio">obrigatório</span>@endif</td>
                                <td class="celula-curta">{{ $tipo }}</td>
                                <td class="celula-curta">{{ $onde }}</td>
                                <td>{!! $descricao !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (! empty($item['campos']))
            <h3>Campos da resposta</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Campo</th><th>Descrição</th></tr></thead>
                    <tbody>
                        @foreach ($item['campos'] as [$campo, $descricao])
                            <tr><td><code>{{ $campo }}</code></td><td>{!! $descricao !!}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (! empty($item['nota_das_respostas']))
            <p class="nota">{!! $item['nota_das_respostas'] !!}</p>
        @endif
    </div>

    <div class="code">
        <div class="code-sticky">
            @include('api._codigo', ['tituloDoBloco' => 'Requisição', 'abas' => \App\Support\DocumentacaoApi::requisicoes($item, $base)])
            @include('api._codigo', ['tituloDoBloco' => 'Resposta', 'abas' => $respostas])
        </div>
    </div>
</section>
