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
                <input type="hidden" name="modal_origem" value="criar">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label font-weight-bold text-dark">Título</label>
                            <input type="text" name="titulo_galeria" class="form-control" value="{{ old('titulo_galeria') }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold text-dark">Ordem</label>
                            <input type="number" name="ordem_galeria" id="create_ordem" class="form-control" min="1" value="{{ old('ordem_galeria', $proximaOrdem) }}" required>
                            <div id="create_ordem_feedback" class="small mt-1">
                                <span class="text-muted">Próxima disponível: {{ $proximaOrdem }}</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Categoria</label>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold text-dark">Status</label>
                            <select name="status_galeria" class="form-select" required>
                                <option value="ATIVO">ATIVO</option>
                                <option value="INATIVO">INATIVO</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
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
