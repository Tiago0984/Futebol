<div class="modal fade" id="modalEditarFoto" tabindex="-1" aria-labelledby="modalEditarFotoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarFotoLabel">
                    <i class="bi bi-pencil"></i> Editar Foto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formEditarFoto" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="modal_origem" value="editar">
                <input type="hidden" name="id_foto_edicao" id="edit_id_foto">
                <input type="hidden" name="ordem_original_galeria" id="edit_ordem_original">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_galeria" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_galeria" id="edit_ordem" class="form-control text-center" min="1" required>
                            <div id="edit_ordem_feedback" class="small mt-1"></div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select name="categoria_galeria" id="edit_cat_select" class="form-select" required>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="__nova__">+ Nova categoria</option>
                            </select>
                            <input type="text" name="nova_categoria_galeria" id="edit_cat_nova"
                                   class="form-control mt-2" style="display:none;"
                                   placeholder="Nome da nova categoria (ex: TORNEIOS)">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_galeria" id="edit_status" class="form-select" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alterar Foto <span class="text-muted fw-normal">(opcional — deixe vazio para manter)</span></label>
                            <input type="file" name="foto_galeria" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Atualizar Foto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
