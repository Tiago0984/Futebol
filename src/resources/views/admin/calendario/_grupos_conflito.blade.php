{{--
    Grupos de conflito do lote da geração (Fase 7, Etapa 3): um item por par de eventos, com os atletas.
    Até 5 nomes à vista; o resto num <details>. Recebe $grupos (EventoCalendario::conflitosEmLote).
--}}
<ul class="mb-2 mt-2">
    @foreach ($grupos as $grupo)
    @php
        $outro    = $grupo['outro'];
        $visiveis = array_slice($grupo['atletas'], 0, 5);
        $resto    = array_slice($grupo['atletas'], 5);
    @endphp
    <li>
        <strong>{{ $grupo['data']->format('d/m') }}</strong> ·
        {{ $grupo['novo']->titulo_evento_calendario }} ({{ $grupo['novo']->horario_texto }})
        × {{ $outro->tipo_evento_calendario }} "{{ $outro->titulo_evento_calendario }}" ({{ $outro->horario_texto }})
        @if ($grupo['outro_no_lote'])<span class="text-muted">(também será gerado)</span>@endif
        : <strong>{{ count($grupo['atletas']) }} atleta(s)</strong>: {{ implode(', ', $visiveis) }}
        @if (count($resto))
            <details class="d-inline">
                <summary class="d-inline text-primary" style="cursor:pointer;">+{{ count($resto) }}</summary>
                {{ implode(', ', $resto) }}
            </details>
        @endif
    </li>
    @endforeach
</ul>
