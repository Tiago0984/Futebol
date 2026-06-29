@extends('layout.admin')

@section('title', 'Gerenciar Campeonatos')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Campeonatos</h1>
                <p class="page-subtitle">Gerencie competições, datas e times participantes</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarCampeonato">
                    <i class="bi bi-plus-lg"></i> Novo Campeonato
                </button>
            </div>
        </div>

        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label">Nome</label>
                        <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Tipo</label>
                        <select id="filtroTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach(['Liga','Copa','Torneio','Mata-mata','Pontos Corridos','Amistoso'] as $t)
                            <option value="{{ strtolower($t) }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Categoria</label>
                        <select id="filtroCategoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categorias as $cat)
                            <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
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

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($campeonatos) }} campeonato(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Logo</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Período</th>
                            <th>Local</th>
                            <th>Categoria</th>
                            <th class="text-center">Times</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campeonatos as $camp)
                        <tr class="linha-camp"
                            data-nome="{{ strtolower($camp->nome_campeonato) }}"
                            data-tipo="{{ strtolower($camp->tipo_campeonato ?? '') }}"
                            data-categoria="{{ $camp->id_categoria }}"
                            data-status="{{ strtoupper($camp->status_campeonato) }}">
                            <td>
                                @if($camp->logo_evento)
                                <img src="{{ asset('futebol/images/campeonatos/' . $camp->logo_evento) }}"
                                     alt="{{ $camp->nome_campeonato }}"
                                     style="width:40px;height:40px;object-fit:contain;">
                                @else
                                <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-trophy text-muted"></i>
                                </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $camp->nome_campeonato }}</span></td>
                            <td><span class="badge-cat">{{ $camp->tipo_campeonato ?? '—' }}</span></td>
                            <td class="text-muted" style="font-size:0.82rem;">
                                {{ $camp->data_inicio_campeonato ? \Carbon\Carbon::parse($camp->data_inicio_campeonato)->format('d/m/Y') : '—' }}
                                @if($camp->data_fim_campeonato)
                                    <br>até {{ \Carbon\Carbon::parse($camp->data_fim_campeonato)->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="text-muted">{{ $camp->local_evento ?? '—' }}</td>
                            <td class="text-muted">{{ $camp->categoria->nome_categoria ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-secondary">{{ $camp->times->count() }}</span>
                            </td>
                            <td class="text-center">
                                @if(strtoupper($camp->status_campeonato) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn-tbl edit" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarCampeonato"
                                        data-id="{{ $camp->id_campeonato }}"
                                        data-nome="{{ $camp->nome_campeonato }}"
                                        data-tipo="{{ $camp->tipo_campeonato }}"
                                        data-categoria="{{ $camp->id_categoria }}"
                                        data-inicio="{{ $camp->data_inicio_campeonato?->format('Y-m-d') }}"
                                        data-fim="{{ $camp->data_fim_campeonato?->format('Y-m-d') }}"
                                        data-local="{{ $camp->local_evento }}"
                                        data-organizador="{{ $camp->organizador_campeonato }}"
                                        data-descricao="{{ $camp->descricao_campeonato }}"
                                        data-logo="{{ $camp->logo_evento }}"
                                        data-banner="{{ $camp->banner_evento }}"
                                        data-times="{{ json_encode($camp->times->pluck('id_time')->toArray()) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.campeonatos.toggleStatus', $camp->id_campeonato) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($camp->status_campeonato) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($camp->status_campeonato) !== 'ATIVO')
                                    <form action="{{ route('admin.campeonatos.destroy', $camp->id_campeonato) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente {{ addslashes($camp->nome_campeonato) }}?');" style="display:inline">
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
                            <td colspan="9" class="text-center text-muted py-4">Nenhum campeonato cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

{{-- ── Modal Criar Campeonato ─────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCriarCampeonato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy"></i> Novo Campeonato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.campeonatos.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-info-circle"></i> Informações Gerais</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome_campeonato" class="form-control"
                                placeholder="Ex: Liga Premiere 2025" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Liga','Copa','Torneio','Mata-mata','Pontos Corridos','Amistoso'] as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" name="data_inicio_campeonato" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Fim <span class="text-danger">*</span></label>
                            <input type="date" name="data_fim_campeonato" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Local</label>
                            <input type="text" name="local_evento" class="form-control" placeholder="Ex: Campo AACJ">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Organizador</label>
                            <input type="text" name="organizador_campeonato" class="form-control" placeholder="Ex: Liga Municipal">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao_campeonato" class="form-control" rows="2"
                                placeholder="Informações adicionais..."></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-image"></i> Imagens</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Logo / Escudo</label>
                            <input type="file" name="logo_evento" class="form-control" accept="image/*">
                            <span class="form-text">JPG, PNG, WEBP. Máx. 2MB.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Banner</label>
                            <input type="file" name="banner_evento" class="form-control" accept="image/*">
                            <span class="form-text">JPG, PNG. Máx. 4MB.</span>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-shield-fill"></i> Times Participantes</p></div>

                        <div class="col-12">
                            <div class="row g-2">
                                @foreach($times as $time)
                                <div class="col-md-4 col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($time->logo_time)
                                        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                                             style="width:22px;height:22px;object-fit:contain;flex-shrink:0;">
                                        @else
                                        <div style="width:22px;height:22px;background:#eee;border-radius:3px;flex-shrink:0;"></div>
                                        @endif
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="times[]"
                                                value="{{ $time->id_time }}" id="c_time_{{ $time->id_time }}">
                                            <label class="form-check-label" for="c_time_{{ $time->id_time }}">
                                                {{ $time->nome_time }}
                                                @if($time->tipo_time === 'INTERNO')
                                                <span class="badge-status ativo ms-1" style="font-size:0.6rem;">INT</span>
                                                @else
                                                <span class="badge-status inativo ms-1" style="font-size:0.6rem;">EXT</span>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
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

{{-- ── Modal Editar Campeonato ────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarCampeonato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Campeonato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCampeonato" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-info-circle"></i> Informações Gerais</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="edit_nome_campeonato" name="nome_campeonato"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_tipo_campeonato" name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Liga','Copa','Torneio','Mata-mata','Pontos Corridos','Amistoso'] as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Categoria</label>
                            <select id="edit_id_categoria" name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" id="edit_data_inicio" name="data_inicio_campeonato"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Fim <span class="text-danger">*</span></label>
                            <input type="date" id="edit_data_fim" name="data_fim_campeonato"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Local</label>
                            <input type="text" id="edit_local_evento" name="local_evento" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Organizador</label>
                            <input type="text" id="edit_organizador" name="organizador_campeonato"
                                class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea id="edit_descricao" name="descricao_campeonato"
                                class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-image"></i> Imagens</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Logo / Escudo</label>
                            <small id="edit_logo_atual" class="d-block text-muted mb-1"></small>
                            <input type="file" name="logo_evento" class="form-control" accept="image/*">
                            <span class="form-text">Deixe em branco para manter o atual.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Banner</label>
                            <small id="edit_banner_atual" class="d-block text-muted mb-1"></small>
                            <input type="file" name="banner_evento" class="form-control" accept="image/*">
                            <span class="form-text">Deixe em branco para manter o atual.</span>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-shield-fill"></i> Times Participantes</p></div>

                        <div class="col-12">
                            <div class="row g-2">
                                @foreach($times as $time)
                                <div class="col-md-4 col-6">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($time->logo_time)
                                        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                                             style="width:22px;height:22px;object-fit:contain;flex-shrink:0;">
                                        @else
                                        <div style="width:22px;height:22px;background:#eee;border-radius:3px;flex-shrink:0;"></div>
                                        @endif
                                        <div class="form-check mb-0">
                                            <input class="form-check-input edit-time-check" type="checkbox"
                                                name="times[]" value="{{ $time->id_time }}"
                                                id="e_time_{{ $time->id_time }}">
                                            <label class="form-check-label" for="e_time_{{ $time->id_time }}">
                                                {{ $time->nome_time }}
                                                @if($time->tipo_time === 'INTERNO')
                                                <span class="badge-status ativo ms-1" style="font-size:0.6rem;">INT</span>
                                                @else
                                                <span class="badge-status inativo ms-1" style="font-size:0.6rem;">EXT</span>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
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
    function aplicarFiltros() {
        const nome      = document.getElementById('filtroNome')?.value.toLowerCase().trim() ?? '';
        const tipo      = document.getElementById('filtroTipo')?.value.toLowerCase() ?? '';
        const categoria = document.getElementById('filtroCategoria')?.value ?? '';
        const status    = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-camp').forEach(row => {
            const ok = (!nome      || row.dataset.nome.includes(nome))
                    && (!tipo      || row.dataset.tipo === tipo)
                    && (!categoria || row.dataset.categoria === categoria)
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-camp').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (nome || tipo || categoria || status)
            ? `${visiveis} de ${total} campeonato(s) encontrado(s)` : '';
    }

    ['filtroNome','filtroTipo','filtroCategoria','filtroStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', aplicarFiltros);
    });

    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroCategoria').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });


    document.querySelectorAll('[data-bs-target="#modalEditarCampeonato"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            document.getElementById('formEditarCampeonato').action = `{{ url('admin/campeonatos') }}/${id}`;

            document.getElementById('edit_nome_campeonato').value = this.dataset.nome        || '';
            document.getElementById('edit_data_inicio').value     = this.dataset.inicio      || '';
            document.getElementById('edit_data_fim').value        = this.dataset.fim          || '';
            document.getElementById('edit_local_evento').value    = this.dataset.local        || '';
            document.getElementById('edit_organizador').value     = this.dataset.organizador  || '';
            document.getElementById('edit_descricao').value       = this.dataset.descricao    || '';

            const logoEl = document.getElementById('edit_logo_atual');
            logoEl.textContent = this.dataset.logo ? 'Atual: ' + this.dataset.logo : '';

            const bannerEl = document.getElementById('edit_banner_atual');
            bannerEl.textContent = this.dataset.banner ? 'Atual: ' + this.dataset.banner : '';

            const selTipo = document.getElementById('edit_tipo_campeonato');
            for (let opt of selTipo.options) opt.selected = opt.value === this.dataset.tipo;

            const selCat = document.getElementById('edit_id_categoria');
            for (let opt of selCat.options) opt.selected = opt.value === this.dataset.categoria;

            const timesVinculados = JSON.parse(this.dataset.times || '[]');
            document.querySelectorAll('.edit-time-check').forEach(chk => {
                chk.checked = timesVinculados.includes(parseInt(chk.value));
            });
        });
    });
});
</script>
@endpush
@endsection
