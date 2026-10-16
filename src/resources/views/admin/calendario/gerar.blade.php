@extends('layout.admin')

@section('title', 'Gerar agenda: ' . \App\Models\EventoCalendario::rotuloDoMes($previa['mes']))

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-8">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700;">
                        Gerar agenda: {{ \App\Models\EventoCalendario::rotuloDoMes($previa['mes']) }}
                    </h1>
                    <p class="text-muted mb-0">Prévia: nada foi gravado ainda.</p>
                </div>
                <div class="col-sm-4 text-end">
                    <a href="{{ route('admin.calendario.index', ['tab' => 'grade']) }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar à grade
                    </a>
                </div>
            </div>

            @if (session('erro'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('erro') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Trocar de mês (só os permitidos: o atual e os seguintes) --}}
            <form method="GET" action="{{ route('admin.calendario.grade.previa') }}" class="d-flex align-items-center gap-2 mb-3">
                <label for="previa_mes" class="form-label mb-0">Mês</label>
                <select id="previa_mes" name="mes" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    @foreach($mesesGeracao as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($valor === $mes)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </form>

            @php $totais = $previa['totais']; @endphp

            <div class="alert alert-light border mb-3">
                <strong>{{ $totais['eventos'] }}</strong> evento(s) novo(s) ·
                <strong>{{ $totais['inscricoes'] }}</strong> inscrição(ões) ·
                {{ $totais['existentes'] }} já gerado(s) ·
                {{ $totais['nao_geram'] }} horário(s) que não gera(m)
                @if ($totais['puladas'] > 0)
                    · {{ $totais['puladas'] }} de hoje já passou(aram)
                @endif
                @if ($totais['desmarcadas'] > 0)
                    · {{ $totais['desmarcadas'] }} data(s) desmarcada(s)
                @endif
                <div class="text-muted mt-1" style="font-size:0.85rem;">
                    Só de hoje em diante. Evento já gerado não é recriado, mesmo que tenha sido cancelado, ocultado ou mudado de dia.
                    Feriado ou dia sem aula: desmarque a data (ou o dia inteiro, abaixo). Ela não é gerada e, se o mês for
                    gerado de novo, volta a aparecer marcada.
                </div>
            </div>

            {{-- Treino no turno oposto ao da escola: quem está sem período escolar não entra em nenhum treino --}}
            @if ($totais['sem_periodo'])
            <div class="alert alert-warning mb-3" role="alert" id="avisoSemPeriodo">
                <strong><i class="bi bi-clock"></i> {{ count($totais['sem_periodo']) }} atleta(s) ativo(s) sem período escolar</strong>
                não {{ count($totais['sem_periodo']) === 1 ? 'será inscrito' : 'serão inscritos' }} nos treinos:
                {{ implode(', ', $totais['sem_periodo']) }}.
                Preencha o período em <a href="{{ route('admin.atletas.index') }}">Atletas</a> e atualize a prévia.
            </div>
            @endif

            {{-- Dia sem treino: desmarca a data em todas as linhas (feriado, dia sem aula) --}}
            @php
                $diasDoLote = $previa['linhas']
                    ->flatMap(fn ($l) => $l['novas']->map(fn ($d) => [$d, true])->concat($l['desmarcadas']->map(fn ($d) => [$d, false])))
                    ->groupBy(fn ($par) => $par[0]->toDateString())
                    ->sortKeys();
            @endphp
            @if ($diasDoLote->isNotEmpty())
            <div class="card mb-3" id="diasSemTreino">
                <div class="card-body py-2">
                    <div class="small fw-semibold mb-1"><i class="bi bi-calendar-x"></i> Dias com treino novo
                        <span class="fw-normal text-muted">· desmarque um dia para não gerar nenhum treino nele</span></div>
                    @foreach ($diasDoLote as $dia => $pares)
                        @php $data = $pares->first()[0]; @endphp
                        <label class="form-check form-check-inline mb-0 me-3 small">
                            <input type="checkbox" class="form-check-input js-dia" value="{{ $dia }}" @checked($pares->contains(fn ($p) => $p[1]))>
                            <span class="js-rotulo-data">{{ $data->locale('pt_BR')->isoFormat('ddd DD/MM') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Conflitos de horário do lote (Etapa 3): real pede confirmação; mesmo dia só informa --}}
            @php
                $conflitos = $previa['conflitos'];
                $reais     = $conflitos[\App\Models\EventoCalendario::CONFLITO_REAL];
                $fracos    = $conflitos[\App\Models\EventoCalendario::CONFLITO_FRACO];
            @endphp
            @if ($reais->isNotEmpty())
                <div class="alert alert-warning mb-3" role="alert">
                    <strong><i class="bi bi-exclamation-triangle"></i> Conflito de horário</strong>:
                    {{ $conflitos['totais']['reais'] }} conflito(s) em {{ $conflitos['totais']['dias'] }} dia(s), {{ $conflitos['totais']['atletas'] }} atleta(s).
                    Nada é gerado sem confirmar.
                    @include('admin.calendario._grupos_conflito', ['grupos' => $reais])
                </div>
            @endif
            @if ($fracos->isNotEmpty())
                <div class="alert alert-info mb-3" role="alert">
                    <strong><i class="bi bi-info-circle"></i> Mesmo dia — confira o horário</strong>:
                    {{ $conflitos['totais']['fracos'] }} aviso(s); algum evento sem horário de início. Não impede a geração.
                    @include('admin.calendario._grupos_conflito', ['grupos' => $fracos])
                </div>
            @endif

            <div class="table-card mb-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th>Horário da grade</th>
                                <th>Datas novas</th>
                                <th>Já geradas</th>
                                <th class="text-center">Atletas por evento</th>
                                <th class="text-center">Inscrições</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($previa['linhas'] as $linha)
                            @php $grade = $linha['grade']; @endphp
                            <tr class="{{ $linha['motivo'] ? 'text-muted' : '' }}">
                                <td style="white-space:nowrap;">{{ $grade->dia_label }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $grade->rotulo }}</span>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        {{ $grade->horario_texto }} · {{ $grade->local_grade_treino }}
                                    </div>
                                </td>
                                @if ($linha['motivo'])
                                    <td colspan="4"><i class="bi bi-slash-circle"></i> Não gera: {{ $linha['motivo'] }}</td>
                                @else
                                    <td style="font-size:0.85rem;">
                                        {{-- Cada data nova é uma caixa: desmarcada, não é gerada (feriado, dia sem aula) --}}
                                        @php
                                            $datasDaLinha = $linha['novas']->map(fn ($d) => [$d, true])
                                                ->concat($linha['desmarcadas']->map(fn ($d) => [$d, false]))
                                                ->sortBy(fn ($par) => $par[0]->timestamp);
                                        @endphp
                                        @forelse ($datasDaLinha as [$data, $marcada])
                                            <label class="form-check form-check-inline mb-0 me-2">
                                                <input type="checkbox" class="form-check-input js-data" name="marcadas[]" form="formGerarAgenda"
                                                       value="{{ \App\Models\GradeTreino::chaveDaData($grade->id_grade_treino, $data) }}"
                                                       data-dia="{{ $data->toDateString() }}" @checked($marcada)>
                                                <span class="js-rotulo-data">{{ $data->format('d/m') }}</span>
                                            </label>
                                        @empty
                                            —
                                        @endforelse
                                        @foreach ($linha['puladas'] as $data)
                                            <div class="text-warning-emphasis"><i class="bi bi-clock"></i> {{ $data->format('d/m') }}: hoje, já passou; não será gerado</div>
                                        @endforeach
                                    </td>
                                    <td style="font-size:0.85rem;">
                                        @forelse ($linha['existentes'] as $existente)
                                            {{ $existente['data']->format('d/m') }}@if ($existente['status'] !== 'ATIVO') ({{ mb_strtolower(\App\Models\EventoCalendario::STATUS[$existente['status']] ?? $existente['status']) }})@endif{{ $loop->last ? '' : ',' }}
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        @if (count($linha['atletas']) === 0)
                                            <span class="badge bg-warning text-dark">0 atletas</span>
                                        @else
                                            {{ count($linha['atletas']) }}
                                        @endif
                                        {{-- Só quem treina neste turno (o oposto ao da escola); no fim de semana, todos --}}
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            {{ $linha['origem'] === 'CATEGORIA' ? 'da categoria' : 'ativos' }}
                                            {{ match ($linha['turno']) {
                                                'MANHA' => 'que treinam de manhã',
                                                'TARDE' => 'que treinam à tarde',
                                                default => '(fim de semana: todos)',
                                            } }}
                                        </div>
                                        @if ($linha['semPeriodo'])
                                            <div class="text-warning-emphasis js-sem-periodo-linha" style="font-size:0.75rem;"
                                                 title="{{ implode(', ', $linha['semPeriodo']) }}">
                                                <i class="bi bi-clock"></i> {{ count($linha['semPeriodo']) }} sem período escolar
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $linha['novas']->count() * count($linha['atletas']) }}</td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                $temConflito = $totais['conflitos_reais'] > 0;
                $rotuloMes = \App\Models\EventoCalendario::rotuloDoMes($previa['mes']);
                $temDatas = $diasDoLote->isNotEmpty();
            @endphp
            {{-- As caixas das datas (form="formGerarAgenda") vão junto: só as marcadas são geradas --}}
            <form method="POST" action="{{ route('admin.calendario.grade.gerar') }}" id="formGerarAgenda"
                  class="d-flex flex-wrap align-items-center gap-2"
                  data-mes="{{ $rotuloMes }}" data-conflitos="{{ $totais['conflitos_reais'] }}">
                @csrf
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="escolha" value="1">
                @if ($temConflito)
                    <input type="hidden" name="confirmar_conflito" value="1">
                @endif
                <button type="submit" class="btn {{ $temConflito ? 'btn-warning' : 'btn-primary' }}" @disabled($totais['eventos'] === 0)>
                    <i class="bi bi-calendar-plus"></i>
                    {{ $temConflito ? 'Confirmar mesmo assim e gerar' : 'Gerar' }} <span class="js-total-eventos">{{ $totais['eventos'] }}</span> evento(s)
                </button>
                @if ($temDatas)
                    <a href="#" class="btn btn-outline-secondary" id="atualizarPrevia"
                       data-url="{{ route('admin.calendario.grade.previa') }}" data-valor-mes="{{ $mes }}">
                        <i class="bi bi-arrow-repeat"></i> Atualizar prévia
                    </a>
                    <span class="text-warning-emphasis small d-none" id="previaDesatualizada">
                        <i class="bi bi-exclamation-circle"></i> Você mudou as datas: os totais e os conflitos acima são da prévia anterior.
                        Use "Atualizar prévia" para conferir (o "Gerar" confere de novo de qualquer jeito).
                    </span>
                @else
                    <span class="text-muted ms-2">Nada novo para gerar neste mês.</span>
                @endif
            </form>

        </div>
    </main>
@endsection

@push('scripts')
<style>
    /* Data desmarcada: riscada, não será gerada */
    .js-data:not(:checked) + .js-rotulo-data, .js-dia:not(:checked) + .js-rotulo-data { text-decoration: line-through; color: #9ca3af; }
</style>
<script>
    (function () {
        const form = document.getElementById('formGerarAgenda');
        if (!form) return;
        const datas  = () => Array.from(document.querySelectorAll('.js-data'));
        const botao  = form.querySelector('button[type="submit"]');
        const total  = form.querySelector('.js-total-eventos');
        const aviso  = document.getElementById('previaDesatualizada');
        const contar = () => datas().filter(c => c.checked).length;

        function atualizar() {
            const n = contar();
            if (total) total.textContent = n;
            botao.disabled = n === 0;
            aviso?.classList.remove('d-none');
            // O dia fica marcado se alguma linha ainda tem treino nele
            document.querySelectorAll('.js-dia').forEach(dia => {
                dia.checked = datas().some(c => c.dataset.dia === dia.value && c.checked);
            });
        }

        datas().forEach(c => c.addEventListener('change', atualizar));
        document.querySelectorAll('.js-dia').forEach(dia => dia.addEventListener('change', function () {
            datas().filter(c => c.dataset.dia === this.value).forEach(c => { c.checked = this.checked; });
            atualizar();
        }));

        // Atualizar prévia: recarrega com as datas marcadas (totais e conflitos recalculados no servidor)
        document.getElementById('atualizarPrevia')?.addEventListener('click', function (e) {
            e.preventDefault();
            const params = new URLSearchParams({ mes: this.dataset.valorMes, escolha: '1' });
            datas().filter(c => c.checked).forEach(c => params.append('marcadas[]', c.value));
            window.location.href = this.dataset.url + '?' + params.toString();
        });

        // Confirmação com o número de agora; clique duplo: depois de confirmar, o botão fica desabilitado
        form.addEventListener('submit', function (e) {
            const conflitos = Number(form.dataset.conflitos);
            const pergunta = 'Gerar ' + contar() + ' evento(s) em ' + form.dataset.mes
                + (conflitos > 0 ? ', com até ' + conflitos + ' conflito(s) de horário' : '')
                + '? Não dá para desfazer pela tela.';
            if (!confirm(pergunta)) { e.preventDefault(); return; }
            setTimeout(() => { botao.disabled = true; botao.textContent = 'Gerando...'; }, 0);
        });
    })();
</script>
@endpush
