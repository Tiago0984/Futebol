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
                            <input type="text" name="nome_time" class="form-control" maxlength="50"
                                placeholder="Ex: AACJ Masculino, Botafogo" required value="{{ old('_editar_id') ? '' : old('nome_time') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_time" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                <option value="INTERNO" {{ ! old('_editar_id') && old('tipo_time') == 'INTERNO' ? 'selected' : '' }}>Interno (AACJ)</option>
                                <option value="EXTERNO" {{ ! old('_editar_id') && old('tipo_time') == 'EXTERNO' ? 'selected' : '' }}>Externo</option>
                            </select>
                        </div>
                        @include('admin.times._categorias', ['prefixo' => 'criar', 'marcadas' => old('_editar_id') ? [] : old('categorias', [])])
                        <div class="col-12">
                            <label class="form-label">Logo / Escudo</label>
                            <input type="file" name="logo_time" class="form-control" accept="image/*">
                            <span class="form-text">JPG, PNG, WEBP, até 10 MB. A logo entra inteira num quadrado de até 512 px, com fundo transparente.</span>
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
