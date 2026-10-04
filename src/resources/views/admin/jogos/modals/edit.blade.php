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
                    @include('admin.jogos.modals._campos', ['p' => 'edit_', 'comOld' => false])
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Mudanças de data, horário e local entram no histórico do evento. Para cancelar ou ocultar o jogo, use os botões da lista.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
