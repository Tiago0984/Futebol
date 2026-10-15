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

            @if($campeonato->categorias->isNotEmpty())
            <div class="camp-hero-cats">
                @foreach($campeonato->categorias as $categoria)
                <span class="camp-hero-cat">{{ $categoria->rotulo }}</span>
                @endforeach
            </div>
            @endif

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
     CATEGORIAS (Fase H, Etapa H5): uma aba por categoria, cada uma com os times, os jogos (com a fase) e a
     classificação de cada grupo (chave), com os classificados destacados. Uma categoria só: sem abas.
     ====================================================== --}}
@php $comAbas = $abas->count() > 1; @endphp
@if($comAbas)
<section class="camp-cat-tabs-section">
    <div class="camp-show-container">
        <div class="camp-cat-tabs" role="tablist" id="abasCategorias">
            @foreach($abas as $i => $aba)
            <button type="button" class="camp-cat-tab-btn {{ $i === 0 ? 'is-active' : '' }}" role="tab"
                    data-alvo="cat-{{ $aba['categoria']->id_categoria }}">{{ $aba['categoria']->rotulo }}</button>
            @endforeach
        </div>
    </div>
</section>
@endif

@foreach($abas as $i => $aba)
<div class="{{ $comAbas ? 'camp-cat-panel' . ($i === 0 ? ' is-active' : '') : '' }}"
     @if($comAbas) id="cat-{{ $aba['categoria']->id_categoria }}" role="tabpanel" @endif>

{{-- Times participantes da categoria --}}
<section class="camp-teams-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">{{ $aba['categoria']?->rotulo ?? 'Edição ' . \Carbon\Carbon::parse($campeonato->data_inicio_campeonato)->format('Y') }}</span>
            <h2>Times Participantes</h2>
            <p>{{ $aba['times']->count() }} {{ $aba['times']->count() == 1 ? 'time inscrito' : 'times inscritos' }}</p>
        </div>

        @if($aba['times']->isEmpty())
        <p class="camp-empty-msg">Nenhum time inscrito ainda.</p>
        @else
        <div class="camp-teams-grid">
            @foreach ($aba['times'] as $time)
            {{-- Só o escudo, o nome e o selo, sem lista de jogadores (Fase G, Etapa 4): o atleta é da AACJ,
                 não de um time; os jogadores ficam na página Jogadores --}}
            @php $daAcj = strtolower($time->tipo_time) === 'interno'; @endphp
            <div class="camp-team-card" title="{{ $time->nome_time }}">
                <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                     alt="{{ $time->nome_time }}"
                     class="camp-team-logo {{ $daAcj ? '' : 'camp-team-logo-externo' }}">
                <p class="camp-team-name">{{ $time->nome_time }}</p>
                <span class="camp-team-type-tag {{ $daAcj ? 'tag-interno' : 'tag-externo' }}">{{ $daAcj ? 'AACJ' : 'Externo' }}</span>
            </div>
            @endforeach
        </div>
        @endif

    </div>
</section>

{{-- Jogos da categoria (com a fase quando não é a de grupos) --}}
@if($aba['jogos']->count() > 0)
<section class="camp-matches-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">Resultados</span>
            <h2>Jogos</h2>
            <p>{{ $aba['jogos']->count() }} {{ $aba['jogos']->count() == 1 ? 'jogo registrado' : 'jogos registrados' }}</p>
        </div>

        <div class="camp-matches-list">
            {{-- Os jogos já vêm ordenados pela data do evento --}}
            @foreach ($aba['jogos'] as $jogo)
            @php $dataJogo = $jogo->evento->data_evento_calendario; @endphp
            <div class="match-card">

                <div class="match-date-col">
                    <span class="match-day">{{ $dataJogo->format('d') }}</span>
                    <span class="match-month">{{ $dataJogo->format('M') }}</span>
                    <span class="match-year">{{ $dataJogo->format('Y') }}</span>
                </div>

                <div class="match-body">
                    <div class="match-team match-team-home">
                        <img src="{{ asset('futebol/images/team/' . $jogo->timeCasa->logo_time) }}"
                             alt="{{ $jogo->timeCasa->nome_time }}"
                             class="match-team-logo">
                        <span class="match-team-name">{{ $jogo->timeCasa->nome_time }}</span>
                    </div>

                    <div class="match-score">
                        @if($jogo->fase_jogo && ! $jogo->contaNaClassificacao())
                            <span class="match-fase">{{ $jogo->fase_label }}</span>
                        @endif
                        @if($jogo->estaCancelado())
                            <span class="event-selo-cancelado"><i class="fa fa-ban"></i> Cancelado</span>
                        @elseif($jogo->temPlacar())
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

{{-- Classificação de cada grupo (chave): só a fase de grupos --}}
<section class="camp-table-section">
    <div class="camp-show-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">Tabela</span>
            <h2>Classificação</h2>
        </div>

        @php $comLinhas = collect($aba['tabelas'])->contains(fn ($t) => ! empty($t['tabela'])); @endphp
        @if(! $comLinhas)
        <p class="camp-empty-msg">Nenhum jogo cadastrado para gerar a classificação.</p>
        @else
        @foreach($aba['tabelas'] as $tabela)
        @if($tabela['grupo'])
        <h3 class="camp-grupo-titulo">{{ $tabela['grupo'] }}</h3>
        @endif
        @if(empty($tabela['tabela']))
        <p class="camp-empty-msg">Nenhum time neste grupo ainda.</p>
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
                    @foreach ($tabela['tabela'] as $pos => $time)
                    @php $classificado = $tabela['classificados'] && $pos < $tabela['classificados']; @endphp
                    <tr class="
                        @if($classificado) camp-classificado
                        @elseif($pos === 0) camp-pos-1
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
                            @if($classificado)<span class="camp-classificado-tag">Classificado</span>@endif
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
        @if($tabela['classificados'])
        <p class="camp-classificados-legenda">Passam {{ $tabela['classificados'] === 1 ? 'o primeiro' : "os {$tabela['classificados']} primeiros" }} de cada grupo (chave).</p>
        @endif
        @endif
        @endforeach
        @endif

    </div>
</section>

</div>
@endforeach

@if($comAbas)
<script>
// Abas das categorias: mostra o painel da aba clicada
document.querySelectorAll('#abasCategorias .camp-cat-tab-btn').forEach(function (botao) {
    botao.addEventListener('click', function () {
        document.querySelectorAll('#abasCategorias .camp-cat-tab-btn').forEach(function (b) { b.classList.remove('is-active'); });
        document.querySelectorAll('.camp-cat-panel').forEach(function (p) { p.classList.remove('is-active'); });
        botao.classList.add('is-active');
        document.getElementById(botao.dataset.alvo).classList.add('is-active');
    });
});
</script>
@endif


@endsection
