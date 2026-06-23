<div class="modal fade" id="modalCriarNoticia" tabindex="-1" aria-labelledby="modalCriarNoticiaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarNoticiaLabel">
                    <i class="bi bi-newspaper"></i> Nova Notícia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="{{ route('admin.noticias.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_noticia" class="form-control" placeholder="Digite o título da notícia" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <input type="text" name="categoria_noticia" class="form-control" placeholder="Ex: Esportes" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Autor <span class="text-danger">*</span></label>
                            <input type="text" name="autor_noticia" class="form-control" placeholder="Nome do autor" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Publicação <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="data_publicacao_noticia" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto de Capa</label>
                            <input type="file" name="foto_noticia" class="form-control" accept="image/*">
                            <span class="form-text">Formatos aceitos: JPG, PNG, WEBP. Máx. 2MB</span>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Conteúdo <span class="text-danger">*</span></label>
                            <textarea name="conteudo_noticia" class="form-control" rows="6" placeholder="Escreva o conteúdo da notícia aqui..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Salvar Notícia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
