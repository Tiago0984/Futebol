@extends('layout.admin')

@section('title', 'Escalação — ' . $time->nome_time)

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    {{-- Cabeçalho da página --}}
    <div class="admin-page-header">
      <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.escalacao.index') }}" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left"></i>
        </a>
        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
             alt="{{ $time->nome_time }}"
             style="width:40px;height:40px;object-fit:contain;border-radius:8px;"
             onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
        <div>
          <h1 class="page-title mb-0">{{ $time->nome_time }}</h1>
          <p class="page-subtitle mb-0">
            @if($time->categoria) {{ $time->categoria->nome_categoria }} &bull; @endif
            {{ $atletas->count() }} atleta{{ $atletas->count() != 1 ? 's' : '' }} no elenco
          </p>
        </div>
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

    @if($atletas->isEmpty())
      <div class="text-center py-5 text-muted">
        <i class="bi bi-people" style="font-size:3rem; opacity:.3;"></i>
        <p class="mt-3">Nenhum atleta vinculado a este time.<br>
          <a href="{{ route('admin.atletas.index') }}">Vincule atletas na gestão de atletas.</a>
        </p>
      </div>
    @else

    {{-- Filtros --}}
    <div class="collapse" id="filterPanel">
      <div class="filter-panel">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="filter-label">Nome do atleta</label>
            <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
          </div>
          <div class="col-md-3">
            <label class="filter-label">Posição</label>
            <select id="filtroPosicao" class="form-select form-select-sm">
              <option value="">Todas</option>
              @foreach(['GOLEIRO','ZAGUEIRO','LATERAL','VOLANTE','MEIA','ATACANTE'] as $pos)
                <option value="{{ $pos }}">{{ ucfirst(strtolower($pos)) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="filter-label">Status</label>
            <select id="filtroStatus" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="TITULAR">Titular</option>
              <option value="RESERVA">Reserva</option>
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

    {{-- Legenda --}}
    <div class="d-flex gap-3 mb-3 flex-wrap">
      <span class="escal-legend escal-titular"><i class="bi bi-circle-fill"></i> Titular</span>
      <span class="escal-legend escal-reserva"><i class="bi bi-circle-fill"></i> Reserva</span>
    </div>

    {{-- Tabela --}}
    <div class="dash-panel">
      <div class="table-responsive">
        <table class="table escal-table mb-0">
          <thead>
            <tr>
              <th>Atleta</th>
              <th class="text-center">Camisa</th>
              <th class="text-center">Posição</th>
              <th class="text-center">Status</th>
              <th class="text-center">Jogos</th>
              <th class="text-center escal-th-gols">Gols</th>
              <th class="text-center escal-th-defesas">Defesas</th>
              <th class="text-center">Convocações</th>
              <th class="text-center">Cartões</th>
              <th class="text-center">Ações</th>
            </tr>
          </thead>
          <tbody id="escal-tbody">
            @foreach($atletas as $atleta)
            @php
              $cartoesAtleta = $cartoes[$atleta->id_atleta] ?? collect();
              $amarelos      = $cartoesAtleta->where('tipo_cartao', 'AMARELO')->first()?->total ?? 0;
              $vermelhos     = $cartoesAtleta->where('tipo_cartao', 'VERMELHO')->first()?->total ?? 0;
              $isTitular     = in_array(strtoupper($atleta->status_atleta_time), ['TITULAR', 'ATIVO']);
              $statusLabel   = $isTitular ? 'TITULAR' : 'RESERVA';
              $posicoes      = ['GOLEIRO','ZAGUEIRO','LATERAL','VOLANTE','MEIA','ATACANTE'];
            @endphp
            <tr class="escal-row {{ $isTitular ? 'escal-row-titular' : 'escal-row-reserva' }}"
                data-nome="{{ strtolower($atleta->nome_atleta) }}"
                data-posicao="{{ strtoupper($atleta->posicao_atleta_time ?? '') }}"
                data-status="{{ $statusLabel }}">

              {{-- Foto + Nome --}}
              <td>
                <div class="escal-player-cell">
                  <div class="escal-avatar">
                    @php
                      $foto = $atleta->foto_atleta;
                      if (!$foto || $foto === 'default-player.jpg') {
                          $fotoSrc = asset('futebol/images/our-teams/default-player.jpg');
                      } elseif (str_starts_with($foto, 'atletas/')) {
                          $fotoSrc = asset('storage/' . $foto);
                      } else {
                          $fotoSrc = asset('futebol/images/our-teams/' . $foto);
                      }
                    @endphp
                    <img src="{{ $fotoSrc }}"
                         alt="{{ $atleta->nome_atleta }}"
                         onerror="this.src='{{ asset('futebol/images/our-teams/default-player.jpg') }}'">
                  </div>
                  <span class="escal-name">{{ $atleta->nome_atleta }}</span>
                </div>
              </td>
              <td class="text-center">
                <span class="escal-camisa">{{ $atleta->camisa_atleta_time ?: '—' }}</span>
              </td>
              <td class="text-center">
                <span class="escal-pos-badge">{{ $atleta->posicao_atleta_time ?: '—' }}</span>
              </td>
              <td class="text-center">
                @if($isTitular)
                  <span class="badge escal-badge-titular">Titular</span>
                @else
                  <span class="badge escal-badge-reserva">Reserva</span>
                @endif
              </td>
              <td class="text-center escal-stat">{{ $atleta->jogos_atleta_time }}</td>
              <td class="text-center escal-stat escal-stat-gols">{{ $atleta->gols_atleta_time }}</td>
              <td class="text-center escal-stat escal-stat-defesas">{{ $atleta->defesas_atleta_time }}</td>
              <td class="text-center escal-stat">{{ $atleta->convocacao_atleta_time }}</td>
              <td class="text-center">
                @if($amarelos)
                  <span class="escal-cartao amarelo">{{ $amarelos }}</span>
                @endif
                @if($vermelhos)
                  <span class="escal-cartao vermelho">{{ $vermelhos }}</span>
                @endif
                @if(!$amarelos && !$vermelhos)
                  <span class="text-muted" style="font-size:.75rem;">—</span>
                @endif
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEdit{{ $atleta->id_atleta }}">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>

            {{-- Modal de edição --}}
            <div class="modal fade" id="modalEdit{{ $atleta->id_atleta }}" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-admin">
                  <div class="modal-header">
                    <h5 class="modal-title">
                      <i class="bi bi-pencil-square me-1"></i>{{ $atleta->nome_atleta }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <form action="{{ route('admin.escalacao.update', [$time->id_time, $atleta->id_atleta]) }}"
                        method="POST">
                    @csrf @method('PATCH')
                    <div class="modal-body">
                      <div class="row g-3">
                        <div class="col-6">
                          <label class="form-label">Nº Camisa</label>
                          <input type="number" name="camisa_atleta_time" class="form-control text-center"
                                 value="{{ $atleta->camisa_atleta_time }}" min="0" max="99">
                        </div>
                        <div class="col-6">
                          <label class="form-label">Status</label>
                          <select name="status_atleta_time" class="form-select">
                            <option value="TITULAR" {{ $isTitular ? 'selected' : '' }}>Titular</option>
                            <option value="RESERVA" {{ !$isTitular ? 'selected' : '' }}>Reserva</option>
                          </select>
                        </div>
                        <div class="col-12">
                          <label class="form-label">Posição</label>
                          <select name="posicao_atleta_time" class="form-select">
                            <option value="">— Selecionar —</option>
                            @foreach($posicoes as $pos)
                              <option value="{{ $pos }}"
                                {{ strtoupper($atleta->posicao_atleta_time) === $pos ? 'selected' : '' }}>
                                {{ ucfirst(strtolower($pos)) }}
                              </option>
                            @endforeach
                          </select>
                        </div>

                        <div class="col-12">
                          <hr class="my-1">
                          <p class="form-label mb-0 text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;">Estatísticas</p>
                        </div>

                        <div class="col-6">
                          <label class="form-label">Jogos disputados</label>
                          <input type="number" name="jogos_atleta_time" class="form-control text-center"
                                 value="{{ $atleta->jogos_atleta_time }}" min="0">
                        </div>
                        <div class="col-6">
                          <label class="form-label">Convocações</label>
                          <input type="number" name="convocacao_atleta_time" class="form-control text-center"
                                 value="{{ $atleta->convocacao_atleta_time }}" min="0">
                        </div>
                        <div class="col-6">
                          <label class="form-label">Gols</label>
                          <input type="number" name="gols_atleta_time" class="form-control text-center"
                                 value="{{ $atleta->gols_atleta_time }}" min="0">
                        </div>
                        <div class="col-6">
                          <label class="form-label">Defesas <span class="text-muted" style="font-size:.72rem;">(goleiros)</span></label>
                          <input type="number" name="defesas_atleta_time" class="form-control text-center"
                                 value="{{ $atleta->defesas_atleta_time }}" min="0">
                        </div>
                      </div>

                        <div class="col-12">
                          <hr class="my-1">
                          <p class="form-label mb-2 text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;">Cartões</p>
                        </div>
                        <div class="col-6">
                          <label class="form-label d-flex align-items-center gap-1">
                            <span class="escal-cartao amarelo" style="width:16px;height:20px;border-radius:2px;font-size:0;display:inline-block;"></span>
                            Amarelos
                          </label>
                          <input type="number" name="cartao_amarelo_manual" class="form-control text-center"
                                 value="{{ $amarelos }}" min="0">
                        </div>
                        <div class="col-6">
                          <label class="form-label d-flex align-items-center gap-1">
                            <span class="escal-cartao vermelho" style="width:16px;height:20px;border-radius:2px;font-size:0;display:inline-block;"></span>
                            Vermelhos
                          </label>
                          <input type="number" name="cartao_vermelho_manual" class="form-control text-center"
                                 value="{{ $vermelhos }}" min="0">
                        </div>

                      <div class="mt-3 p-2 rounded" style="background:#f8f9fa;font-size:.75rem;color:#6b7280;">
                        <i class="bi bi-info-circle me-1"></i>
                        Cartões sem partida vinculada são lançados manualmente aqui. Quando houver jogos cadastrados, os totais serão somados automaticamente.
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

            @endforeach

            <tr class="d-none" id="escal-empty-row">
              <td colspan="10" class="text-center py-4 text-muted">
                <i class="bi bi-search me-2"></i>Nenhum atleta encontrado com os filtros selecionados.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    @endif

  </div>
</main>

<script>
(function () {
  const rows     = document.querySelectorAll('#escal-tbody tr.escal-row');
  const fNome    = document.getElementById('filtroNome');
  const fPos     = document.getElementById('filtroPosicao');
  const fStatus  = document.getElementById('filtroStatus');
  const btnLimp  = document.getElementById('btnLimparFiltros');
  const emptyRow = document.getElementById('escal-empty-row');
  const contador = document.getElementById('filtroContador');

  if (!fNome) return;

  function filtrar() {
    const nome   = fNome.value.toLowerCase().trim();
    const pos    = fPos.value;
    const status = fStatus.value;
    let visible  = 0;

    rows.forEach(tr => {
      const ok = (!nome   || tr.dataset.nome.includes(nome))
              && (!pos    || tr.dataset.posicao === pos)
              && (!status || tr.dataset.status  === status);
      tr.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    emptyRow.classList.toggle('d-none', visible > 0);
    contador.textContent = visible + ' atleta' + (visible !== 1 ? 's' : '') + ' encontrado' + (visible !== 1 ? 's' : '');
  }

  fNome.addEventListener('input',  filtrar);
  fPos .addEventListener('change', filtrar);
  fStatus.addEventListener('change', filtrar);

  btnLimp.addEventListener('click', () => {
    fNome.value   = '';
    fPos.value    = '';
    fStatus.value = '';
    filtrar();
  });

  filtrar();
})();
</script>
@endsection