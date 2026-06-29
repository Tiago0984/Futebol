@extends('layout.admin')

@section('title', 'Gerenciar Times')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Times</h1>
                <p class="page-subtitle">Gerencie os times internos e externos da AACJ</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarTime">
                    <i class="bi bi-plus-lg"></i> Novo Time
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
                            <option value="INTERNO">Interno</option>
                            <option value="EXTERNO">Externo</option>
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

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($times) }} time(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Logo</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($times as $time)
                        <tr class="linha-time"
                            data-nome="{{ strtolower($time->nome_time) }}"
                            data-tipo="{{ strtoupper($time->tipo_time) }}"
                            data-categoria="{{ $time->id_categoria }}"
                            data-status="{{ strtoupper($time->status_time) }}">
                            <td>
                                @if($time->logo_time)
                                <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                                     alt="{{ $time->nome_time }}"
                                     style="width:40px;height:40px;object-fit:contain;">
                                @else
                                <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-shield text-muted"></i>
                                </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $time->nome_time }}</span></td>
                            <td>
                                @if(strtoupper($time->tipo_time) === 'INTERNO')
                                    <span class="badge-status ativo">Interno</span>
                                @else
                                    <span class="badge-status inativo">Externo</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $time->categoria->nome_categoria ?? '—' }}</td>
                            <td class="text-center">
                                @if(strtoupper($time->status_time) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn-tbl edit btn-editar-time"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarTime"
                                        data-id="{{ $time->id_time }}"
                                        data-nome="{{ $time->nome_time }}"
                                        data-tipo="{{ $time->tipo_time }}"
                                        data-categoria="{{ $time->id_categoria }}"
                                        data-logo="{{ $time->logo_time }}"
                                        title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.times.toggleStatus', $time->id_time) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($time->status_time) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($time->status_time) !== 'ATIVO')
                                    <form action="{{ route('admin.times.destroy', $time->id_time) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente o time {{ addslashes($time->nome_time) }}?');" style="display:inline">
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
                            <td colspan="6" class="text-center text-muted py-4">Nenhum time cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.times.modals.create')
@include('admin.times.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Filtros ---
    function aplicarFiltros() {
        const nome      = document.getElementById('filtroNome')?.value.toLowerCase().trim() ?? '';
        const tipo      = document.getElementById('filtroTipo')?.value ?? '';
        const categoria = document.getElementById('filtroCategoria')?.value ?? '';
        const status    = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-time').forEach(row => {
            const ok = (!nome      || row.dataset.nome.includes(nome))
                    && (!tipo      || row.dataset.tipo === tipo)
                    && (!categoria || row.dataset.categoria === categoria)
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-time').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (nome || tipo || categoria || status)
            ? `${visiveis} de ${total} time(s) encontrado(s)` : '';
    }
    ['filtroNome','filtroTipo','filtroCategoria','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicarFiltros));
    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroCategoria').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });

});

document.querySelectorAll('.btn-editar-time').forEach(btn => {
    btn.addEventListener('click', function () {
        const f = document.getElementById('formEditarTime');
        f.action = `{{ url('admin/times') }}/${this.dataset.id}`;
        document.getElementById('edit_nome_time').value    = this.dataset.nome;
        document.getElementById('edit_tipo_time').value    = this.dataset.tipo;
        document.getElementById('edit_id_categoria').value = this.dataset.categoria;
        const logoInfo = document.getElementById('edit_logo_atual');
        logoInfo.textContent = this.dataset.logo ? `Atual: ${this.dataset.logo}` : 'Sem logo cadastrado';
    });
});
</script>
@endsection
