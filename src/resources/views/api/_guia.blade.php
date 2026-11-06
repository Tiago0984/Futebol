{{-- Guia da documentação, sem rota: texto à esquerda e, se houver, exemplos à direita ($item, $grupo, $textos, $endpoints) --}}
<section class="section{{ ($item['largura_total'] ?? false) ? ' larga' : '' }}" id="{{ $item['id'] }}">
    <div class="doc">
        <div class="group-label">{{ $grupo['titulo'] }}</div>
        @if ($item['id'] === 'introducao')
            <h1>API da Escolinha de Futebol AACJ</h1>
        @else
            <h2>{{ $item['titulo'] }}</h2>
        @endif

        {!! strtr($item['doc'], $textos) !!}

        @if ($item['id'] === 'rotas')
            @include('api._todas-as-rotas')
        @endif
    </div>

    @unless ($item['largura_total'] ?? false)
        <div class="code{{ $item['exemplos'] === [] ? ' sem-exemplos' : '' }}">
            <div class="code-sticky">
                @foreach ($item['exemplos'] as $exemplo)
                    @include('api._codigo', [
                        'tituloDoBloco' => $exemplo['titulo'],
                        'abas' => [[$exemplo['rotulo'], $exemplo['lang'], strtr($exemplo['codigo'], $textos)]],
                    ])
                @endforeach
            </div>
        </div>
    @endunless
</section>
