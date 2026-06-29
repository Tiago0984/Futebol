<div class="modal fade" id="modalCriarTime" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-plus"></i> Novo Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.times.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome do Time <span class="text-danger">*</span></label>
                            <input type="text" name="nome_time" class="form-control"
                                placeholder="Ex: AACJ Sub-13" required value="{{ old('nome_time') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_time" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                <option value="INTERNO" {{ old('tipo_time') == 'INTERNO' ? 'selected' : '' }}>Interno</option>
                                <option value="EXTERNO" {{ old('tipo_time') == 'EXTERNO' ? 'selected' : '' }}>Externo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
                                    {{ $cat->nome_categoria }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Logo / Escudo</label>
                            <input type="file" name="logo_time" class="form-control" accept="image/*">
                            <span class="form-text">JPG, PNG, WEBP. Máx. 1MB.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
