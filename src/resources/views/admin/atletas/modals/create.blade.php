<div class="modal fade {{ $errors->any() ? 'show' : '' }}" id="modalNovoAtleta" tabindex="-1"
    aria-labelledby="modalNovoAtletaLabel" @if($errors->any()) style="display:block;" aria-modal="true" role="dialog" @endif>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start modal-admin">

            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoAtletaLabel">
                    <i class="bi bi-person-plus"></i> Cadastrar novo atleta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form action="{{ route('admin.atletas.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="modal-body px-4" style="max-height:65vh;overflow-y:auto;">

                    <p class="modal-section-label mt-2">
                        <i class="bi bi-person-circle"></i> Dados do Atleta
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nome_atleta" class="form-control @error('nome_atleta') is-invalid @enderror"
                                placeholder="Ex: Gabriel Silva" required value="{{ old('nome_atleta') }}">
                            @error('nome_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data de Nascimento <span class="text-danger">*</span></label>
                            <input type="date" name="data_nasc_atleta"
                                class="form-control @error('data_nasc_atleta') is-invalid @enderror" required
                                value="{{ old('data_nasc_atleta') }}">
                            @error('data_nasc_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">CPF <span class="text-danger">*</span></label>
                            <input type="text" name="cpf_atleta" class="form-control @error('cpf_atleta') is-invalid @enderror"
                                placeholder="000.000.000-00" maxlength="14" required value="{{ old('cpf_atleta') }}">
                            @error('cpf_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">RG <span class="text-danger">*</span></label>
                            <input type="text" name="rg_atleta" class="form-control @error('rg_atleta') is-invalid @enderror"
                                placeholder="00.000.000-0" maxlength="12" required value="{{ old('rg_atleta') }}">
                            @error('rg_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nº Camisa</label>
                            <input type="text" name="camisa_atleta_time" class="form-control text-center" placeholder="Ex: 10" maxlength="10"
                                value="{{ old('camisa_atleta_time') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                                    {{ $cat->nome_categoria }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Posição</label>
                            <select name="posicao_atleta" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach(['Goleiro', 'Zagueiro', 'Lateral', 'Volante', 'Meia', 'Atacante'] as $pos)
                                <option value="{{ $pos }}" {{ old('posicao_atleta') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo_atleta" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                <option value="M" {{ old('sexo_atleta') == 'M' ? 'selected' : '' }}>Masculino</option>
                                <option value="F" {{ old('sexo_atleta') == 'F' ? 'selected' : '' }}>Feminino</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" name="peso_atleta" class="form-control text-center" placeholder="55.00"
                                step="0.01" min="0" value="{{ old('peso_atleta') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Altura (m)</label>
                            <input type="number" name="altura_atleta" class="form-control text-center" placeholder="1.70"
                                step="0.01" min="0" value="{{ old('altura_atleta') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Foto do Atleta</label>
                            <input type="file" name="foto_atleta" class="form-control" accept="image/*">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Escola <span class="text-danger">*</span></label>
                            <input type="text" name="escola_atleta" class="form-control @error('escola_atleta') is-invalid @enderror"
                                placeholder="Nome da escola" required value="{{ old('escola_atleta') }}">
                            @error('escola_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Série</label>
                            <input type="text" name="serie_atleta" class="form-control" placeholder="Ex: 7º Ano"
                                value="{{ old('serie_atleta') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Período</label>
                            <select name="periodo_escolar_atleta" class="form-select">
                                <option value="">—</option>
                                @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $per)
                                <option value="{{ $per }}" {{ old('periodo_escolar_atleta') == $per ? 'selected' : '' }}>{{ $per }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Sala</label>
                            <input type="text" name="sala_atleta" class="form-control text-center" placeholder="A1"
                                value="{{ old('sala_atleta') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descrição / Observações</label>
                            <input type="text" name="descricao_atleta" class="form-control" placeholder="Observações opcionais"
                                value="{{ old('descricao_atleta') }}">
                        </div>
                    </div>

                    <p class="modal-section-label">
                        <i class="bi bi-person-check"></i> Responsável
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label">Nome do Responsável <span class="text-danger">*</span></label>
                            <input type="text" name="nome_responsavel"
                                class="form-control @error('nome_responsavel') is-invalid @enderror" placeholder="Ex: João Silva"
                                required value="{{ old('nome_responsavel') }}">
                            @error('nome_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Grau de Parentesco <span class="text-danger">*</span></label>
                            <select name="grau_parentesco_responsavel"
                                class="form-select @error('grau_parentesco_responsavel') is-invalid @enderror" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                                <option value="{{ $grau }}" {{ old('grau_parentesco_responsavel') == $grau ? 'selected' : '' }}>{{ $grau }}</option>
                                @endforeach
                            </select>
                            @error('grau_parentesco_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="whatsapp_responsavel"
                                class="form-control @error('whatsapp_responsavel') is-invalid @enderror" placeholder="(11) 99999-9999"
                                required value="{{ old('whatsapp_responsavel') }}">
                            @error('whatsapp_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF do Responsável <span class="text-danger">*</span></label>
                            <input type="text" name="cpf_responsavel"
                                class="form-control @error('cpf_responsavel') is-invalid @enderror" placeholder="000.000.000-00"
                                maxlength="14" required value="{{ old('cpf_responsavel') }}">
                            @error('cpf_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <p class="modal-section-label">
                        <i class="bi bi-geo-alt"></i> Endereço
                    </p>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">CEP <span class="text-danger">*</span></label>
                            <input type="text" name="cep_endereco" class="form-control @error('cep_endereco') is-invalid @enderror"
                                placeholder="00000-000" maxlength="9" required value="{{ old('cep_endereco') }}">
                            @error('cep_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Rua <span class="text-danger">*</span></label>
                            <input type="text" name="rua_endereco" class="form-control @error('rua_endereco') is-invalid @enderror"
                                placeholder="Nome da rua" required value="{{ old('rua_endereco') }}">
                            @error('rua_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Número <span class="text-danger">*</span></label>
                            <input type="text" name="numero_endereco"
                                class="form-control text-center @error('numero_endereco') is-invalid @enderror" placeholder="123" required
                                value="{{ old('numero_endereco') }}">
                            @error('numero_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Bairro <span class="text-danger">*</span></label>
                            <input type="text" name="bairro_endereco"
                                class="form-control @error('bairro_endereco') is-invalid @enderror" placeholder="Nome do bairro"
                                required value="{{ old('bairro_endereco') }}">
                            @error('bairro_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Cidade <span class="text-danger">*</span></label>
                            <input type="text" name="cidade_endereco"
                                class="form-control @error('cidade_endereco') is-invalid @enderror" placeholder="São Paulo" required
                                value="{{ old('cidade_endereco') }}">
                            @error('cidade_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF <span class="text-danger">*</span></label>
                            <input type="text" name="estado_endereco"
                                class="form-control text-center @error('estado_endereco') is-invalid @enderror" placeholder="SP" maxlength="2"
                                required value="{{ old('estado_endereco') }}">
                            @error('estado_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Complemento <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="complemento_endereco" class="form-control"
                                placeholder="Apto, bloco, referência..." value="{{ old('complemento_endereco') }}">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-person-check"></i> Salvar atleta
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
