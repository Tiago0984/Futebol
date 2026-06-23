<div class="modal fade" id="modalEditarBanner" tabindex="-1" aria-labelledby="modalEditarBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarBannerLabel">
                    <i class="bi bi-pencil"></i> Editar Banner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formEditarBanner" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_banner" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subtítulo <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="subtitulo_banner" id="edit_subtitulo" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_banner" id="edit_ordem" class="form-control text-center" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_banner" id="edit_status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Alterar Imagem <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="file" name="foto_banner" class="form-control" accept="image/*">
                            <span class="form-text">Deixe vazio para manter a imagem atual</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Atualizar Banner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
