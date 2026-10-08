@extends('layout.admin')

@section('title', 'Jogos — ' . $campeonato->nome_campeonato)

{{--
    Jogos do campeonato (o "Jogos" do campeonato no menu), só para ver, como nos Times dos amistosos: um bloco
    por jogo (próximos e últimos realizados) com o cartão do mandante e o do visitante, que abre os jogadores
    escalados por aquele time naquele jogo; no fim, os participantes que não aparecem em nenhum desses jogos,
    que abrem o elenco (sem edição). O filtro "Campeonato" troca de campeonato (cada opção é a URL da tela dele).
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    {{-- Linha de caminho: cada parte leva à lista dela; a última é esta tela --}}
    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small" id="caminhoTimes">
        @foreach ($caminho as [$rotulo, $url])
          @if ($url)
          <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
          @else
          <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
          @endif
        @endforeach
      </ol>
    </nav>

    <div class="admin-page-header d-flex flex-wrap align-items-end justify-content-between gap-3">
      <div>
        <h1 class="page-title">Jogos · {{ $campeonato->nome_campeonato }}</h1>
        <p class="page-subtitle">Cada jogo com os dois times; clique num time interno para ver os jogadores escalados</p>
      </div>
      <div>
        <label for="filtroCampeonato" class="form-label small text-muted mb-1">Campeonato</label>
        <select id="filtroCampeonato" class="form-select form-select-sm" style="min-width: 220px;"
                onchange="if (this.value) window.location.href = this.value">
          @foreach ($campeonatos as $opcao)
          <option value="{{ route('admin.campeonatos.times', $opcao->id_campeonato) }}" @selected($opcao->id_campeonato === $campeonato->id_campeonato)>
            {{ $opcao->nome_campeonato }}
          </option>
          @endforeach
        </select>
      </div>
    </div>

    @if(session('erro'))
      <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('erro') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @include('admin.partials.blocos-de-jogos', [
        'rotulo'     => 'jogo',
        'linkDoJogo' => fn ($jogo) => route('admin.jogos.index', ['campeonato' => $campeonato->id_campeonato, 'jogo' => $jogo->id_jogo]),
    ])

    {{-- Participantes que não aparecem em nenhum jogo acima (ainda sem jogo marcado, ou só em jogos mais
         antigos): o cartão abre o elenco do time --}}
    @if ($semJogo->isNotEmpty())
    <h2 class="h6 text-uppercase text-muted fw-bold mt-4 mb-2">Participantes sem jogo na lista</h2>
    <div class="row g-3" id="participantesSemJogo">
      @foreach ($semJogo as $time)
      <div class="col-md-4 col-lg-3" data-id-time="{{ $time->id_time }}">
        @include('admin.partials.cartao-time', ['link' => route('admin.campeonatos.times.show', [$campeonato->id_campeonato, $time->id_time])])
      </div>
      @endforeach
    </div>
    @endif

  </div>
</main>
@endsection
