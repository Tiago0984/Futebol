@extends('layout.admin')

@section('title', 'Gerenciar Notícias')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Notícias</h1>
                <p class="page-subtitle">Crie, edite e publique notícias do site</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarNoticia">
                    <i class="bi bi-newspaper"></i> Nova Notícia
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
                    <div class="col-md-4">
                        <label class="filter-label">Título</label>
                        <input type="text" id="filtroTitulo" class="form-control form-control-sm" placeholder="Buscar por título...">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">Categoria</label>
                        <select id="filtroCategoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($noticias->pluck('categoria_noticia')->filter()->unique()->sort() as $cat)
                                <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
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
                <span class="tbl-count">{{ count($noticias) }} registro(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:80px">Imagem</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Publicado em</th>
                            <th>Autor</th>
                            <th class="text-center" style="width:90px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($noticias as $noticia)
                        <tr class="linha-noticia"
                            data-titulo="{{ strtolower($noticia->titulo_noticia) }}"
                            data-categoria="{{ strtolower($noticia->categoria_noticia) }}"
                            data-status="{{ strtolower($noticia->status_noticia) }}">
                            <td>
                                @if($noticia->foto_noticia)
                                    <img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}"
                                         alt="Capa"
                                         style="width:60px;height:42px;object-fit:cover;border-radius:6px;">
                                @else
                                    <span class="badge-cat">Sem foto</span>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $noticia->titulo_noticia }}</span></td>
                            <td><span class="badge-cat">{{ $noticia->categoria_noticia }}</span></td>
                            <td>
                                @if($noticia->status_noticia === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d/m/Y H:i') }}</td>
                            <td class="text-muted">{{ $noticia->autor_noticia }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button"
                                            class="btn-tbl edit btn-editar"
                                            title="Editar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarNoticia"
                                            data-id="{{ $noticia->id_noticia }}"
                                            data-titulo="{{ $noticia->titulo_noticia }}"
                                            data-conteudo="{{ $noticia->conteudo_noticia }}"
                                            data-categoria="{{ $noticia->categoria_noticia }}"
                                            data-autor="{{ $noticia->autor_noticia }}"
                                            data-data="{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('Y-m-d\TH:i') }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.noticias.destroy', $noticia->id_noticia) }}"
                                          method="POST"
                                          onsubmit="return confirm('Deseja realmente alterar o status desta notícia?');"
                                          style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        @if($noticia->status_noticia === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Ativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.noticias.modals.create')
@include('admin.noticias.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Modal editar ---
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function () {
            const f = document.getElementById('formEditarNoticia');
            f.action = `/admin/noticias/${this.dataset.id}`;
            document.getElementById('edit_titulo').value    = this.dataset.titulo;
            document.getElementById('edit_conteudo').value  = this.dataset.conteudo;
            document.getElementById('edit_categoria').value = this.dataset.categoria;
            document.getElementById('edit_autor').value     = this.dataset.autor;
            document.getElementById('edit_data').value      = this.dataset.data;
        });
    });

    // --- Filtros ---
    function aplicar() {
        const titulo    = document.getElementById('filtroTitulo').value.toLowerCase().trim();
        const categoria = document.getElementById('filtroCategoria').value.toLowerCase();
        const status    = document.getElementById('filtroStatus').value.toLowerCase();
        let count = 0;
        document.querySelectorAll('.linha-noticia').forEach(row => {
            const ok = (!titulo    || row.dataset.titulo.includes(titulo))
                    && (!categoria || row.dataset.categoria === categoria)
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) count++;
        });
        const total = document.querySelectorAll('.linha-noticia').length;
        document.getElementById('filtroContador').textContent =
            (titulo || categoria || status) ? `${count} de ${total} notícia(s) encontrada(s)` : '';
    }

    ['filtroTitulo','filtroCategoria','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicar));

    document.getElementById('btnLimpar')?.addEventListener('click', () => {
        document.getElementById('filtroTitulo').value    = '';
        document.getElementById('filtroCategoria').value = '';
        document.getElementById('filtroStatus').value    = '';
        aplicar();
    });
});
</script>
@endsection
