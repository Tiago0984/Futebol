@extends('layout.admin')

@section('title', 'Gerenciar Notícias')

@section('content')
<main class="app-main pt-3" style="text-align: center;">
    <div class="container-fluid">
        
        <div class="row mb-3 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700; text-align: left;">Gerenciar Notícias</h1>
            </div>
            <div class="col-sm-6 text-end pr-4" style="text-align: right;">
                <button type="button" class="btn btn-success px-3" data-bs-toggle="modal" data-bs-target="#modalCriarNoticia">
                    <i class="bi bi-plus-circle"></i> Nova Notícia
                </button>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">

                @if(session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show m-3" role="alert" style="text-align: left;">
                    <strong>Sucesso!</strong> {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0; background: transparent;"></button>
                </div>
                @endif

                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert" style="text-align: left;">
                    <strong>Ops! Verifique os campos do formulário:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0; background: transparent;"></button>
                </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 100px;">Imagem</th>
                                <th style="text-align: left; padding-left: 250px;">Título</th>
                                <th>Categoria</th>
                                <th style="font-size: 14px;">Status</th>
                                <th>Data de Pub.</th>
                                <th>Autor</th>
                                <th style="width: 130px;" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($noticias as $noticia)
                            <tr>
                                <td>
                                    @if($noticia->foto_noticia)
                                    <img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="Capa" class="img-thumbnail" style="max-height: 50px; max-width: 70px; object-fit: cover;">
                                    @else
                                    <span class="badge bg-secondary text-white">Sem foto</span>
                                    @endif
                                </td>
                                <td style="text-align: left; padding-left: 250px;">
                                    <strong class="text-dark">{{ $noticia->titulo_noticia }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-info text-white" style="font-size: 13px; padding: 5px 10px;">
                                        {{ $noticia->categoria_noticia }}
                                    </span>
                                </td>
                                <td>
                                    @if($noticia->status_noticia === 'ATIVO')
                                        <span class="badge bg-success text-white" style="font-size: 12px; padding: 6px 10px; font-weight: 700;">ATIVO</span>
                                    @else
                                        <span class="badge bg-danger text-white" style="font-size: 12px; padding: 6px 10px; font-weight: 700;">INATIVO</span>
                                    @endif
                                </td>
                                <td>
                                    {{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $noticia->autor_noticia }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap: 5px;">
                                        
                                        <button type="button" 
                                                class="btn btn-sm btn-warning text-dark btn-editar" 
                                                title="Editar" 
                                                style="padding: .25rem .4rem; line-height: 1;"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarNoticia"
                                                data-id="{{ $noticia->id_noticia }}"
                                                data-titulo="{{ $noticia->titulo_noticia }}"
                                                data-conteudo="{{ $noticia->conteudo_noticia }}"
                                                data-categoria="{{ $noticia->categoria_noticia }}"
                                                data-autor="{{ $noticia->autor_noticia }}"
                                                data-data="{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('Y-m-d\TH:i') }}">
                                            <i class="bi bi-pencil" style="font-size: 13px;"></i>
                                        </button>

                                        <form action="{{ route('admin.noticias.destroy', $noticia->id_noticia) }}" method="POST" onsubmit="return confirm('Deseja realmente alterar o status desta notícia?');" style="display: inline-block; margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            @if($noticia->status_noticia === 'ATIVO')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Inativar Notícia" style="padding: .25rem .4rem; line-height: 1;">
                                                    <i class="bi bi-eye-slash" style="font-size: 13px;"></i>
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-success" title="Ativar Notícia" style="padding: .25rem .4rem; line-height: 1;">
                                                    <i class="bi bi-eye" style="font-size: 13px;"></i>
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
    </div>
</main>

@include('admin.noticias.modals.create')
@include('admin.noticias.modals.edit')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const botoesEditar = document.querySelectorAll('.btn-editar');
        const formEditar = document.getElementById('formEditarNoticia');

        botoesEditar.forEach(botao => {
            botao.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const titulo = this.getAttribute('data-titulo');
                const conteudo = this.getAttribute('data-conteudo');
                const categoria = this.getAttribute('data-categoria');
                const autor = this.getAttribute('data-autor');
                const data = this.getAttribute('data-data');

                // Ajusta a rota do formulário dinamicamente com o ID correto
                formEditar.action = `/admin/noticias/${id}`;

                // Alimenta os campos do Modal de Editar
                document.getElementById('edit_titulo').value = titulo;
                document.getElementById('edit_conteudo').value = conteudo;
                document.getElementById('edit_categoria').value = categoria;
                document.getElementById('edit_autor').value = autor;
                document.getElementById('edit_data').value = data;
            });
        });
    });
</script>
@endsection