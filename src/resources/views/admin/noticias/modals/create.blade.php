<div class="modal fade" id="modalCriarNoticia" tabindex="-1" aria-labelledby="modalCriarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalCriarNoticiaLabel"><i class="bi bi-plus-circle"></i> Nova Notícia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="background: transparent; border: 0; font-size: 20px; color: white;">&times;</button>
            </div>
            <form action="{{ route('admin.noticias.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título da Notícia</label>
                            <input type="text" name="titulo_noticia" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Categoria</label>
                            <input type="text" name="categoria_noticia" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Autor</label>
                            <input type="text" name="autor_noticia" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Data de Publicação</label>
                            <input type="datetime-local" name="data_publicacao_noticia" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Foto de Capa</label>
                            <input type="file" name="foto_noticia" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label font-weight-bold text-dark">Conteúdo da Matéria</label>
                            <textarea name="conteudo_noticia" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar Notícia</button>
                </div>
            </form>
        </div>
    </div>
</div>