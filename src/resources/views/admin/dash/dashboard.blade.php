@extends('layout.admin')

@section('title', 'Dashboard')

{{--
    Página inicial do painel (09/11/2026; dados em App\Services\InicioDoPainel): o que precisa de atenção (só os
    itens com algo pendente, cada um com o caminho para resolver), os números do dia, a agenda dos próximos
    7 dias, os últimos resultados e o acesso rápido nos mesmos grupos do menu.
--}}
@section('content')
@php
  $usuario = Auth::guard('admin')->user();
  $primeiroNome = \Illuminate\Support\Str::before(trim($usuario->nome_usuario), ' ') ?: $usuario->nome_usuario;
@endphp
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho :titulo="'Olá, ' . $primeiroNome">
      <x-slot:subtitulo class="dash-data">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</x-slot:subtitulo>
    </x-admin.cabecalho>

    {{-- ── Números do dia ─────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4" id="numerosDoPainel">
      <div class="col-6 col-xl-3">
        <a href="{{ route('admin.atletas.index') }}" class="kpi-card kpi-red">
          <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
          <div class="kpi-body">
            <div class="kpi-number">{{ $numeros['atletas_ativos'] }}</div>
            <div class="kpi-label">Atletas ativos</div>
            @if ($numeros['afastados_hoje'])
            <div class="kpi-extra">{{ $numeros['afastados_hoje'] }} afastado(s) hoje</div>
            @endif
          </div>
          <i class="bi bi-arrow-right kpi-arrow"></i>
        </a>
      </div>

      <div class="col-6 col-xl-3">
        <a href="{{ route('admin.calendario.index') }}" class="kpi-card kpi-dark">
          <div class="kpi-icon"><i class="bi bi-calendar-week"></i></div>
          <div class="kpi-body">
            <div class="kpi-number">{{ $numeros['atividades_semana'] }}</div>
            <div class="kpi-label">Atividades nos próximos {{ \App\Services\InicioDoPainel::DIAS_DA_AGENDA }} dias</div>
          </div>
          <i class="bi bi-arrow-right kpi-arrow"></i>
        </a>
      </div>

      <div class="col-6 col-xl-3">
        <a href="{{ route('admin.campeonatos.index') }}" class="kpi-card kpi-slate">
          <div class="kpi-icon"><i class="bi bi-trophy"></i></div>
          <div class="kpi-body">
            <div class="kpi-number">{{ $numeros['campeonatos'] }}</div>
            <div class="kpi-label">Campeonatos ativos</div>
          </div>
          <i class="bi bi-arrow-right kpi-arrow"></i>
        </a>
      </div>

      <div class="col-6 col-xl-3">
        <a href="{{ route('admin.matriculas.index') }}" class="kpi-card {{ $numeros['matriculas_pendentes'] ? 'kpi-warning' : 'kpi-slate' }}">
          <div class="kpi-icon"><i class="bi bi-clipboard-check"></i></div>
          <div class="kpi-body">
            <div class="kpi-number">{{ $numeros['matriculas_pendentes'] }}</div>
            <div class="kpi-label">Matrículas pendentes</div>
          </div>
          <i class="bi {{ $numeros['matriculas_pendentes'] ? 'bi-exclamation-circle' : 'bi-check-circle' }} kpi-arrow"></i>
        </a>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-8">

        {{-- ── Precisa da sua atenção ─────────────────────────────────────── --}}
        <x-admin.secao titulo="Precisa da sua atenção" icone="exclamation-triangle" id="atencaoDoPainel"
                       ajuda="Só aparece o que tem algo pendente. Cada item leva à tela que resolve.">
          @forelse ($pendencias as $item)
          <div class="dash-atencao dash-atencao--{{ $item['nivel'] }}">
            <i class="bi bi-{{ $item['icone'] }} dash-atencao__icone"></i>
            <div class="dash-atencao__textos">
              <div class="dash-atencao__texto">{{ $item['texto'] }}</div>
              @if (! empty($item['detalhe']))
              <div class="dash-atencao__detalhe">{{ $item['detalhe'] }}</div>
              @endif
              @if (! empty($item['jogos']))
              <ul class="dash-atencao__jogos">
                @foreach ($item['jogos'] as [$texto, $url])
                <li><a href="{{ $url }}">{{ $texto }}</a></li>
                @endforeach
                @if ($item['mais'])
                <li class="text-body-secondary">e mais {{ $item['mais'] }}</li>
                @endif
              </ul>
              @endif
            </div>
            @if (! empty($item['url']))
            <a href="{{ $item['url'] }}" class="btn btn-sm btn-outline-secondary dash-atencao__acao">{{ $item['rotulo'] }}</a>
            @endif
          </div>
          @empty
          <div class="dash-vazio">
            <i class="bi bi-check-circle-fill text-success"></i> Tudo em dia: nada pendente agora.
          </div>
          @endforelse
        </x-admin.secao>

        {{-- ── Agenda dos próximos dias ──────────────────────────────────── --}}
        <x-admin.secao titulo="Agenda dos próximos {{ \App\Services\InicioDoPainel::DIAS_DA_AGENDA }} dias" icone="calendar3" id="agendaDoPainel"
                       ajuda="Treinos, jogos e outros eventos de hoje em diante, sem os ocultos e os que já terminaram.">
          <x-slot:acoes>
            <a href="{{ route('admin.calendario.index') }}" class="btn btn-sm btn-outline-secondary">Ver calendário</a>
          </x-slot:acoes>
          @forelse ($agenda as $evento)
          @php $jogo = $evento->jogo; @endphp
          <a href="{{ route('admin.calendario.eventos.show', $evento->id_evento_calendario) }}" class="dash-linha">
            <div class="dash-linha__data">
              <span class="dash-linha__dia">{{ $evento->data_evento_calendario->isToday() ? 'Hoje' : $evento->data_evento_calendario->locale('pt_BR')->isoFormat('ddd') }}</span>
              <span class="dash-linha__numero">{{ $evento->data_evento_calendario->format('d/m') }}</span>
            </div>
            <div class="dash-linha__textos">
              <span class="dash-linha__titulo">{{ $evento->titulo_evento_calendario }}</span>
              <span class="dash-linha__meta">
                {{ \App\Models\EventoCalendario::TIPOS[$evento->tipo_evento_calendario] ?? $evento->tipo_evento_calendario }}
                @if ($jogo) · {{ $jogo->campeonato?->nome_campeonato ?? 'Amistoso' }} @endif
                · {{ $evento->horario_texto }}
                @if ($evento->local_evento_calendario) · {{ $evento->local_evento_calendario }} @endif
              </span>
            </div>
            <div class="dash-linha__selos">
              @if (! $evento->estaPublicado())
              <span class="badge bg-warning-subtle text-warning-emphasis">Rascunho</span>
              @endif
              @if ($evento->estaCancelado())
              <span class="badge bg-danger-subtle text-danger-emphasis">Cancelado</span>
              @endif
              <span class="badge bg-body-secondary text-body-secondary" title="Atletas ativos inscritos">
                <i class="bi bi-people"></i> {{ $evento->inscritos_ativos }}
              </span>
            </div>
          </a>
          @empty
          <div class="dash-vazio"><i class="bi bi-calendar-x"></i> Nenhuma atividade nos próximos {{ \App\Services\InicioDoPainel::DIAS_DA_AGENDA }} dias.</div>
          @endforelse
          @if ($numeros['atividades_semana'] > $agenda->where('status_evento_calendario', 'ATIVO')->count())
          <p class="dash-rodape">Mostrando as {{ $agenda->count() }} primeiras. As outras estão no Calendário.</p>
          @endif
        </x-admin.secao>

      </div>

      <div class="col-xl-4">

        {{-- ── Últimos resultados ─────────────────────────────────────────── --}}
        <x-admin.secao titulo="Últimos resultados" icone="flag-fill" id="resultadosDoPainel"
                       ajuda="Os jogos que já aconteceram, do mais recente ao mais antigo.">
          <x-slot:acoes>
            <a href="{{ route('admin.jogos.index') }}" class="btn btn-sm btn-outline-secondary">Ver jogos</a>
          </x-slot:acoes>
          @forelse ($resultados as $jogo)
          <a href="{{ route('admin.calendario.eventos.show', $jogo->id_evento) }}" class="dash-linha">
            <div class="dash-linha__textos">
              <span class="dash-linha__titulo">{{ $jogo->timeCasa?->nome_time ?? '?' }} x {{ $jogo->timeVisitante?->nome_time ?? '?' }}</span>
              <span class="dash-linha__meta">
                {{ $jogo->evento->data_evento_calendario->format('d/m/Y') }} · {{ $jogo->campeonato?->nome_campeonato ?? 'Amistoso' }}
              </span>
            </div>
            <div class="dash-linha__selos">
              @if ($jogo->evento->estaCancelado())
              <span class="badge bg-danger-subtle text-danger-emphasis">Cancelado</span>
              @elseif (is_null($jogo->placar_time_casa_jogos))
              <span class="badge bg-warning-subtle text-warning-emphasis">Sem placar</span>
              @else
              <span class="dash-placar">{{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}</span>
              @endif
            </div>
          </a>
          @empty
          <div class="dash-vazio"><i class="bi bi-flag"></i> Nenhum jogo realizado ainda.</div>
          @endforelse
        </x-admin.secao>

        {{-- ── Acesso rápido (mesmos grupos do menu) ──────────────────────── --}}
        <x-admin.secao titulo="Acesso rápido" icone="grid-3x3-gap" id="atalhosDoPainel">
          @foreach ($atalhos as $grupo => $dados)
          <div class="quick-group">
            <p class="quick-group-label">{{ $grupo }}</p>
            <div class="quick-tiles">
              @foreach ($dados['itens'] as $atalho)
              @php [$rotulo, $icone, $url] = $atalho; $contador = $atalho[3] ?? null; $alerta = $atalho[4] ?? null; @endphp
              <a href="{{ $url }}" class="quick-tile">
                <div class="qt-icon qt-cor-{{ $dados['cor'] }}"><i class="bi bi-{{ $icone }}"></i></div>
                <span class="qt-label">{{ $rotulo }}</span>
                @if ($alerta)
                <span class="qt-badge-warn">{{ $alerta }}</span>
                @elseif (! is_null($contador))
                <span class="qt-count">{{ $contador }}</span>
                @endif
              </a>
              @endforeach
            </div>
          </div>
          @endforeach
        </x-admin.secao>

      </div>
    </div>

  </div>
</main>
@endsection
