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
                {{-- Se a validação falhar, a lista reabre este modal para o mesmo atleta --}}
                <input type="hidden" name="_editar_id" id="edit_id_atleta">
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

                        {{-- Sem camisa nem times (Fase G, Etapa 4): a camisa e o time são da convocação de cada campeonato --}}
                        <div class="col-md-6">
                            <label class="form-label">CPF <span class="text-danger">*</span></label>
                            <input type="text" name="cpf_atleta" id="edit_cpf" class="form-control" placeholder="000.000.000-00" maxlength="14" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RG <span class="text-danger">*</span></label>
                            <input type="text" name="rg_atleta" id="edit_rg" class="form-control" placeholder="00.000.000-0" maxlength="12" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">E-mail do atleta</label>
                            <input type="email" name="email_atleta" id="edit_email" class="form-control" maxlength="255" placeholder="atleta@email.com">
                            <div class="form-text">Opcional. Sem e-mail, o atleta não consegue acessar o app.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" id="edit_categoria" class="form-select">
                                <option value="">— Selecionar —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}"
                                    data-min="{{ $cat->idade_min_categoria }}" data-max="{{ $cat->idade_max_categoria }}" data-sexo="{{ $cat->sexo_categoria }}">
                                    {{ $cat->rotulo }}
                                </option>
                                @endforeach
                                {{-- Inativas em uso: escondidas; o JS mostra só a do atleta aberto (manter é aceito, trocar para ela não) --}}
                                @foreach($categoriasInativasEmUso as $cat)
                                <option value="{{ $cat->id_categoria }}" class="js-categoria-inativa" hidden disabled
                                    data-min="{{ $cat->idade_min_categoria }}" data-max="{{ $cat->idade_max_categoria }}" data-sexo="{{ $cat->sexo_categoria }}">
                                    {{ $cat->rotulo }} (inativa)
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text" id="edit_dica_categoria"></div>
                            <input type="text" name="motivo_categoria" id="edit_motivo_categoria" maxlength="500"
                                class="form-control mt-2 d-none" placeholder="Motivo (categoria acima da idade)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo_atleta" id="edit_sexo" class="form-select" required>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_atleta" id="edit_status" class="form-select" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                            <div class="form-text d-none" id="edit_status_aviso"></div>
                        </div>

                        {{-- Preenchidas pelo JS da lista (data-posicoes) --}}
                        @include('admin.atletas._posicoes', ['prefixo' => 'edit', 'selecionadas' => []])

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

                        <div class="col-md-5">
                            <label class="form-label">Escola <span class="text-danger">*</span></label>
                            <input type="text" name="escola_atleta" id="edit_escola" class="form-control" placeholder="Nome da escola" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Série</label>
                            <input type="text" name="serie_atleta" id="edit_serie" class="form-control" placeholder="Ex: 7º Ano">
                        </div>
                        @include('admin.atletas.modals._periodo', ['prefixo' => 'edit', 'comOld' => false])
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
                                @foreach(\App\Models\Responsavel::GRAUS_PARENTESCO as $grau)
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
                        <div class="col-md-12">
                            <label class="form-label">E-mail do Responsável</label>
                            <input type="email" name="email_responsavel" id="edit_email_responsavel" class="form-control" maxlength="150" placeholder="responsavel@email.com">
                            <div class="form-text">Opcional. Se o responsável tiver outros atletas, o e-mail muda para todos.</div>
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

                    {{-- Endereço do responsável, quando é outro (29/10/2026): o responsável muda o dele pelo app, e os campos
                         acima são só os do atleta. Preenchido pelo data-endereco-responsavel do botão Editar --}}
                    <div id="edit_endereco_responsavel" class="alert alert-info small py-2 mb-3 d-none" role="note"></div>

                    {{-- Nome, CPF e data de nascimento corrigidos pelo próprio atleta no app (tbl_atleta_historico),
                         preenchido pelo data-historico do botão Editar; some quando não há nada --}}
                    <div id="edit_historico_bloco" class="d-none">
                        <p class="modal-section-label">
                            <i class="bi bi-clock-history"></i> Alterações feitas pelo app
                        </p>
                        <ul id="edit_historico" class="list-unstyled small text-muted mb-3" style="max-height:140px;overflow-y:auto;"></ul>
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
