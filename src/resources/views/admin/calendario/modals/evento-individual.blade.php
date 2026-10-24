{{--
    Novo evento individual (lista de Individuais): o técnico descreve o que o atleta vai fazer e escolhe os
    atletas. Sem categoria; tipos EventoCalendario::TIPOS_INDIVIDUAIS. Cada atleta escolhido é inscrito e
    recebe a notificação (com a descrição resumida); o evento aparece só na agenda deles.
    $atletasParaIndividual: atletas ativos agrupados pela categoria atual.
--}}
@php $atletasEscolhidos = array_map('strval', (array) old('atletas', [])); @endphp
<div class="modal fade" id="modalEventoIndividual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-check"></i> Novo evento individual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.calendario.eventos.individual') }}" method="POST" id="formEventoIndividual">
                @csrf
                <input type="hidden" name="_formulario" value="individual">
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Fase E: a peneira é este mesmo formulário; a chave só preenche tipo, subtipo, título e descrição.
                             Sem name: não vai ao servidor (o que vale é o subtipo, EventoCalendario::ehPeneira()) --}}
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="ind_peneira"
                                    @checked(old('subtipo_evento_calendario') === \App\Models\EventoCalendario::SUBTIPO_PENEIRA)>
                                <label class="form-check-label" for="ind_peneira">
                                    <i class="bi bi-binoculars"></i> É uma peneira (avaliação técnica de um clube)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ind_tipo">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_evento_calendario" id="ind_tipo" class="form-select js-tipo-evento" required>
                                @foreach(\App\Models\EventoCalendario::TIPOS_INDIVIDUAIS as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo_evento_calendario', 'AVALIACAO') === $tipo)>{{ \App\Models\EventoCalendario::TIPOS[$tipo] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ind_subtipo">Subtipo</label>
                            <input type="text" name="subtipo_evento_calendario" id="ind_subtipo" class="form-control js-subtipo" autocomplete="off"
                                placeholder="Ex: Exame médico, Teste físico, Reunião com a família" value="{{ old('subtipo_evento_calendario') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ind_titulo">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_evento_calendario" id="ind_titulo" class="form-control" required maxlength="255"
                                placeholder="Ex: Exame cardiológico" value="{{ old('titulo_evento_calendario') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ind_data">Data <span class="text-danger">*</span></label>
                            <input type="date" name="data_evento_calendario" id="ind_data" class="form-control" required value="{{ old('data_evento_calendario') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="ind_inicio">Início</label>
                            <input type="time" name="horario_inicio_evento_calendario" id="ind_inicio" class="form-control" value="{{ old('horario_inicio_evento_calendario') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="ind_fim">Fim</label>
                            <input type="time" name="horario_fim_evento_calendario" id="ind_fim" class="form-control" value="{{ old('horario_fim_evento_calendario') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ind_local">Local</label>
                            <input type="text" name="local_evento_calendario" id="ind_local" class="form-control" list="locaisUsados" autocomplete="off"
                                placeholder="Ex: Clínica São Lucas" value="{{ old('local_evento_calendario') }}">
                        </div>
                        <div class="col-md-8">
                            @include('admin.partials.select-responsavel', ['idCampo' => 'ind_responsavel'])
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ind_descricao">O que o atleta vai fazer</label>
                            <textarea name="descricao_evento_calendario" id="ind_descricao" class="form-control" rows="3"
                                placeholder="Ex: Exame de sangue. Venha em jejum de 8 horas e traga um documento.">{{ old('descricao_evento_calendario') }}</textarea>
                            <small class="text-muted">Aparece na agenda do atleta no app; o começo vai junto na notificação.</small>
                        </div>
                        {{-- Atletas: caixa com a lista para ticar, por categoria, com busca e "marcar todos" da categoria --}}
                        <div class="col-12">
                            @include('admin.partials.escolher-atletas', [
                                'prefixo'          => 'ind',
                                'grupos'           => $atletasParaIndividual,
                                'escolhidos'       => $atletasEscolhidos,
                                'ajuda'            => 'Tique quem vai fazer. Só os escolhidos veem o evento na agenda.',
                                'comFiltroDeIdade' => true,   // Fase E: peneira por faixa de ano de nascimento e sexo
                            ])
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-send"></i> Criar e avisar os atletas</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Fase E: ligar a chave "É uma peneira" prepara o formulário (tipo Avaliação, subtipo, título e um modelo de
     descrição); desligar limpa o que a peneira tinha posto. O que foi digitado à mão fica --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('modalEventoIndividual');
    const chave = document.getElementById('ind_peneira');
    const SUBTIPO = @js(\App\Models\EventoCalendario::SUBTIPO_PENEIRA);
    const TITULO = 'Peneira · ';
    const DESCRICAO = 'Avaliação técnica do clube (nome). Traga chuteira, caneleira e documento.';
    const mostrarTitulo = peneira => modal.querySelector('.modal-title').innerHTML = peneira
        ? '<i class="bi bi-binoculars"></i> Nova peneira (avaliação técnica)'
        : '<i class="bi bi-person-check"></i> Novo evento individual';
    // Voltou com erro de validação já como peneira: a chave vem ligada pelo old(); só o título acompanha
    if (chave?.checked) mostrarTitulo(true);
    chave?.addEventListener('change', function () {
        const peneira = chave.checked;
        const campo = id => document.getElementById(id);
        mostrarTitulo(peneira);
        if (peneira) {
            campo('ind_tipo').value = 'AVALIACAO';
            campo('ind_subtipo').value = SUBTIPO;
            if (!campo('ind_titulo').value) campo('ind_titulo').value = TITULO;
            if (!campo('ind_descricao').value) campo('ind_descricao').value = DESCRICAO;
        } else {
            if (campo('ind_subtipo').value === SUBTIPO) campo('ind_subtipo').value = '';
            if (campo('ind_titulo').value === TITULO) campo('ind_titulo').value = '';
            if (campo('ind_descricao').value === DESCRICAO) campo('ind_descricao').value = '';
        }
    });
});
</script>

{{-- Voltou com erro de validação: reabre o formulário com o que foi preenchido --}}
@if($errors->any() && old('_formulario') === 'individual')
<script>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEventoIndividual')).show();
});
</script>
@endif
