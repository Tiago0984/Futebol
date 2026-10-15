{{--
    Time de um inscrito no jogo: o select (trocar recarrega e o atleta muda de bloco) e, no jogo de campeonato,
    o aviso de por qual time ele está convocado. $evento, $inscricao, $escalaveis (times internos do jogo), $jogo,
    $elencos ([id_atleta => [id_time]]: a convocação do campeonato; vazio no amistoso, que não tem lista).
    "Não convocado" compara com o time escalado (como na tela de jogadores do time no jogo): escalado num time
    pelo qual não está convocado (com o time dele, se for o outro time do jogo); sem time, só informa.
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
@if ($jogo->pelaConvocacao())
@php
  $timesDaConvocacao = $escalaveis->whereIn('id_time', $elencos[$inscricao->id_atleta] ?? []);
  $convocadoPor      = $timesDaConvocacao->pluck('nome_time');
  $naoConvocado      = $inscricao->id_time
      ? ! $timesDaConvocacao->contains('id_time', (int) $inscricao->id_time)
      : $convocadoPor->isEmpty();
@endphp
@if ($naoConvocado)
    <small class="text-warning fw-semibold">Não convocado{{ $convocadoPor->isNotEmpty() ? ' · convocado pelo ' . $convocadoPor->implode(' e do ') : '' }}</small>
@else
    <small class="text-muted">Convocado: {{ $convocadoPor->implode(', ') }}</small>
@endif
@endif
