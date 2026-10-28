{{--
    Cartão de um time (visual da antiga tela de Escalação), usado nos Jogos do campeonato e nos dos amistosos.
    $time (com categorias); $link = jogadores do time no jogo ou convocados do time no campeonato, ignorado no
    externo, que fica bloqueado (sem atletas na associação); $contagem = texto do número, por exemplo
    "3 escalado(s) no jogo" ou "5 convocado(s)".
    $categoriaDoCampeonato (opcional): a do jogo ou a do time no campeonato, mostrada no lugar das categorias do
    cadastro (o time tem várias desde 27/10/2026). Se o cadastro não a tem marcada (jogo de antes), as do cadastro
    aparecem embaixo, menores.
--}}
@php
  $categoriaDoCampeonato ??= null;
  $outraCategoria = $categoriaDoCampeonato && $time->categorias->isNotEmpty()
      && ! $time->categorias->contains('id_categoria', $categoriaDoCampeonato->id_categoria);
@endphp
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
    @include('admin.partials._categoria-do-time')
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
    @include('admin.partials._categoria-do-time')
    <span class="escal-team-count">
      <i class="bi bi-people-fill"></i>
      {{ $contagem }}
    </span>
  </div>
  <i class="bi bi-chevron-right escal-team-arrow"></i>
</a>
@endif
