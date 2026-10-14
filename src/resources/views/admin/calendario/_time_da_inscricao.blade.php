{{--
    Time de um inscrito no jogo: o select (trocar recarrega e o atleta muda de bloco) e o aviso de por qual
    time ele joga. $evento, $inscricao, $escalaveis (times internos do jogo), $jogo, $elencos ([id_atleta =>
    [id_time, ...]]: convocação no jogo de campeonato, elenco no amistoso).
    "Não convocado" / "Fora do elenco" compara com o time escalado (como na tela de jogadores do time no jogo):
    escalado num time pelo qual não joga (com o time dele, se for o outro time do jogo); sem time, só informa.
--}}
<form action="{{ route('admin.calendario.eventos.inscricoes.time', [$evento->id_evento_calendario, $inscricao->id_atleta]) }}" method="POST">
    @csrf @method('PATCH')
    <select name="id_time" class="form-select form-select-sm" onchange="this.form.submit()"
            aria-label="Time de {{ $inscricao->atleta->nome_atleta }}">
        <option value="">— Sem time —</option>
        @foreach ($escalaveis as $time)
        <option value="{{ $time->id_time }}" @selected((int) $inscricao->id_time === (int) $time->id_time)>{{ $time->nome_time }}</option>
        @endforeach
    </select>
</form>
@php
  $pelaConvocacao = $jogo->pelaConvocacao();
  $timesDoElenco  = $escalaveis->whereIn('id_time', $elencos[$inscricao->id_atleta] ?? []);
  $doElenco       = $timesDoElenco->pluck('nome_time');
  $foraDoElenco   = $inscricao->id_time
      ? ! $timesDoElenco->contains('id_time', (int) $inscricao->id_time)
      : $doElenco->isEmpty();
@endphp
@if ($foraDoElenco)
    <small class="text-warning fw-semibold">{{ $pelaConvocacao ? 'Não convocado' : 'Fora do elenco' }}{{ $doElenco->isNotEmpty() ? ($pelaConvocacao ? ' · convocado pelo ' : ' · é do ') . $doElenco->implode(' e do ') : '' }}</small>
@else
    <small class="text-muted">{{ $pelaConvocacao ? 'Convocado' : 'Elenco' }}: {{ $doElenco->implode(', ') }}</small>
@endif
