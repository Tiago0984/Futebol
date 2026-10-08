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
                        <div class="col-12">
                            <label class="form-label" for="ind_descricao">O que o atleta vai fazer</label>
                            <textarea name="descricao_evento_calendario" id="ind_descricao" class="form-control" rows="3"
                                placeholder="Ex: Exame de sangue. Venha em jejum de 8 horas e traga um documento.">{{ old('descricao_evento_calendario') }}</textarea>
                            <small class="text-muted">Aparece na agenda do atleta no app; o começo vai junto na notificação.</small>
                        </div>
                        {{-- Atletas: caixa com a lista para ticar, por categoria, com busca e "marcar todos" da categoria --}}
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 mb-1">
                                <label class="form-label mb-0 me-auto" for="ind_busca">Atletas <span class="text-danger">*</span>
                                    <span class="badge bg-secondary ms-1" id="ind_contador">0 escolhido(s)</span>
                                </label>
                                <input type="search" id="ind_busca" class="form-control form-control-sm" style="max-width:240px"
                                    placeholder="Buscar atleta..." autocomplete="off">
                            </div>
                            <div class="border rounded p-2" id="ind_atletas" style="max-height:260px;overflow-y:auto;">
                                @forelse($atletasParaIndividual as $rotuloCategoria => $grupo)
                                <div class="js-grupo-atletas mb-2">
                                    <div class="d-flex align-items-center gap-2 border-bottom pb-1 mb-1">
                                        <strong class="small">{{ $rotuloCategoria }}</strong>
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-auto js-marcar-grupo">Marcar todos</button>
                                    </div>
                                    <div class="row g-1">
                                        @foreach($grupo as $atleta)
                                        <div class="col-md-6 js-item-atleta" data-nome="{{ mb_strtolower($atleta->nome_atleta) }}">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input js-check-atleta" type="checkbox" name="atletas[]"
                                                    value="{{ $atleta->id_atleta }}" id="ind_atleta_{{ $atleta->id_atleta }}"
                                                    @checked(in_array((string) $atleta->id_atleta, $atletasEscolhidos, true))>
                                                <label class="form-check-label" for="ind_atleta_{{ $atleta->id_atleta }}">{{ $atleta->nome_atleta }}</label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @empty
                                <p class="text-muted small mb-0">Nenhum atleta ativo.</p>
                                @endforelse
                            </div>
                            <small class="text-muted">Tique quem vai fazer. Só os escolhidos veem o evento na agenda.</small>
                            <div class="text-danger small d-none" id="ind_aviso_atletas">Escolha pelo menos um atleta.</div>
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

<script>
// Caixa de atletas: contador, busca por nome, "marcar todos" da categoria (só os visíveis) e pelo menos um
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formEventoIndividual');
    if (!form) return;
    const checks = () => [...form.querySelectorAll('.js-check-atleta')];
    const contador = document.getElementById('ind_contador');
    const aviso = document.getElementById('ind_aviso_atletas');

    function contar() {
        const n = checks().filter(c => c.checked).length;
        contador.textContent = `${n} escolhido(s)`;
        if (n) aviso.classList.add('d-none');
    }

    form.addEventListener('change', e => { if (e.target.classList.contains('js-check-atleta')) contar(); });

    document.getElementById('ind_busca').addEventListener('input', function () {
        const busca = this.value.toLowerCase().trim();
        form.querySelectorAll('.js-grupo-atletas').forEach(grupo => {
            let algum = false;
            grupo.querySelectorAll('.js-item-atleta').forEach(item => {
                const ok = !busca || item.dataset.nome.includes(busca);
                item.classList.toggle('d-none', !ok);
                algum = algum || ok;
            });
            grupo.classList.toggle('d-none', !algum);
        });
    });

    form.querySelectorAll('.js-marcar-grupo').forEach(botao => botao.addEventListener('click', function () {
        const visiveis = [...this.closest('.js-grupo-atletas').querySelectorAll('.js-item-atleta:not(.d-none) .js-check-atleta')];
        const marcar = visiveis.some(c => !c.checked);
        visiveis.forEach(c => c.checked = marcar);
        this.textContent = marcar ? 'Desmarcar todos' : 'Marcar todos';
        contar();
    }));

    form.addEventListener('submit', e => {
        if (!checks().some(c => c.checked)) {
            e.preventDefault();
            aviso.classList.remove('d-none');
            document.getElementById('ind_atletas').scrollIntoView({ block: 'nearest' });
        }
    });

    contar();
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
