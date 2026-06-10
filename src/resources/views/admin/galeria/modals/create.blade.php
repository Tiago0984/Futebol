<div class="modal fade" id="modalCriarFoto" tabindex="-1" aria-labelledby="modalCriarFotoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalCriarFotoLabel"><i class="bi bi-image"></i> Nova Foto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"
                    style="background: transparent; border: 0; font-size: 20px; color: white;">&times;</button>
            </div>
            <form action="{{ route('admin.galeria.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título</label>
                            <input type="text" name="titulo_galeria" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Ordem</label>
                            <input type="number" name="ordem_galeria" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Status</label>
                            <select name="status_galeria" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Foto</label>
                            <input type="file" name="foto_galeria" class="form-control" accept="image/*" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
