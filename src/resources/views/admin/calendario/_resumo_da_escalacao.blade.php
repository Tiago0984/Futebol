{{--
    Resumo da escalação de um time da AACJ no jogo (Fase D, Etapa D2): titulares, reservas e a definir, o número
    de titulares de cada modalidade (5 futsal, 7 society, 11 campo; Jogo::TITULARES_POR_MODALIDADE) e os avisos
    (sem goleiro titular, mais titulares que o campo). Conta o que está gravado. $lista: escalados no time.
--}}
@if ($lista->isNotEmpty())
@php $resumo = \App\Models\Jogo::resumoDaEscalacao($lista); @endphp
<div class="px-3 py-2 border-bottom small js-resumo-escalacao">
    <span class="me-2"><strong>{{ $resumo['titulares'] }}</strong> titular(es)</span>
    <span class="me-2"><strong>{{ $resumo['reservas'] }}</strong> reserva(s)</span>
    <span class="text-muted">{{ $resumo['a_definir'] }} a definir</span>
    @if ($resumo['modalidade'])
        <span class="badge text-bg-info ms-1" title="Titulares por modalidade: 5 no futsal, 7 no society, 11 no campo">número do {{ $resumo['modalidade'] }}</span>
    @endif
    @foreach ($resumo['avisos'] as $aviso)
        <div class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle"></i> {{ $aviso }}</div>
    @endforeach
</div>
@endif
