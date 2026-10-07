@extends('layout.admin')

@section('title', 'Gerenciar Jogos')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Jogos</h1>
                <p class="page-subtitle">Cada jogo é um evento do calendário, com times, campeonato e placar</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarJogo">
                    <i class="bi bi-plus-lg"></i> Novo Jogo
                </button>
            </div>
        </div>

        {{-- Campeonato e situação vão na URL (o menu abre a lista já filtrada); o time filtra só na tela.
             Com filtro na URL, o painel abre --}}
        <div class="collapse {{ array_filter($filtros) ? 'show' : '' }}" id="filterPanel">
            <form method="GET" action="{{ route('admin.jogos.index') }}" class="filter-panel" id="formFiltros">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label" for="filtroTime">Time</label>
                        <input type="text" id="filtroTime" class="form-control form-control-sm" placeholder="Buscar mandante ou visitante...">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label" for="filtroCampeonato">Campeonato</label>
                        <select id="filtroCampeonato" name="campeonato" class="form-select form-select-sm js-filtro-url">
                            <option value="">Todos</option>
                            <option value="amistoso" @selected($filtros['campeonato'] === 'amistoso')>Amistosos</option>
                            @foreach($campeonatos as $camp)
                            <option value="{{ $camp->id_campeonato }}" @selected($filtros['campeonato'] === (string) $camp->id_campeonato)>{{ $camp->nome_campeonato }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label" for="filtroStatus">Situação</label>
                        <select id="filtroStatus" name="situacao" class="form-select form-select-sm js-filtro-url">
                            <option value="">Todas, menos ocultos</option>
                            @foreach(\App\Models\EventoCalendario::SITUACOES as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <a href="{{ route('admin.jogos.index') }}" class="btn-filter-clear text-decoration-none">
                            <i class="bi bi-x-circle"></i> Limpar
                        </a>
                    </div>
                </div>
                <div class="mt-2"><small class="text-muted" id="filtroContador"></small></div>
            </form>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('aviso'))
        <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
            <strong>Atenção!</strong> {{ session('aviso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('erro') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong>
            @if(session('erro'))<div>{{ session('erro') }}</div>@endif
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @include('admin.calendario._conflitos')

        <div class="table-card">
            <div class="table-card-toolbar d-flex flex-wrap align-items-center gap-2">
                <span class="tbl-count">{{ count($jogos) }} jogo(s)</span>
                @if($ocultosForaDaLista)
                <a href="{{ route('admin.jogos.index', [...array_filter($filtros), 'situacao' => 'INATIVO']) }}"
                   class="small text-muted ms-auto">{{ $ocultosForaDaLista }} oculto(s) fora da lista · ver</a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Campeonato</th>
                            <th class="text-center">Mandante</th>
                            <th class="text-center" style="width:90px">Placar</th>
                            <th class="text-center">Visitante</th>
                            <th class="text-center">Situação</th>
                            <th class="text-center" style="width:150px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jogos as $jogo)
                        @php
                            $ev = $jogo->evento;
                            // Situação = status do evento + derivados (Alterado, Concluído); só no admin
                            $classeStatus = [
                                'ATIVO' => 'ativo', 'ALTERADO' => 'pendente', 'CONCLUIDO' => 'concluido',
                                'CANCELADO' => 'inativo', 'INATIVO' => 'rejeitado',
                            ][$ev->situacao];
                        @endphp
                        <tr class="linha-jogo"
                            data-time="{{ strtolower(($jogo->timeCasa->nome_time ?? '') . ' ' . ($jogo->timeVisitante->nome_time ?? '')) }}"
                            data-campeonato="{{ $jogo->id_campeonato ?? 'amistoso' }}"
                            data-status="{{ $ev->situacao }}">
                            <td class="text-muted" style="font-size:0.82rem;white-space:nowrap;">
                                {{ $ev->data_evento_calendario->format('d/m/Y') }}
                                <div>{{ $ev->horario_texto }}</div>
                            </td>
                            <td>
                                @if($jogo->ehAmistoso())
                                    <span class="badge-cat">Amistoso</span>
                                @else
                                    <span class="badge-cat">{{ $jogo->campeonato->nome_campeonato }}</span>
                                @endif
                                @if($ev->categoria)
                                    <div class="text-muted" style="font-size:0.75rem;">{{ $ev->categoria->rotulo }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <span class="fw-semibold">{{ $jogo->timeCasa->nome_time ?? '—' }}</span>
                                    @if($jogo->timeCasa?->logo_time)
                                    <img src="{{ asset('futebol/images/team/' . $jogo->timeCasa->logo_time) }}"
                                         style="width:28px;height:28px;object-fit:contain;">
                                    @endif
                                </div>
                            </td>
                            <td class="text-center fw-bold">
                                @if($jogo->placar_time_casa_jogos !== null && $jogo->placar_time_visitante_jogos !== null)
                                    <span class="badge bg-dark">{{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}</span>
                                @else
                                    <span class="text-muted">VS</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-start gap-2">
                                    @if($jogo->timeVisitante?->logo_time)
                                    <img src="{{ asset('futebol/images/team/' . $jogo->timeVisitante->logo_time) }}"
                                         style="width:28px;height:28px;object-fit:contain;">
                                    @endif
                                    <span class="fw-semibold">{{ $jogo->timeVisitante->nome_time ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge-status {{ $classeStatus }}">{{ $ev->situacao_label }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
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
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ array_filter($filtros) ? 'Nenhum jogo com estes filtros.' : 'Nenhum jogo registrado.' }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.jogos.modals.create')
@include('admin.jogos.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Campeonato e situação já vêm filtrados do servidor (URL); aqui só o nome do time
    function aplicarFiltros() {
        const time = document.getElementById('filtroTime')?.value.toLowerCase().trim() ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-jogo').forEach(row => {
            const ok = !time || row.dataset.time.includes(time);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-jogo').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = time ? `${visiveis} de ${total} jogo(s) encontrado(s)` : '';
    }
    document.getElementById('filtroTime')?.addEventListener('input', aplicarFiltros);

    // Mudar o select recarrega a lista; Enter no campo do time não envia o formulário
    document.querySelectorAll('.js-filtro-url').forEach(sel => sel.addEventListener('change', () => sel.form.submit()));
    document.getElementById('formFiltros')?.addEventListener('submit', e => {
        if (document.activeElement?.id === 'filtroTime') e.preventDefault();
    });

    // Campeonato: mostra a categoria dele; Amistoso: mostra o select de categoria,
    // sugerindo a categoria do time mandante quando ainda está vazio
    function atualizarCategoria(form) {
        const campeonato = form.querySelector('.js-campeonato');
        const amistoso   = campeonato.value === @js(\App\Http\Controllers\Admin\JogosController::AMISTOSO);
        const bloco      = form.querySelector('.js-bloco-categoria');
        const categoria  = form.querySelector('.js-categoria');
        const dica       = form.querySelector('.js-categoria-campeonato');

        bloco.classList.toggle('d-none', !amistoso);
        const rotulo = campeonato.selectedOptions[0]?.dataset.categoria;
        dica.textContent = !amistoso && rotulo ? `Categoria do campeonato: ${rotulo} (atletas inscritos no jogo).` : '';

        const casa = form.querySelector('.js-time-casa').selectedOptions[0];
        if (amistoso && !categoria.value && casa?.dataset.categoria) {
            categoria.value = casa.dataset.categoria;
        }
    }
    document.querySelectorAll('.form-jogo').forEach(form => {
        form.querySelector('.js-campeonato').addEventListener('change', () => atualizarCategoria(form));
        form.querySelector('.js-time-casa').addEventListener('change', () => atualizarCategoria(form));
        atualizarCategoria(form);
    });

    document.querySelectorAll('.btn-editar-jogo').forEach(btn => {
        btn.addEventListener('click', function () {
            const f = document.getElementById('formEditarJogo');
            f.action = `{{ url('admin/jogos') }}/${this.dataset.id}`;
            document.getElementById('edit_id_campeonato').value    = this.dataset.campeonato;
            document.getElementById('edit_id_categoria').value     = this.dataset.categoria;
            document.getElementById('edit_id_time_casa').value     = this.dataset.casa;
            document.getElementById('edit_id_time_visitante').value = this.dataset.visitante;
            document.getElementById('edit_data').value             = this.dataset.data;
            document.getElementById('edit_inicio').value           = this.dataset.inicio;
            document.getElementById('edit_fim').value              = this.dataset.fim;
            document.getElementById('edit_local').value            = this.dataset.local;
            document.getElementById('edit_placar_casa').value      = this.dataset.placarCasa;
            document.getElementById('edit_placar_visitante').value = this.dataset.placarVisitante;
            atualizarCategoria(f.querySelector('.form-jogo'));
        });
    });

});
</script>
@endsection
