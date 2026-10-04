{{-- Selo da autorização nas listas de Matrículas; $situacao vem de Atleta::situacaoAutorizacao() --}}
@if($situacao === 'ASSINADA')
    <span class="badge-status ativo"><i class="bi bi-pen me-1"></i>Assinada</span>
@elseif($situacao === 'PENDENTE')
    <span class="badge-status pendente" title="Há link para o responsável assinar (em Ver)"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
@else
    <span class="badge-status inativo" title="Nenhuma autorização com link para assinar"><i class="bi bi-slash-circle me-1"></i>Sem autorização</span>
@endif
