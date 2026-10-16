@extends('layout.admin')

@section('title', 'Afastamentos — ' . $atleta->nome_atleta)

{{--
    Afastamentos de um atleta (16/10/2026; AfastamentosController): o atual (ou os próximos), com mudar as datas,
    encerrar (o atleta volta hoje) e excluir; embaixo, registrar um novo e os anteriores (só leitura). O atleta continua
    ATIVO e com o app. O motivo fica só aqui (dado de saúde de menor: não vai para o atleta nem para o responsável).
--}}
@php
  $ativo = strtoupper((string) $atleta->status_atleta) === 'ATIVO';
@endphp

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        @foreach ($caminho as [$rotulo, $url])
          @if ($url)
          <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
          @else
          <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
          @endif
        @endforeach
      </ol>
    </nav>

    <div class="admin-page-header">
      <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.atletas.index') }}" class="btn btn-sm btn-outline-secondary" title="Voltar" aria-label="Voltar">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <h1 class="page-title mb-0">Afastamentos · {{ $atleta->nome_atleta }}</h1>
          <p class="page-subtitle mb-0">
            {{ $atleta->categoriasAtivas->first()?->rotulo ?? 'Sem categoria' }}
            &bull; {{ ['ATIVO' => 'Ativo', 'INATIVO' => 'Inativo', 'PENDENTE' => 'Pendente', 'REJEITADO' => 'Rejeitado'][strtoupper((string) $atleta->status_atleta)] ?? $atleta->status_atleta }}
          </p>
        </div>
      </div>
    </div>

    @include('admin.jogos._mensagens')

    <p class="text-muted small mb-3">
      <i class="bi bi-info-circle"></i> No afastamento (doença, lesão, viagem), o atleta não treina entre as duas datas e
      continua <strong>ativo</strong>, com o aplicativo. O motivo aparece só aqui, no painel.
    </p>

    {{-- Atual e próximos --}}
    <div class="table-card mb-4">
      <div class="table-card-toolbar">
        <span class="tbl-count">Atual e próximos ({{ $vigentes->count() }})</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0" id="tabelaAfastamentosVigentes">
          <thead>
            <tr>
              <th>Período</th>
              <th>Motivo</th>
              <th>Registrado por</th>
              <th class="text-center" style="width:150px">Ações</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($vigentes as $afastamento)
            <tr>
              <td>
                <span class="fw-semibold">{{ $afastamento->periodoTexto() }}</span>
                <div><span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:0.7rem;">
                  {{ $afastamento->jaComecou() ? 'Em andamento' : 'Ainda vai começar' }}</span></div>
              </td>
              <td>{{ $afastamento->motivo_atleta_afastamento ?: '—' }}</td>
              <td class="small text-muted">
                {{ $afastamento->usuario?->nome_usuario ?? '—' }}<br>{{ $afastamento->data_atleta_afastamento?->format('d/m/Y H:i') }}
              </td>
              <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                  <button type="button" class="btn-tbl edit" title="Mudar as datas ou o motivo"
                          data-bs-toggle="collapse" data-bs-target="#editarAfastamento{{ $afastamento->id_atleta_afastamento }}">
                    <i class="bi bi-pencil"></i>
                  </button>
                  @if ($afastamento->podeEncerrar())
                  <form action="{{ route('admin.atletas.afastamentos.encerrar', [$atleta->id_atleta, $afastamento->id_atleta_afastamento]) }}"
                        method="POST" onsubmit="return confirm('Encerrar o afastamento? O atleta volta a treinar hoje.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-tbl activate" title="Encerrar (volta a treinar hoje)"><i class="bi bi-check2-circle"></i></button>
                  </form>
                  @endif
                  <form action="{{ route('admin.atletas.afastamentos.destroy', [$atleta->id_atleta, $afastamento->id_atleta_afastamento]) }}"
                        method="POST" onsubmit="return confirm('Excluir este afastamento? Use para corrigir um registro feito por engano.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-tbl deactivate" title="Excluir"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            <tr class="collapse" id="editarAfastamento{{ $afastamento->id_atleta_afastamento }}">
              <td colspan="4" class="bg-body-tertiary">
                <form action="{{ route('admin.atletas.afastamentos.update', [$atleta->id_atleta, $afastamento->id_atleta_afastamento]) }}"
                      method="POST" class="row g-2 align-items-end">
                  @csrf @method('PUT')
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Início</label>
                    {{-- Afastamento que já começou: só o fim muda (prorrogar ou encurtar) --}}
                    <input type="date" name="data_inicio_atleta_afastamento" class="form-control form-control-sm" required
                           value="{{ $afastamento->data_inicio_atleta_afastamento->format('Y-m-d') }}" @readonly($afastamento->jaComecou())>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Fim</label>
                    <input type="date" name="data_fim_atleta_afastamento" class="form-control form-control-sm" required
                           min="{{ today()->format('Y-m-d') }}" value="{{ $afastamento->data_fim_atleta_afastamento->format('Y-m-d') }}">
                  </div>
                  <div class="col-md-5">
                    <label class="form-label small mb-1">Motivo (só no painel)</label>
                    <input type="text" name="motivo_atleta_afastamento" class="form-control form-control-sm"
                           maxlength="{{ \App\Models\AtletaAfastamento::MOTIVO_MAX }}" value="{{ $afastamento->motivo_atleta_afastamento }}">
                  </div>
                  <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                  </div>
                </form>
              </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum afastamento em andamento ou marcado.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Registrar --}}
    @if ($ativo)
    <div class="table-card mb-4">
      <div class="table-card-toolbar"><span class="tbl-count">Registrar afastamento</span></div>
      <form action="{{ route('admin.atletas.afastamentos.store', $atleta->id_atleta) }}" method="POST" class="row g-2 align-items-end p-3" id="formNovoAfastamento">
        @csrf
        <div class="col-md-2">
          <label class="form-label small mb-1">Início <span class="text-danger">*</span></label>
          <input type="date" name="data_inicio_atleta_afastamento" class="form-control form-control-sm" required
                 value="{{ old('data_inicio_atleta_afastamento', today()->format('Y-m-d')) }}">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-1">Fim <span class="text-danger">*</span></label>
          <input type="date" name="data_fim_atleta_afastamento" class="form-control form-control-sm" required
                 min="{{ today()->format('Y-m-d') }}" value="{{ old('data_fim_atleta_afastamento') }}">
        </div>
        <div class="col-md-5">
          <label class="form-label small mb-1">Motivo (opcional, só no painel)</label>
          <input type="text" name="motivo_atleta_afastamento" class="form-control form-control-sm" placeholder="Ex.: gripe, entorse no tornozelo"
                 maxlength="{{ \App\Models\AtletaAfastamento::MOTIVO_MAX }}" value="{{ old('motivo_atleta_afastamento') }}">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-calendar-x"></i> Registrar</button>
        </div>
        <div class="col-12 form-text">O fim é obrigatório; se o atleta precisar de mais tempo, prorrogue depois.</div>
      </form>
    </div>
    @else
    <div class="alert alert-secondary small">Só atleta ativo pode ser afastado (o inativo já não treina).</div>
    @endif

    {{-- Anteriores --}}
    @if ($anteriores->isNotEmpty())
    <div class="table-card">
      <div class="table-card-toolbar"><span class="tbl-count">Anteriores ({{ $anteriores->count() }})</span></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Período</th><th>Motivo</th><th>Registrado por</th></tr></thead>
          <tbody>
            @foreach ($anteriores as $afastamento)
            <tr>
              <td>{{ $afastamento->periodoTexto() }}</td>
              <td>{{ $afastamento->motivo_atleta_afastamento ?: '—' }}</td>
              <td class="small text-muted">{{ $afastamento->usuario?->nome_usuario ?? '—' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

  </div>
</main>
@endsection
