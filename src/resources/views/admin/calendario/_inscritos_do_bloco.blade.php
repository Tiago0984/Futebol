{{--
    Inscritos de um bloco da escalação lado a lado (um time do jogo, ou "Sem time"): nome com a categoria
    embaixo, time (select e aviso do elenco), origem e remover. "Inscrito por" fica de fora para caber.
    $lista (inscrições), $vazio (texto quando não há ninguém), $evento, $escalaveis, $elencos, $camisas
    (camisa da convocação no jogo de campeonato: "10 · Nome"), $inscritosAcimaDaIdade (selo de quem passa
    da idade do campeonato).
    $formEscalacao (só nos times da AACJ; Fase D, Etapa D2): id do formulário "Salvar escalação" do bloco. Com ele,
    cada linha ganha a posição no jogo (a principal do atleta pré-selecionada, "sugerida", até salvar) e
    titular/reserva, e a lista fica dividida em Titulares, Reservas e A definir (a ordem de $lista é mantida).
--}}
@php
    $formEscalacao = $formEscalacao ?? null;
    $grupos = $formEscalacao
        ? collect(['TITULAR' => 'Titulares', 'RESERVA' => 'Reservas', '' => 'A definir'])
            ->map(fn ($titulo, $valor) => ['titulo' => $titulo, 'lista' => $lista->filter(fn ($i) => (string) $i->titular_evento_atleta === (string) $valor)])
        : collect([['titulo' => null, 'lista' => $lista]]);
    $grupos = $grupos->filter(fn ($grupo) => $grupo['lista']->isNotEmpty());
    $porSetor = collect(\App\Models\Atleta::POSICOES)->groupBy('setor', preserveKeys: true);
@endphp
<table class="table table-sm table-hover align-middle mb-0">
    <tbody>
        @forelse ($grupos as $grupo)
        @if ($grupo['titulo'])
        <tr class="js-grupo-escalacao">
            <td colspan="4" class="ps-3 py-1 bg-body-tertiary small fw-semibold text-uppercase text-muted">{{ $grupo['titulo'] }} ({{ $grupo['lista']->count() }})</td>
        </tr>
        @endif
        @foreach ($grupo['lista'] as $inscricao)
        <tr>
            <td class="ps-3">
                @if (isset($camisas[$inscricao->id_atleta]))
                    <span class="badge text-bg-secondary me-1" title="Camisa na convocação do campeonato">{{ $camisas[$inscricao->id_atleta] }}</span>
                @endif
                <span class="fw-semibold">{{ $inscricao->atleta->nome_atleta }}</span>
                <small class="d-block text-muted">{{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</small>
                @if (isset($inscritosAcimaDaIdade[$inscricao->id_atleta]))
                    <small class="d-block text-danger fw-semibold">Acima da idade do campeonato</small>
                @endif
                @if ($formEscalacao)
                @php $posicao = $inscricao->posicaoNaTela(); @endphp
                <div class="d-flex gap-1 mt-1">
                    <select name="escalacao[{{ $inscricao->id_atleta }}][posicao]" form="{{ $formEscalacao }}"
                            class="form-select form-select-sm" style="min-width:0" aria-label="Posição de {{ $inscricao->atleta->nome_atleta }} no jogo"
                            title="{{ $inscricao->posicaoSugerida() ? 'Sugerida: a posição principal do atleta (grava ao salvar)' : 'Posição no jogo' }}">
                        <option value="">Posição…</option>
                        @foreach ($porSetor as $setor => $posicoes)
                        <optgroup label="{{ $setor }}">
                            @foreach ($posicoes as $codigo => $dados)
                            <option value="{{ $codigo }}" @selected($posicao === $codigo)>{{ $dados['sigla'] }} · {{ $dados['nome'] }}{{ $posicao === $codigo && $inscricao->posicaoSugerida() ? ' (sugerida)' : '' }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                    <select name="escalacao[{{ $inscricao->id_atleta }}][titular]" form="{{ $formEscalacao }}"
                            class="form-select form-select-sm" style="min-width:0;max-width:110px" aria-label="Titular ou reserva: {{ $inscricao->atleta->nome_atleta }}">
                        <option value="">A definir</option>
                        @foreach (\App\Models\EventoAtleta::TITULARIDADES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($inscricao->titular_evento_atleta === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </td>
            <td style="min-width:150px">@include('admin.calendario._time_da_inscricao')</td>
            <td><span class="badge-cat" style="cursor:help" title="{{ $inscricao->origem_dica }}">{{ $inscricao->origem_label }}</span></td>
            <td class="text-center" style="width:50px">@include('admin.calendario._remover_inscricao')</td>
        </tr>
        @endforeach
        @empty
        <tr>
            <td class="ps-3 text-muted small py-2">{{ $vazio }}</td>
        </tr>
        @endforelse
    </tbody>
</table>
