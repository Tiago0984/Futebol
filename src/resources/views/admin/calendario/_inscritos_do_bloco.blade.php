{{--
    Inscritos de um bloco da escalação lado a lado (um time do jogo, ou "Sem time"): nome com a categoria
    embaixo, time (select e aviso do elenco), origem e remover. "Inscrito por" fica de fora para caber.
    $lista (inscrições), $vazio (texto quando não há ninguém), $evento, $escalaveis, $elencos.
--}}
<table class="table table-sm table-hover align-middle mb-0">
    <tbody>
        @forelse ($lista as $inscricao)
        <tr>
            <td class="ps-3">
                <span class="fw-semibold">{{ $inscricao->atleta->nome_atleta }}</span>
                <small class="d-block text-muted">{{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</small>
            </td>
            <td style="min-width:150px">@include('admin.calendario._time_da_inscricao')</td>
            <td><span class="badge-cat" style="cursor:help" title="{{ $inscricao->origem_dica }}">{{ $inscricao->origem_label }}</span></td>
            <td class="text-center" style="width:50px">@include('admin.calendario._remover_inscricao')</td>
        </tr>
        @empty
        <tr>
            <td class="ps-3 text-muted small py-2">{{ $vazio }}</td>
        </tr>
        @endforelse
    </tbody>
</table>
