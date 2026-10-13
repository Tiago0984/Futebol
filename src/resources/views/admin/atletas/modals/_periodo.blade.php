{{--
    Período escolar (obrigatório: o treino é no turno oposto ao da escola) e, só para quem estuda à noite, o
    turno do treino (Atleta::PERIODOS e TURNOS_TREINO). Usado nos modais de cadastro e de edição do atleta.
    $prefixo: começo dos ids (cad, edit); $comOld: valores e erros do old() (cadastro; a edição é preenchida
    pelo script da lista). O script da lista (.js-periodo-escolar) mostra o turno quando o período é Noite.
--}}
@php $periodoAntigo = $comOld ? old('periodo_escolar_atleta') : null; @endphp
<div class="col-md-2">
    <label class="form-label">Período <span class="text-danger">*</span></label>
    <select name="periodo_escolar_atleta" id="{{ $prefixo }}_periodo" required
        class="form-select js-periodo-escolar {{ $comOld && $errors->has('periodo_escolar_atleta') ? 'is-invalid' : '' }}"
        data-turno="{{ $prefixo }}_turno">
        <option value="">—</option>
        @foreach(\App\Models\Atleta::PERIODOS as $valor => $rotulo)
        <option value="{{ $valor }}" @selected($periodoAntigo === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
    @if($comOld)
        @error('periodo_escolar_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
</div>
<div class="col-md-2 {{ $periodoAntigo === 'NOITE' ? '' : 'd-none' }}" id="{{ $prefixo }}_turno_col">
    <label class="form-label" title="Quem estuda à noite escolhe o turno do treino">Treina de <span class="text-danger">*</span></label>
    <select name="turno_treino_atleta" id="{{ $prefixo }}_turno" @required($periodoAntigo === 'NOITE')
        class="form-select {{ $comOld && $errors->has('turno_treino_atleta') ? 'is-invalid' : '' }}">
        <option value="">—</option>
        @foreach(\App\Models\Atleta::TURNOS_TREINO as $valor => $rotulo)
        <option value="{{ $valor }}" @selected($comOld && old('turno_treino_atleta') === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
    @if($comOld)
        @error('turno_treino_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
</div>
