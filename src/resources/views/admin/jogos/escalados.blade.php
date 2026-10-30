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

    <x-admin.cabecalho :titulo="$time->nome_time . ' · jogadores do jogo'"
                       :caminho="$caminho" id-caminho="caminhoEscalados"
                       :voltar="$voltar" id-voltar="btnVoltarEscalados"
                       :logo="asset('futebol/images/team/' . $time->logo_time)" :logo-alt="$time->nome_time"
                       :logo-padrao="asset('futebol/images/team/default-team.png')">
      <x-slot:subtitulo>
            {{ $jogo->timeCasa->nome_time }} x {{ $jogo->timeVisitante->nome_time }}
            · {{ $ev->data_evento_calendario->locale('pt_BR')->isoFormat('ddd, DD/MM/YYYY') }} · {{ $ev->horario_texto }}
            @if ($ev->local_evento_calendario) · {{ $ev->local_evento_calendario }} @endif
            @if ($ev->estaCancelado()) · <span class="badge-status inativo">Cancelado</span> @endif
      </x-slot:subtitulo>
      <x-slot:acoes>
      <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-diagram-3"></i> Abrir o jogo (escalação)
      </a>
      </x-slot:acoes>
    </x-admin.cabecalho>

    <div class="table-card">
      <div class="table-card-toolbar">
        <span class="tbl-count">{{ $escalados->count() }} escalado(s) pelo {{ $time->nome_time }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Atleta</th>
              <th>Posição</th>
              <th class="text-center">Titular/Reserva</th>
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
              {{-- Posição e titular/reserva gravados na tela do jogo (Fase D, Etapa D2) --}}
              <td>{{ $atleta->posicao_evento_atleta ? \App\Models\Atleta::POSICOES[$atleta->posicao_evento_atleta]['sigla'] . ' · ' . \App\Models\Atleta::POSICOES[$atleta->posicao_evento_atleta]['nome'] : '—' }}</td>
              <td class="text-center">{{ \App\Models\EventoAtleta::TITULARIDADES[$atleta->titular_evento_atleta] ?? 'A definir' }}</td>
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
              <td colspan="{{ $pelaConvocacao ? 5 : 3 }}" class="text-center text-muted py-4">
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
