{{-- Súmula do jogo (Etapa S1): gols dos times da AACJ (autor, assistência, gol contra) e cartões. Só no admin,
     sem aviso ao atleta. Corrigir = remover e lançar de novo. Regras em JogoGol::errosPara e Cartao::erroPara. --}}
@php
    $errosSumula = $errors->getBag('sumula');
    $timesDoJogo = [(int) $jogo->id_time_casa => $jogo->timeCasa, (int) $jogo->id_time_visitante => $jogo->timeVisitante];
    $selectsDeAtletas = function (string $vazio, $selecionado) use ($sumula) {
        $html = '<option value="">' . e($vazio) . '</option>';
        foreach ($sumula['escalados'] as $idTime => $time) {
            $html .= '<optgroup label="' . e($time['nome']) . '">';
            foreach ($time['atletas'] as $a) {
                $html .= '<option value="' . $a['id'] . '" data-time="' . $idTime . '"' . ((string) $selecionado === (string) $a['id'] ? ' selected' : '') . '>'
                    . ($a['camisa'] ? e($a['camisa']) . ' · ' : '') . e($a['nome']) . '</option>';
            }
            $html .= '</optgroup>';
        }

        return $html;
    };
@endphp
<div class="card shadow-sm mt-4" id="sumula">
    <div class="card-header admin-card-topo">
        <i class="bi bi-clipboard-check me-2"></i> Súmula
        <span class="fw-normal small">· gols ({{ $sumula['gols']->count() }}) e cartões ({{ $sumula['cartoes']->count() }})</span>
    </div>
    <div class="card-body">
        @if ($errosSumula->any())
            <div class="alert alert-danger py-2" id="errosSumula">
                <ul class="mb-0 ps-3">@foreach ($errosSumula->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $jogo->temPlacar())
            <p class="text-muted mb-0" id="sumulaSemPlacar">
                <i class="bi bi-info-circle"></i> Lance o placar do jogo (botão <strong>Placar</strong>, no topo) para registrar quem fez os gols e os cartões.
            </p>
        @else
            {{-- Situação de cada time da AACJ: gols no placar x lançados (faltar é só aviso) --}}
            <div class="d-flex flex-wrap gap-2 mb-3" id="resumoSumula">
                @foreach ($sumula['resumo'] as $linha)
                    <span class="badge {{ $linha['faltam'] ? 'bg-warning-subtle text-warning-emphasis border border-warning' : 'bg-success-subtle text-success-emphasis border border-success' }} fw-normal py-2 px-3">
                        <strong>{{ $linha['nome'] }}</strong>: {{ $linha['placar'] }} gol(s) no placar · {{ $linha['lancados'] }} lançado(s)
                        @if ($linha['faltam']) · falta(m) {{ $linha['faltam'] }} @else <i class="bi bi-check2"></i> @endif
                    </span>
                @endforeach
            </div>

            {{-- Gols lançados --}}
            <h6 class="fw-semibold"><i class="bi bi-dribbble"></i> Gols</h6>
            <table class="table table-sm align-middle mb-3" id="golsDaSumula">
                <thead>
                    <tr>
                        <th style="width:110px">Quando</th>
                        <th>Time</th>
                        <th>Gol</th>
                        <th>Assistência</th>
                        <th class="small">Lançado por</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sumula['gols'] as $gol)
                    <tr>
                        <td class="small">{{ $gol->minuto_jogo_gol ? $gol->minuto_jogo_gol . "'" : '—' }}
                            @if ($gol->periodo_jogo_gol === 'PRORROGACAO')<span class="badge bg-secondary">prorr.</span>@endif</td>
                        <td>{{ $gol->time?->nome_time }}</td>
                        <td>
                            @if ($gol->ehContra())
                                {{ $gol->atleta ? $gol->atleta->nome_atleta : 'Adversário' }} <span class="badge bg-danger-subtle text-danger-emphasis">Gol contra</span>
                            @else
                                {{ $gol->atleta?->nome_atleta ?? 'Autor não identificado' }}
                                @if ($gol->tipo_jogo_gol === 'PENALTI')<span class="badge bg-info-subtle text-info-emphasis">Pênalti</span>@endif
                            @endif
                            @if ($gol->id_atleta && ! in_array((int) $gol->id_atleta, $sumula['id_escalados'], true))
                                <small class="d-block text-warning-emphasis js-autor-fora"><i class="bi bi-exclamation-triangle"></i> não está mais escalado neste jogo</small>
                            @endif
                        </td>
                        <td>{{ $gol->assistente?->nome_atleta ?? '—' }}</td>
                        <td class="text-muted small">{{ $gol->usuario?->nome_usuario ?? '—' }}<br>{{ $gol->data_jogo_gol?->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.jogos.gols.destroy', [$jogo->id_jogo, $gol->id_jogo_gol]) }}"
                                  onsubmit="return confirm('Remover este gol da súmula?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">Nenhum gol lançado.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('admin.jogos.gols.store', $jogo->id_jogo) }}" class="row g-2 align-items-end border rounded p-2 mb-4 bg-body-tertiary" id="formGol">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="gol_time">Gol do time</label>
                    <select name="id_time" id="gol_time" class="form-select form-select-sm" required>
                        @foreach ($timesDoJogo as $idTime => $time)
                            <option value="{{ $idTime }}" @selected((int) old('id_time', array_key_first($sumula['escalados'])) === $idTime)>{{ $time->nome_time }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="gol_tipo">Tipo</label>
                    <select name="tipo_jogo_gol" id="gol_tipo" class="form-select form-select-sm">
                        @foreach (\App\Models\JogoGol::TIPOS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('tipo_jogo_gol', 'NORMAL') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="gol_autor">Autor</label>
                    <select name="id_atleta" id="gol_autor" class="form-select form-select-sm">{!! $selectsDeAtletas('Não identificado', old('id_atleta')) !!}</select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="gol_assist">Assistência</label>
                    <select name="id_atleta_assistencia" id="gol_assist" class="form-select form-select-sm">{!! $selectsDeAtletas('Sem assistência', old('id_atleta_assistencia')) !!}</select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0" for="gol_minuto">Minuto</label>
                    <input type="number" min="1" max="{{ \App\Models\JogoGol::MINUTO_MAX }}" name="minuto_jogo_gol" id="gol_minuto"
                           class="form-control form-control-sm" placeholder="—" value="{{ old('minuto_jogo_gol') }}">
                </div>
                @if ($jogo->temProrrogacao())
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="gol_periodo">Período</label>
                    <select name="periodo_jogo_gol" id="gol_periodo" class="form-select form-select-sm">
                        @foreach (\App\Models\JogoGol::PERIODOS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('periodo_jogo_gol', 'TEMPO_NORMAL') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="periodo_jogo_gol" value="TEMPO_NORMAL">
                @endif
                <div class="col-md-1 ms-auto text-end">
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-plus-lg"></i> Gol</button>
                </div>
                <div class="col-12 small text-muted" id="dicaGol">
                    Lance os gols dos times da AACJ. O gol contra de um atleta da AACJ vai no time que ganhou o gol; o do adversário, no time da AACJ, sem autor.
                </div>
            </form>

            {{-- Cartões --}}
            <h6 class="fw-semibold"><i class="bi bi-file-earmark"></i> Cartões</h6>
            <table class="table table-sm align-middle mb-3" id="cartoesDaSumula">
                <thead>
                    <tr><th>Atleta</th><th>Cartão</th><th class="small">Lançado por</th><th style="width:50px"></th></tr>
                </thead>
                <tbody>
                    @forelse ($sumula['cartoes'] as $cartao)
                    <tr>
                        <td>{{ $cartao->atleta?->nome_atleta }}</td>
                        <td><span class="badge {{ $cartao->tipo_cartao === 'VERMELHO' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ \App\Models\Cartao::TIPOS[$cartao->tipo_cartao] ?? $cartao->tipo_cartao }}</span></td>
                        <td class="text-muted small">{{ $cartao->usuario?->nome_usuario ?? '—' }}<br>{{ $cartao->data_cartao?->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.jogos.cartoes.destroy', [$jogo->id_jogo, $cartao->id_cartao]) }}"
                                  onsubmit="return confirm('Remover este cartão da súmula?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">Nenhum cartão lançado.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('admin.jogos.cartoes.store', $jogo->id_jogo) }}" class="row g-2 align-items-end border rounded p-2 bg-body-tertiary" id="formCartao">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small mb-0" for="cartao_atleta">Atleta</label>
                    <select name="id_atleta" id="cartao_atleta" class="form-select form-select-sm" required>{!! $selectsDeAtletas('— Selecionar —', old('id_atleta')) !!}</select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="cartao_tipo">Cartão</label>
                    <select name="tipo_cartao" id="cartao_tipo" class="form-select form-select-sm">
                        @foreach (\App\Models\Cartao::TIPOS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected(old('tipo_cartao') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 ms-auto text-end">
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-plus-lg"></i> Cartão</button>
                </div>
            </form>
        @endif
    </div>
</div>

@if ($jogo->temPlacar())
<script>
// Autor e assistência só do time certo: no gol, quem joga pelo time que marcou; no gol contra, quem joga pelo outro
// time (vazio = o adversário fez contra), sem assistência. O servidor confere de novo (JogoGol::errosPara).
document.addEventListener('DOMContentLoaded', function () {
    const time = document.getElementById('gol_time'), tipo = document.getElementById('gol_tipo');
    const autor = document.getElementById('gol_autor'), assist = document.getElementById('gol_assist');
    const times = @js(array_map('intval', array_keys($timesDoJogo)));

    function filtrar(select, idTime) {
        select.querySelectorAll('option[data-time]').forEach(function (op) {
            const ok = Number(op.dataset.time) === idTime;
            op.hidden = !ok; op.disabled = !ok;
            if (!ok && op.selected) select.value = '';
        });
        select.querySelectorAll('optgroup').forEach(function (g) { g.hidden = !g.querySelector('option:not([hidden])'); });
    }

    function atualizar() {
        const idTime = Number(time.value), contra = tipo.value === 'CONTRA';
        const outro = times.find(function (t) { return t !== idTime; });
        filtrar(autor, contra ? outro : idTime);
        autor.options[0].text = contra ? 'Adversário (gol contra dele)' : 'Não identificado';
        filtrar(assist, idTime);
        assist.disabled = contra;
        if (contra) assist.value = '';
    }

    time.addEventListener('change', atualizar);
    tipo.addEventListener('change', atualizar);
    atualizar();
});
</script>
@endif
