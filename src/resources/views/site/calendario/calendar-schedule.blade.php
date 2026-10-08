<section class="cal-schedule-section">
    <div class="container-cal">

        <div class="cal-schedule-header">
            <span class="section-subtitle-tag">Rotina Semanal</span>
            <h2>Grade de Treinos</h2>
            <p>Horários fixos dos treinos regulares por categoria. Eventos especiais e jogos são publicados na agenda acima.</p>
        </div>

        <div class="cal-schedule-grid">

            {{-- Um cartão por dia que tem horário ativo, na ordem da semana (GradeTreino::DIAS_SEMANA) --}}
            @foreach(\App\Models\GradeTreino::DIAS_SEMANA as $dia => $diaLabel)
                @if($gradeTreinos->has($dia))
                <div class="cal-schedule-card {{ $dia === 'sabado' ? 'cal-schedule-card--destaque' : '' }}">
                    <div class="cal-schedule-day">
                        <i class="fa fa-calendar-o"></i>
                        <span>{{ $diaLabel }}</span>
                    </div>
                    <div class="cal-schedule-body">
                        @foreach($gradeTreinos[$dia] as $item)
                        <div class="cal-schedule-row">
                            <span class="cal-schedule-cat {{ $item->cat_class }}">
                                @if($item->tipo_grade_treino === 'JOGO')
                                    <i class="fa fa-dot-circle-o"></i>
                                @endif
                                {{ $item->rotulo }}
                            </span>
                            <span class="cal-schedule-time">
                                <i class="fa fa-clock-o"></i>
                                @if($item->horario_obs_grade_treino)
                                    {{ $item->horario_obs_grade_treino }}
                                @else
                                    {{ \Carbon\Carbon::parse($item->horario_inicio_grade_treino)->format('H:i') }} – {{ \Carbon\Carbon::parse($item->horario_fim_grade_treino)->format('H:i') }}
                                @endif
                            </span>
                            <span class="cal-schedule-local"><i class="fa fa-map-marker"></i> {{ $item->local_grade_treino }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

        </div>

        <p class="cal-schedule-obs">
            <i class="fa fa-info-circle"></i>
            {{-- A frase do domingo só vale enquanto não houver horário ativo nele --}}
            @unless($gradeTreinos->has('domingo'))
                Os treinos de domingo são reservados para repouso.
            @endunless
            Alterações de horário são comunicadas com antecedência no grupo de WhatsApp dos responsáveis.
        </p>

    </div>
</section>
