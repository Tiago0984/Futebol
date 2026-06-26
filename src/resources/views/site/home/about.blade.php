<!-- JOGOS & CLASSIFICAÇÃO — seletor dinâmico por campeonato -->
<section class="home-matches-section">
    <div class="container">

        <div class="home-matches-header">
            <span class="section-subtitle-tag">Competições</span>
            <h2 class="home-matches-title">Jogos &amp; Classificação</h2>
        </div>

        @if($campeonatos->isEmpty())
        <p class="home-tab-empty">Nenhum campeonato cadastrado.</p>
        @else
        <div class="home-camp-picker">

            {{-- Seletor de campeonatos (esquerda) --}}
            <div class="home-camp-list">
                @foreach($campeonatos as $camp)
                <div class="home-camp-card {{ $loop->first ? 'is-active' : '' }}"
                     data-camp-id="{{ $camp->id_campeonato }}">
                    <img src="{{ asset('futebol/images/campeonatos/' . $camp->logo_evento) }}"
                         alt="{{ $camp->nome_campeonato }}"
                         class="home-camp-logo">
                    <div class="home-camp-info">
                        <span class="home-camp-type">{{ $camp->tipo_campeonato }}</span>
                        <strong class="home-camp-name">{{ $camp->nome_campeonato }}</strong>
                    </div>
                    <i class="fa fa-chevron-right home-camp-arrow"></i>
                </div>
                @endforeach
            </div>

            {{-- Painéis de conteúdo (direita) --}}
            <div class="home-camp-content">
                @foreach($campeonatos as $camp)
                <div class="home-camp-panel {{ $loop->first ? 'is-active' : '' }}"
                     data-camp-id="{{ $camp->id_campeonato }}">

                    {{-- Cabeçalho do painel com link para o campeonato --}}
                    <div class="home-camp-panel-header">
                        <div class="home-camp-panel-title">
                            <span class="home-camp-panel-type">{{ $camp->tipo_campeonato }}</span>
                            <h3 class="home-camp-panel-name">{{ $camp->nome_campeonato }}</h3>
                        </div>
                        <a href="{{ route('campeonato.show', $camp->id_campeonato) }}"
                           class="btn-home-camp-link">
                            Ver campeonato <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>

                    {{-- Botões de aba --}}
                    <div class="home-camp-tabs">
                        <button class="home-camp-tab-btn is-active"
                                data-target="jogos-{{ $camp->id_campeonato }}">
                            <i class="fa fa-futbol-o"></i> Jogos
                        </button>
                        <button class="home-camp-tab-btn"
                                data-target="class-{{ $camp->id_campeonato }}">
                            <i class="fa fa-list-ol"></i> Classificação
                        </button>
                    </div>

                    {{-- Aba: Jogos (prévia — 4 mais recentes) --}}
                    <div class="home-camp-tab-panel is-active" id="jogos-{{ $camp->id_campeonato }}">
                        @php $jogosPrevia = $camp->jogos->sortByDesc('data_jogo')->take(4); @endphp
                        @forelse($jogosPrevia as $jogo)
                        <div class="home-match-row">
                            <span class="home-match-date">
                                {{ $jogo->data_jogo ? \Carbon\Carbon::parse($jogo->data_jogo)->format('d M') : '-' }}
                            </span>
                            <div class="home-match-teams">
                                <span class="home-match-home">{{ $jogo->timeCasa->nome_time ?? '-' }}</span>
                                <span class="home-match-sep">
                                    @if($jogo->placar_time_casa_jogos !== null && $jogo->placar_time_visitante_jogos !== null)
                                        {{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}
                                    @else
                                        VS
                                    @endif
                                </span>
                                <span class="home-match-away">{{ $jogo->timeVisitante->nome_time ?? '-' }}</span>
                            </div>
                        </div>
                        @empty
                        <p class="home-tab-empty">Nenhum jogo registrado neste campeonato.</p>
                        @endforelse
                        @if($camp->jogos->count() > 4)
                        <div class="home-tab-ver-mais">
                            <a href="{{ route('campeonato.show', $camp->id_campeonato) }}">
                                Ver todos os {{ $camp->jogos->count() }} jogos <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                        @endif
                    </div>

                    {{-- Aba: Classificação (prévia — top 4) --}}
                    <div class="home-camp-tab-panel" id="class-{{ $camp->id_campeonato }}">
                        @php
                            $classif      = $classificacaoPorCampeonato[$camp->id_campeonato] ?? [];
                            $classifPrevia = array_slice($classif, 0, 4);
                        @endphp
                        @if(empty($classifPrevia))
                        <p class="home-tab-empty">Nenhum dado de classificação ainda.</p>
                        @else
                        <div style="overflow-x:auto;">
                        <table class="home-class-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Time</th>
                                    <th title="Vitórias">V</th>
                                    <th title="Empates">E</th>
                                    <th title="Derrotas">D</th>
                                    <th title="Pontos">PTS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($classifPrevia as $i => $time)
                                <tr class="{{ $i === 0 ? 'home-class-leader' : '' }}">
                                    <td class="home-class-pos">{{ $i + 1 }}</td>
                                    <td class="home-class-name">{{ $time['nome'] }}</td>
                                    <td class="stat-v">{{ $time['v'] }}</td>
                                    <td>{{ $time['e'] }}</td>
                                    <td class="stat-d">{{ $time['d'] }}</td>
                                    <td><strong>{{ $time['pontos'] }}</strong></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        @if(count($classif) > 4)
                        <div class="home-tab-ver-mais">
                            <a href="{{ route('campeonato.show', $camp->id_campeonato) }}">
                                Ver classificação completa ({{ count($classif) }} times) <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                        @endif
                        @endif
                    </div>

                </div>
                @endforeach
            </div>

        </div>
        @endif

    </div>
</section>

