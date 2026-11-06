{{--
    Bloco de código com abas ($tituloDoBloco e $abas: [rótulo, linguagem, código]). Sem ENT_QUOTES: as aspas são texto,
    e o JSON fica legível também sem o JavaScript. A aba com um número é um status HTTP, com o cartão da família (verde 2xx, amarelo 4xx, vermelho 5xx).
--}}
<div class="code-title">{{ $tituloDoBloco }}</div>
<div class="block js-bloco">
    <div class="block-head">
        @foreach ($abas as $indice => [$rotulo])
            <button type="button" class="tab{{ $indice === 0 ? ' active' : '' }}" data-panel="{{ $indice }}">
                @if (is_numeric($rotulo))<span class="cartao s{{ $rotulo[0] }}"></span>@endif{{ $rotulo }}
            </button>
        @endforeach
        <button type="button" class="copy">Copiar</button>
    </div>
    @foreach ($abas as $indice => [$rotulo, $linguagem, $codigo])
        <div class="panel{{ $indice === 0 ? ' active' : '' }}"><pre><code class="js-codigo" data-lang="{{ $linguagem }}">{!! htmlspecialchars($codigo, ENT_NOQUOTES) !!}</code></pre></div>
    @endforeach
</div>
