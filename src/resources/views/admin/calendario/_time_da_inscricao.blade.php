{{--
    Time de um inscrito no jogo: o select (trocar recarrega e o atleta muda de bloco) e o aviso do elenco.
    $evento, $inscricao, $escalaveis (times internos do jogo), $elencos ([id_atleta => [id_time, ...]]).
    "Fora do elenco" compara com o time escalado (como na tela de jogadores do time no jogo): escalado num time
    de cujo elenco não é (com o time dele, se for do outro time do jogo); sem time, só informa o elenco.
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
  $timesDoElenco = $escalaveis->whereIn('id_time', $elencos[$inscricao->id_atleta] ?? []);
  $doElenco      = $timesDoElenco->pluck('nome_time');
  $foraDoElenco  = $inscricao->id_time
      ? ! $timesDoElenco->contains('id_time', (int) $inscricao->id_time)
      : $doElenco->isEmpty();
@endphp
@if ($foraDoElenco)
    <small class="text-warning fw-semibold">Fora do elenco{{ $doElenco->isNotEmpty() ? ' · é do ' . $doElenco->implode(' e do ') : '' }}</small>
@else
    <small class="text-muted">Elenco: {{ $doElenco->implode(', ') }}</small>
@endif
