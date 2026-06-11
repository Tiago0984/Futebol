<div class="modal fade" id="modalEditarAtleta" tabindex="-1" aria-labelledby="modalEditarAtletaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">

            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-semibold" id="modalEditarAtletaLabel">
                    <i class="bi bi-pencil me-2"></i>Editar Atleta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar" style="background: transparent; border: 0; font-size: 20px;">&times;</button>
            </div>

            <form id="formEditarAtleta" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body px-4" style="max-height:65vh;overflow-y:auto;">

                    <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
                        <i class="bi bi-person-circle me-1"></i> Dados do Atleta
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Nome Completo</label>
                            <input type="text" name="nome_atleta" id="edit_nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Data de Nascimento</label>
                            <input type="date" name="data_nasc_atleta" id="edit_data_nasc" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">CPF</label>
                            <input type="text" name="cpf_atleta" id="edit_cpf" class="form-control" placeholder="000.000.000-00" maxlength="14" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">RG</label>
                            <input type="text" name="rg_atleta" id="edit_rg" class="form-control" placeholder="00.000.000-0" maxlength="12" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Categoria</label>
                            <select name="id_categoria" id="edit_categoria" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Nº Camisa / Matrícula</label>
                            <input type="text" name="numero_atleta" id="edit_numero" class="form-control" placeholder="Ex: 10" maxlength="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Status</label>
                            <select name="status_atleta" id="edit_status" class="form-select" required>
                                <option value="ATIVO">ATIVO</option>
                                <option value="INATIVO">INATIVO</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Sexo</label>
                            <select name="sexo_atleta" id="edit_sexo" class="form-select" required>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Peso (kg)</label>
                            <input type="number" name="peso_atleta" id="edit_peso" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Altura (m)</label>
                            <input type="number" name="altura_atleta" id="edit_altura" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark">Escola</label>
                            <input type="text" name="escola_atleta" id="edit_escola" class="form-control" placeholder="Nome da escola" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Série</label>
                            <input type="text" name="serie_atleta" id="edit_serie" class="form-control" placeholder="Ex: 7º Ano">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Período</label>
                            <select name="periodo_escolar_atleta" id="edit_periodo" class="form-select">
                                <option value="">— Selecionar —</option>
                                <option value="MANHÃ">MANHÃ</option>
                                <option value="TARDE">TARDE</option>
                                <option value="NOITE">NOITE</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Alterar Foto (Opcional)</label>
                            <input type="file" name="foto_atleta" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Descrição / Observações</label>
                            <input type="text" name="descricao_atleta" id="edit_descricao" class="form-control" placeholder="Observações opcionais">
                        </div>
                    </div>

                    <hr class="my-3">

                    <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
                        <i class="bi bi-person-check me-1"></i> Responsável
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Nome do Responsável</label>
                            <input type="text" name="nome_responsavel" id="edit_nome_responsavel" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">Grau de Parentesco</label>
                            <select name="grau_parentesco_responsavel" id="edit_grau_responsavel" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                                <option value="{{ $grau }}">{{ $grau }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">WhatsApp</label>
                            <input type="text" name="whatsapp_responsavel" id="edit_whatsapp_responsavel" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold text-dark">CPF do Responsável</label>
                            <input type="text" name="cpf_responsavel" id="edit_cpf_responsavel" class="form-control" maxlength="14" required>
                        </div>
                    </div>

                    <hr class="my-3">

                    <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
                        <i class="bi bi-geo-alt me-1"></i> Endereço
                    </p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">CEP</label>
                            <input type="text" name="cep_endereco" id="edit_cep_endereco" class="form-control" maxlength="9" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label font-weight-bold text-dark">Rua / Logradouro</label>
                            <input type="text" name="rua_endereco" id="edit_rua_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold text-dark">Número</label>
                            <input type="text" name="numero_endereco" id="edit_numero_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label font-weight-bold text-dark">Bairro</label>
                            <input type="text" name="bairro_endereco" id="edit_bairro_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Complemento</label>
                            <input type="text" name="complemento_endereco" id="edit_complemento_endereco" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label font-weight-bold text-dark">Cidade</label>
                            <input type="text" name="cidade_endereco" id="edit_cidade_endereco" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold text-dark">Estado (UF)</label>
                            <input type="text" name="estado_endereco" id="edit_estado_endereco" class="form-control" maxlength="2" required>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-check-circle me-1"></i> Atualizar Atleta
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>