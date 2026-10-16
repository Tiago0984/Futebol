{{--
    Posições do atleta (Fase D): até Atleta::POSICOES_MAX selects posicoes[], a principal primeiro, agrupados por setor.
    $prefixo: prefixo dos ids (cad/edit; o JS da edição preenche edit_posicao_1..3). $selecionadas: códigos já escolhidos.
--}}
@php
    $porSetor = collect(\App\Models\Atleta::POSICOES)->groupBy('setor', preserveKeys: true);
    $selecionadas = array_values($selecionadas ?? []);
@endphp
<div class="col-12">
    <label class="form-label mb-1">Posições <span class="text-muted small">(até {{ \App\Models\Atleta::POSICOES_MAX }}; a primeira é a principal)</span></label>
    <div class="row g-2">
        @for($i = 0; $i < \App\Models\Atleta::POSICOES_MAX; $i++)
        <div class="col-md-4">
            <select name="posicoes[]" id="{{ $prefixo }}_posicao_{{ $i + 1 }}" class="form-select" aria-label="{{ $i === 0 ? 'Posição principal' : ($i + 1) . 'ª posição' }}">
                <option value="">{{ $i === 0 ? 'Principal — Selecionar —' : ($i + 1) . 'ª posição (opcional)' }}</option>
                @foreach($porSetor as $setor => $posicoes)
                <optgroup label="{{ $setor }}">
                    @foreach($posicoes as $codigo => $posicao)
                    <option value="{{ $codigo }}" {{ ($selecionadas[$i] ?? null) === $codigo ? 'selected' : '' }}>{{ $posicao['nome'] }} ({{ $posicao['sigla'] }})</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
        </div>
        @endfor
    </div>
    {{-- Erros só no cadastro (a edição mostra os dela no alerta do topo da página) --}}
    @if($prefixo === 'cad')
        @foreach(['posicoes', 'posicoes.*'] as $campo)
            @error($campo)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @endforeach
    @endif
</div>
