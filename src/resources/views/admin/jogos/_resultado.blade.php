{{-- Depois do placar do tempo normal (Fase I, Etapa I1b): prorrogação e pênaltis ("prorr. 1 × 1 · pên. 4 × 3") e,
     no mata-mata decidido, quem passa ("Botafogo passa no agregado (3 × 2)"). Recebe $jogo. --}}
@if ($jogo->placar_extra)
<div class="text-muted admin-texto-mini">{{ $jogo->placar_extra }}</div>
@endif
@if ($confronto = $jogo->resultadoDoConfronto())
<div class="text-success fw-semibold admin-texto-mini"><i class="bi bi-arrow-right-circle"></i> {{ $confronto['texto'] }}</div>
@endif
