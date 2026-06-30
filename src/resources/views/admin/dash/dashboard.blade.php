@extends('layout.admin')

@section('content')
<main class="app-main">

  {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <p class="dash-greeting-label">Bem-vindo de volta,</p>
          <h3 class="dash-greeting-name">{{ Auth::guard('admin')->user()->nome_usuario }}</h3>
        </div>
        <div class="col-sm-5 text-sm-end">
          <p class="dash-date">{{ \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      {{-- ── KPI CARDS ──────────────────────────────────────────────────── --}}
      <div class="row mb-4 g-3">

        <div class="col-6 col-lg-3">
          <a href="{{ route('admin.atletas.index') }}" class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
            <div class="kpi-body">
              <div class="kpi-number">{{ $stats['atletas_ativos'] }}</div>
              <div class="kpi-label">Atletas Ativos</div>
            </div>
            <i class="bi bi-arrow-right kpi-arrow"></i>
          </a>
        </div>

        <div class="col-6 col-lg-3">
          <a href="{{ route('admin.campeonatos.index') }}" class="kpi-card kpi-dark">
            <div class="kpi-icon"><i class="bi bi-trophy"></i></div>
            <div class="kpi-body">
              <div class="kpi-number">{{ $stats['campeonatos'] }}</div>
              <div class="kpi-label">Campeonatos</div>
            </div>
            <i class="bi bi-arrow-right kpi-arrow"></i>
          </a>
        </div>

        <div class="col-6 col-lg-3">
          <a href="{{ route('admin.jogos.index') }}" class="kpi-card kpi-slate">
            <div class="kpi-icon"><i class="bi bi-calendar-event"></i></div>
            <div class="kpi-body">
              <div class="kpi-number">{{ $stats['jogos'] }}</div>
              <div class="kpi-label">Jogos Cadastrados</div>
            </div>
            <i class="bi bi-arrow-right kpi-arrow"></i>
          </a>
        </div>

        <div class="col-6 col-lg-3">
          <a href="{{ route('admin.matriculas.index') }}" class="kpi-card {{ $stats['matriculas_pendentes'] > 0 ? 'kpi-warning' : 'kpi-slate' }}">
            <div class="kpi-icon"><i class="bi bi-clipboard-check"></i></div>
            <div class="kpi-body">
              <div class="kpi-number">{{ $stats['matriculas_pendentes'] }}</div>
              <div class="kpi-label">Matrículas Pendentes</div>
            </div>
            @if($stats['matriculas_pendentes'] > 0)
              <i class="bi bi-exclamation-circle kpi-arrow"></i>
            @else
              <i class="bi bi-check-circle kpi-arrow"></i>
            @endif
          </a>
        </div>

      </div>

      {{-- ── CORPO PRINCIPAL ────────────────────────────────────────────── --}}
      <div class="row g-3">

        {{-- Acesso Rápido ──────────────────────────────────────────────── --}}
        <div class="col-lg-8">
          <div class="dash-panel">
            <div class="dash-panel-header">
              <i class="bi bi-grid-3x3-gap"></i> Acesso Rápido
            </div>
            <div class="dash-panel-body">

              {{-- Conteúdo do Site --}}
              <div class="quick-group">
                <p class="quick-group-label">Conteúdo do Site</p>
                <div class="quick-tiles">
                  <a href="{{ route('admin.noticias.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#e31c1c;">
                      <i class="bi bi-newspaper"></i>
                    </div>
                    <span class="qt-label">Notícias</span>
                    <span class="qt-count">{{ $stats['noticias'] }}</span>
                  </a>
                  <a href="{{ route('admin.banners.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#b51212;">
                      <i class="bi bi-image"></i>
                    </div>
                    <span class="qt-label">Banners</span>
                    <span class="qt-count">{{ $stats['banners'] }}</span>
                  </a>
                  <a href="{{ route('admin.galeria.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#c0392b;">
                      <i class="bi bi-images"></i>
                    </div>
                    <span class="qt-label">Galeria</span>
                    <span class="qt-count">{{ $stats['galerias'] }}</span>
                  </a>
                  <a href="{{ route('admin.videos.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#7c3aed;">
                      <i class="bi bi-camera-video"></i>
                    </div>
                    <span class="qt-label">Vídeos</span>
                    <span class="qt-count">{{ $stats['videos'] ?? 0 }}</span>
                  </a>
                </div>
              </div>

              {{-- Esporte --}}
              <div class="quick-group">
                <p class="quick-group-label">Esporte</p>
                <div class="quick-tiles">
                  <a href="{{ route('admin.campeonatos.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#374151;">
                      <i class="bi bi-trophy"></i>
                    </div>
                    <span class="qt-label">Campeonatos</span>
                    <span class="qt-count">{{ $stats['campeonatos'] }}</span>
                  </a>
                  <a href="{{ route('admin.times.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#1f2937;">
                      <i class="bi bi-shield-fill"></i>
                    </div>
                    <span class="qt-label">Times</span>
                    <span class="qt-count">{{ $stats['times'] }}</span>
                  </a>
                  <a href="{{ route('admin.categorias.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#111827;">
                      <i class="bi bi-tags"></i>
                    </div>
                    <span class="qt-label">Categorias</span>
                  </a>
                  <a href="{{ route('admin.jogos.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#4b5563;">
                      <i class="bi bi-calendar-event"></i>
                    </div>
                    <span class="qt-label">Jogos</span>
                    <span class="qt-count">{{ $stats['jogos'] }}</span>
                  </a>
                  <a href="{{ route('admin.calendario.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#6b7280;">
                      <i class="bi bi-calendar3"></i>
                    </div>
                    <span class="qt-label">Calendário</span>
                  </a>
                  <a href="{{ route('admin.escalacao.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#0891b2;">
                      <i class="bi bi-people-fill"></i>
                    </div>
                    <span class="qt-label">Escalação</span>
                  </a>
                </div>
              </div>

              {{-- Pessoas --}}
              <div class="quick-group">
                <p class="quick-group-label">Pessoas</p>
                <div class="quick-tiles">
                  <a href="{{ route('admin.atletas.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:#e31c1c;">
                      <i class="bi bi-person-badge"></i>
                    </div>
                    <span class="qt-label">Atletas</span>
                    <span class="qt-count">{{ $stats['atletas_ativos'] }}</span>
                  </a>
                  <a href="{{ route('admin.matriculas.index') }}" class="quick-tile">
                    <div class="qt-icon" style="background:{{ $stats['matriculas_pendentes'] > 0 ? '#d97706' : '#374151' }};">
                      <i class="bi bi-clipboard-check"></i>
                    </div>
                    <span class="qt-label">Matrículas</span>
                    @if($stats['matriculas_pendentes'] > 0)
                      <span class="qt-badge-warn">{{ $stats['matriculas_pendentes'] }}</span>
                    @endif
                  </a>
                </div>
              </div>

            </div>
          </div>
        </div>

        {{-- Feed de Atividade ─────────────────────────────────────────── --}}
        <div class="col-lg-4">

          {{-- Matrículas Pendentes --}}
          <div class="dash-panel mb-3">
            <div class="dash-panel-header">
              <i class="bi bi-clipboard-check"></i> Matrículas Pendentes
              <a href="{{ route('admin.matriculas.index') }}" class="dash-panel-link">Ver todas</a>
            </div>
            <div class="dash-feed">
              @forelse($ultimasMatriculas as $m)
              <div class="feed-item">
                <div class="feed-avatar" style="background:var(--aacj-red);">
                  {{ strtoupper(substr($m->nome_atleta, 0, 1)) }}
                </div>
                <div class="feed-info">
                  <span class="feed-name">{{ $m->nome_atleta }}</span>
                  <span class="feed-meta">Aguardando aprovação</span>
                </div>
                <a href="{{ route('admin.matriculas.show', $m->id_atleta) }}" class="feed-action" title="Visualizar">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
              @empty
              <div class="feed-empty">
                <i class="bi bi-check-circle-fill text-success"></i>
                Tudo em dia!
              </div>
              @endforelse
            </div>
          </div>

          {{-- Últimos Jogos --}}
          <div class="dash-panel">
            <div class="dash-panel-header">
              <i class="bi bi-calendar-event"></i> Últimos Jogos
              <a href="{{ route('admin.jogos.index') }}" class="dash-panel-link">Ver todos</a>
            </div>
            <div class="dash-feed">
              @forelse($ultimosJogos as $j)
              <div class="feed-item">
                <div class="feed-avatar" style="background:#374151;">
                  <i class="bi bi-trophy-fill" style="font-size:.85rem;"></i>
                </div>
                <div class="feed-info">
                  <span class="feed-name">
                    {{ Str::limit($j->timeCasa?->nome_time ?? '?', 10) }}
                    <span style="color:var(--aacj-text-muted); font-weight:400;">vs</span>
                    {{ Str::limit($j->timeVisitante?->nome_time ?? '?', 10) }}
                  </span>
                  <span class="feed-meta">
                    {{ \Carbon\Carbon::parse($j->data_jogo)->format('d/m/Y') }}
                    &bull; {{ Str::limit($j->campeonato?->nome_campeonato ?? '—', 18) }}
                  </span>
                </div>
                @if(!is_null($j->placar_time_casa_jogos))
                  <span class="feed-score">{{ $j->placar_time_casa_jogos }}×{{ $j->placar_time_visitante_jogos }}</span>
                @else
                  <span class="feed-badge">{{ $j->status_jogo }}</span>
                @endif
              </div>
              @empty
              <div class="feed-empty">
                <i class="bi bi-calendar-x"></i> Sem jogos cadastrados.
              </div>
              @endforelse
            </div>
          </div>

        </div>

      </div>
      {{-- end::corpo principal --}}

    </div>
  </div>
</main>
@endsection