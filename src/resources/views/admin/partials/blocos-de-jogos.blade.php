{{--
    Jogos dos amistosos e Jogos do campeonato: um bloco por jogo, com data, horário, local, "Cancelado" ou
    placar, o nome do campeonato no centro (identifica o jogo na opção "Todos"), e os cartões do mandante e
    do visitante. Jogo em rascunho: selo e "Revisar e
    publicar" (tela do jogo), com um aviso no topo contando quantos faltam.
    $proximos, $realizados (Jogo::separadosParaTelaDeTimes); $rotulo = "amistoso" ou "jogo" (textos).
    Sem o antigo "Ver o jogo" (a lista de Jogos só com ele): a tela do jogo abre pelo botão de inscritos.
    O cartão do time abre os jogadores escalados por ele
    naquele jogo (admin.jogos.times.show); $jogo->escalados = quantos por time (Jogo::separadosParaTelaDeTimes).
    Próximos e realizados em abas (visual das abas do Calendário, sem recarregar a página): abre em "Próximos";
    sem nenhum próximo, em "Já realizados".
    À direita do cabeçalho, as ações do jogo (admin.jogos._acoes): a página precisa incluir o modal de edição
    (admin.jogos.modals.edit, com voltarParaPagina) e o admin.jogos._script_form. Jogo ocultado sai da tela
    (mostrar de novo: lista de Jogos, situação Oculto).
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
  {{-- Três partes: data e selos | nome do campeonato (centralizado; o amistoso não tem) | ações.
       As laterais dividem o espaço por igual (flex 1 1 0), para o nome ficar no meio do cabeçalho --}}
  <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2" style="flex: 1 1 0;">
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
        <span class="badge bg-warning text-dark" title="Os atletas ainda não foram avisados">Rascunho</span>
      @endunless
    </div>
    @if ($jogo->campeonato)
      {{-- Logo do campeonato (a mesma da tela de Campeonatos); sem logo, o troféu --}}
      <div class="d-flex align-items-center gap-2 fw-semibold text-center px-2 js-campeonato-do-jogo">
        @if ($jogo->campeonato->logo_evento)
          <img src="{{ asset('futebol/images/campeonatos/' . $jogo->campeonato->logo_evento) }}" alt=""
               style="width:32px;height:32px;object-fit:contain;">
        @else
          <i class="bi bi-trophy text-muted"></i>
        @endif
        {{ $jogo->campeonato->nome_campeonato }}
      </div>
    @endif
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2" style="flex: 1 1 0;">
      @unless ($ev->estaPublicado())
        {{-- Rascunho: revisar os inscritos e a escalação na tela do jogo, onde fica o "Publicar e avisar os atletas" --}}
        <a href="{{ route('admin.calendario.eventos.show', $jogo->id_evento) }}" class="btn btn-warning btn-sm js-publicar-jogo">
          <i class="bi bi-send"></i> Revisar e publicar
        </a>
      @endunless
      {{-- Inscritos, editar, cancelar/reativar e ocultar, como na lista de Jogos --}}
      <div class="d-flex gap-1 js-acoes-jogo">
        @include('admin.jogos._acoes')
      </div>
    </div>
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
