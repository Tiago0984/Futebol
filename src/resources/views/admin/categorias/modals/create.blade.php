<div class="modal fade" id="modalCriarCategoria" tabindex="-1" aria-labelledby="modalCriarCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarCategoriaLabel">
                    <i class="bi bi-tags"></i> Nova Categoria
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.categorias.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" name="nome_categoria" class="form-control"
                                placeholder="Ex: Sub-13" required value="{{ old('nome_categoria') }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Idade Mínima <span class="text-danger">*</span></label>
                            <input type="number" name="idade_min_categoria" class="form-control text-center"
                                placeholder="Ex: 12" min="1" max="99" required value="{{ old('idade_min_categoria') }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Idade Máxima <span class="text-danger">*</span></label>
                            <input type="number" name="idade_max_categoria" class="form-control text-center"
                                placeholder="Ex: 13" min="1" max="99" required value="{{ old('idade_max_categoria') }}">
                        </div>
                        <div class="col-md-2"></div>
                        <div class="col-12">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo_categoria" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                <option value="M" {{ old('sexo_categoria') == 'M' ? 'selected' : '' }}>Masculino</option>
                                <option value="F" {{ old('sexo_categoria') == 'F' ? 'selected' : '' }}>Feminino</option>
                                <option value="Misto" {{ old('sexo_categoria') == 'Misto' ? 'selected' : '' }}>Misto</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
