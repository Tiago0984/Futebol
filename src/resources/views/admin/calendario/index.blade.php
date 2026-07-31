@extends('layout.admin')

@section('title', 'Calendário')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Calendário</h1>
                <p class="page-subtitle">Gerencie eventos e a grade de treinos</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button class="btn-admin-primary" data-bs-toggle="modal"
                    data-bs-target="{{ request('tab') === 'grade' ? '#modalCriarGrade' : '#modalCriarEvento' }}">
                    <i class="bi bi-plus-lg"></i>
                    {{ request('tab') === 'grade' ? 'Novo Horário' : 'Novo Evento' }}
                </button>
            </div>
        </div>

        {{-- Painel de filtros: Eventos --}}
        @if(request('tab') !== 'grade')
        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label">Título</label>
                        <input type="text" id="filtroTitulo" class="form-control form-control-sm" placeholder="Buscar por título...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Tipo</label>
                        <select id="filtroTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach(['JOGO','TREINO','EVENTO','REUNIÃO','CONFRATERNIZAÇÃO'] as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Status</label>
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="ATIVO">Ativo</option>
                            <option value="INATIVO">Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn-filter-clear" id="btnLimparFiltros">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2"><small class="text-muted" id="filtroContador"></small></div>
            </div>
        </div>
        {{-- Painel de filtros: Grade --}}
        @else
        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="filter-label">Dia da Semana</label>
                        <select id="filtroDia" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach(\App\Models\GradeTreino::DIAS_SEMANA as $valor => $label)
                            <option value="{{ $valor }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Tipo</label>
                        <select id="filtroTipoGrade" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="TREINO">Treino</option>
                            <option value="JOGO">Jogo</option>
                            <option value="LIVRE">Livre</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">Categoria</label>
                        <input type="text" id="filtroCategoria" class="form-control form-control-sm" placeholder="Ex: Sub-13...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Status</label>
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="ATIVO">Ativo</option>
                            <option value="INATIVO">Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn-filter-clear" id="btnLimparFiltros">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2"><small class="text-muted" id="filtroContador"></small></div>
            </div>
        </div>
        @endif

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-3" id="calendarioTabs">
            <li class="nav-item">
                <a class="nav-link {{ request('tab') !== 'grade' ? 'active' : '' }}"
                   href="{{ route('admin.calendario.index') }}">
                    <i class="bi bi-calendar-event me-1"></i> Eventos
                    <span class="badge bg-secondary ms-1">{{ count($eventos) }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('tab') === 'grade' ? 'active' : '' }}"
                   href="{{ route('admin.calendario.index', ['tab' => 'grade']) }}">
                    <i class="bi bi-clock me-1"></i> Grade de Treinos
                    <span class="badge bg-secondary ms-1">{{ count($grades) }}</span>
                </a>
            </li>
        </ul>

        {{-- TAB: Eventos --}}
        @if(request('tab') !== 'grade')
        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($eventos) }} evento(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Subtipo</th>
                            <th>Horário</th>
                            <th>Local</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eventos as $ev)
                        <tr class="linha-ev"
                            data-titulo="{{ strtolower($ev->titulo_evento_calendario) }}"
                            data-tipo="{{ $ev->tipo_evento_calendario }}"
                            data-status="{{ strtoupper($ev->status_evento_calendario) }}">
                            <td class="text-muted" style="font-size:0.82rem;white-space:nowrap;">
                                {{ $ev->data_evento_calendario->format('d/m/Y') }}
                            </td>
                            <td><span class="fw-semibold">{{ $ev->titulo_evento_calendario }}</span></td>
                            <td><span class="badge-cat">{{ $ev->tipo_evento_calendario }}</span></td>
                            <td class="text-muted">{{ $ev->subtipo_evento_calendario ?? '—' }}</td>
                            <td class="text-muted" style="font-size:0.82rem;">
                                {{ $ev->horario_inicio_evento_calendario ?? '—' }}
                                @if($ev->horario_fim_evento_calendario)
                                – {{ $ev->horario_fim_evento_calendario }}
                                @endif
                            </td>
                            <td class="text-muted">{{ $ev->local_evento_calendario ?? '—' }}</td>
                            <td class="text-center">
                                @if(strtoupper($ev->status_evento_calendario) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn-tbl edit" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarEvento"
                                        data-id="{{ $ev->id_evento_calendario }}"
                                        data-titulo="{{ $ev->titulo_evento_calendario }}"
                                        data-tipo="{{ $ev->tipo_evento_calendario }}"
                                        data-subtipo="{{ $ev->subtipo_evento_calendario }}"
                                        data-data="{{ $ev->data_evento_calendario->format('Y-m-d') }}"
                                        data-inicio="{{ $ev->horario_inicio_evento_calendario }}"
                                        data-fim="{{ $ev->horario_fim_evento_calendario }}"
                                        data-local="{{ $ev->local_evento_calendario }}"
                                        data-descricao="{{ $ev->descricao_evento_calendario }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.calendario.eventos.toggleStatus', $ev->id_evento_calendario) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($ev->status_evento_calendario) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($ev->status_evento_calendario) !== 'ATIVO')
                                    <form action="{{ route('admin.calendario.eventos.destroy', $ev->id_evento_calendario) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente este evento?');" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-tbl delete" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nenhum evento cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB: Grade de Treinos --}}
        @else
        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($grades) }} horário(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Dia</th>
                            <th>Categoria</th>
                            <th>Tipo</th>
                            <th>Horário</th>
                            <th>Local</th>
                            <th>Obs.</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grades as $g)
                        <tr class="linha-grade"
                            data-dia="{{ $g->dia_semana_grade_treino }}"
                            data-tipo="{{ $g->tipo_grade_treino }}"
                            data-categoria="{{ strtolower($g->categoria_grade_treino ?? '') }}"
                            data-status="{{ strtoupper($g->status_grade_treino) }}">
                            <td class="text-muted" style="font-size:0.8rem;">{{ $g->ordem_grade_treino }}</td>
                            <td><span class="fw-semibold">{{ $g->dia_label }}</span></td>
                            <td><span class="badge-cat">{{ $g->categoria_grade_treino ?? '—' }}</span></td>
                            <td class="text-muted">{{ $g->tipo_grade_treino ?? '—' }}</td>
                            <td class="text-muted" style="font-size:0.82rem;white-space:nowrap;">
                                {{ $g->horario_inicio_grade_treino }} – {{ $g->horario_fim_grade_treino }}
                            </td>
                            <td class="text-muted">{{ $g->local_grade_treino ?? '—' }}</td>
                            <td class="text-muted" style="font-size:0.8rem;">{{ $g->horario_obs_grade_treino ?? '—' }}</td>
                            <td class="text-center">
                                @if(strtoupper($g->status_grade_treino) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn-tbl edit" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarGrade"
                                        data-id="{{ $g->id_grade_treino }}"
                                        data-dia="{{ $g->dia_semana_grade_treino }}"
                                        data-categoria="{{ $g->categoria_grade_treino }}"
                                        data-tipo="{{ $g->tipo_grade_treino }}"
                                        data-inicio="{{ $g->horario_inicio_grade_treino }}"
                                        data-fim="{{ $g->horario_fim_grade_treino }}"
                                        data-obs="{{ $g->horario_obs_grade_treino }}"
                                        data-local="{{ $g->local_grade_treino }}"
                                        data-ordem="{{ $g->ordem_grade_treino }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.calendario.grade.toggleStatus', $g->id_grade_treino) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($g->status_grade_treino) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($g->status_grade_treino) !== 'ATIVO')
                                    <form action="{{ route('admin.calendario.grade.destroy', $g->id_grade_treino) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente este horário?');" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-tbl delete" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nenhum horário na grade.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</main>

{{-- ── Modal Criar Evento ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCriarEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Novo Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.calendario.eventos.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo_evento_calendario" class="form-control"
                                placeholder="Ex: Jogo Sub-13 vs Rival FC" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" name="data_evento_calendario" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_evento_calendario" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['JOGO', 'TREINO', 'EVENTO', 'REUNIÃO', 'CONFRATERNIZAÇÃO'] as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtipo</label>
                            <input type="text" name="subtipo_evento_calendario" class="form-control"
                                placeholder="Ex: Sub-13, Feminino...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Início</label>
                            <input type="time" name="horario_inicio_evento_calendario" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fim</label>
                            <input type="time" name="horario_fim_evento_calendario" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Local</label>
                            <input type="text" name="local_evento_calendario" class="form-control"
                                placeholder="Ex: Campo AACJ">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao_evento_calendario" class="form-control" rows="2"
                                placeholder="Informações adicionais..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Editar Evento ────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarEvento" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" id="edit_ev_titulo" name="titulo_evento_calendario"
                                class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" id="edit_ev_data" name="data_evento_calendario"
                                class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_ev_tipo" name="tipo_evento_calendario" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['JOGO', 'TREINO', 'EVENTO', 'REUNIÃO', 'CONFRATERNIZAÇÃO'] as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtipo</label>
                            <input type="text" id="edit_ev_subtipo" name="subtipo_evento_calendario"
                                class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Início</label>
                            <input type="time" id="edit_ev_inicio" name="horario_inicio_evento_calendario"
                                class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fim</label>
                            <input type="time" id="edit_ev_fim" name="horario_fim_evento_calendario"
                                class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Local</label>
                            <input type="text" id="edit_ev_local" name="local_evento_calendario"
                                class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea id="edit_ev_descricao" name="descricao_evento_calendario"
                                class="form-control" rows="2"></textarea>
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

{{-- ── Modal Criar Grade ──────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCriarGrade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Novo Horário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.calendario.grade.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Dia da Semana <span class="text-danger">*</span></label>
                            <select name="dia_semana_grade_treino" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\GradeTreino::DIAS_SEMANA as $valor => $label)
                                <option value="{{ $valor }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem_grade_treino" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo_grade_treino" class="form-select">
                                <option value="">—</option>
                                @foreach(['TREINO','JOGO','LIVRE'] as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria_grade_treino" class="form-control"
                                placeholder="Ex: Sub-13, Feminino...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Início <span class="text-danger">*</span></label>
                            <input type="time" name="horario_inicio_grade_treino" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fim <span class="text-danger">*</span></label>
                            <input type="time" name="horario_fim_grade_treino" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Local</label>
                            <input type="text" name="local_grade_treino" class="form-control"
                                placeholder="Ex: Campo A">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação do horário</label>
                            <input type="text" name="horario_obs_grade_treino" class="form-control"
                                placeholder="Ex: Somente com confirmação">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Editar Grade ─────────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarGrade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Horário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarGrade" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Dia da Semana <span class="text-danger">*</span></label>
                            <select id="edit_g_dia" name="dia_semana_grade_treino" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\GradeTreino::DIAS_SEMANA as $valor => $label)
                                <option value="{{ $valor }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem</label>
                            <input type="number" id="edit_g_ordem" name="ordem_grade_treino"
                                class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select id="edit_g_tipo" name="tipo_grade_treino" class="form-select">
                                <option value="">—</option>
                                @foreach(['TREINO','JOGO','LIVRE'] as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <input type="text" id="edit_g_categoria" name="categoria_grade_treino"
                                class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Início <span class="text-danger">*</span></label>
                            <input type="time" id="edit_g_inicio" name="horario_inicio_grade_treino"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fim <span class="text-danger">*</span></label>
                            <input type="time" id="edit_g_fim" name="horario_fim_grade_treino"
                                class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Local</label>
                            <input type="text" id="edit_g_local" name="local_grade_treino" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação do horário</label>
                            <input type="text" id="edit_g_obs" name="horario_obs_grade_treino" class="form-control">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Filtros ---
    const isGrade = {{ request('tab') === 'grade' ? 'true' : 'false' }};

    function aplicarFiltros() {
        let visiveis = 0;
        if (!isGrade) {
            const titulo = document.getElementById('filtroTitulo')?.value.toLowerCase().trim() ?? '';
            const tipo   = document.getElementById('filtroTipo')?.value ?? '';
            const status = document.getElementById('filtroStatus')?.value ?? '';
            document.querySelectorAll('.linha-ev').forEach(row => {
                const ok = (!titulo || row.dataset.titulo.includes(titulo))
                        && (!tipo   || row.dataset.tipo === tipo)
                        && (!status || row.dataset.status === status);
                row.style.display = ok ? '' : 'none';
                if (ok) visiveis++;
            });
            const total = document.querySelectorAll('.linha-ev').length;
            const contador = document.getElementById('filtroContador');
            if (contador) contador.textContent = (titulo || tipo || status)
                ? `${visiveis} de ${total} evento(s) encontrado(s)` : '';
        } else {
            const dia       = document.getElementById('filtroDia')?.value ?? '';
            const tipoGrade = document.getElementById('filtroTipoGrade')?.value ?? '';
            const cat       = document.getElementById('filtroCategoria')?.value.toLowerCase().trim() ?? '';
            const status    = document.getElementById('filtroStatus')?.value ?? '';
            document.querySelectorAll('.linha-grade').forEach(row => {
                const ok = (!dia       || row.dataset.dia === dia)
                        && (!tipoGrade || row.dataset.tipo === tipoGrade)
                        && (!cat       || row.dataset.categoria.includes(cat))
                        && (!status    || row.dataset.status === status);
                row.style.display = ok ? '' : 'none';
                if (ok) visiveis++;
            });
            const total = document.querySelectorAll('.linha-grade').length;
            const contador = document.getElementById('filtroContador');
            if (contador) contador.textContent = (dia || tipoGrade || cat || status)
                ? `${visiveis} de ${total} horário(s) encontrado(s)` : '';
        }
    }

    ['filtroTitulo','filtroTipo','filtroStatus','filtroDia','filtroTipoGrade','filtroCategoria'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicarFiltros));

    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        ['filtroTitulo','filtroTipo','filtroStatus','filtroDia','filtroTipoGrade','filtroCategoria'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        aplicarFiltros();
    });

    // Popula modal editar evento
    document.querySelectorAll('[data-bs-target="#modalEditarEvento"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const baseUrl = '{{ url("admin/calendario/eventos") }}';
            document.getElementById('formEditarEvento').action = `${baseUrl}/${this.dataset.id}`;
            document.getElementById('edit_ev_titulo').value   = this.dataset.titulo  || '';
            document.getElementById('edit_ev_data').value     = this.dataset.data    || '';
            document.getElementById('edit_ev_subtipo').value  = this.dataset.subtipo || '';
            document.getElementById('edit_ev_inicio').value   = this.dataset.inicio  || '';
            document.getElementById('edit_ev_fim').value      = this.dataset.fim     || '';
            document.getElementById('edit_ev_local').value    = this.dataset.local   || '';
            document.getElementById('edit_ev_descricao').value = this.dataset.descricao || '';
            const sel = document.getElementById('edit_ev_tipo');
            for (let opt of sel.options) opt.selected = opt.value === this.dataset.tipo;
        });
    });

    // Popula modal editar grade
    document.querySelectorAll('[data-bs-target="#modalEditarGrade"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const baseUrl = '{{ url("admin/calendario/grade") }}';
            document.getElementById('formEditarGrade').action = `${baseUrl}/${this.dataset.id}`;
            document.getElementById('edit_g_ordem').value     = this.dataset.ordem     || 0;
            document.getElementById('edit_g_categoria').value = this.dataset.categoria || '';
            document.getElementById('edit_g_inicio').value    = this.dataset.inicio    || '';
            document.getElementById('edit_g_fim').value       = this.dataset.fim       || '';
            document.getElementById('edit_g_obs').value       = this.dataset.obs       || '';
            document.getElementById('edit_g_local').value     = this.dataset.local     || '';
            const selDia  = document.getElementById('edit_g_dia');
            for (let opt of selDia.options)  opt.selected = opt.value === this.dataset.dia;
            const selTipo = document.getElementById('edit_g_tipo');
            for (let opt of selTipo.options) opt.selected = opt.value === this.dataset.tipo;
        });
    });

});
</script>
@endpush
@endsection
