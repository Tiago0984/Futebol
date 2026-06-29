@extends('layout.admin')

@section('title', 'Gerenciar Categorias')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Categorias</h1>
                <p class="page-subtitle">Gerencie as categorias etárias (Sub-11, Sub-13, Sub-15...)</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarCategoria">
                    <i class="bi bi-plus-lg"></i> Nova Categoria
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
                        <label class="filter-label">Sexo</label>
                        <select id="filtroSexo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                            <option value="Misto">Misto</option>
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
                <span class="tbl-count">{{ count($categorias) }} categoria(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="text-center">Idade Mín.</th>
                            <th class="text-center">Idade Máx.</th>
                            <th class="text-center">Sexo</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $cat)
                        <tr class="linha-cat"
                            data-nome="{{ strtolower($cat->nome_categoria) }}"
                            data-sexo="{{ $cat->sexo_categoria }}"
                            data-status="{{ strtoupper($cat->status_categoria) }}">
                            <td><span class="fw-semibold">{{ $cat->nome_categoria }}</span></td>
                            <td class="text-center text-muted">{{ $cat->idade_min_categoria ?? '—' }} anos</td>
                            <td class="text-center text-muted">{{ $cat->idade_max_categoria ?? '—' }} anos</td>
                            <td class="text-center">
                                @if($cat->sexo_categoria === 'M')
                                    <span class="badge-cat">Masculino</span>
                                @elseif($cat->sexo_categoria === 'F')
                                    <span class="badge-cat">Feminino</span>
                                @else
                                    <span class="badge-cat">Misto</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if(strtoupper($cat->status_categoria) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn-tbl edit btn-editar-cat"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarCategoria"
                                        data-id="{{ $cat->id_categoria }}"
                                        data-nome="{{ $cat->nome_categoria }}"
                                        data-idade-min="{{ $cat->idade_min_categoria }}"
                                        data-idade-max="{{ $cat->idade_max_categoria }}"
                                        data-sexo="{{ $cat->sexo_categoria }}"
                                        title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.categorias.toggleStatus', $cat->id_categoria) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($cat->status_categoria) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($cat->status_categoria) !== 'ATIVO')
                                    <form action="{{ route('admin.categorias.destroy', $cat->id_categoria) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente a categoria {{ addslashes($cat->nome_categoria) }}?');" style="display:inline">
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
                            <td colspan="6" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.categorias.modals.create')
@include('admin.categorias.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {

    function aplicarFiltros() {
        const nome   = document.getElementById('filtroNome')?.value.toLowerCase().trim() ?? '';
        const sexo   = document.getElementById('filtroSexo')?.value ?? '';
        const status = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-cat').forEach(row => {
            const ok = (!nome   || row.dataset.nome.includes(nome))
                    && (!sexo   || row.dataset.sexo === sexo)
                    && (!status || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-cat').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (nome || sexo || status)
            ? `${visiveis} de ${total} categoria(s) encontrada(s)` : '';
    }
    ['filtroNome','filtroSexo','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicarFiltros));
    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value = '';
        document.getElementById('filtroSexo').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });

});

document.querySelectorAll('.btn-editar-cat').forEach(btn => {
    btn.addEventListener('click', function () {
        const f = document.getElementById('formEditarCategoria');
        f.action = `{{ url('admin/categorias') }}/${this.dataset.id}`;
        document.getElementById('edit_nome_categoria').value      = this.dataset.nome;
        document.getElementById('edit_idade_min_categoria').value = this.dataset.idadeMin;
        document.getElementById('edit_idade_max_categoria').value = this.dataset.idadeMax;
        document.getElementById('edit_sexo_categoria').value      = this.dataset.sexo;
    });
});
</script>
@endsection
