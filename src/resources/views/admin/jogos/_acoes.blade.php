{{--
    Ações do jogo: inscritos (abre a tela do jogo), editar (modal #modalEditarJogo, preenchido pelo JS de
    admin.jogos._script_form), cancelar <-> reativar e ocultar <-> mostrar. Usado na lista de Jogos e nos
    blocos de jogos (Jogos do campeonato e dos amistosos); quem inclui põe o contêiner e o modal na página.
    $jogo com o evento carregado com comInscritosAtivos(). Cancelar e ocultar voltam para a tela de onde vieram.
--}}
@php $ev = $jogo->evento; @endphp
<a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}"
   class="btn-tbl view" title="Inscritos ({{ $ev->inscritos_ativos }})">
    <i class="bi bi-people"></i><small class="ms-1">{{ $ev->inscritos_ativos }}</small>
</a>
<button type="button" class="btn-tbl edit btn-editar-jogo"
    data-bs-toggle="modal" data-bs-target="#modalEditarJogo"
    data-id="{{ $jogo->id_jogo }}"
    data-campeonato="{{ $jogo->id_campeonato ?? \App\Http\Controllers\Admin\JogosController::AMISTOSO }}"
    data-categoria="{{ $ev->id_categoria }}"
    data-casa="{{ $jogo->id_time_casa }}"
    data-visitante="{{ $jogo->id_time_visitante }}"
    data-data="{{ $ev->data_evento_calendario->format('Y-m-d') }}"
    data-inicio="{{ substr((string) $ev->horario_inicio_evento_calendario, 0, 5) }}"
    data-fim="{{ substr((string) $ev->horario_fim_evento_calendario, 0, 5) }}"
    data-local="{{ $ev->local_evento_calendario }}"
    data-placar-casa="{{ $jogo->placar_time_casa_jogos }}"
    data-placar-visitante="{{ $jogo->placar_time_visitante_jogos }}"
    title="Editar">
    <i class="bi bi-pencil"></i>
</button>
{{-- Status é do evento: cancelar <-> reativar (oculto precisa ser mostrado antes) --}}
@unless($ev->estaOculto())
<form action="{{ route('admin.calendario.eventos.cancelar', $ev->id_evento_calendario) }}" method="POST" style="display:inline"
      onsubmit="return confirm(@js($ev->estaCancelado() ? 'Reativar este jogo?' : 'Cancelar este jogo? Ele continua visível no site, com o selo "Cancelado".'))">
    @csrf @method('PATCH')
    @if($ev->estaCancelado())
        <button type="submit" class="btn-tbl activate" title="Reativar jogo">
            <i class="bi bi-check-circle"></i>
        </button>
    @else
        <button type="submit" class="btn-tbl deactivate" title="Cancelar jogo">
            <i class="bi bi-x-circle"></i>
        </button>
    @endif
</form>
@endunless
{{-- Ocultar <-> mostrar: substitui a exclusão (o registro e os cartões ficam) --}}
<form action="{{ route('admin.calendario.eventos.ocultar', $ev->id_evento_calendario) }}" method="POST" style="display:inline"
      onsubmit="return confirm(@js($ev->estaOculto() ? 'Mostrar este jogo de novo?' : 'Ocultar este jogo? Ele some do calendário do site, mas o registro fica.'))">
    @csrf @method('PATCH')
    @if($ev->estaOculto())
        <button type="submit" class="btn-tbl activate" title="Mostrar jogo">
            <i class="bi bi-eye"></i>
        </button>
    @else
        <button type="submit" class="btn-tbl deactivate" title="Ocultar jogo">
            <i class="bi bi-eye-slash"></i>
        </button>
    @endif
</form>
