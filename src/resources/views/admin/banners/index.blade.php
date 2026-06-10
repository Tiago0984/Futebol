@extends('layout.admin')

@section('title', 'Gerenciar Banners')

@section('content')
    <main class="app-main pt-3" style="text-align: center;">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700; text-align: left;">Gerenciar Banners</h1>
                </div>
                <div class="col-sm-6 text-end pr-4" style="text-align: right;">
                    <button type="button" class="btn btn-success px-3" data-bs-toggle="modal"
                        data-bs-target="#modalCriarBanner">
                        <i class="bi bi-plus-circle"></i> Novo Banner
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
                                    <th style="width: 100px;">Imagem</th>
                                    <th style="text-align: left; padding-left: 80px;">Título</th>
                                    <th>Subtítulo</th>
                                    <th>Ordem</th>
                                    <th style="font-size: 14px;">Status</th>
                                    <th style="width: 130px;" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($banners as $banner)
                                    <tr>
                                        <td>
                                            @if ($banner->foto_banner)
                                                <img src="{{ asset('futebol/images/banner/' . $banner->foto_banner) }}"
                                                    alt="{{ $banner->titulo_banner }}" class="img-thumbnail"
                                                    style="max-height: 50px; max-width: 90px; object-fit: cover;">
                                            @else
                                                <span class="badge bg-secondary text-white">Sem foto</span>
                                            @endif
                                        </td>
                                        <td style="text-align: left; padding-left: 80px;">
                                            <strong class="text-dark">{{ $banner->titulo_banner }}</strong>
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $banner->subtitulo_banner ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-white"
                                                style="font-size: 13px; padding: 5px 10px;">
                                                {{ $banner->ordem_banner }}
                                            </span>
                                        </td>
                                        <td>
                                            @if (strtolower($banner->status_banner) === 'ativo')
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
                                                    data-bs-toggle="modal" data-bs-target="#modalEditarBanner"
                                                    data-id="{{ $banner->id_banner }}"
                                                    data-titulo="{{ $banner->titulo_banner }}"
                                                    data-subtitulo="{{ $banner->subtitulo_banner }}"
                                                    data-ordem="{{ $banner->ordem_banner }}"
                                                    data-status="{{ strtolower($banner->status_banner) }}">
                                                    <i class="bi bi-pencil" style="font-size: 13px;"></i>
                                                </button>

                                                <form action="{{ route('admin.banners.destroy', $banner->id_banner) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Deseja realmente alterar o status deste banner?');"
                                                    style="display: inline-block; margin: 0;">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if (strtolower($banner->status_banner) === 'ativo')
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                            title="Inativar Banner"
                                                            style="padding: .25rem .4rem; line-height: 1;">
                                                            <i class="bi bi-eye-slash" style="font-size: 13px;"></i>
                                                        </button>
                                                    @else
                                                        <button type="submit" class="btn btn-sm btn-success"
                                                            title="Ativar Banner"
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
        </div>
    </main>

    @include('admin.banners.modals.create')
    @include('admin.banners.modals.edit')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const botoesEditar = document.querySelectorAll('.btn-editar');
            const formEditar = document.getElementById('formEditarBanner');

            botoesEditar.forEach(botao => {
                botao.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');

                    formEditar.action = `/admin/banners/${id}`;

                    document.getElementById('edit_titulo').value = this.getAttribute('data-titulo') ?? '';
                    document.getElementById('edit_subtitulo').value = this.getAttribute('data-subtitulo') ?? '';
                    document.getElementById('edit_ordem').value = this.getAttribute('data-ordem') ?? '';

                    const statusSelect = document.getElementById('edit_status');
                    if (statusSelect) statusSelect.value = this.getAttribute('data-status') ?? 'ativo';
                });
            });
        });
    </script>
@endsection
