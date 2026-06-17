<!-- LIGA PREMIERE SECTION -->
<div class="lp-section">
    <div class="lp-overlay">
        <div class="container">

            <div class="lp-header">
                @if($proximoJogo && $proximoJogo->campeonato)
                <a href="{{ route('campeonato.show', $proximoJogo->campeonato->id_campeonato) }}" class="lp-label lp-label--link">
                    <i class="fa fa-trophy"></i>
                    {{ strtoupper($proximoJogo->campeonato->nome_campeonato) }}
                </a>
                @else
                <span class="lp-label">
                    <i class="fa fa-trophy"></i>
                    LIGA PREMIERE
                </span>
                @endif
            </div>

            @if($proximoJogo)
            <div class="row lp-match-row">

                {{-- Time da Casa --}}
                <div class="col-sm-4 col-xs-12">
                    <div class="lp-team lp-team--home">
                        <div class="lp-team-logo-wrap">
                            <img src="{{ asset('futebol/images/team/' . ($proximoJogo->timeCasa->logo_time ?? 'time-azul.png')) }}"
                                 alt="{{ $proximoJogo->timeCasa->nome_time ?? 'Time A' }}"
                                 class="lp-team-logo" />
                        </div>
                        <div class="lp-team-name">{{ strtoupper($proximoJogo->timeCasa->nome_time ?? 'TIME A') }}</div>
                        <div class="lp-team-badge">CASA</div>
                    </div>
                </div>

                {{-- Centro: Placar ou VS + data + local --}}
                <div class="col-sm-4 col-xs-12">
                    <div class="lp-center">
                        @if($proximoJogo->placar_time_casa_jogos !== null && $proximoJogo->placar_time_visitante_jogos !== null)
                            <div class="lp-score">
                                <span class="lp-score-num">{{ $proximoJogo->placar_time_casa_jogos }}</span>
                                <span class="lp-score-sep">:</span>
                                <span class="lp-score-num">{{ $proximoJogo->placar_time_visitante_jogos }}</span>
                            </div>
                            <div class="lp-result-label">RESULTADO FINAL</div>
                        @else
                            <div class="lp-vs">VS</div>
                        @endif
                        <div class="lp-divider"></div>
                        <div class="lp-date">
                            <i class="fa fa-calendar-o"></i>
                            {{ $proximoJogo->data_jogo ? $proximoJogo->data_jogo->format('d M, H:i') : '—' }}
                        </div>
                        @if($proximoJogo->campeonato && $proximoJogo->campeonato->local_evento)
                        <div class="lp-location">
                            <i class="fa fa-map-marker"></i>
                            {{ strtoupper($proximoJogo->campeonato->local_evento) }}
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Time Visitante --}}
                <div class="col-sm-4 col-xs-12">
                    <div class="lp-team lp-team--away">
                        <div class="lp-team-logo-wrap">
                            <img src="{{ asset('futebol/images/team/' . ($proximoJogo->timeVisitante->logo_time ?? 'time-visitante.png')) }}"
                                 alt="{{ $proximoJogo->timeVisitante->nome_time ?? 'Time B' }}"
                                 class="lp-team-logo" />
                        </div>
                        <div class="lp-team-name lp-team-name--red">{{ strtoupper($proximoJogo->timeVisitante->nome_time ?? 'TIME B') }}</div>
                        <div class="lp-team-badge lp-team-badge--away">VISITANTE</div>
                    </div>
                </div>

            </div>
            @else
            <div class="lp-empty">
                <i class="fa fa-calendar-times-o"></i>
                <p>Nenhuma partida agendada no momento.</p>
            </div>
            @endif

        </div>
    </div>
</div>
