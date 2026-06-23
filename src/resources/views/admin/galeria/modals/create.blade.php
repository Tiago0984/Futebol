<div class="modal fade" id="modalCriarFoto" tabindex="-1" aria-labelledby="modalCriarFotoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarFotoLabel">
                    <i class="bi bi-camera"></i> Nova Foto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="{{ route('admin.galeria.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="modal_origem" value="criar">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_galeria" class="form-control" placeholder="Ex: Treino do dia 20/06" value="{{ old('titulo_galeria') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem <span class="text-danger">*</span></label>
                            <input type="number" name="ordem_galeria" id="create_ordem" class="form-control text-center" min="1" value="{{ old('ordem_galeria', $proximaOrdem) }}" required>
                            <div id="create_ordem_feedback" class="small mt-1">
                                <span class="text-muted">Próxima: {{ $proximaOrdem }}</span>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select name="categoria_galeria" id="create_cat_select" class="form-select" required>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat }}" {{ old('categoria_galeria', 'GERAL') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                                <option value="__nova__" {{ old('categoria_galeria') === '__nova__' ? 'selected' : '' }}>+ Nova categoria</option>
                            </select>
                            <input type="text" name="nova_categoria_galeria" id="create_cat_nova"
                                   class="form-control mt-2"
                                   style="{{ old('categoria_galeria') === '__nova__' ? '' : 'display:none;' }}"
                                   value="{{ old('nova_categoria_galeria') }}"
                                   placeholder="Nome da nova categoria (ex: TORNEIOS)">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_galeria" class="form-select" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto <span class="text-danger">*</span></label>
                            <input type="file" name="foto_galeria" class="form-control" accept="image/*" required>
                            <span class="form-text">Formatos aceitos: JPG, PNG, WEBP. Máx. 5MB</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Salvar Foto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
