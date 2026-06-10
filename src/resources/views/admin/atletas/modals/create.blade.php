@php $abrirModalCriar = $errors->any() && old('form_origin') === 'create'; @endphp
<div class="modal fade {{ $abrirModalCriar ? 'show' : '' }}" id="modalNovoAtleta" tabindex="-1"
    aria-labelledby="modalNovoAtletaLabel"
    @if ($abrirModalCriar) style="display:block;" aria-modal="true" role="dialog" @endif>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-semibold" id="modalNovoAtletaLabel">
                    <i class="bi bi-person-plus me-2"></i>Cadastrar novo atleta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"
                    style="background: transparent; border: 0; font-size: 20px; color: white;">&times;</button>
            </div>

            <form action="{{ route('admin.atletas.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="form_origin" value="create">
                @csrf
                <div class="modal-body px-4" style="max-height:65vh;overflow-y:auto;">

                    <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
                        <i class="bi bi-person-circle me-1"></i> Dados do Atleta
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Nome Completo</label>
                            <input type="text" name="nome_atleta"
                                class="form-control @error('nome_atleta') is-invalid @enderror"
                                placeholder="Ex: Gabriel Silva" required value="{{ old('nome_atleta') }}">
                            @error('nome_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Data de Nascimento</label>
                            <input type="date" name="data_nasc_atleta"
                                class="form-control @error('data_nasc_atleta') is-invalid @enderror" required
                                value="{{ old('data_nasc_atleta') }}">
                            @error('data_nasc_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">CPF</label>
                            <input type="text" name="cpf_atleta"
                                class="form-control @error('cpf_atleta') is-invalid @enderror"
                                placeholder="000.000.000-00" maxlength="14" required value="{{ old('cpf_atleta') }}">
                            @error('cpf_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">RG</label>
                            <input type="text" name="rg_atleta"
                                class="form-control @error('rg_atleta') is-invalid @enderror" placeholder="00.000.000-0"
                                maxlength="12" required value="{{ old('rg_atleta') }}">
                            @error('rg_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Categoria</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach ($categorias as $cat)
                                    <option value="{{ $cat->id_categoria }}"
                                        {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                                        {{ $cat->nome_categoria }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Posição</label>
                            <select name="posicao_atleta" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach (['Goleiro', 'Zagueiro', 'Lateral', 'Volante', 'Meia', 'Atacante'] as $pos)
                                    <option value="{{ $pos }}"
                                        {{ old('posicao') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Nº Camisa / Matrícula</label>
                            <input type="text" name="numero_matricula_atleta" class="form-control" placeholder="Ex: 10"
                                maxlength="10" value="{{ old('numero_matricula_atleta') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Sexo</label>
                            <select name="sexo_atleta" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                <option value="M" {{ old('sexo_atleta') == 'M' ? 'selected' : '' }}>Masculino
                                </option>
                                <option value="F" {{ old('sexo_atleta') == 'F' ? 'selected' : '' }}>Feminino
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Peso (kg)</label>
                            <input type="number" name="peso_atleta" class="form-control" placeholder="Ex: 55.00"
                                step="0.01" min="0" value="{{ old('peso_atleta') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Altura (m)</label>
                            <input type="number" name="altura_atleta" class="form-control" placeholder="Ex: 1.70"
                                step="0.01" min="0" value="{{ old('altura_atleta') }}">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark">Escola</label>
                            <input type="text" name="escola_atleta"
                                class="form-control @error('escola_atleta') is-invalid @enderror"
                                placeholder="Nome da escola" required value="{{ old('escola_atleta') }}">
                            @error('escola_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Série</label>
                            <input type="text" name="serie_atleta" class="form-control" placeholder="Ex: 7º Ano"
                                value="{{ old('serie_atleta') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Período</label>
                            <select name="periodo_escolar_atleta" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach (['MANHÃ', 'TARDE', 'NOITE'] as $per)
                                    <option value="{{ $per }}"
                                        {{ old('periodo_escolar_atleta') == $per ? 'selected' : '' }}>
                                        {{ $per }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Sala</label>
                            <input type="text" name="sala_atleta" class="form-control" placeholder="Ex: 101"
                                value="{{ old('sala_atleta') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Foto</label>
                            <input type="file" name="foto_atleta" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Descrição / Observações</label>
                            <input type="text" name="descricao_atleta" class="form-control"
                                placeholder="Observações opcionais" value="{{ old('descricao_atleta') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">E-mail <span class="text-muted"
                                    style="font-size:11px;">(opcional)</span></label>
                            <input type="email" name="email_atleta"
                                class="form-control @error('email_atleta') is-invalid @enderror"
                                placeholder="email@exemplo.com" value="{{ old('email_atleta') }}">
                            @error('email_atleta')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Senha <span class="text-muted"
                                    style="font-size:11px;">(opcional)</span></label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Mínimo 8 caracteres">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Confirmar Senha</label>
                            <input type="password" name="password_confirmation" class="form-control"
                                placeholder="Repita a senha">
                        </div>
                    </div>

                    <hr class="my-1">

                    <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
                        <i class="bi bi-person-check me-1"></i> Responsável
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Nome do Responsável</label>
                            <input type="text" name="nome_responsavel"
                                class="form-control @error('nome_responsavel') is-invalid @enderror"
                                placeholder="Ex: João Silva" required value="{{ old('nome_responsavel') }}">
                            @error('nome_responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Grau de Parentesco</label>
                            <select name="grau_parentesco_responsavel"
                                class="form-select @error('grau_parentesco_responsavel') is-invalid @enderror"
                                required>
                                <option value="">— Selecionar —</option>
                                @foreach (['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                                    <option value="{{ $grau }}"
                                        {{ old('grau_parentesco_responsavel') == $grau ? 'selected' : '' }}>
                                        {{ $grau }}</option>
                                @endforeach
                            </select>
                            @error('grau_parentesco_responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">CPF do Responsável</label>
                            <input type="text" name="cpf_responsavel"
                                class="form-control @error('cpf_responsavel') is-invalid @enderror"
                                placeholder="000.000.000-00" maxlength="14" required
                                value="{{ old('cpf_responsavel') }}">
                            @error('cpf_responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">RG do Responsável</label>
                            <input type="text" name="rg_responsavel"
                                class="form-control @error('rg_responsavel') is-invalid @enderror"
                                placeholder="00.000.000-0" maxlength="12" value="{{ old('rg_responsavel') }}">
                            @error('rg_responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Telefone do Responsável</label>
                            <input type="text" name="telefone_responsavel" class="form-control"
                                placeholder="(11) 0000-0000" maxlength="20"
                                value="{{ old('telefone_responsavel') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">WhatsApp</label>
                            <input type="text" name="whatsapp_responsavel"
                                class="form-control @error('whatsapp_responsavel') is-invalid @enderror"
                                placeholder="(11) 99999-9999" required value="{{ old('whatsapp_responsavel') }}">
                            <div class="form-text"><i class="bi bi-whatsapp text-success"></i> O link de assinatura
                                será enviado aqui</div>
                            @error('whatsapp_responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-1">

                    <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
                        <i class="bi bi-geo-alt me-1"></i> Endereço do Responsável
                    </p>

                    <div class="row g-3 mb-2">
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">CEP</label>
                            <input type="text" name="cep_resp_endereco"
                                class="form-control @error('cep_resp_endereco') is-invalid @enderror"
                                placeholder="00000-000" maxlength="9" required
                                value="{{ old('cep_resp_endereco') }}">
                            @error('cep_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Rua</label>
                            <input type="text" name="rua_resp_endereco"
                                class="form-control @error('rua_resp_endereco') is-invalid @enderror"
                                placeholder="Nome da rua" required value="{{ old('rua_resp_endereco') }}">
                            @error('rua_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Número</label>
                            <input type="text" name="numero_resp_endereco"
                                class="form-control @error('numero_resp_endereco') is-invalid @enderror"
                                placeholder="123" required value="{{ old('numero_resp_endereco') }}">
                            @error('numero_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Bairro</label>
                            <input type="text" name="bairro_resp_endereco"
                                class="form-control @error('bairro_resp_endereco') is-invalid @enderror"
                                placeholder="Nome do bairro" required value="{{ old('bairro_resp_endereco') }}">
                            @error('bairro_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark">Cidade</label>
                            <input type="text" name="cidade_resp_endereco"
                                class="form-control @error('cidade_resp_endereco') is-invalid @enderror"
                                placeholder="São Paulo" required value="{{ old('cidade_resp_endereco') }}">
                            @error('cidade_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Estado (UF)</label>
                            <input type="text" name="estado_resp_endereco"
                                class="form-control @error('estado_resp_endereco') is-invalid @enderror"
                                placeholder="SP" maxlength="2" required value="{{ old('estado_resp_endereco') }}">
                            @error('estado_resp_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-1">

                    <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
                        <i class="bi bi-geo-alt me-1"></i> Endereço
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">CEP</label>
                            <input type="text" name="cep_endereco"
                                class="form-control @error('cep_endereco') is-invalid @enderror"
                                placeholder="00000-000" maxlength="9" required value="{{ old('cep_endereco') }}">
                            @error('cep_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label font-weight-bold text-dark">Cidade</label>
                            <input type="text" name="cidade_endereco"
                                class="form-control @error('cidade_endereco') is-invalid @enderror"
                                placeholder="São Paulo" required value="{{ old('cidade_endereco') }}">
                            @error('cidade_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Rua</label>
                            <input type="text" name="rua_endereco"
                                class="form-control @error('rua_endereco') is-invalid @enderror"
                                placeholder="Nome da rua" required value="{{ old('rua_endereco') }}">
                            @error('rua_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Número</label>
                            <input type="text" name="numero_endereco"
                                class="form-control @error('numero_endereco') is-invalid @enderror" placeholder="123"
                                required value="{{ old('numero_endereco') }}">
                            @error('numero_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Estado (UF)</label>
                            <input type="text" name="estado_endereco"
                                class="form-control @error('estado_endereco') is-invalid @enderror" placeholder="SP"
                                maxlength="2" required value="{{ old('estado_endereco') }}">
                            @error('estado_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Bairro</label>
                            <input type="text" name="bairro_endereco"
                                class="form-control @error('bairro_endereco') is-invalid @enderror"
                                placeholder="Nome do bairro" required value="{{ old('bairro_endereco') }}">
                            @error('bairro_endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Complemento</label>
                            <input type="text" name="complemento_endereco" class="form-control"
                                placeholder="Apto, bloco... (opcional)" value="{{ old('complemento_endereco') }}">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-person-check me-1"></i> Salvar atleta
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
