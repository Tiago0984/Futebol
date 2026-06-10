<div class="modal fade" id="modalEditarBanner" tabindex="-1" aria-labelledby="modalEditarBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarBannerLabel"><i class="bi bi-pencil"></i> Editar Banner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    style="background: transparent; border: 0; font-size: 20px;">&times;</button>
            </div>
            <form id="formEditarBanner" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título do Banner <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_banner" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Subtítulo</label>
                            <input type="text" name="subtitulo_banner" id="edit_subtitulo" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_banner" id="edit_ordem" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Status <span class="text-danger">*</span></label>
                            <select name="status_banner" id="edit_status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Alterar Imagem (Opcional)</label>
                            <input type="file" name="foto_banner" class="form-control" accept="image/*">
                            <small class="text-muted">Deixe vazio para manter a imagem atual</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark">Atualizar Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
