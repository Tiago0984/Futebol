{{--
    Escalação de um time da AACJ no jogo, no campo (03/11/2026, no lugar da lista com os selects de posição e
    titular/reserva): à esquerda o campo com as 11 vagas da formação escolhida; à direita os escalados no time.
    Clicar numa vaga e depois num atleta põe o atleta nela (se ele já estava em outra, os dois trocam); também dá
    para arrastar o atleta da lista até a vaga, de uma vaga para outra, ou de volta para a lista; o "×" tira
    do campo. Quem não está no campo é reserva ao salvar; nunca mais de Escalacao::TITULARES_MAX titulares (o
    servidor confere). A formação não é gravada: Escalacao::doJogo() a reconhece pelas posições dos titulares.
    JS em coderatech/js/escalacao-campo.js; estilos no estilo.css, seções 78 (o campo, o mesmo da home) e 79.
    $bloco (do evento.blade: inscricoes, id_time), $evento, $jogo, $escalaveis, $elencos, $camisas, $inscritosAcimaDaIdade.
--}}
@php
    $lista = $bloco['inscricoes'];
    $campo = \App\Services\Escalacao::doJogo($lista, session('formacao.' . $bloco['id_time']));
    $dadosDoCampo = [
        'formacao'  => $campo['formacao'],
        'ocupadas'  => $campo['vagas'],
        'formacoes' => \App\Services\Escalacao::FORMACOES,
        'vagas'     => collect(\App\Services\Escalacao::VAGAS)->map(fn ($aceitas) => [
            'posicao' => $aceitas[0], 'aceitas' => $aceitas, 'nome' => \App\Models\Atleta::POSICOES[$aceitas[0]]['nome'],
        ]),
        'setores'   => collect(\App\Models\Atleta::POSICOES)->map(fn ($p) => $p['setor']),
        'sugestoes' => \App\Services\Escalacao::sugestoes($lista->pluck('atleta')->sortBy('nome_atleta')->values()),
        'atletas'   => $lista->mapWithKeys(fn ($i) => [$i->id_atleta => [
            'nome'     => $i->atleta->nome_atleta,
            'curto'    => \App\Services\Escalacao::nomeCurto($i->atleta->nome_atleta),
            'iniciais' => \App\Services\Escalacao::iniciais($i->atleta->nome_atleta),
            'foto'     => $i->atleta->urlFoto(),
            'camisa'   => $camisas[$i->id_atleta] ?? null,
            'posicoes' => $i->atleta->posicoes->pluck('posicao')->values(),
        ]]),
        'maximo'    => \App\Services\Escalacao::TITULARES_MAX,
    ];
    $semVaga = $lista->whereIn('id_atleta', $campo['sem_vaga']);
@endphp
<div class="admin-campo js-campo">
    <script type="application/json" class="js-campo-dados">@json($dadosDoCampo)</script>

    @if ($lista->isEmpty())
        <div class="px-3 py-2 text-muted small">Ninguém escalado neste time.</div>
    @else
    <div class="admin-campo-barra">
        <label class="small text-body-secondary" for="formacao-{{ $bloco['id_time'] }}">Formação</label>
        <select id="formacao-{{ $bloco['id_time'] }}" class="form-select form-select-sm admin-campo-formacao js-campo-formacao">
            @foreach (array_keys(\App\Services\Escalacao::FORMACOES) as $formacao)
            <option value="{{ $formacao }}" @selected($formacao === $campo['formacao'])>{{ $formacao }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary js-campo-sugerir"
                title="Põe cada atleta na vaga da posição dele no cadastro (a mesma regra da home do site)">
            <i class="bi bi-magic"></i> Sugerir pelo cadastro
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary js-campo-limpar"><i class="bi bi-eraser"></i> Limpar campo</button>
        <span class="admin-campo-avisos js-campo-avisos"></span>
        <span class="admin-campo-contador">
            <strong class="js-campo-titulares">0</strong>/{{ \App\Services\Escalacao::TITULARES_MAX }} titulares ·
            <strong class="js-campo-reservas">0</strong> reserva(s)
        </span>
    </div>

    @if ($semVaga->isNotEmpty())
    <div class="alert alert-warning small mx-3 mt-2 mb-0 py-2">
        <i class="bi bi-exclamation-triangle"></i>
        Titulares gravados que não couberam no campo ({{ $semVaga->pluck('atleta.nome_atleta')->implode(', ') }}): ficam como reserva ao salvar.
    </div>
    @endif

    <div class="admin-campo-corpo">
        <div class="escalacao-campo">
            <div class="escalacao-gramado" aria-hidden="true">
                <span class="escalacao-linha-meio"></span>
                <span class="escalacao-circulo"></span>
                <span class="escalacao-area escalacao-area-cima"></span>
                <span class="escalacao-area escalacao-area-baixo"></span>
                <span class="escalacao-pequena-area escalacao-area-cima"></span>
                <span class="escalacao-pequena-area escalacao-area-baixo"></span>
            </div>
            <div class="js-campo-vagas"></div>
        </div>

        {{-- A lista tem a altura do campo (o resto rola): ela fica por cima desta coluna, que só estica --}}
        <div class="admin-campo-lado">
        <div class="admin-campo-lista js-campo-lista">
            <div class="admin-campo-dica js-campo-dica"></div>
            <div class="js-campo-atletas">
                @foreach ($lista as $inscricao)
                <div class="admin-campo-atleta js-campo-atleta" data-id="{{ $inscricao->id_atleta }}">
                    <button type="button" class="admin-campo-escolher js-campo-escolher" draggable="true"
                            title="Clique para pôr na posição escolhida, ou arraste até uma posição do campo">
                        <span class="admin-campo-foto">
                            @if ($inscricao->atleta->urlFoto())
                                <img src="{{ $inscricao->atleta->urlFoto() }}" alt="" loading="lazy" draggable="false">
                            @else
                                {{ \App\Services\Escalacao::iniciais($inscricao->atleta->nome_atleta) }}
                            @endif
                        </span>
                        <span class="admin-campo-info">
                            <span class="admin-campo-nome">
                                @if (isset($camisas[$inscricao->id_atleta]))
                                    <span class="badge text-bg-secondary" title="Camisa na convocação do campeonato">{{ $camisas[$inscricao->id_atleta] }}</span>
                                @endif
                                {{ $inscricao->atleta->nome_atleta }}
                            </span>
                            <small class="text-body-secondary">
                                {{ $inscricao->atleta->siglasDasPosicoes() ?: 'Sem posição no cadastro' }}
                                · {{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? 'Sem categoria' }}
                            </small>
                            @if (isset($inscritosAcimaDaIdade[$inscricao->id_atleta]))
                                <small class="text-danger fw-semibold">Acima da idade do campeonato</small>
                            @endif
                        </span>
                        <span class="badge admin-campo-status js-campo-status">Reserva</span>
                    </button>
                    <div class="admin-campo-acoes">
                        <span class="badge-cat admin-cursor-ajuda" title="{{ $inscricao->origem_dica }}">{{ $inscricao->origem_label }}</span>
                        <div class="admin-escalacao-time">@include('admin.calendario._time_da_inscricao')</div>
                        @include('admin.calendario._remover_inscricao')
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        </div>
    </div>

    <form id="escalacao-{{ $bloco['id_time'] }}" class="admin-campo-salvar js-campo-form" method="POST"
          action="{{ route('admin.calendario.eventos.escalacao.salvar', [$evento->id_evento_calendario, $bloco['id_time']]) }}">
        @csrf @method('PATCH')
        <span class="badge text-bg-warning d-none js-campo-pendente">Alterações não salvas</span>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2"></i> Salvar escalação</button>
    </form>
    @endif
</div>

@once
@push('scripts')
<script src="{{ asset('coderatech/js/escalacao-campo.js') }}?v={{ filemtime(public_path('coderatech/js/escalacao-campo.js')) }}"></script>
@endpush
@endonce
