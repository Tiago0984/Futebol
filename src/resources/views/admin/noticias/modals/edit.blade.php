<div class="modal fade" id="modalEditarNoticia" tabindex="-1" aria-labelledby="modalEditarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarNoticiaLabel"><i class="bi bi-pencil"></i> Editar Notícia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: transparent; border: 0; font-size: 20px;">&times;</button>
            </div>
            <form id="formEditarNoticia" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título da Notícia</label>
                            <input type="text" name="titulo_noticia" id="edit_titulo" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Categoria</label>
                            <input type="text" name="categoria_noticia" id="edit_categoria" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Autor</label>
                            <input type="text" name="autor_noticia" id="edit_autor" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Data de Publicação</label>
                            <input type="datetime-local" name="data_publicacao_noticia" id="edit_data" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Alterar Foto de Capa (Opcional)</label>
                            <input type="file" name="foto_noticia" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Conteúdo da Matéria</label>
                            <textarea name="conteudo_noticia" id="edit_conteudo" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark">Atualizar Notícia</button>
                </div>
            </form>
        </div>
    </div>
</div>