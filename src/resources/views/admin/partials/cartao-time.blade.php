{{--
    Cartão de um time (visual da antiga tela de Escalação), usado nos Times do campeonato e nos Times dos
    amistosos. $time (com categoria); $link = jogadores do time (só leitura), ignorado no externo, que fica
    bloqueado (sem elenco na associação); $contagem (opcional) = texto do número, por exemplo "3 escalado(s)";
    sem ele, o total de atletas do elenco ($time->total_atletas).
--}}
@if(strtoupper($time->tipo_time) === 'EXTERNO')
<div class="escal-team-card escal-team-card-external">
  <div class="escal-team-logo">
    <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="{{ $time->nome_time }}"
         onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
  </div>
  <div class="escal-team-info">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="escal-team-name">{{ $time->nome_time }}</span>
      <span class="badge escal-badge-externo">Externo</span>
    </div>
    @if($time->categoria)
      <span class="escal-team-cat">{{ $time->categoria->rotulo }}</span>
    @endif
    <span class="escal-team-unavailable">
      <i class="bi bi-lock-fill"></i> Elenco não disponível nesta associação
    </span>
  </div>
  <i class="bi bi-lock" style="color:#9ca3af;flex-shrink:0;"></i>
</div>
@else
<a href="{{ $link }}" class="escal-team-card">
  <div class="escal-team-logo">
    <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="{{ $time->nome_time }}"
         onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
  </div>
  <div class="escal-team-info">
    <span class="escal-team-name">{{ $time->nome_time }}</span>
    @if($time->categoria)
      <span class="escal-team-cat">{{ $time->categoria->rotulo }}</span>
    @endif
    <span class="escal-team-count">
      <i class="bi bi-people-fill"></i>
      {{ $contagem ?? ($time->total_atletas . ' atleta' . ($time->total_atletas != 1 ? 's' : '')) }}
    </span>
  </div>
  <i class="bi bi-chevron-right escal-team-arrow"></i>
</a>
@endif
