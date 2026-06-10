<div class="modal fade" id="modalEditarFoto" tabindex="-1" aria-labelledby="modalEditarFotoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarFotoLabel"><i class="bi bi-pencil"></i> Editar Foto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    style="background: transparent; border: 0; font-size: 20px;">&times;</button>
            </div>
            <form id="formEditarFoto" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título</label>
                            <input type="text" name="titulo_galeria" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Ordem</label>
                            <input type="number" name="ordem_galeria" id="edit_ordem" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Status</label>
                            <select name="status_galeria" id="edit_status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Alterar Foto (Opcional)</label>
                            <input type="file" name="foto_galeria" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark">Atualizar Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
