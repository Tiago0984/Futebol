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
                            <input type="text" name="nome_campeonato" class="form-control" maxlength="100" required
                                value="{{ old('nome_campeonato', $campeonato->nome_campeonato) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                {{-- Gravado em maiúsculas (Campeonato::TIPOS); tipo antigo fora da lista continua como opção --}}
                                @php
                                    $tipoAtual = mb_strtoupper((string) old('tipo_campeonato', $campeonato->tipo_campeonato));
                                    $tiposCampeonato = \App\Models\Campeonato::TIPOS
                                        + ($tipoAtual !== '' ? [$tipoAtual => $campeonato->tipo_label] : []);
                                @endphp
                                @foreach($tiposCampeonato as $tipo => $rotuloTipo)
                                <option value="{{ $tipo }}" @selected($tipoAtual === $tipo)>{{ $rotuloTipo }}</option>
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
                            <label class="form-label">Local principal</label>
                            <input type="text" name="local_evento" class="form-control" maxlength="100" list="locaisUsados" autocomplete="off"
                                value="{{ old('local_evento', $campeonato->local_evento) }}">
                            <span class="form-text">Opcional. Cada jogo tem o seu local; este é a sugestão para os jogos novos.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organizador</label>
                            <input type="text" name="organizador_campeonato" class="form-control" maxlength="100"
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
                        @include('admin.campeonatos._imagens', [
                            'editando'    => true,
                            'logoAtual'   => $campeonato->logo_url,
                            'bannerAtual' => $campeonato->banner_url,
                        ])
                    </div>
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="table-card-toolbar" style="padding:20px 24px 0">
                    <p class="modal-section-label mb-0"><i class="bi bi-tags"></i> Categorias e Times Participantes</p>
                </div>
                <div class="p-4">
                    <div class="row">
                        @include('admin.campeonatos._categorias_e_participantes', [
                            'prefixo' => 'p',
                            'selecionadas' => old('categorias', $campeonato->categorias->pluck('id_categoria')->all()),
                            'participantes' => old('participantes', $campeonato->participantesPorCategoria()),
                        ])
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
@include('admin.partials.sugestoes')
@endsection
