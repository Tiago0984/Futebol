{{-- Botão de remover a inscrição (com confirmação). $evento, $inscricao --}}
<form action="{{ route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $inscricao->id_atleta]) }}"
      method="POST" style="display:inline"
      onsubmit="return confirm(@js('Remover a inscrição de ' . $inscricao->atleta->nome_atleta . '?'))">
    @csrf @method('DELETE')
    <button type="submit" class="btn-tbl delete" title="Remover inscrição">
        <i class="bi bi-x-lg"></i>
    </button>
</form>
