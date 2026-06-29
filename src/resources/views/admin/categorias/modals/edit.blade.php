<div class="modal fade" id="modalEditarCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square"></i> Editar Categoria
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCategoria" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" id="edit_nome_categoria" name="nome_categoria" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Idade Mínima <span class="text-danger">*</span></label>
                            <input type="number" id="edit_idade_min_categoria" name="idade_min_categoria"
                                class="form-control text-center" min="1" max="99" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Idade Máxima <span class="text-danger">*</span></label>
                            <input type="number" id="edit_idade_max_categoria" name="idade_max_categoria"
                                class="form-control text-center" min="1" max="99" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select id="edit_sexo_categoria" name="sexo_categoria" class="form-select" required>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                                <option value="Misto">Misto</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Atualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
