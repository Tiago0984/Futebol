<div class="modal fade" id="modalEditarJogo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarJogo" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Campeonato <span class="text-danger">*</span></label>
                            <select id="edit_id_campeonato" name="id_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($campeonatos as $camp)
                                <option value="{{ $camp->id_campeonato }}">{{ $camp->nome_campeonato }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data e Hora <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="edit_data_jogo" name="data_jogo" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Time Mandante <span class="text-danger">*</span></label>
                            <select id="edit_id_time_casa" name="id_time_casa" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($times as $time)
                                <option value="{{ $time->id_time }}">{{ $time->nome_time }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end justify-content-center pb-1">
                            <span class="fw-bold text-muted">VS</span>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Time Visitante <span class="text-danger">*</span></label>
                            <select id="edit_id_time_visitante" name="id_time_visitante" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($times as $time)
                                <option value="{{ $time->id_time }}">{{ $time->nome_time }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-123"></i> Placar</p></div>

                        <div class="col-md-3">
                            <label class="form-label">Gols Mandante</label>
                            <input type="number" id="edit_placar_casa" name="placar_time_casa_jogos"
                                class="form-control text-center" min="0" placeholder="—">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gols Visitante</label>
                            <input type="number" id="edit_placar_visitante" name="placar_time_visitante_jogos"
                                class="form-control text-center" min="0" placeholder="—">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
