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
                            <label class="form-label" for="criar_logo_time">Logo / Escudo</label>
                            <input type="file" name="logo_time" id="criar_logo_time" class="form-control" accept="image/*"
                                data-max-kb="{{ \App\Services\TratadorDeImagem::ENVIO_MAX_KB }}"
                                aria-describedby="criar_logo_time_ajuda criar_logo_time_erro">
                            <span class="form-text" id="criar_logo_time_ajuda">JPG, PNG, WEBP, até 10 MB. A logo entra inteira num quadrado de até 512 px, com fundo transparente.</span>
                            <p id="criar_logo_time_erro" class="text-danger small mt-2 mb-0" role="alert" hidden></p>
                            <div id="criar_previa_escudo" class="mt-3" hidden>
                                <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>
                                <img id="criar_imagem_escudo" class="previa-time-escudo rounded border bg-body-tertiary"
                                    alt="Prévia do escudo do novo time">
                                <span class="form-text">O escudo fica inteiro e centralizado, sem cortes.</span>
                            </div>
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
