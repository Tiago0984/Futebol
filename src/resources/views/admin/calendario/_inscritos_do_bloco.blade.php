{{--
    Inscritos de um bloco da escalação lado a lado (um time do jogo, ou "Sem time"): em cima, camisa, nome, origem
    e a categoria; embaixo, posição e titular/reserva (times da AACJ) e o time (select e aviso da convocação);
    remover na coluna da direita. "Inscrito por" fica de fora para caber.
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
            <td colspan="2" class="ps-3 py-1 bg-body-tertiary small fw-semibold text-uppercase text-muted">{{ $grupo['titulo'] }} ({{ $grupo['lista']->count() }})</td>
        </tr>
        @endif
        @foreach ($grupo['lista'] as $inscricao)
        {{-- 30/10/2026: uma linha por atleta em duas faixas (antes, 4 colunas apertavam os selects num bloco de meia
             tela: "GOl", "A d", "AACJ Sub-1"). Em cima, camisa, nome, categoria e origem; embaixo, posição,
             titular/reserva e o time, cada um com largura própria e quebrando de linha quando não cabem --}}
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
                @if ($formEscalacao)
                @php $posicao = $inscricao->posicaoNaTela(); @endphp
                    <select name="escalacao[{{ $inscricao->id_atleta }}][posicao]" form="{{ $formEscalacao }}"
                            class="form-select form-select-sm admin-escalacao-posicao" aria-label="Posição de {{ $inscricao->atleta->nome_atleta }} no jogo"
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
                            class="form-select form-select-sm admin-escalacao-titular" aria-label="Titular ou reserva: {{ $inscricao->atleta->nome_atleta }}">
                        <option value="">A definir</option>
                        @foreach (\App\Models\EventoAtleta::TITULARIDADES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($inscricao->titular_evento_atleta === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @endif
                    <div class="admin-escalacao-time">@include('admin.calendario._time_da_inscricao')</div>
                </div>
            </td>
            <td class="text-center align-top pt-2 admin-coluna-remover">@include('admin.calendario._remover_inscricao')</td>
        </tr>
        @endforeach
        @empty
        <tr>
            <td class="ps-3 text-muted small py-2">{{ $vazio }}</td>
        </tr>
        @endforelse
    </tbody>
</table>
