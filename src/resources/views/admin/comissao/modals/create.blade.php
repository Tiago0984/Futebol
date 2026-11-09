@php
    $marcadas = collect(old('categorias', []))->map(fn ($id) => (int) $id)->all();
    $fotoAutorizada = (bool) old('foto_autorizada_comissao_tecnica');
@endphp
<div class="modal fade" id="modalCriarProfissional" tabindex="-1" aria-labelledby="modalCriarProfissionalLabel" aria-hidden="true"
    data-abrir="{{ $errors->any() || request()->boolean('novo') ? '1' : '0' }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarProfissionalLabel"><i class="bi bi-person-plus"></i> Novo profissional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="{{ route('admin.comissao.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                    <div class="alert alert-danger mb-3" role="alert">
                        <strong>Verifique os campos:</strong>
                        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-person-badge"></i> Dados do profissional</p></div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_criar_nome">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="ct_criar_nome" name="nome_comissao_tecnica" class="form-control" required maxlength="100"
                                value="{{ old('nome_comissao_tecnica', $profissional->nome_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_criar_cargo">Cargo <span class="text-danger">*</span></label>
                            <select id="ct_criar_cargo" name="cargo_comissao_tecnica" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\ComissaoTecnica::CARGOS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('cargo_comissao_tecnica', $profissional->cargo_comissao_tecnica) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_criar_especialidade">Especialidade</label>
                            <input type="text" id="ct_criar_especialidade" name="especialidade_comissao_tecnica" class="form-control" maxlength="100"
                                placeholder="Ex: Treinamento técnico" value="{{ old('especialidade_comissao_tecnica', $profissional->especialidade_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_criar_licenca">Licença / certificação</label>
                            <input type="text" id="ct_criar_licenca" name="licenca_comissao_tecnica" class="form-control" maxlength="100"
                                placeholder="Ex: CBF Academy" value="{{ old('licenca_comissao_tecnica', $profissional->licenca_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ct_criar_inicio">Na AACJ desde</label>
                            <input type="date" id="ct_criar_inicio" name="data_inicio_comissao_tecnica" class="form-control" max="{{ now()->toDateString() }}"
                                value="{{ old('data_inicio_comissao_tecnica', $profissional->data_inicio_comissao_tecnica?->format('Y-m-d')) }}">
                            <small class="text-muted">O app mostra o tempo na AACJ a partir desta data.</small>
                        </div>
                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-tags"></i> Categorias</p></div>
                        <div class="col-md-4">
                            <label class="form-label" for="ct_criar_principal">Categoria principal</label>
                            <select id="ct_criar_principal" name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" @selected((string) old('id_categoria', $profissional->id_categoria) === (string) $cat->id_categoria)>
                                    {{ $cat->rotulo }}{{ $cat->status_categoria !== 'ATIVO' ? ' (inativa)' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label mb-1">Categorias atendidas</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($categorias as $cat)
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" name="categorias[]" id="ct_criar_cat_{{ $cat->id_categoria }}"
                                        value="{{ $cat->id_categoria }}" @checked(in_array((int) $cat->id_categoria, $marcadas, true))>
                                    <label class="form-check-label" for="ct_criar_cat_{{ $cat->id_categoria }}">{{ $cat->rotulo }}{{ $cat->status_categoria !== 'ATIVO' ? ' (inativa)' : '' }}</label>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">A principal entra na lista mesmo sem marcar.</small>
                        </div>
                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-camera"></i> Foto</p></div>
                        <div class="col-12">
                            @include('admin.comissao._foto', ['prefixo' => 'ct_criar_'])
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="foto_autorizada_comissao_tecnica" value="1" id="ct_criar_autorizada" @checked($fotoAutorizada)>
                                <label class="form-check-label" for="ct_criar_autorizada">Autorizou mostrar a foto no app</label>
                            </div>
                            <small class="text-muted">Sem esta caixa marcada, a foto fica só aqui no painel e o app mostra as iniciais.</small>
                        </div>
                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-telephone"></i> Contato institucional</p></div>
                        <div class="col-12">
                            <div class="alert alert-warning py-2 small mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Use só o telefone e o e-mail <strong>da escolinha</strong> para este profissional.
                                Não informe o celular nem o e-mail pessoal: atletas e responsáveis veem estes contatos no app.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ct_criar_telefone">Telefone institucional</label>
                            <input type="text" id="ct_criar_telefone" name="telefone_institucional_comissao_tecnica" class="form-control js-telefone-comissao" maxlength="20" inputmode="numeric"
                                placeholder="Ex: (11) 4000-0000" value="{{ old('telefone_institucional_comissao_tecnica', $profissional->telefone_formatado) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ct_criar_email">E-mail institucional</label>
                            <input type="email" id="ct_criar_email" name="email_institucional_comissao_tecnica" class="form-control" maxlength="255"
                                placeholder="Ex: carlos@aacj.org.br" value="{{ old('email_institucional_comissao_tecnica', $profissional->email_institucional_comissao_tecnica) }}">
                        </div>
                        <div class="col-12"><small class="text-muted">Opcionais. Sem nenhum dos dois, o app esconde a seção de contato.</small></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
