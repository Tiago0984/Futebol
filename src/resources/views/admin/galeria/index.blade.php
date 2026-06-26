@extends('layout.admin')

@section('title', 'Gerenciar Galeria')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Galeria</h1>
                <p class="page-subtitle">Gerencie as fotos exibidas na galeria do site</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarFoto">
                    <i class="bi bi-camera"></i> Nova Foto
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
                            @foreach($fotos->pluck('categoria_galeria')->filter()->unique()->sort() as $cat)
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
                <span class="tbl-count">{{ count($fotos) }} foto(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:90px">Foto</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Ordem</th>
                            <th>Status</th>
                            <th class="text-center" style="width:90px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fotos as $foto)
                        <tr class="linha-foto"
                            data-titulo="{{ strtolower($foto->titulo_galeria) }}"
                            data-categoria="{{ strtolower($foto->categoria_galeria ?? 'geral') }}"
                            data-status="{{ strtolower($foto->status_galeria) }}">
                            <td>
                                @if($foto->foto_galeria)
                                    <img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                                         alt="{{ $foto->titulo_galeria }}"
                                         style="width:70px;height:46px;object-fit:cover;border-radius:6px;">
                                @else
                                    <span class="badge-cat">Sem foto</span>
                                @endif
                            </td>
                            <td><strong>{{ $foto->titulo_galeria }}</strong></td>
                            <td><span class="badge-cat">{{ $foto->categoria_galeria ?? 'GERAL' }}</span></td>
                            <td><span class="badge-cat">{{ $foto->ordem_galeria }}</span></td>
                            <td>
                                @if($foto->status_galeria === 'ATIVO')
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
                                            data-bs-target="#modalEditarFoto"
                                            data-id="{{ $foto->id_galeria }}"
                                            data-titulo="{{ $foto->titulo_galeria }}"
                                            data-categoria="{{ $foto->categoria_galeria ?? 'GERAL' }}"
                                            data-ordem="{{ $foto->ordem_galeria }}"
                                            data-status="{{ $foto->status_galeria }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.galeria.destroy', $foto->id_galeria) }}"
                                          method="POST"
                                          onsubmit="return confirm('Deseja realmente alterar o status desta foto?');"
                                          style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        @if($foto->status_galeria === 'ATIVO')
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
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-images fs-2 d-block mb-2"></i>
                                Nenhuma foto cadastrada ainda.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<div id="galeria-state"
     data-reopen="{{ old('modal_origem', '') }}"
     data-old-titulo="{{ old('titulo_galeria', '') }}"
     data-old-ordem="{{ old('ordem_galeria', '') }}"
     data-old-status="{{ old('status_galeria', 'ATIVO') }}"
     data-old-categoria="{{ old('categoria_galeria', '') }}"
     data-old-nova-cat="{{ old('nova_categoria_galeria', '') }}"
     data-old-foto-id="{{ old('id_foto_edicao', '') }}"
     data-old-ordem-original="{{ old('ordem_original_galeria', '') }}"
     data-ordens-usadas="{{ $fotos->pluck('ordem_galeria')->toJson() }}"
     style="display:none;"></div>

@include('admin.galeria.modals.create')
@include('admin.galeria.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Categoria nova (modal criar) ---
    const createCatSelect = document.getElementById('create_cat_select');
    const createCatNova   = document.getElementById('create_cat_nova');
    if (createCatSelect) {
        createCatSelect.addEventListener('change', function () {
            const isNova = this.value === '__nova__';
            createCatNova.style.display = isNova ? '' : 'none';
            createCatNova.required      = isNova;
        });
    }

    // --- Categoria nova (modal editar) ---
    const editCatSelect = document.getElementById('edit_cat_select');
    const editCatNova   = document.getElementById('edit_cat_nova');
    if (editCatSelect) {
        editCatSelect.addEventListener('change', function () {
            const isNova = this.value === '__nova__';
            editCatNova.style.display = isNova ? '' : 'none';
            editCatNova.required      = isNova;
        });
    }

    // --- Modal editar ---
    const formEditar = document.getElementById('formEditarFoto');
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function () {
            const id        = this.dataset.id;
            const categoria = this.dataset.categoria ?? 'GERAL';
            formEditar.action = `{{ url('admin/galeria') }}/${id}`;
            document.getElementById('edit_id_foto').value = id;

            const ordemAtual  = this.dataset.ordem ?? '';
            const editOrdemEl = document.getElementById('edit_ordem');
            const editFeedback = document.getElementById('edit_ordem_feedback');

            document.getElementById('edit_titulo').value        = this.dataset.titulo ?? '';
            document.getElementById('edit_ordem_original').value = ordemAtual;
            editOrdemEl.value                = ordemAtual;
            editOrdemEl.dataset.ordemAtual   = ordemAtual;
            editOrdemEl.classList.remove('is-valid', 'is-invalid');
            if (editFeedback) editFeedback.innerHTML = '';

            const statusSelect = document.getElementById('edit_status');
            if (statusSelect) statusSelect.value = this.dataset.status ?? 'ATIVO';

            const optionExiste = Array.from(editCatSelect.options).some(o => o.value === categoria);
            if (optionExiste) {
                editCatSelect.value       = categoria;
                editCatNova.style.display = 'none';
                editCatNova.required      = false;
            } else {
                editCatSelect.value       = '__nova__';
                editCatNova.style.display = '';
                editCatNova.required      = true;
                editCatNova.value         = categoria;
            }
        });
    });

    // --- Validação de ordem ---
    const state = document.getElementById('galeria-state');
    if (state && state.dataset.ordensUsadas) {
        const ordensUsadas = JSON.parse(state.dataset.ordensUsadas).map(Number);

        function proximaLivre(excluir) {
            const lista = ordensUsadas.filter(o => o !== excluir);
            let n = 1;
            while (lista.includes(n)) { n++; }
            return n;
        }

        function checarOrdem(input, feedback, ordemAtual) {
            const val = parseInt(input.value, 10);
            if (!input.value || isNaN(val) || val < 1) {
                feedback.innerHTML = '';
                input.classList.remove('is-valid', 'is-invalid');
                return;
            }
            const excluir = ordemAtual ? parseInt(ordemAtual, 10) : null;
            const emUso   = ordensUsadas.filter(o => o !== excluir).includes(val);
            if (emUso) {
                feedback.innerHTML = `Esta ordem já está em uso. Tente: <strong>${proximaLivre(excluir)}</strong>.`;
                feedback.className = 'small mt-1 text-danger';
                input.classList.add('is-invalid'); input.classList.remove('is-valid');
            } else {
                feedback.textContent = 'Disponível!';
                feedback.className   = 'small mt-1 text-success';
                input.classList.add('is-valid'); input.classList.remove('is-invalid');
            }
        }

        const createOrdem    = document.getElementById('create_ordem');
        const createFeedback = document.getElementById('create_ordem_feedback');
        if (createOrdem && createFeedback) {
            createOrdem.addEventListener('input', function () { checarOrdem(this, createFeedback, null); });
            createOrdem.closest('form').addEventListener('submit', e => {
                if (createOrdem.classList.contains('is-invalid')) e.preventDefault();
            });
        }

        const editOrdem    = document.getElementById('edit_ordem');
        const editFeedback = document.getElementById('edit_ordem_feedback');
        if (editOrdem && editFeedback) {
            editOrdem.addEventListener('input', function () { checarOrdem(this, editFeedback, this.dataset.ordemAtual); });
            editOrdem.closest('form').addEventListener('submit', e => {
                if (editOrdem.classList.contains('is-invalid')) e.preventDefault();
            });
        }
    }

    // --- Filtros ---
    function aplicar() {
        const titulo    = document.getElementById('filtroTitulo').value.toLowerCase().trim();
        const categoria = document.getElementById('filtroCategoria').value.toLowerCase();
        const status    = document.getElementById('filtroStatus').value.toLowerCase();
        let count = 0;
        document.querySelectorAll('.linha-foto').forEach(row => {
            const ok = (!titulo    || row.dataset.titulo.includes(titulo))
                    && (!categoria || row.dataset.categoria === categoria)
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) count++;
        });
        const total = document.querySelectorAll('.linha-foto').length;
        document.getElementById('filtroContador').textContent =
            (titulo || categoria || status) ? `${count} de ${total} foto(s) encontrada(s)` : '';
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
