@extends('layout.admin')

@section('title', 'Jogos dos amistosos')

{{--
    Jogos dos amistosos (o item Amistosos do menu), só para ver: um bloco por jogo (data, horário, local,
    situação e o link para o jogo) com o cartão do mandante e o do visitante. Próximos primeiro, depois os
    últimos realizados. O time interno abre os jogadores do elenco (sem edição).
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

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

    <div class="admin-page-header">
      <div>
        <h1 class="page-title">Jogos dos amistosos</h1>
        <p class="page-subtitle">Cada amistoso com os dois times; clique num time interno para ver os jogadores</p>
      </div>
    </div>

    @if(session('erro'))
      <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('erro') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @include('admin.partials.blocos-de-jogos', [
        'rotulo'     => 'amistoso',
        'linkDoJogo' => fn ($jogo) => route('admin.jogos.index', ['campeonato' => 'amistoso', 'jogo' => $jogo->id_jogo]),
    ])

  </div>
</main>
@endsection
