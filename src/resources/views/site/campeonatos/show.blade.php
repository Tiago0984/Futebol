@extends('layout.site')

@section('content')

{{-- ======================================================
     HERO DO CAMPEONATO
     ====================================================== --}}
<section class="camp-hero-section"
         style="background-image: url('{{ asset('futebol/images/campeonatos/' . $campeonato->banner_evento) }}');">
    <div class="camp-hero-overlay"></div>
    <div class="camp-hero-inner">

        <div class="camp-hero-logo-wrap">
            <img src="{{ asset('futebol/images/campeonatos/' . $campeonato->logo_evento) }}"
                 alt="{{ $campeonato->nome_campeonato }}"
                 class="camp-hero-logo">
        </div>

        <div class="camp-hero-info">
            <span class="camp-hero-badge">{{ $campeonato->tipo_campeonato }}</span>
            <h1 class="camp-hero-title">{{ $campeonato->nome_campeonato }}</h1>

            <div class="camp-hero-meta">
                <div class="camp-hero-meta-row">
                    <i class="fa fa-calendar"></i>
                    <span>
                        {{ \Carbon\Carbon::parse($campeonato->data_inicio_campeonato)->format('d/m/Y') }}
                        &nbsp;até&nbsp;
                        {{ \Carbon\Carbon::parse($campeonato->data_fim_campeonato)->format('d/m/Y') }}
                    </span>
                </div>
                <div class="camp-hero-meta-row">
                    <i class="fa fa-map-marker"></i>
                    <span>{{ $campeonato->local_evento }}</span>
                </div>
                @if($campeonato->organizador_campeonato)
                <div class="camp-hero-meta-row">
                    <i class="fa fa-users"></i>
                    <span>{{ $campeonato->organizador_campeonato }}</span>
                </div>
                @endif
            </div>

            @if($campeonato->descricao_campeonato)
            <p class="camp-hero-desc">{{ $campeonato->descricao_campeonato }}</p>
            @endif
        </div>

    </div>
</section>

{{-- ======================================================
     TIMES PARTICIPANTES
     ====================================================== --}}
<section class="camp-teams-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">Edição {{ \Carbon\Carbon::parse($campeonato->data_inicio_campeonato)->format('Y') }}</span>
            <h2>Times Participantes</h2>
            <p>{{ $campeonato->times->count() }} {{ $campeonato->times->count() == 1 ? 'time inscrito' : 'times inscritos' }} nesta edição</p>
        </div>

        @if($campeonato->times->isEmpty())
        <p class="camp-empty-msg">Nenhum time inscrito ainda.</p>
        @else
        <div class="camp-teams-grid">
            @foreach ($campeonato->times as $time)
            @if(strtolower($time->tipo_time) === 'interno')
            {{-- Time interno: abre modal completo com elenco --}}
            <div class="camp-team-card camp-team-clickable"
                 data-toggle="modal"
                 data-target="#modalTime{{ $time->id_time }}"
                 title="Ver elenco de {{ $time->nome_time }}">
                <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                     alt="{{ $time->nome_time }}"
                     class="camp-team-logo">
                <p class="camp-team-name">{{ $time->nome_time }}</p>
                <span class="camp-team-type-tag tag-interno">INTERNO</span>
            </div>
            @else
            {{-- Time externo: abre modal simples informativo --}}
            <div class="camp-team-card camp-team-clickable camp-team-externo"
                 data-toggle="modal"
                 data-target="#modalTimeExterno"
                 data-nome="{{ $time->nome_time }}"
                 data-logo="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                 title="{{ $time->nome_time }} — Time externo">
                <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                     alt="{{ $time->nome_time }}"
                     class="camp-team-logo camp-team-logo-externo">
                <p class="camp-team-name">{{ $time->nome_time }}</p>
                <span class="camp-team-type-tag tag-externo">
                    <i class="fa fa-info-circle"></i> Externo
                </span>
            </div>
            @endif
            @endforeach
        </div>
        @endif

    </div>
</section>

{{-- ======================================================
     RESULTADOS / JOGOS
     ====================================================== --}}
@if($jogos->count() > 0)
<section class="camp-matches-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">Resultados</span>
            <h2>Jogos</h2>
            <p>{{ $jogos->count() }} {{ $jogos->count() == 1 ? 'jogo registrado' : 'jogos registrados' }} nesta edição</p>
        </div>

        <div class="camp-matches-list">
            @foreach ($jogos->sortBy('data_jogo') as $jogo)
            <div class="match-card">

                <div class="match-date-col">
                    <span class="match-day">{{ \Carbon\Carbon::parse($jogo->data_jogo)->format('d') }}</span>
                    <span class="match-month">{{ \Carbon\Carbon::parse($jogo->data_jogo)->format('M') }}</span>
                    <span class="match-year">{{ \Carbon\Carbon::parse($jogo->data_jogo)->format('Y') }}</span>
                </div>

                <div class="match-body">
                    <div class="match-team match-team-home">
                        <img src="{{ asset('futebol/images/team/' . $jogo->timeCasa->logo_time) }}"
                             alt="{{ $jogo->timeCasa->nome_time }}"
                             class="match-team-logo">
                        <span class="match-team-name">{{ $jogo->timeCasa->nome_time }}</span>
                    </div>

                    <div class="match-score">
                        @if($jogo->placar_time_casa_jogos !== null && $jogo->placar_time_visitante_jogos !== null)
                            <span class="score-value">{{ $jogo->placar_time_casa_jogos }}</span>
                            <span class="score-sep">×</span>
                            <span class="score-value">{{ $jogo->placar_time_visitante_jogos }}</span>
                        @else
                            <span class="score-tbd">VS</span>
                        @endif
                    </div>

                    <div class="match-team match-team-away">
                        <img src="{{ asset('futebol/images/team/' . $jogo->timeVisitante->logo_time) }}"
                             alt="{{ $jogo->timeVisitante->nome_time }}"
                             class="match-team-logo">
                        <span class="match-team-name">{{ $jogo->timeVisitante->nome_time }}</span>
                    </div>
                </div>

            </div>
            @endforeach
        </div>

    </div>
</section>
@endif

{{-- ======================================================
     CLASSIFICAÇÃO
     ====================================================== --}}
<section class="camp-table-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">Tabela</span>
            <h2>Classificação</h2>
        </div>

        @if(empty($classificacao))
        <p class="camp-empty-msg">Nenhum jogo cadastrado para gerar a classificação.</p>
        @else
        <div class="camp-table-wrapper">
            <table class="camp-table">
                <thead>
                    <tr>
                        <th class="col-pos">#</th>
                        <th class="col-team">Time</th>
                        <th title="Pontos">PTS</th>
                        <th title="Jogos">PJ</th>
                        <th title="Vitórias">V</th>
                        <th title="Empates">E</th>
                        <th title="Derrotas">D</th>
                        <th title="Gols Marcados">GM</th>
                        <th title="Gols Concedidos">GC</th>
                        <th title="Saldo de Gols">SG</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classificacao as $pos => $time)
                    <tr class="
                        @if($pos === 0) camp-pos-1
                        @elseif($pos === 1) camp-pos-2
                        @elseif($pos === 2) camp-pos-3
                        @endif
                    ">
                        <td class="col-pos">
                            @if($pos === 0)
                                <span class="camp-medal camp-medal-gold">1</span>
                            @elseif($pos === 1)
                                <span class="camp-medal camp-medal-silver">2</span>
                            @elseif($pos === 2)
                                <span class="camp-medal camp-medal-bronze">3</span>
                            @else
                                <span class="camp-pos-num">{{ $pos + 1 }}</span>
                            @endif
                        </td>
                        <td class="col-team">
                            @if($time['logo'])
                            <img src="{{ asset('futebol/images/team/' . $time['logo']) }}"
                                 alt="{{ $time['nome'] }}"
                                 class="camp-table-logo">
                            @endif
                            <span class="camp-table-team-name">{{ $time['nome'] }}</span>
                        </td>
                        <td><strong>{{ $time['pontos'] }}</strong></td>
                        <td>{{ $time['pj'] }}</td>
                        <td class="stat-v">{{ $time['v'] }}</td>
                        <td>{{ $time['e'] }}</td>
                        <td class="stat-d">{{ $time['d'] }}</td>
                        <td>{{ $time['gm'] }}</td>
                        <td>{{ $time['gc'] }}</td>
                        <td>{{ $time['gm'] - $time['gc'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</section>

{{-- Modais dos times internos (reutiliza o partial global) --}}
@php $times = $campeonato->times; @endphp
@include('partials.modal-times')

{{-- Modal informativo para times externos --}}
<div class="modal fade" id="modalTimeExterno" tabindex="-1" role="dialog" aria-labelledby="modalTimeExternoLabel">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none;">
            <div class="modal-body" style="padding: 0;">

                <div class="camp-ext-modal-header">
                    <button type="button" class="sport-close-btn" data-dismiss="modal" aria-label="Fechar">&times;</button>
                    <img id="extModalLogo" src="" alt="" class="camp-ext-modal-logo">
                    <h3 id="extModalNome" class="camp-ext-modal-nome"></h3>
                    <span class="camp-ext-modal-badge">Time Externo</span>
                </div>

                <div class="camp-ext-modal-body">
                    <div class="camp-ext-icon-block">
                        <i class="fa fa-lock"></i>
                    </div>
                    <p class="camp-ext-modal-text">
                        Este time é <strong>externo à AACJ</strong> e não possui elenco cadastrado na escolinha.
                        Apenas times da nossa escola têm o plantel disponível para consulta.
                    </p>
                    <button type="button" class="camp-ext-modal-btn" data-dismiss="modal">
                        Entendido
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.camp-team-externo').forEach(function(card) {
    card.addEventListener('click', function() {
        document.getElementById('extModalLogo').src = this.dataset.logo;
        document.getElementById('extModalNome').textContent = this.dataset.nome;
    });
});
</script>

@endsection
