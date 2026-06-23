<div class="modal fade" id="modalEditarNoticia" tabindex="-1" aria-labelledby="modalEditarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarNoticiaLabel">
                    <i class="bi bi-pencil"></i> Editar Notícia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formEditarNoticia" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_noticia" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <input type="text" name="categoria_noticia" id="edit_categoria" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Autor <span class="text-danger">*</span></label>
                            <input type="text" name="autor_noticia" id="edit_autor" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Publicação <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="data_publicacao_noticia" id="edit_data" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto de Capa <span class="text-muted fw-normal">(opcional — deixe vazio para manter)</span></label>
                            <input type="file" name="foto_noticia" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Conteúdo <span class="text-danger">*</span></label>
                            <textarea name="conteudo_noticia" id="edit_conteudo" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Atualizar Notícia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
