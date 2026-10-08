{{--
    Jogos dos amistosos e Jogos do campeonato: um bloco por jogo, com data, horário, local, "Cancelado" ou
    placar e o link para o jogo, e os cartões do mandante e do visitante. Jogo em rascunho: selo e "Revisar e
    publicar" (tela do jogo), com um aviso no topo contando quantos faltam.
    $proximos, $realizados (Jogo::separadosParaTelaDeTimes); $rotulo = "amistoso" ou "jogo" (textos);
    $linkDoJogo(Jogo) = a lista de Jogos só com ele. O cartão do time abre os jogadores escalados por ele
    naquele jogo (admin.jogos.times.show); $jogo->escalados = quantos por time (Jogo::separadosParaTelaDeTimes).
    Próximos e realizados em abas (visual das abas do Calendário, sem recarregar a página): abre em "Próximos";
    sem nenhum próximo, em "Já realizados".
--}}
@php
  $plural = $rotulo === 'amistoso' ? 'amistosos' : 'jogos';
  $secoes = [
      'proximos'   => ['jogos' => $proximos,   'titulo' => "Próximos {$plural}", 'icone' => 'bi-calendar-event'],
      'realizados' => ['jogos' => $realizados, 'titulo' => 'Já realizados',      'icone' => 'bi-check2-circle'],
  ];
  $abaInicial = $proximos->isEmpty() && $realizados->isNotEmpty() ? 'realizados' : 'proximos';
  // Jogos em rascunho (Fase 10, Etapa 4): os atletas ainda não foram avisados
  $rascunhos = $proximos->concat($realizados)->reject(fn ($jogo) => $jogo->evento->estaPublicado())->count();
@endphp
@if ($rascunhos > 0)
<div class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0" role="alert" id="avisoRascunhos">
  <i class="bi bi-pencil-square"></i>
  <div>
    <strong>{{ $rascunhos }} {{ $rascunhos === 1 ? $rotulo . ' ainda não publicado' : $plural . ' ainda não publicados' }}:</strong>
    os atletas não foram avisados. Procure o selo "Rascunho" e use "Revisar e publicar".
  </div>
</div>
@endif
<ul class="nav nav-tabs mt-3 mb-3" id="abasDeJogos" role="tablist">
  @foreach ($secoes as $secao => $dados)
  <li class="nav-item" role="presentation">
    <button type="button" class="nav-link {{ $secao === $abaInicial ? 'active' : '' }}" id="aba-{{ $secao }}"
      data-bs-toggle="tab" data-bs-target="#painel-{{ $secao }}" role="tab" aria-controls="painel-{{ $secao }}"
      aria-selected="{{ $secao === $abaInicial ? 'true' : 'false' }}">
      <i class="bi {{ $dados['icone'] }} me-1"></i> {{ $dados['titulo'] }}
      <span class="badge bg-secondary ms-1">{{ $dados['jogos']->count() }}</span>
    </button>
  </li>
  @endforeach
</ul>

<div class="tab-content">
@foreach ($secoes as $secao => $dados)
<div class="tab-pane fade {{ $secao === $abaInicial ? 'show active' : '' }}" id="painel-{{ $secao }}" role="tabpanel" aria-labelledby="aba-{{ $secao }}">

@forelse ($dados['jogos'] as $jogo)
@php $ev = $jogo->evento; @endphp
<div class="card shadow-sm mb-3 js-bloco-jogo" data-id-jogo="{{ $jogo->id_jogo }}">
  <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-calendar2-event text-muted"></i>
    <strong>{{ $ev->data_evento_calendario->locale('pt_BR')->isoFormat('ddd, DD/MM/YYYY') }}</strong>
    <span class="text-muted">· {{ $ev->horario_texto }}</span>
    @if ($ev->local_evento_calendario)
      <span class="text-muted">· {{ $ev->local_evento_calendario }}</span>
    @endif
    @if ($ev->estaCancelado())
      <span class="badge-status inativo">Cancelado</span>
    @elseif ($jogo->temPlacar())
      <span class="badge bg-dark">{{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}</span>
    @endif
    @unless ($ev->estaPublicado())
      {{-- Rascunho: revisar os inscritos e a escalação na tela do jogo, onde fica o "Publicar e avisar os atletas" --}}
      <span class="badge bg-warning text-dark" title="Os atletas ainda não foram avisados">Rascunho</span>
      <a href="{{ route('admin.calendario.eventos.show', $jogo->id_evento) }}" class="btn btn-warning btn-sm ms-auto js-publicar-jogo">
        <i class="bi bi-send"></i> Revisar e publicar
      </a>
    @endunless
    <a href="{{ $linkDoJogo($jogo) }}" class="{{ $ev->estaPublicado() ? 'ms-auto ' : '' }}small">Ver o jogo <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="card-body">
    <div class="row g-3 align-items-center">
      @foreach ([$jogo->timeCasa, $jogo->timeVisitante] as $i => $time)
        @if ($i === 1)
        <div class="col-md-auto text-center fw-bold text-muted">VS</div>
        @endif
        <div class="col-md">
          <div class="small text-muted mb-1">{{ $i === 0 ? 'Mandante' : 'Visitante' }}</div>
          @php $escalados = $jogo->escalados[$time->id_time] ?? 0; @endphp
          @include('admin.partials.cartao-time', [
              'link'     => route('admin.jogos.times.show', [$jogo->id_jogo, $time->id_time]),
              'contagem' => $escalados . ' escalado' . ($escalados != 1 ? 's' : '') . ' no jogo',
          ])
        </div>
      @endforeach
    </div>
  </div>
</div>
@empty
<p class="text-muted">{{ $secao === 'proximos' ? "Nenhum {$rotulo} marcado." : "Nenhum {$rotulo} realizado." }}</p>
@endforelse

</div>
@endforeach
</div>
