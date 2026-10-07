{{--
    Editar evento (não jogo: o jogo é editado pelo formulário do jogo).
    - Lista do Calendário: o JS preenche o modal pelo botão da linha (ação, campos, histórico, categoria inativa).
      $categoriasInativasEmUso: inativas que algum evento usa, escondidas até o JS mostrar a do evento aberto.
    - Tela do evento: $eventoEmEdicao vem preenchido (com o histórico carregado): o formulário já abre com
      os valores atuais e, depois de salvar, volta para a tela do evento (voltar=evento).
    Tipo JOGO: o Calendário não cria jogos; só o evento JOGO antigo (sem tbl_jogos) continua com a opção.
--}}
@php
    $ev = $eventoEmEdicao ?? null;
    $valorEv = fn (string $campo, $atual = null) => $ev ? old($campo, $atual ?? $ev->{$campo}) : null;
    $hora = fn ($valor) => $valor ? substr((string) $valor, 0, 5) : null;
    $tipoAtual = $valorEv('tipo_evento_calendario');
@endphp
<div class="modal fade" id="modalEditarEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarEvento" method="POST" @if($ev) action="{{ route('admin.calendario.eventos.update', $ev->id_evento_calendario) }}" @endif>
                @csrf @method('PUT')
                @if($ev)
                <input type="hidden" name="voltar" value="evento">
                @endif
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" id="edit_ev_titulo" name="titulo_evento_calendario"
                                class="form-control" required value="{{ $valorEv('titulo_evento_calendario') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" id="edit_ev_data" name="data_evento_calendario"
                                class="form-control" required value="{{ $valorEv('data_evento_calendario', $ev?->data_evento_calendario->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_ev_tipo" name="tipo_evento_calendario" class="form-select js-tipo-evento" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\EventoCalendario::TIPOS as $tipo => $rotulo)
                                    {{-- JOGO: só para o evento JOGO antigo (na lista, o JS libera quando é o caso) --}}
                                    @if($tipo === 'JOGO')
                                        @if(! $ev || $ev->tipo_evento_calendario === 'JOGO')
                                        <option value="JOGO" class="js-tipo-jogo" @if(! $ev) hidden disabled @endif @selected($tipoAtual === 'JOGO')>{{ $rotulo }} (antigo)</option>
                                        @endif
                                    @else
                                    <option value="{{ $tipo }}" @selected($tipoAtual === $tipo)>{{ $rotulo }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtipo</label>
                            <input type="text" id="edit_ev_subtipo" name="subtipo_evento_calendario"
                                class="form-control js-subtipo" autocomplete="off" value="{{ $valorEv('subtipo_evento_calendario') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Início</label>
                            <input type="time" id="edit_ev_inicio" name="horario_inicio_evento_calendario"
                                class="form-control" value="{{ $hora($valorEv('horario_inicio_evento_calendario')) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fim</label>
                            <input type="time" id="edit_ev_fim" name="horario_fim_evento_calendario"
                                class="form-control" value="{{ $hora($valorEv('horario_fim_evento_calendario')) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Local</label>
                            <input type="text" id="edit_ev_local" name="local_evento_calendario"
                                class="form-control" list="locaisUsados" autocomplete="off" value="{{ $valorEv('local_evento_calendario') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoria</label>
                            <select id="edit_ev_categoria" name="id_categoria" class="form-select">
                                <option value="">Sem categoria (evento individual)</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" @selected((string) $valorEv('id_categoria') === (string) $cat->id_categoria)>{{ $cat->rotulo }}</option>
                                @endforeach
                                {{-- Inativas em uso: na lista, escondidas (o JS mostra só a do evento aberto); na tela do
                                     evento, só a categoria atual, se estiver inativa --}}
                                @foreach($categoriasInativasEmUso as $cat)
                                    @if(! $ev)
                                    <option value="{{ $cat->id_categoria }}" class="js-categoria-inativa" hidden disabled>{{ $cat->rotulo }} (inativa)</option>
                                    @elseif((int) $cat->id_categoria === (int) $ev->id_categoria)
                                    <option value="{{ $cat->id_categoria }}" @selected((string) $valorEv('id_categoria') === (string) $cat->id_categoria)>{{ $cat->rotulo }} (inativa)</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-text"><i class="bi bi-person"></i> Responsável (quem criou): <strong id="edit_ev_responsavel">{{ $ev?->responsavel?->nome_usuario ?? '—' }}</strong></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1"><i class="bi bi-clock-history"></i> Histórico de alterações</label>
                            <ul id="edit_ev_historico" class="list-unstyled small text-muted mb-0" style="max-height:140px;overflow-y:auto;">
                                @if($ev)
                                    @forelse($ev->historico->take(20) as $h)
                                    <li>{{ $h->data_evento_historico->format('d/m/Y H:i') }} · {{ $h->usuario?->nome_usuario ?? '—' }} · {{ $h->resumo }}</li>
                                    @empty
                                    <li>Nenhuma alteração registrada.</li>
                                    @endforelse
                                @endif
                            </ul>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea id="edit_ev_descricao" name="descricao_evento_calendario"
                                class="form-control" rows="2">{{ $valorEv('descricao_evento_calendario') }}</textarea>
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
