<section class="cal-events-section">
    <div class="container-cal">

        {{-- PRÓXIMO EVENTO EM DESTAQUE --}}
        @if($proximoEvento)
        @php
            $mesesProx = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            $dtProx    = $proximoEvento->data_evento_calendario;
        @endphp
        <div class="cal-next-event">
            <div class="cal-next-left">
                <span class="cal-next-badge"><i class="fa fa-bell"></i> Próximo Evento</span>
                <div class="cal-next-date-block">
                    <span class="cal-next-day">{{ $dtProx->format('d') }}</span>
                    <span class="cal-next-month">{{ strtoupper($mesesProx[$dtProx->month]) }} {{ $dtProx->year }}</span>
                </div>
            </div>
            <div class="cal-next-right">
                <span class="event-type-tag tag-{{ $proximoEvento->tipo_class }}" style="margin-bottom:10px; display:inline-block;">{{ $proximoEvento->subtipo_evento_calendario }}</span>
                <h3 class="cal-next-title">{{ $proximoEvento->titulo_evento_calendario }}</h3>
                <p class="cal-next-meta">
                    <i class="fa fa-clock-o"></i> {{ \Carbon\Carbon::parse($proximoEvento->horario_inicio_evento_calendario)->format('H:i') }} &nbsp;&nbsp;
                    <i class="fa fa-map-marker"></i> {{ $proximoEvento->local_evento_calendario }}
                </p>
            </div>
            <div class="cal-next-countdown">
                <span class="cal-next-days-label">Em</span>
                <span class="cal-next-days-num" id="cal-days-left" data-date="{{ $dtProx->format('Y-m-d') }}">—</span>
                <span class="cal-next-days-label">dias</span>
            </div>
        </div>
        @endif

        <div class="cal-filter-bar">
            <span class="filter-label">Filtrar por:</span>
            <div class="filter-buttons">
                <button class="btn-cal-filter is-active" data-filter="all">
                    <i class="fa fa-list"></i> Todos <span class="filter-count">{{ $eventos->count() }}</span>
                </button>
                <button class="btn-cal-filter" data-filter="jogo">
                    <i class="fa fa-dot-circle-o"></i> Jogos / Confrontos <span class="filter-count">{{ $eventos->where('tipo_evento_calendario', 'JOGO')->count() }}</span>
                </button>
                <button class="btn-cal-filter" data-filter="treino">
                    <i class="fa fa-male"></i> Treinos Especiais <span class="filter-count">{{ $eventos->where('tipo_evento_calendario', 'TREINO')->count() }}</span>
                </button>
                <button class="btn-cal-filter" data-filter="campeonato">
                    <i class="fa fa-trophy"></i> Campeonatos <span class="filter-count">{{ $eventos->where('tipo_evento_calendario', 'CAMPEONATO')->count() }}</span>
                </button>
            </div>
        </div>

        <div class="cal-events-timeline" id="cal-events-list">

            @php $meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; @endphp

            @forelse($eventos as $evento)
            @php
                $dt   = $evento->data_evento_calendario;
                $tipo = $evento->tipo_class;
            @endphp
            <div class="cal-event-card event-{{ $tipo }}">
                <div class="event-date-block">
                    <span class="event-day">{{ $dt->format('d') }}</span>
                    <span class="event-month">{{ $meses[$dt->month] }}</span>
                </div>
                <div class="event-details-block">
                    <div class="event-type-tag tag-{{ $tipo }}">{{ $evento->subtipo_evento_calendario }}</div>
                    <h3 class="event-title">{{ $evento->titulo_evento_calendario }}</h3>
                    <p class="event-meta">
                        <i class="fa fa-clock-o"></i> <strong>Horário:</strong>
                        {{ \Carbon\Carbon::parse($evento->horario_inicio_evento_calendario)->format('H:i') }}
                        @if($evento->horario_fim_evento_calendario)
                            às {{ \Carbon\Carbon::parse($evento->horario_fim_evento_calendario)->format('H:i') }}
                        @endif
                        &nbsp;|&nbsp;
                        <i class="fa fa-map-marker"></i> <strong>Local:</strong> {{ $evento->local_evento_calendario }}
                    </p>
                    @if($evento->descricao_evento_calendario)
                    <p class="event-description">{{ $evento->descricao_evento_calendario }}</p>
                    @endif
                </div>
            </div>
            @empty
            <p class="cal-empty-msg">Nenhum evento agendado no momento.</p>
            @endforelse

        </div>

        <div class="cal-no-results" id="cal-no-results" style="display:none;">
            <i class="fa fa-calendar-times-o"></i>
            <p>Nenhum evento encontrado para este filtro.</p>
        </div>

    </div>
</section>
