@extends('layout.admin')

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <div class="admin-page-header">
      <div>
        <h1 class="page-title">Vídeos</h1>
        <p class="page-subtitle">Gerencie os vídeos exibidos no site</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn-filter-toggle" id="btnFiltros"
                data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoVideo">
          <i class="bi bi-plus-lg me-1"></i> Adicionar
        </button>
      </div>
    </div>

    @if(session('sucesso'))
      <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        <strong>Sucesso!</strong> {{ session('sucesso') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    {{-- Filtros --}}
    <div class="collapse" id="filterPanel">
      <div class="filter-panel">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="filter-label">Título</label>
            <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por título...">
          </div>
          <div class="col-md-3">
            <label class="filter-label">Status</label>
            <select id="filtroStatus" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="ATIVO">Ativo</option>
              <option value="INATIVO">Inativo</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="filter-label">Tipo</label>
            <select id="filtroTipo" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="1">Ao Vivo</option>
              <option value="0">Gravado</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="filter-label">Seção</label>
            <select id="filtroSecao" class="form-select form-select-sm">
              <option value="">Todas</option>
              <option value="home">Home</option>
              <option value="campeonatos">Campeonatos</option>
              <option value="ambas">Ambas</option>
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

    {{-- Grid de vídeos --}}
    <div class="row g-3" id="videoGrid">
      @forelse($videos as $video)
      <div class="col-md-6 col-xl-4 video-item"
           data-titulo="{{ strtolower($video->titulo_video) }}"
           data-status="{{ $video->status_video }}"
           data-ao-vivo="{{ $video->ao_vivo_video ? '1' : '0' }}"
           data-secao="{{ $video->secao_video ?? 'home' }}">
        <div class="video-admin-card {{ $video->status_video !== 'ATIVO' ? 'video-card-inactive' : '' }}">

          {{-- Thumbnail --}}
          <div class="video-thumb-wrap">
            @if($video->thumbnail)
              <img src="{{ $video->thumbnail }}" alt="{{ $video->titulo_video }}" class="video-thumb-img">
            @else
              <div class="video-thumb-placeholder">
                <i class="bi bi-play-circle"></i>
              </div>
            @endif
            <a href="javascript:void(0)" class="video-thumb-play"
               data-bs-toggle="modal"
               data-bs-target="#modalPreview{{ $video->id_video }}">
              <i class="bi bi-play-fill"></i>
            </a>
            @if($video->status_video !== 'ATIVO')
              <span class="video-inactive-badge">Inativo</span>
            @endif
            @if($video->ao_vivo_video)
              <span class="video-live-badge"><i class="bi bi-broadcast me-1"></i>Ao Vivo</span>
            @endif
            <span class="badge bg-secondary position-absolute" style="bottom:8px;left:8px;font-size:.65rem;">
              {{ $video->secao_video === 'campeonatos' ? 'Campeonatos' : ($video->secao_video === 'ambas' ? 'Home & Camp.' : 'Home') }}
            </span>
          </div>

          {{-- Info --}}
          <div class="video-card-body">
            <h6 class="video-card-title">{{ $video->titulo_video }}</h6>
            @if($video->descricao_video)
              <p class="video-card-desc">{{ Str::limit($video->descricao_video, 80) }}</p>
            @endif
            <p class="video-card-url">
              <i class="bi bi-link-45deg"></i>
              <span>{{ Str::limit($video->url_video, 45) }}</span>
            </p>
          </div>

          {{-- Ações --}}
          <div class="video-card-actions">
            <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditVideo{{ $video->id_video }}">
              <i class="bi bi-pencil"></i> Editar
            </button>
            <form action="{{ route('admin.videos.toggleStatus', $video->id_video) }}" method="POST" class="d-inline">
              @csrf @method('PATCH')
              <button type="submit" class="btn btn-sm {{ $video->status_video === 'ATIVO' ? 'btn-outline-warning' : 'btn-outline-success' }}">
                <i class="bi {{ $video->status_video === 'ATIVO' ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                {{ $video->status_video === 'ATIVO' ? 'Desativar' : 'Ativar' }}
              </button>
            </form>
            <form action="{{ route('admin.videos.destroy', $video->id_video) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Remover este vídeo?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>

      {{-- Modal de preview --}}
      <div class="modal fade" id="modalPreview{{ $video->id_video }}" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 pb-0">
              <h6 class="modal-title text-white">{{ $video->titulo_video }}</h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
              <div class="ratio ratio-16x9">
                <iframe src="{{ $video->embed_url }}"
                        allowfullscreen
                        allow="autoplay; encrypted-media"
                        class="rounded"></iframe>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Modal de edição --}}
      <div class="modal fade" id="modalEditVideo{{ $video->id_video }}" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content modal-admin">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Editar Vídeo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.videos.update', $video->id_video) }}" method="POST">
              @csrf @method('PUT')
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Título <span class="text-danger">*</span></label>
                  <input type="text" name="titulo_video" class="form-control"
                         value="{{ $video->titulo_video }}" required maxlength="255">
                </div>
                <div class="mb-3">
                  <label class="form-label">URL do Vídeo <span class="text-danger">*</span></label>
                  <input type="url" name="url_video" class="form-control"
                         value="{{ $video->url_video }}" required maxlength="500"
                         placeholder="https://www.youtube.com/watch?v=...">
                  <div class="form-text">Aceita links do YouTube e Vimeo.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Descrição</label>
                  <textarea name="descricao_video" class="form-control" rows="2"
                            placeholder="Legenda exibida na home quando não for ao vivo">{{ $video->descricao_video }}</textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label">Seção <span class="text-danger">*</span></label>
                  <select name="secao_video" class="form-select">
                    <option value="home"         {{ ($video->secao_video ?? 'home') === 'home'         ? 'selected' : '' }}>Home</option>
                    <option value="campeonatos"  {{ ($video->secao_video ?? '') === 'campeonatos'       ? 'selected' : '' }}>Campeonatos</option>
                    <option value="ambas"        {{ ($video->secao_video ?? '') === 'ambas'             ? 'selected' : '' }}>Ambas (Home & Campeonatos)</option>
                  </select>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="ao_vivo_video"
                         id="aoVivo{{ $video->id_video }}" value="1"
                         {{ $video->ao_vivo_video ? 'checked' : '' }}>
                  <label class="form-check-label" for="aoVivo{{ $video->id_video }}">
                    <i class="bi bi-broadcast text-danger me-1"></i>
                    Ao Vivo — exibe "ASSISTA AO VIVO" na seção selecionada
                  </label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-modal-submit">
                  <i class="bi bi-check-lg"></i> Salvar
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      @empty
      <div class="col-12 text-center py-5 text-muted" id="videoEmpty">
        <i class="bi bi-camera-video" style="font-size:3rem; opacity:.3;"></i>
        <p class="mt-3">Nenhum vídeo cadastrado ainda.</p>
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoVideo">
          <i class="bi bi-plus-lg me-1"></i> Adicionar primeiro vídeo
        </button>
      </div>
      @endforelse

      <div class="col-12 text-center py-5 text-muted d-none" id="videoFilterEmpty">
        <i class="bi bi-search" style="font-size:2.5rem; opacity:.3;"></i>
        <p class="mt-3">Nenhum vídeo encontrado com os filtros selecionados.</p>
      </div>
    </div>

  </div>
</main>

{{-- Modal Novo Vídeo --}}
<div class="modal fade" id="modalNovoVideo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content modal-admin">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-camera-video me-1"></i> Adicionar Vídeo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="{{ route('admin.videos.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Título <span class="text-danger">*</span></label>
            <input type="text" name="titulo_video" class="form-control"
                   placeholder="Ex: Jogo Sub-13 vs Grêmio" required maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label">URL do Vídeo <span class="text-danger">*</span></label>
            <input type="url" name="url_video" class="form-control" id="novoVideoUrl"
                   placeholder="https://www.youtube.com/watch?v=..." required maxlength="500">
            <div class="form-text">Cole o link do YouTube ou Vimeo.</div>
          </div>
          <div id="novoVideoPreview" class="mb-3" style="display:none;">
            <label class="form-label text-muted" style="font-size:.75rem;">Pré-visualização</label>
            <div class="ratio ratio-16x9">
              <iframe id="novoVideoIframe" src="" allowfullscreen class="rounded border"></iframe>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea name="descricao_video" class="form-control" rows="2"
                      placeholder="Legenda exibida na home quando não for ao vivo"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Seção <span class="text-danger">*</span></label>
            <select name="secao_video" class="form-select">
              <option value="home">Home</option>
              <option value="campeonatos">Campeonatos</option>
              <option value="ambas">Ambas (Home & Campeonatos)</option>
            </select>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ao_vivo_video"
                   id="novoAoVivo" value="1">
            <label class="form-check-label" for="novoAoVivo">
              <i class="bi bi-broadcast text-danger me-1"></i>
              Ao Vivo — exibe "ASSISTA AO VIVO" na seção selecionada
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn-modal-submit">
            <i class="bi bi-plus-lg"></i> Adicionar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('novoVideoUrl').addEventListener('input', function () {
    var url = this.value.trim();
    var embed = '';
    var ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    var vmMatch = url.match(/vimeo\.com\/(\d+)/);
    if (ytMatch) embed = 'https://www.youtube.com/embed/' + ytMatch[1];
    if (vmMatch) embed = 'https://player.vimeo.com/video/' + vmMatch[1];

    var preview = document.getElementById('novoVideoPreview');
    var iframe  = document.getElementById('novoVideoIframe');
    if (embed) { iframe.src = embed; preview.style.display = ''; }
    else       { iframe.src = '';    preview.style.display = 'none'; }
});

(function () {
    var items    = document.querySelectorAll('.video-item');
    var fNome    = document.getElementById('filtroNome');
    var fStatus  = document.getElementById('filtroStatus');
    var fTipo    = document.getElementById('filtroTipo');
    var fSecao   = document.getElementById('filtroSecao');
    var btnLimp  = document.getElementById('btnLimparFiltros');
    var emptyMsg = document.getElementById('videoFilterEmpty');
    var contador = document.getElementById('filtroContador');

    if (!fNome) return;

    function filtrar() {
        var nome   = fNome.value.toLowerCase().trim();
        var status = fStatus.value;
        var tipo   = fTipo.value;
        var secao  = fSecao.value;
        var visible = 0;

        items.forEach(function (el) {
            var ok = (!nome   || el.dataset.titulo.includes(nome))
                  && (!status || el.dataset.status  === status)
                  && (!tipo   || el.dataset.aoVivo  === tipo)
                  && (!secao  || el.dataset.secao   === secao);
            el.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        emptyMsg.classList.toggle('d-none', visible > 0);
        contador.textContent = visible + ' vídeo' + (visible !== 1 ? 's' : '') + ' encontrado' + (visible !== 1 ? 's' : '');
    }

    fNome.addEventListener('input',    filtrar);
    fStatus.addEventListener('change', filtrar);
    fTipo.addEventListener('change',   filtrar);
    fSecao.addEventListener('change',  filtrar);
    btnLimp.addEventListener('click', function () {
        fNome.value = ''; fStatus.value = ''; fTipo.value = ''; fSecao.value = '';
        filtrar();
    });
    filtrar();
})();
</script>
@endsection