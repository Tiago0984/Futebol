@extends('layout.admin')

@section('title', 'Editar Campeonato')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Editar Campeonato</h1>
                <p class="page-subtitle">{{ $campeonato->nome_campeonato }}</p>
            </div>
            <a href="{{ route('admin.campeonatos.index') }}" class="btn-filter-toggle">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <form action="{{ route('admin.campeonatos.update', $campeonato->id_campeonato) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="table-card mb-3">
                <div class="table-card-toolbar" style="padding:20px 24px 0">
                    <p class="modal-section-label mb-0"><i class="bi bi-trophy"></i> Informações Gerais</p>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Nome do Campeonato <span class="text-danger">*</span></label>
                            <input type="text" name="nome_campeonato" class="form-control" required
                                value="{{ old('nome_campeonato', $campeonato->nome_campeonato) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(['Liga', 'Copa', 'Torneio', 'Mata-mata', 'Pontos Corridos', 'Amistoso'] as $tipo)
                                <option value="{{ $tipo }}" {{ old('tipo_campeonato', $campeonato->tipo_campeonato) == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">— Nenhuma —</option>
                                @foreach($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}"
                                    {{ old('id_categoria', $campeonato->id_categoria) == $cat->id_categoria ? 'selected' : '' }}>
                                    {{ $cat->nome_categoria }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" name="data_inicio_campeonato" class="form-control" required
                                value="{{ old('data_inicio_campeonato', $campeonato->data_inicio_campeonato?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data de Fim <span class="text-danger">*</span></label>
                            <input type="date" name="data_fim_campeonato" class="form-control" required
                                value="{{ old('data_fim_campeonato', $campeonato->data_fim_campeonato?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Local</label>
                            <input type="text" name="local_evento" class="form-control"
                                value="{{ old('local_evento', $campeonato->local_evento) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organizador</label>
                            <input type="text" name="organizador_campeonato" class="form-control"
                                value="{{ old('organizador_campeonato', $campeonato->organizador_campeonato) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao_campeonato" class="form-control" rows="3">{{ old('descricao_campeonato', $campeonato->descricao_campeonato) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card mb-3">
                <div class="table-card-toolbar" style="padding:20px 24px 0">
                    <p class="modal-section-label mb-0"><i class="bi bi-image"></i> Imagens</p>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Logo / Escudo</label>
                            @if($campeonato->logo_evento)
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img src="{{ asset('futebol/images/campeonatos/' . $campeonato->logo_evento) }}"
                                     style="width:48px;height:48px;object-fit:contain;border:1px solid #eee;border-radius:4px;">
                                <small class="text-muted">Atual: {{ $campeonato->logo_evento }}</small>
                            </div>
                            @endif
                            <input type="file" name="logo_evento" class="form-control" accept="image/*">
                            <span class="form-text">Deixe em branco para manter o atual.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Banner (imagem de destaque)</label>
                            @if($campeonato->banner_evento)
                            <div class="mb-2">
                                <small class="text-muted">Atual: {{ $campeonato->banner_evento }}</small>
                            </div>
                            @endif
                            <input type="file" name="banner_evento" class="form-control" accept="image/*">
                            <span class="form-text">Deixe em branco para manter o atual.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="table-card-toolbar" style="padding:20px 24px 0">
                    <p class="modal-section-label mb-0"><i class="bi bi-shield-fill"></i> Times Participantes</p>
                </div>
                <div class="p-4">
                    @php $timesVinculados = $campeonato->times->pluck('id_time')->toArray(); @endphp
                    <div class="row g-2">
                        @foreach($times as $time)
                        <div class="col-md-4 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="times[]"
                                    value="{{ $time->id_time }}" id="time_{{ $time->id_time }}"
                                    {{ in_array($time->id_time, old('times', $timesVinculados)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="time_{{ $time->id_time }}">
                                    {{ $time->nome_time }}
                                    @if($time->tipo_time === 'INTERNO')
                                        <span class="badge-status ativo ms-1" style="font-size:0.6rem;">INT</span>
                                    @endif
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn-admin-primary">
                    <i class="bi bi-check-lg"></i> Atualizar Campeonato
                </button>
                <a href="{{ route('admin.campeonatos.index') }}" class="btn-filter-toggle">Cancelar</a>
            </div>
        </form>

    </div>
</main>
@endsection
