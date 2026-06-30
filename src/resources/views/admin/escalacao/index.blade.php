@extends('layout.admin')

@section('title', 'Escalação por Time')

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <div class="admin-page-header">
      <div>
        <h1 class="page-title">Escalação</h1>
        <p class="page-subtitle">Selecione um time interno para gerenciar escalação e estatísticas dos atletas</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn-filter-toggle" id="btnFiltros"
                data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
      </div>
    </div>

    @if(session('sucesso'))
      <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        <strong>Sucesso!</strong> {{ session('sucesso') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    @if(session('erro'))
      <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('erro') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    {{-- Filtros --}}
    <div class="collapse" id="filterPanel">
      <div class="filter-panel">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="filter-label">Nome do time</label>
            <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
          </div>
          <div class="col-md-3">
            <label class="filter-label">Tipo</label>
            <select id="filtroTipo" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="INTERNO">Apenas Internos</option>
              <option value="EXTERNO">Apenas Externos</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="filter-label">Categoria</label>
            <select id="filtroCategoria" class="form-select form-select-sm">
              <option value="">Todas</option>
              @foreach($categorias as $cat)
                <option value="{{ $cat->id_categoria }}">{{ $cat->nome_categoria }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn-filter-clear" id="btnLimparFiltros">
              <i class="bi bi-x-circle"></i> Limpar
            </button>
          </div>
        </div>
        <div class="mt-2">
          <small class="text-muted" id="filtroContador"></small>
        </div>
      </div>
    </div>

    {{-- Grid de times --}}
    <div class="row g-3" id="escal-grid">
      @forelse($times as $time)
      @php $externo = $time->tipo_time === 'EXTERNO'; @endphp
      <div class="col-md-4 col-lg-3 escal-item"
           data-nome="{{ strtolower($time->nome_time) }}"
           data-tipo="{{ $time->tipo_time }}"
           data-categoria="{{ $time->id_categoria }}">

        @if($externo)
        {{-- Card desabilitado — time externo --}}
        <div class="escal-team-card escal-team-card-external">
          <div class="escal-team-logo">
            <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                 alt="{{ $time->nome_time }}"
                 onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
          </div>
          <div class="escal-team-info">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="escal-team-name">{{ $time->nome_time }}</span>
              <span class="badge escal-badge-externo">Externo</span>
            </div>
            @if($time->categoria)
              <span class="escal-team-cat">{{ $time->categoria->nome_categoria }}</span>
            @endif
            <span class="escal-team-unavailable">
              <i class="bi bi-lock-fill"></i> Elenco não disponível nesta associação
            </span>
          </div>
          <i class="bi bi-lock" style="color:#9ca3af;flex-shrink:0;"></i>
        </div>

        @else
        {{-- Card clicável — time interno --}}
        <a href="{{ route('admin.escalacao.show', $time->id_time) }}" class="escal-team-card">
          <div class="escal-team-logo">
            <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                 alt="{{ $time->nome_time }}"
                 onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
          </div>
          <div class="escal-team-info">
            <span class="escal-team-name">{{ $time->nome_time }}</span>
            @if($time->categoria)
              <span class="escal-team-cat">{{ $time->categoria->nome_categoria }}</span>
            @endif
            <span class="escal-team-count">
              <i class="bi bi-people-fill"></i>
              {{ $time->total_atletas }} atleta{{ $time->total_atletas != 1 ? 's' : '' }}
            </span>
          </div>
          <i class="bi bi-chevron-right escal-team-arrow"></i>
        </a>
        @endif

      </div>
      @empty
      <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-shield-x" style="font-size:3rem; opacity:.3;"></i>
        <p class="mt-3">Nenhum time cadastrado.</p>
      </div>
      @endforelse

      <div class="col-12 text-center py-5 text-muted d-none" id="escal-empty">
        <i class="bi bi-search" style="font-size:2.5rem; opacity:.3;"></i>
        <p class="mt-3">Nenhum time encontrado com os filtros selecionados.</p>
      </div>
    </div>

  </div>
</main>

<script>
(function () {
  const items    = document.querySelectorAll('.escal-item');
  const fNome    = document.getElementById('filtroNome');
  const fTipo    = document.getElementById('filtroTipo');
  const fCat     = document.getElementById('filtroCategoria');
  const btnLimp  = document.getElementById('btnLimparFiltros');
  const emptyMsg = document.getElementById('escal-empty');
  const contador = document.getElementById('filtroContador');

  function filtrar() {
    const nome = fNome.value.toLowerCase().trim();
    const tipo = fTipo.value;
    const cat  = fCat.value;
    let visible = 0;

    items.forEach(el => {
      const ok = (!nome || el.dataset.nome.includes(nome))
              && (!tipo || el.dataset.tipo === tipo)
              && (!cat  || el.dataset.categoria === cat);
      el.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    emptyMsg.classList.toggle('d-none', visible > 0);
    contador.textContent = visible + ' time' + (visible !== 1 ? 's' : '') + ' encontrado' + (visible !== 1 ? 's' : '');
  }

  fNome.addEventListener('input',   filtrar);
  fTipo.addEventListener('change',  filtrar);
  fCat .addEventListener('change',  filtrar);

  btnLimp.addEventListener('click', () => {
    fNome.value = '';
    fTipo.value = '';
    fCat.value  = '';
    filtrar();
  });

  filtrar();
})();
</script>
@endsection