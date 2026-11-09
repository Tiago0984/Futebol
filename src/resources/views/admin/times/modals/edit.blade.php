<div class="modal fade" id="modalEditarTime" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTime" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <input type="hidden" name="_editar_id" id="edit_editar_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome do Time <span class="text-danger">*</span></label>
                            <input type="text" id="edit_nome_time" name="nome_time" class="form-control" maxlength="50" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_tipo_time" name="tipo_time" class="form-select" required>
                                <option value="INTERNO">Interno (AACJ)</option>
                                <option value="EXTERNO">Externo</option>
                            </select>
                        </div>
                        @include('admin.times._categorias', ['prefixo' => 'editar', 'marcadas' => []])
                        <div class="col-12">
                            <label class="form-label" for="edit_logo_time">Logo / Escudo</label>
                            <small id="edit_logo_atual" class="d-block text-muted mb-1"></small>
                            <input type="file" name="logo_time" id="edit_logo_time" class="form-control" accept="image/*"
                                data-max-kb="{{ \App\Services\TratadorDeImagem::ENVIO_MAX_KB }}"
                                aria-describedby="edit_logo_time_ajuda edit_logo_time_erro">
                            <span class="form-text" id="edit_logo_time_ajuda">Deixe em branco para manter a logo atual. JPG, PNG, WEBP, até 10 MB.</span>
                            <p id="edit_logo_time_erro" class="text-danger small mt-2 mb-0" role="alert" hidden></p>
                            <div id="edit_previa_escudo" class="mt-3" hidden>
                                <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>
                                <img id="edit_imagem_escudo" class="previa-time-escudo rounded border bg-body-tertiary"
                                    alt="Prévia do escudo do time em edição">
                                <span class="form-text">O escudo fica inteiro e centralizado, sem cortes.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
