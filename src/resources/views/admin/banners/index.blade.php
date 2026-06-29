@extends('layout.admin')

@section('title', 'Gerenciar Banners')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Banners</h1>
                <p class="page-subtitle">Gerencie os banners exibidos na página inicial</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarBanner">
                    <i class="bi bi-image"></i> Novo Banner
                </button>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Ops! Verifique os campos do formulário:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        {{-- Filtros --}}
        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="filter-label">Título</label>
                        <input type="text" id="filtroTitulo" class="form-control form-control-sm" placeholder="Buscar por título...">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">Status</label>
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn-filter-clear" id="btnLimpar">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted" id="filtroContador"></small>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($banners) }} registro(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:90px">Imagem</th>
                            <th>Título</th>
                            <th>Subtítulo</th>
                            <th>Ordem</th>
                            <th>Status</th>
                            <th class="text-center" style="width:90px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($banners as $banner)
                        <tr class="linha-banner"
                            data-titulo="{{ strtolower($banner->titulo_banner) }}"
                            data-status="{{ strtolower($banner->status_banner) }}">
                            <td>
                                @if($banner->foto_banner)
                                    <img src="{{ asset('futebol/images/banner/' . $banner->foto_banner) }}"
                                         alt="{{ $banner->titulo_banner }}"
                                         style="width:80px;height:46px;object-fit:cover;border-radius:6px;">
                                @else
                                    <span class="badge-cat">Sem foto</span>
                                @endif
                            </td>
                            <td><strong>{{ $banner->titulo_banner }}</strong></td>
                            <td class="text-muted">{{ $banner->subtitulo_banner ?? '—' }}</td>
                            <td><span class="badge-cat">{{ $banner->ordem_banner }}</span></td>
                            <td>
                                @if(strtolower($banner->status_banner) === 'ativo')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button"
                                            class="btn-tbl edit btn-editar"
                                            title="Editar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarBanner"
                                            data-id="{{ $banner->id_banner }}"
                                            data-titulo="{{ $banner->titulo_banner }}"
                                            data-subtitulo="{{ $banner->subtitulo_banner }}"
                                            data-ordem="{{ $banner->ordem_banner }}"
                                            data-status="{{ strtolower($banner->status_banner) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.banners.toggleStatus', $banner->id_banner) }}"
                                          method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtolower($banner->status_banner) === 'ativo')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtolower($banner->status_banner) !== 'ativo')
                                    <form action="{{ route('admin.banners.destroy', $banner->id_banner) }}"
                                          method="POST"
                                          onsubmit="return confirm('Excluir permanentemente este banner?');"
                                          style="display:inline">
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
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-image fs-2 d-block mb-2"></i>
                                Nenhum banner cadastrado ainda.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.banners.modals.create')
@include('admin.banners.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Modal editar ---
    const formEditar = document.getElementById('formEditarBanner');
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function () {
            formEditar.action = `{{ url('admin/banners') }}/${this.dataset.id}`;
            document.getElementById('edit_titulo').value    = this.dataset.titulo ?? '';
            document.getElementById('edit_subtitulo').value = this.dataset.subtitulo ?? '';
            document.getElementById('edit_ordem').value     = this.dataset.ordem ?? '';
            const sel = document.getElementById('edit_status');
            if (sel) sel.value = this.dataset.status ?? 'ativo';
        });
    });

    // --- Filtros ---
    function aplicar() {
        const titulo  = document.getElementById('filtroTitulo').value.toLowerCase().trim();
        const status  = document.getElementById('filtroStatus').value.toLowerCase();
        let count = 0;
        document.querySelectorAll('.linha-banner').forEach(row => {
            const ok = (!titulo || row.dataset.titulo.includes(titulo))
                    && (!status || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) count++;
        });
        const total = document.querySelectorAll('.linha-banner').length;
        document.getElementById('filtroContador').textContent =
            (titulo || status) ? `${count} de ${total} banner(s) encontrado(s)` : '';
    }

    ['filtroTitulo','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicar));

    document.getElementById('btnLimpar')?.addEventListener('click', () => {
        document.getElementById('filtroTitulo').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicar();
    });
});
</script>
@endsection
