{{--
    Inscritos do bloco "Sem time" da escalação (os times da AACJ usam o campo, _campo_do_time, desde 03/11/2026): em
    cima, camisa, nome, origem e a categoria; embaixo, o time (select e aviso da convocação); remover na coluna da
    direita. "Inscrito por" fica de fora para caber.
    $lista (inscrições), $vazio (texto quando não há ninguém), $evento, $escalaveis, $elencos, $camisas
    (camisa da convocação no jogo de campeonato: "10 · Nome"), $inscritosAcimaDaIdade (selo de quem passa
    da idade do campeonato).
--}}
<table class="table table-sm table-hover align-middle mb-0">
    <tbody>
        @forelse ($lista as $inscricao)
        <tr>
            <td class="ps-3">
                <div class="d-flex flex-wrap align-items-center gap-1">
                    @if (isset($camisas[$inscricao->id_atleta]))
                        <span class="badge text-bg-secondary" title="Camisa na convocação do campeonato">{{ $camisas[$inscricao->id_atleta] }}</span>
                    @endif
                    <span class="fw-semibold">{{ $inscricao->atleta->nome_atleta }}</span>
                    <span class="badge-cat admin-cursor-ajuda ms-auto" title="{{ $inscricao->origem_dica }}">{{ $inscricao->origem_label }}</span>
                </div>
                <small class="d-block text-muted">{{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</small>
                @if (isset($inscritosAcimaDaIdade[$inscricao->id_atleta]))
                    <small class="d-block text-danger fw-semibold">Acima da idade do campeonato</small>
                @endif
                <div class="admin-escalacao-campos mt-1">
                    <div class="admin-escalacao-time">@include('admin.calendario._time_da_inscricao')</div>
                </div>
            </td>
            <td class="text-center align-top pt-2 admin-coluna-remover">@include('admin.calendario._remover_inscricao')</td>
        </tr>
        @empty
        <tr>
            <td class="ps-3 text-muted small py-2">{{ $vazio }}</td>
        </tr>
        @endforelse
    </tbody>
</table>
