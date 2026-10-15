@extends('layout.admin')

@section('title', $time->nome_time . ' — jogadores do jogo')

{{--
    Jogadores de um time num jogo (cartão do time nas telas de times por jogo), só leitura: os atletas ativos
    inscritos e escalados por aquele time naquele jogo. Jogo de campeonato: camisa da convocação e "Não
    convocado"; amistoso: só os nomes (os atletas são escolhidos no jogo). A escalação é feita na tela do
    jogo (link "Abrir o jogo").
--}}
@section('content')
@php $ev = $jogo->evento; $pelaConvocacao = $jogo->pelaConvocacao(); @endphp
<main class="app-main">
  <div class="container-fluid py-4">

    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small" id="caminhoEscalados">
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
      <div class="d-flex align-items-center gap-3">
        <a href="{{ $voltar }}" class="btn btn-sm btn-outline-secondary" title="Voltar" aria-label="Voltar" id="btnVoltarEscalados">
          <i class="bi bi-arrow-left"></i>
        </a>
        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="{{ $time->nome_time }}"
             style="width:40px;height:40px;object-fit:contain;border-radius:8px;"
             onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
        <div>
          <h1 class="page-title mb-0">{{ $time->nome_time }} · jogadores do jogo</h1>
          <p class="page-subtitle mb-0">
            {{ $jogo->timeCasa->nome_time }} x {{ $jogo->timeVisitante->nome_time }}
            · {{ $ev->data_evento_calendario->locale('pt_BR')->isoFormat('ddd, DD/MM/YYYY') }} · {{ $ev->horario_texto }}
            @if ($ev->local_evento_calendario) · {{ $ev->local_evento_calendario }} @endif
            @if ($ev->estaCancelado()) · <span class="badge-status inativo">Cancelado</span> @endif
          </p>
        </div>
      </div>
      <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-diagram-3"></i> Abrir o jogo (escalação)
      </a>
    </div>

    <div class="table-card">
      <div class="table-card-toolbar">
        <span class="tbl-count">{{ $escalados->count() }} escalado(s) pelo {{ $time->nome_time }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Atleta</th>
              @if ($pelaConvocacao)
              <th class="text-center">Camisa</th>
              <th class="text-center">Convocação</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @forelse ($escalados as $atleta)
            <tr class="linha-escalado" data-id-atleta="{{ $atleta->id_atleta }}">
              <td class="fw-semibold">{{ $atleta->nome_atleta }}</td>
              @if ($pelaConvocacao)
              {{-- Campeonato: a camisa é a da convocação por este time --}}
              <td class="text-center">{{ $atleta->camisa_convocacao ?: '—' }}</td>
              <td class="text-center">
                @if ($atleta->id_convocacao)
                  <span class="badge escal-badge-titular">Convocado</span>
                @else
                  <span class="badge text-bg-warning">Não convocado</span>
                @endif
              </td>
              @endif
            </tr>
            @empty
            <tr>
              <td colspan="{{ $pelaConvocacao ? 3 : 1 }}" class="text-center text-muted py-4">
                Nenhum atleta escalado pelo {{ $time->nome_time }} neste jogo. Escale na tela do jogo
                ("{{ $pelaConvocacao ? 'Preencher pela convocação' : 'Escolher atletas' }}").
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>
@endsection
