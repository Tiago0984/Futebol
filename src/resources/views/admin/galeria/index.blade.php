@extends('layout.admin')

@section('title', 'Gerenciar Galeria')

@section('content')
    <main class="app-main pt-3" style="text-align: center;">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700; text-align: left;">Gerenciar Galeria
                    </h1>
                </div>
                <div class="col-sm-6 text-end pr-4" style="text-align: right;">
                    <button type="button" class="btn btn-success px-3" data-bs-toggle="modal"
                        data-bs-target="#modalCriarFoto">
                        <i class="bi bi-image"></i> Nova Foto
                    </button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">

                    @if (session('sucesso'))
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert"
                            style="text-align: left;">
                            <strong>Sucesso!</strong> {{ session('sucesso') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert"
                            style="text-align: left;">
                            <strong>Ops! Verifique os campos do formulário:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 100px;">Foto</th>
                                    <th style="text-align: left; padding-left: 80px;">Título</th>
                                    <th>Categoria</th>
                                    <th>Ordem</th>
                                    <th style="font-size: 14px;">Status</th>
                                    <th style="width: 130px;" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($fotos as $foto)
                                    <tr>
                                        <td>
                                            @if ($foto->foto_galeria)
                                                <img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                                                    alt="{{ $foto->titulo_galeria }}" class="img-thumbnail"
                                                    style="max-height: 50px; max-width: 70px; object-fit: cover;">
                                            @else
                                                <span class="badge bg-secondary text-white">Sem foto</span>
                                            @endif
                                        </td>
                                        <td style="text-align: left; padding-left: 80px;">
                                            <strong class="text-dark">{{ $foto->titulo_galeria }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark"
                                                style="font-size: 12px; padding: 5px 10px; font-weight: 700;">
                                                {{ $foto->categoria_galeria ?? 'GERAL' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-white"
                                                style="font-size: 13px; padding: 5px 10px;">
                                                {{ $foto->ordem_galeria }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($foto->status_galeria === 'ATIVO')
                                                <span class="badge bg-success text-white"
                                                    style="font-size: 12px; padding: 6px 10px; font-weight: 700;">ATIVO</span>
                                            @else
                                                <span class="badge bg-danger text-white"
                                                    style="font-size: 12px; padding: 6px 10px; font-weight: 700;">INATIVO</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap: 5px;">

                                                <button type="button" class="btn btn-sm btn-warning text-dark btn-editar"
                                                    title="Editar" style="padding: .25rem .4rem; line-height: 1;"
                                                    data-bs-toggle="modal" data-bs-target="#modalEditarFoto"
                                                    data-id="{{ $foto->id_galeria }}"
                                                    data-titulo="{{ $foto->titulo_galeria }}"
                                                    data-categoria="{{ $foto->categoria_galeria ?? 'GERAL' }}"
                                                    data-ordem="{{ $foto->ordem_galeria }}"
                                                    data-status="{{ $foto->status_galeria }}">
                                                    <i class="bi bi-pencil" style="font-size: 13px;"></i>
                                                </button>

                                                <form action="{{ route('admin.galeria.destroy', $foto->id_galeria) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Deseja realmente alterar o status desta foto?');"
                                                    style="display: inline-block; margin: 0;">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if ($foto->status_galeria === 'ATIVO')
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                            title="Inativar Foto"
                                                            style="padding: .25rem .4rem; line-height: 1;">
                                                            <i class="bi bi-eye-slash" style="font-size: 13px;"></i>
                                                        </button>
                                                    @else
                                                        <button type="submit" class="btn btn-sm btn-success"
                                                            title="Ativar Foto"
                                                            style="padding: .25rem .4rem; line-height: 1;">
                                                            <i class="bi bi-eye" style="font-size: 13px;"></i>
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
        document.addEventListener('DOMContentLoaded', function() {

            // --- Mostrar/ocultar campo de nova categoria (modal criar) ---
            const createCatSelect = document.getElementById('create_cat_select');
            const createCatNova   = document.getElementById('create_cat_nova');
            if (createCatSelect) {
                createCatSelect.addEventListener('change', function() {
                    const isNova = this.value === '__nova__';
                    createCatNova.style.display = isNova ? '' : 'none';
                    createCatNova.required      = isNova;
                });
            }

            // --- Mostrar/ocultar campo de nova categoria (modal editar) ---
            const editCatSelect = document.getElementById('edit_cat_select');
            const editCatNova   = document.getElementById('edit_cat_nova');
            if (editCatSelect) {
                editCatSelect.addEventListener('change', function() {
                    const isNova = this.value === '__nova__';
                    editCatNova.style.display = isNova ? '' : 'none';
                    editCatNova.required      = isNova;
                });
            }

            // --- Pré-preencher modal de edição ---
            const botoesEditar = document.querySelectorAll('.btn-editar');
            const formEditar   = document.getElementById('formEditarFoto');

            botoesEditar.forEach(botao => {
                botao.addEventListener('click', function() {
                    const id       = this.getAttribute('data-id');
                    const categoria = this.getAttribute('data-categoria') ?? 'GERAL';

                    formEditar.action = `/admin/galeria/${id}`;
                    document.getElementById('edit_id_foto').value = id;

                    const ordemAtual = this.getAttribute('data-ordem') ?? '';
                    const editOrdemEl = document.getElementById('edit_ordem');
                    const editFeedback = document.getElementById('edit_ordem_feedback');

                    document.getElementById('edit_titulo').value     = this.getAttribute('data-titulo') ?? '';
                    document.getElementById('edit_ordem_original').value = ordemAtual;
                    editOrdemEl.value                    = ordemAtual;
                    editOrdemEl.dataset.ordemAtual       = ordemAtual;
                    editOrdemEl.classList.remove('is-valid', 'is-invalid');
                    if (editFeedback) editFeedback.innerHTML = '';

                    const statusSelect = document.getElementById('edit_status');
                    if (statusSelect) statusSelect.value = this.getAttribute('data-status') ?? 'ATIVO';

                    // Seleciona a categoria no select; se não existir, abre campo de nova
                    const optionExiste = Array.from(editCatSelect.options).some(o => o.value === categoria);
                    if (optionExiste) {
                        editCatSelect.value     = categoria;
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

            // --- Validação em tempo real — campo Ordem ---
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
                        const proxima = proximaLivre(excluir);
                        feedback.innerHTML = `Esta ordem já está sendo usada por outra foto. Tente: <strong>${proxima}</strong>.`;
                        feedback.className = 'small mt-1 text-danger';
                        input.classList.add('is-invalid');
                        input.classList.remove('is-valid');
                    } else {
                        feedback.textContent = 'Disponível!';
                        feedback.className   = 'small mt-1 text-success';
                        input.classList.add('is-valid');
                        input.classList.remove('is-invalid');
                    }
                }

                const createOrdem    = document.getElementById('create_ordem');
                const createFeedback = document.getElementById('create_ordem_feedback');
                if (createOrdem && createFeedback) {
                    createOrdem.addEventListener('input', function () { checarOrdem(this, createFeedback, null); });
                    createOrdem.closest('form').addEventListener('submit', function (e) {
                        if (createOrdem.classList.contains('is-invalid')) e.preventDefault();
                    });
                }

                const editOrdem    = document.getElementById('edit_ordem');
                const editFeedback = document.getElementById('edit_ordem_feedback');
                if (editOrdem && editFeedback) {
                    editOrdem.addEventListener('input', function () { checarOrdem(this, editFeedback, this.dataset.ordemAtual); });
                    editOrdem.closest('form').addEventListener('submit', function (e) {
                        if (editOrdem.classList.contains('is-invalid')) e.preventDefault();
                    });
                }
            }
        });
    </script>
@endsection
