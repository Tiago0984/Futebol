<div class="modal fade" id="modalEditarAtleta" tabindex="-1" aria-labelledby="modalEditarAtletaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start modal-admin">

            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarAtletaLabel">
                    <i class="bi bi-pencil"></i> Editar Atleta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form id="formEditarAtleta" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body px-4" style="max-height:65vh;overflow-y:auto;">

                    <p class="modal-section-label mt-2">
                        <i class="bi bi-person-circle"></i> Dados do Atleta
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nome_atleta" id="edit_nome" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data de Nascimento <span class="text-danger">*</span></label>
                            <input type="date" name="data_nasc_atleta" id="edit_data_nasc" class="form-control" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">CPF <span class="text-danger">*</span></label>
                            <input type="text" name="cpf_atleta" id="edit_cpf" class="form-control" placeholder="000.000.000-00" maxlength="14" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">RG <span class="text-danger">*</span></label>
                            <input type="text" name="rg_atleta" id="edit_rg" class="form-control" placeholder="00.000.000-0" maxlength="12" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nº Camisa</label>
                            <input type="text" name="camisa_atleta_time" id="edit_numero" class="form-control text-center" placeholder="Ex: 10" maxlength="10">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" id="edit_categoria" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Posição</label>
                            <select name="posicao_atleta" id="edit_posicao" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach(['GOLEIRO', 'ZAGUEIRO', 'LATERAL', 'VOLANTE', 'MEIA', 'ATACANTE'] as $pos)
                                <option value="{{ $pos }}">{{ $pos }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo_atleta" id="edit_sexo" class="form-select" required>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_atleta" id="edit_status" class="form-select" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" name="peso_atleta" id="edit_peso" class="form-control text-center" step="0.01" min="0" placeholder="55.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Altura (m)</label>
                            <input type="number" name="altura_atleta" id="edit_altura" class="form-control text-center" step="0.01" min="0" placeholder="1.70">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alterar Foto <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="file" name="foto_atleta" class="form-control" accept="image/*">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Escola <span class="text-danger">*</span></label>
                            <input type="text" name="escola_atleta" id="edit_escola" class="form-control" placeholder="Nome da escola" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Série</label>
                            <input type="text" name="serie_atleta" id="edit_serie" class="form-control" placeholder="Ex: 7º Ano">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Período</label>
                            <select name="periodo_escolar_atleta" id="edit_periodo" class="form-select">
                                <option value="">—</option>
                                <option value="MANHÃ">MANHÃ</option>
                                <option value="TARDE">TARDE</option>
                                <option value="NOITE">NOITE</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Sala</label>
                            <input type="text" name="sala_atleta" id="edit_sala" class="form-control text-center" placeholder="A1">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descrição / Observações</label>
                            <input type="text" name="descricao_atleta" id="edit_descricao" class="form-control" placeholder="Observações opcionais">
                        </div>
                    </div>

                    <p class="modal-section-label">
                        <i class="bi bi-shield-fill"></i> Times
                    </p>

                    <div class="row g-2 mb-4" id="edit-times-list">
                        @foreach($times as $t)
                        <div class="col-md-6">
                            <label class="d-flex align-items-center gap-2 p-2 border rounded w-100" style="cursor:pointer;border-color:var(--aacj-border)!important;">
                                <input type="checkbox"
                                       name="times[]"
                                       value="{{ $t->id_time }}"
                                       class="form-check-input mt-0 time-checkbox"
                                       style="width:18px;height:18px;flex-shrink:0;">
                                @if($t->logo_time)
                                    <img src="{{ asset('futebol/images/team/' . $t->logo_time) }}"
                                         width="30" height="30"
                                         class="rounded-circle object-fit-cover"
                                         style="flex-shrink:0;">
                                @else
                                    <div class="d-flex align-items-center justify-content-center rounded-circle"
                                         style="width:30px;height:30px;flex-shrink:0;background:#e5e7eb;font-size:0.75rem;color:#6b7280;">
                                        <i class="bi bi-shield-fill"></i>
                                    </div>
                                @endif
                                <span style="font-size:0.82rem;font-weight:600;color:var(--aacj-text-primary);">{{ $t->nome_time }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <p class="modal-section-label">
                        <i class="bi bi-person-check"></i> Responsável
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label">Nome do Responsável <span class="text-danger">*</span></label>
                            <input type="text" name="nome_responsavel" id="edit_nome_responsavel" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Grau de Parentesco <span class="text-danger">*</span></label>
                            <select name="grau_parentesco_responsavel" id="edit_grau_responsavel" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                                <option value="{{ $grau }}">{{ $grau }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="whatsapp_responsavel" id="edit_whatsapp_responsavel" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF do Responsável <span class="text-danger">*</span></label>
                            <input type="text" name="cpf_responsavel" id="edit_cpf_responsavel" class="form-control" maxlength="14" required>
                        </div>
                    </div>

                    <p class="modal-section-label">
                        <i class="bi bi-geo-alt"></i> Endereço
                    </p>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">CEP <span class="text-danger">*</span></label>
                            <input type="text" name="cep_endereco" id="edit_cep_endereco" class="form-control" maxlength="9" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Rua / Logradouro <span class="text-danger">*</span></label>
                            <input type="text" name="rua_endereco" id="edit_rua_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Número <span class="text-danger">*</span></label>
                            <input type="text" name="numero_endereco" id="edit_numero_endereco" class="form-control text-center" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Bairro <span class="text-danger">*</span></label>
                            <input type="text" name="bairro_endereco" id="edit_bairro_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Cidade <span class="text-danger">*</span></label>
                            <input type="text" name="cidade_endereco" id="edit_cidade_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF <span class="text-danger">*</span></label>
                            <input type="text" name="estado_endereco" id="edit_estado_endereco" class="form-control text-center" maxlength="2" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Complemento <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="complemento_endereco" id="edit_complemento_endereco" class="form-control" placeholder="Apto, bloco, referência...">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-circle"></i> Atualizar Atleta
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
