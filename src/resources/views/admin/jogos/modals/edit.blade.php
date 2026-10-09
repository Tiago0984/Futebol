{{--
    Editar jogo. Na lista de Jogos, o JS preenche o modal (ação e campos) pelo botão da linha.
    Na tela do jogo, $jogoEmEdicao vem preenchido: o formulário já abre com os valores atuais e, depois de
    salvar, volta para a tela do jogo (voltar=evento). Com $voltarParaPagina (Jogos do campeonato e dos
    amistosos), volta para a página de onde foi aberto (voltar=pagina).
--}}
@php
    $jogoEmEdicao = $jogoEmEdicao ?? null;
    $ev = $jogoEmEdicao?->evento;
    $valoresJogo = $jogoEmEdicao ? [
        'id_campeonato'                    => $jogoEmEdicao->id_campeonato ?? \App\Http\Controllers\Admin\JogosController::AMISTOSO,
        'id_categoria'                     => $ev->id_categoria,
        'id_time_casa'                     => $jogoEmEdicao->id_time_casa,
        'id_time_visitante'                => $jogoEmEdicao->id_time_visitante,
        'data_evento_calendario'           => $ev->data_evento_calendario->format('Y-m-d'),
        'horario_inicio_evento_calendario' => substr((string) $ev->horario_inicio_evento_calendario, 0, 5),
        'horario_fim_evento_calendario'    => substr((string) $ev->horario_fim_evento_calendario, 0, 5),
        'local_evento_calendario'          => $ev->local_evento_calendario,
        'placar_time_casa_jogos'           => $jogoEmEdicao->placar_time_casa_jogos,
        'placar_time_visitante_jogos'      => $jogoEmEdicao->placar_time_visitante_jogos,
    ] : [];
@endphp
<div class="modal fade" id="modalEditarJogo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Jogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarJogo" method="POST" @if($jogoEmEdicao) action="{{ route('admin.jogos.update', $jogoEmEdicao->id_jogo) }}" @endif>
                @csrf @method('PUT')
                @if($jogoEmEdicao)
                <input type="hidden" name="voltar" value="evento">
                @elseif(! empty($voltarParaPagina))
                <input type="hidden" name="voltar" value="pagina">
                @endif
                <div class="modal-body">
                    @include('admin.jogos.modals._campos', ['p' => 'edit_', 'comOld' => (bool) $jogoEmEdicao, 'valores' => $valoresJogo])
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Mudanças de data, horário e local entram no histórico do evento. Para cancelar ou ocultar o jogo, use os botões da lista ou da tela do jogo.
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
