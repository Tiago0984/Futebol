{{--
    Alerta de conflito de horário.
    - Conflito real (horários sobrepostos): alerta amarelo com "Confirmar mesmo assim", que reenvia os
      mesmos dados com confirmar_conflito=1. Se houver também avisos de mesmo dia, aparecem separados.
    - Só aviso de mesmo dia (algum evento sem horário de início): não bloqueia; aviso informativo azul.
--}}
@if ($pendente = session('conflitos_pendentes'))
<div class="alert alert-warning mb-3" role="alert">
    <strong><i class="bi bi-exclamation-triangle"></i> Conflito de horário</strong>
    <ul class="mb-2 mt-2">
        @foreach ($pendente['fortes'] as $mensagem)
        <li>{{ $mensagem }}</li>
        @endforeach
    </ul>
    @if (count($pendente['fracos']))
        <strong><i class="bi bi-info-circle"></i> Mesmo dia — confira o horário</strong>
        <ul class="mb-2 mt-2">
            @foreach ($pendente['fracos'] as $mensagem)
            <li>{{ $mensagem }}</li>
            @endforeach
        </ul>
    @endif
    <form action="{{ $pendente['url'] }}" method="POST" class="d-flex gap-2 align-items-center">
        @csrf
        @if ($pendente['metodo'] !== 'POST')
            @method($pendente['metodo'])
        @endif
        @foreach ($pendente['dados'] as $campo => $valor)
            @if (is_array($valor))
                @foreach ($valor as $item)
                <input type="hidden" name="{{ $campo }}[]" value="{{ $item }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
            @endif
        @endforeach
        <input type="hidden" name="confirmar_conflito" value="1">
        <button type="submit" class="btn btn-warning btn-sm">Confirmar mesmo assim</button>
        <span class="small text-muted">Ou feche este aviso para não salvar.</span>
    </form>
</div>
@endif

@if ($avisos = session('avisos_mesmo_dia'))
<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
    <strong><i class="bi bi-info-circle"></i> Mesmo dia — confira o horário</strong>
    <ul class="mb-0 mt-2">
        @foreach ($avisos as $mensagem)
        <li>{{ $mensagem }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
