<div class="modal fade" id="modalCriarBanner" tabindex="-1" aria-labelledby="modalCriarBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarBannerLabel">
                    <i class="bi bi-image"></i> Novo Banner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_banner" class="form-control" placeholder="Ex: Bem-vindo ao AACJ Futebol" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subtítulo <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="subtitulo_banner" class="form-control" placeholder="Texto de apoio exibido abaixo do título">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_banner" class="form-control text-center" min="1" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_banner" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Imagem <span class="text-danger">*</span></label>
                            <input type="file" name="foto_banner" class="form-control" accept="image/*" required>
                            <span class="form-text">Máx. 2MB · Recomendado: 1920×600px</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Salvar Banner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
