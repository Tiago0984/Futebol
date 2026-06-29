<div class="modal fade" id="modalCriarJogo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event"></i> Novo Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.jogos.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Campeonato <span class="text-danger">*</span></label>
                            <select name="id_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($campeonatos as $camp)
                                <option value="{{ $camp->id_campeonato }}" {{ old('id_campeonato') == $camp->id_campeonato ? 'selected' : '' }}>
                                    {{ $camp->nome_campeonato }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data e Hora <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="data_jogo" class="form-control" required
                                value="{{ old('data_jogo') }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Time Mandante <span class="text-danger">*</span></label>
                            <select name="id_time_casa" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($times as $time)
                                <option value="{{ $time->id_time }}" {{ old('id_time_casa') == $time->id_time ? 'selected' : '' }}>
                                    {{ $time->nome_time }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end justify-content-center pb-1">
                            <span class="fw-bold text-muted">VS</span>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Time Visitante <span class="text-danger">*</span></label>
                            <select name="id_time_visitante" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach($times as $time)
                                <option value="{{ $time->id_time }}" {{ old('id_time_visitante') == $time->id_time ? 'selected' : '' }}>
                                    {{ $time->nome_time }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-123"></i> Placar (opcional — preencha após o jogo)</p></div>

                        <div class="col-md-3">
                            <label class="form-label">Gols Mandante</label>
                            <input type="number" name="placar_time_casa_jogos" class="form-control text-center"
                                min="0" placeholder="—" value="{{ old('placar_time_casa_jogos') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gols Visitante</label>
                            <input type="number" name="placar_time_visitante_jogos" class="form-control text-center"
                                min="0" placeholder="—" value="{{ old('placar_time_visitante_jogos') }}">
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
