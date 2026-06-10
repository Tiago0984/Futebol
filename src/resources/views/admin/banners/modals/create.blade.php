<div class="modal fade" id="modalCriarBanner" tabindex="-1" aria-labelledby="modalCriarBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalCriarBannerLabel"><i class="bi bi-plus-circle"></i> Novo Banner</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                    style="background: transparent; border: 0; font-size: 20px; color: white;">&times;</button>
            </div>
            <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título do Banner <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_banner" class="form-control" placeholder="Ex: Bem-vindo ao AACJ" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Subtítulo</label>
                            <input type="text" name="subtitulo_banner" class="form-control" placeholder="Texto de apoio (opcional)">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_banner" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Status <span class="text-danger">*</span></label>
                            <select name="status_banner" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Imagem <span class="text-danger">*</span></label>
                            <input type="file" name="foto_banner" class="form-control" accept="image/*" required>
                            <small class="text-muted">Máx. 2MB. Recomendado: 1920×600px</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
