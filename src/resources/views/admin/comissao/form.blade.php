{{-- Novo e Editar profissional da comissão técnica (Etapa C1): o mesmo formulário; $profissional novo (sem id) = cadastro --}}
@extends('layout.admin')

@php
    $editando = $profissional->exists;
    // Caixa desmarcada não vem no envio: depois de um erro, vale o que foi enviado, não o que está no banco
    $voltouComErro = $errors->any();
    $marcadas = collect($voltouComErro ? old('categorias', []) : $profissional->categorias->pluck('id_categoria')->all())->map(fn ($id) => (int) $id)->all();
    $fotoAutorizada = $voltouComErro ? (bool) old('foto_autorizada_comissao_tecnica') : (bool) $profissional->foto_autorizada_comissao_tecnica;
@endphp

@section('title', $editando ? 'Editar profissional' : 'Novo profissional')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">{{ $editando ? $profissional->nome_comissao_tecnica : 'Novo profissional' }}</h1>
                <p class="page-subtitle">Comissão técnica · o que estiver aqui aparece no perfil do app (menos a foto sem autorização)</p>
            </div>
            <a href="{{ route('admin.comissao.index') }}" class="btn-filter-toggle"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <strong>Erro!</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" enctype="multipart/form-data"
              action="{{ $editando ? route('admin.comissao.update', $profissional->id_comissao_tecnica) : route('admin.comissao.store') }}">
            @csrf
            @if($editando) @method('PUT') @endif

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ct_nome">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="ct_nome" name="nome_comissao_tecnica" class="form-control" required maxlength="100"
                                value="{{ old('nome_comissao_tecnica', $profissional->nome_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_cargo">Cargo <span class="text-danger">*</span></label>
                            <select id="ct_cargo" name="cargo_comissao_tecnica" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\ComissaoTecnica::CARGOS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('cargo_comissao_tecnica', $profissional->cargo_comissao_tecnica) === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_especialidade">Especialidade</label>
                            <input type="text" id="ct_especialidade" name="especialidade_comissao_tecnica" class="form-control" maxlength="100"
                                placeholder="Ex: Treinamento técnico" value="{{ old('especialidade_comissao_tecnica', $profissional->especialidade_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ct_licenca">Licença / certificação</label>
                            <input type="text" id="ct_licenca" name="licenca_comissao_tecnica" class="form-control" maxlength="100"
                                placeholder="Ex: CBF Academy" value="{{ old('licenca_comissao_tecnica', $profissional->licenca_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ct_inicio">Na AACJ desde</label>
                            <input type="date" id="ct_inicio" name="data_inicio_comissao_tecnica" class="form-control" max="{{ now()->toDateString() }}"
                                value="{{ old('data_inicio_comissao_tecnica', $profissional->data_inicio_comissao_tecnica?->format('Y-m-d')) }}">
                            <small class="text-muted">O app mostra o tempo na AACJ a partir desta data.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Categorias</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="ct_principal">Categoria principal</label>
                            <select id="ct_principal" name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" @selected((string) old('id_categoria', $profissional->id_categoria) === (string) $cat->id_categoria)>
                                    {{ $cat->rotulo }}{{ $cat->status_categoria !== 'ATIVO' ? ' (inativa)' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label mb-1">Categorias atendidas</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($categorias as $cat)
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" name="categorias[]" id="ct_cat_{{ $cat->id_categoria }}"
                                        value="{{ $cat->id_categoria }}" @checked(in_array((int) $cat->id_categoria, $marcadas, true))>
                                    <label class="form-check-label" for="ct_cat_{{ $cat->id_categoria }}">{{ $cat->rotulo }}{{ $cat->status_categoria !== 'ATIVO' ? ' (inativa)' : '' }}</label>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">A principal entra na lista mesmo sem marcar.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Foto</div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        @if($profissional->urlFoto())
                        <div class="col-auto">
                            <img src="{{ $profissional->urlFoto() }}" alt="Foto atual" style="width:72px;height:72px;object-fit:cover;border-radius:50%;">
                        </div>
                        @endif
                        <div class="col-md-6">
                            <input type="file" name="foto_comissao_tecnica" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">JPG, PNG ou WEBP, até 2 MB.{{ $profissional->urlFoto() ? ' Em branco, mantém a atual.' : '' }}</small>
                            @if($profissional->urlFoto())
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remover_foto" value="1" id="ct_remover_foto">
                                <label class="form-check-label" for="ct_remover_foto">Remover a foto</label>
                            </div>
                            @endif
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="foto_autorizada_comissao_tecnica" value="1" id="ct_autorizada"
                                    @checked($fotoAutorizada)>
                                <label class="form-check-label" for="ct_autorizada">Autorizou mostrar a foto no app</label>
                            </div>
                            <small class="text-muted">Sem esta caixa marcada, a foto fica só aqui no painel e o app mostra as iniciais.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Contato institucional</div>
                <div class="card-body">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle"></i> Use só o telefone e o e-mail <strong>da escolinha</strong> para este profissional.
                        Não informe o celular nem o e-mail pessoal: atletas e responsáveis veem estes contatos no app.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="ct_telefone">Telefone institucional</label>
                            <input type="text" id="ct_telefone" name="telefone_institucional_comissao_tecnica" class="form-control" maxlength="20"
                                placeholder="Ex: (11) 4000-0000" value="{{ old('telefone_institucional_comissao_tecnica', $profissional->telefone_institucional_comissao_tecnica) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ct_email">E-mail institucional</label>
                            <input type="email" id="ct_email" name="email_institucional_comissao_tecnica" class="form-control" maxlength="255"
                                placeholder="Ex: carlos@aacj.org.br" value="{{ old('email_institucional_comissao_tecnica', $profissional->email_institucional_comissao_tecnica) }}">
                        </div>
                        <div class="col-12"><small class="text-muted">Opcionais. Sem nenhum dos dois, o app esconde a seção de contato.</small></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="{{ route('admin.comissao.index') }}" class="btn-modal-cancel">Cancelar</a>
                <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> {{ $editando ? 'Atualizar' : 'Salvar' }}</button>
            </div>
        </form>

    </div>
</main>
@endsection
