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
                        {{-- A chave "É uma peneira" saiu em 09/11/2026 (dono do projeto) --}}
                        {{-- Tipo (09/11/2026): os três de sempre, os tipos cadastrados ("tipo:ID": o servidor grava a base dele e
                             o nome no subtipo) e o "Adicionar novo tipo…", que abre o cadastro rápido abaixo --}}
                        @php
                            $tiposCadastrados = \App\Models\TipoEventoIndividual::paraEscolher();
                            $tipoMarcado = (string) old('tipo_escolhido', old('tipo_evento_calendario', 'AVALIACAO'));
                        @endphp
                        <div class="col-md-5">
                            <label class="form-label" for="ind_tipo">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_evento_calendario" id="ind_tipo" class="form-select js-tipo-evento js-tipo-individual" required>
                                @foreach(\App\Models\EventoCalendario::TIPOS_INDIVIDUAIS as $tipo)
                                <option value="{{ $tipo }}" @selected($tipoMarcado === $tipo)>{{ \App\Models\EventoCalendario::TIPOS[$tipo] }}</option>
                                @endforeach
                                @if($tiposCadastrados->isNotEmpty())
                                <optgroup label="Tipos cadastrados" class="js-tipos-cadastrados">
                                    @foreach($tiposCadastrados as $cadastrado)
                                    <option value="{{ $cadastrado->valorNoSelect() }}" @selected($tipoMarcado === $cadastrado->valorNoSelect())>{{ $cadastrado->nome_tipo_evento_individual }}</option>
                                    @endforeach
                                </optgroup>
                                @endif
                                <option value="__novo__">➕ Adicionar novo tipo…</option>
                            </select>
                        </div>
                        {{-- Sem o campo Subtipo (dono do projeto, 09/11/2026): o tipo cadastrado faz esse papel. O servidor
                             grava o subtipo sozinho só com um tipo cadastrado (o nome dele, que o app mostra) --}}

                        {{-- Cadastro rápido do tipo: os campos não pertencem ao formulário (form="sem-formulario"), então não vão
                             no envio dele; o "Salvar tipo" manda em JSON e o tipo novo já fica escolhido --}}
                        <div class="col-12 d-none js-painel-novo-tipo">
                            <div class="border rounded p-3 bg-body-tertiary"
                                 data-url="{{ route('admin.individuais.tipos.store') }}" data-token="{{ csrf_token() }}">
                                <p class="small fw-semibold mb-2"><i class="bi bi-tag"></i> Novo tipo de evento individual</p>
                                <label class="form-label small mb-0" for="ind_novo_tipo_nome">Nome do tipo <span class="text-danger">*</span></label>
                                <input type="text" form="sem-formulario" id="ind_novo_tipo_nome" data-campo="nome_tipo_evento_individual"
                                    class="form-control form-control-sm" maxlength="{{ \App\Models\TipoEventoIndividual::NOME_MAX }}"
                                    placeholder="Ex: VIAGEM, FISIOTERAPIA, ENTREVISTA" autocapitalize="characters">
                                <small class="text-muted d-block mt-1">O tipo fica na lista para os próximos eventos.</small>
                                <ul class="text-danger small mb-0 mt-2 js-erros-novo-tipo"></ul>
                                <div class="d-flex justify-content-end gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-cancelar-novo-tipo">Cancelar</button>
                                    <button type="button" class="btn btn-sm btn-primary js-salvar-novo-tipo"><i class="bi bi-check-lg"></i> Salvar tipo</button>
                                </div>
                            </div>
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
                            @include('admin.partials.select-local', ['idCampo' => 'ind_local'])
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
                                'comFiltroDeIdade' => true,   // marcar por faixa de ano de nascimento e sexo
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

{{-- Tipo (09/11/2026): "Adicionar novo tipo…" abre o cadastro rápido (o select volta ao tipo de antes até salvar);
     salvo, o tipo entra em "Tipos cadastrados" e já fica escolhido --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('ind_tipo');
    const painel = document.querySelector('#modalEventoIndividual .js-painel-novo-tipo');
    if (!select || !painel) return;
    const caixa  = painel.querySelector('[data-url]');
    const nome   = document.getElementById('ind_novo_tipo_nome');
    const erros  = painel.querySelector('.js-erros-novo-tipo');
    let anterior = select.value;

    const fechar = () => { painel.classList.add('d-none'); nome.value = ''; erros.replaceChildren(); };

    select.addEventListener('change', () => {
        if (select.value === '__novo__') {
            select.value = anterior;
            painel.classList.remove('d-none');
            nome.focus();
            return;
        }
        anterior = select.value;
    });

    painel.querySelector('.js-cancelar-novo-tipo').addEventListener('click', fechar);
    // Nome do tipo em maiúsculas já ao digitar (o servidor grava em maiúsculas também), mantendo o cursor no lugar
    nome.addEventListener('input', () => {
        const [ini, fim] = [nome.selectionStart, nome.selectionEnd];
        nome.value = nome.value.toLocaleUpperCase('pt-BR');
        nome.setSelectionRange(ini, fim);
    });
    nome.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); painel.querySelector('.js-salvar-novo-tipo').click(); } });

    painel.querySelector('.js-salvar-novo-tipo').addEventListener('click', () => {
        const dados = new FormData();
        dados.append('nome_tipo_evento_individual', nome.value);
        erros.replaceChildren();
        fetch(caixa.dataset.url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': caixa.dataset.token },
            body: dados,
        }).then(async r => {
            const corpo = await r.json().catch(() => ({}));
            if (r.status === 201) {
                let grupo = select.querySelector('.js-tipos-cadastrados');
                if (!grupo) {
                    grupo = document.createElement('optgroup');
                    grupo.label = 'Tipos cadastrados';
                    grupo.className = 'js-tipos-cadastrados';
                    select.insertBefore(grupo, select.querySelector('option[value="__novo__"]'));
                }
                const opt = document.createElement('option');
                opt.value = corpo.valor;
                opt.textContent = corpo.nome;   // textContent: o nome não é interpretado como HTML
                // Em ordem alfabética, como vem do servidor
                const depois = [...grupo.children].find(o => o.textContent.localeCompare(corpo.nome, 'pt-BR') > 0);
                grupo.insertBefore(opt, depois || null);
                select.value = corpo.valor;
                select.dispatchEvent(new Event('change'));
                fechar();
                return;
            }
            const mensagens = corpo.errors ? Object.values(corpo.errors).flat() : [corpo.message || 'Não foi possível cadastrar o tipo.'];
            erros.replaceChildren(...mensagens.map(m => Object.assign(document.createElement('li'), { textContent: m })));
        }).catch(() => {
            erros.replaceChildren(Object.assign(document.createElement('li'), { textContent: 'Sem conexão: tente de novo.' }));
        });
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
