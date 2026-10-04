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
                <div class="text-muted mt-1" style="font-size:0.85rem;">
                    Só de hoje em diante. Evento já gerado não é recriado, mesmo que tenha sido cancelado, ocultado ou mudado de dia.
                    Feriado: gere normalmente e cancele o evento do dia.
                </div>
            </div>

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
                                        {{ $linha['novas']->map->format('d/m')->implode(', ') ?: '—' }}
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
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            {{ $linha['origem'] === 'CATEGORIA' ? 'da categoria' : 'todos os ativos' }}
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $linha['novas']->count() * count($linha['atletas']) }}</td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.calendario.grade.gerar') }}" id="formGerarAgenda"
                  onsubmit="return confirm(@js('Gerar ' . $totais['eventos'] . ' evento(s) e ' . $totais['inscricoes'] . ' inscrição(ões) em ' . \App\Models\EventoCalendario::rotuloDoMes($previa['mes']) . '? Não dá para desfazer pela tela.'))">
                @csrf
                <input type="hidden" name="mes" value="{{ $mes }}">
                <button type="submit" class="btn btn-primary" @disabled($totais['eventos'] === 0)>
                    <i class="bi bi-calendar-plus"></i> Gerar {{ $totais['eventos'] }} evento(s)
                </button>
                @if ($totais['eventos'] === 0)
                    <span class="text-muted ms-2">Nada novo para gerar neste mês.</span>
                @endif
            </form>

        </div>
    </main>
@endsection

@push('scripts')
<script>
    // Clique duplo: depois de confirmar, o botão fica desabilitado (o servidor também não duplica)
    document.getElementById('formGerarAgenda')?.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) {
            const botao = this.querySelector('button[type="submit"]');
            setTimeout(() => { botao.disabled = true; botao.textContent = 'Gerando...'; }, 0);
        }
    });
</script>
@endpush
