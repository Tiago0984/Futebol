{{-- Convites do app que não foram enviados por e-mail (Fase 9): o admin copia o link e envia (vale 24 horas) --}}
@if($links = session('links_convite'))
<div class="alert alert-warning mb-3" role="alert">
    <strong><i class="bi bi-link-45deg"></i> Links de "Defina sua senha" para copiar e enviar</strong>
    <span class="small text-muted">(valem 24 horas; este é o único momento em que aparecem)</span>
    @foreach($links as $item)
        <div class="mt-2">
            <div class="small fw-semibold">Para {{ $item['para'] }}</div>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" value="{{ $item['link'] }}" readonly onclick="this.select()">
                <button type="button" class="btn btn-outline-secondary"
                        onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent = 'Copiado';">Copiar</button>
            </div>
        </div>
    @endforeach
</div>
@endif
