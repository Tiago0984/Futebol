<div class="modal fade" id="modalEditarTime" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTime" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome do Time <span class="text-danger">*</span></label>
                            <input type="text" id="edit_nome_time" name="nome_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_tipo_time" name="tipo_time" class="form-select" required>
                                <option value="INTERNO">Interno</option>
                                <option value="EXTERNO">Externo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select id="edit_id_categoria" name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Logo / Escudo</label>
                            <small id="edit_logo_atual" class="d-block text-muted mb-1"></small>
                            <input type="file" name="logo_time" class="form-control" accept="image/*">
                            <span class="form-text">Deixe em branco para manter o logo atual.</span>
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
