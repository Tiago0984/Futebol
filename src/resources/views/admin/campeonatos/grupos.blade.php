@extends('layout.admin')

@section('title', 'Grupos (chaves) — ' . $campeonato->nome_campeonato)

{{--
    Grupos e classificação de um campeonato (Fase H, Etapa H4; GruposController), um bloco por categoria:
    os grupos (criar, renomear, remover o vazio), o grupo de cada participante (um select por time) e a
    classificação de cada grupo (Campeonato::classificacoes: só a fase de grupos; o grupo lista todos os times
    dele, os sem jogo com zeros). Categoria sem grupo tem uma tabela só, com todos os participantes. "Grupo
    (chave)": o dono da AACJ chama o grupo de chave. "Classificam por grupo (chave)" destaca os N primeiros.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small" id="caminhoGrupos">
        @foreach ($caminho as [$rotulo, $url])
          @if ($url)
          <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
          @else
          <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
          @endif
        @endforeach
      </ol>
    </nav>

    <div class="admin-page-header d-flex align-items-center gap-3">
      <a href="{{ route('admin.campeonatos.times', $campeonato->id_campeonato) }}" class="btn btn-sm btn-outline-secondary"
         title="Voltar" aria-label="Voltar">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h1 class="page-title mb-0">Grupos (chaves) e classificação · {{ $campeonato->nome_campeonato }}</h1>
        <p class="page-subtitle mb-0">Grupos (chaves) são opcionais. A classificação conta só os jogos da fase de grupos, com placar, publicados e ativos.</p>
      </div>
    </div>

    @include('admin.jogos._mensagens')

    @forelse ($campeonato->categorias as $categoria)
    @php
      $doCategoria   = $grupos[$categoria->id_categoria] ?? collect();
      $timesDaCat    = $participantes[$categoria->id_categoria] ?? collect();
      $classificacao = $classificacoes[$categoria->id_categoria]['tabelas'] ?? [];
    @endphp
    <div class="card shadow-sm mb-4" id="categoria-{{ $categoria->id_categoria }}">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <h2 class="h5 mb-0">{{ $categoria->rotulo }}</h2>
          {{-- Fase I, Etapa I2: todos contra todos (por grupo, se houver), com prévia antes de gravar --}}
          <a href="{{ route('admin.campeonatos.gerar-jogos', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
             class="btn btn-sm btn-outline-primary js-gerar-jogos">
            <i class="bi bi-lightning-charge"></i> Gerar jogos (todos contra todos)
          </a>
        </div>

        <div class="row g-4">
          {{-- Grupos da categoria --}}
          <div class="col-lg-4">
            <h3 class="h6 text-uppercase text-muted fw-bold">Grupos (chaves)</h3>
            @forelse ($doCategoria as $grupo)
            <div class="d-flex align-items-center gap-1 mb-2" data-id-grupo="{{ $grupo->id_campeonato_grupo }}">
              <form action="{{ route('admin.campeonatos.grupos.renomear', [$campeonato->id_campeonato, $grupo->id_campeonato_grupo]) }}"
                    method="POST" class="d-flex gap-1 flex-grow-1">
                @csrf @method('PATCH')
                <input type="text" name="nome_campeonato_grupo" value="{{ $grupo->nome_campeonato_grupo }}" required
                       maxlength="{{ \App\Models\CampeonatoGrupo::NOME_MAX }}" class="form-control form-control-sm"
                       aria-label="Nome do {{ $grupo->nome_campeonato_grupo }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Renomear" aria-label="Renomear">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <span class="badge bg-secondary" title="Times no grupo">{{ $timesPorGrupo[$grupo->id_campeonato_grupo] ?? 0 }}</span>
              <form action="{{ route('admin.campeonatos.grupos.remover', [$campeonato->id_campeonato, $grupo->id_campeonato_grupo]) }}"
                    method="POST" onsubmit="return confirm(@js("Remover o {$grupo->nome_campeonato_grupo}?"))">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover" aria-label="Remover {{ $grupo->nome_campeonato_grupo }}">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
            @empty
            <p class="text-muted small mb-2">Sem grupos (chaves): a categoria tem uma tabela só.</p>
            @endforelse

            <form action="{{ route('admin.campeonatos.grupos.criar', $campeonato->id_campeonato) }}" method="POST" class="d-flex gap-1 mt-2">
              @csrf
              <input type="hidden" name="id_categoria" value="{{ $categoria->id_categoria }}">
              <input type="text" name="nome_campeonato_grupo" placeholder="Novo grupo (ex.: Grupo 01 ou Chave A)" required
                     maxlength="{{ \App\Models\CampeonatoGrupo::NOME_MAX }}" class="form-control form-control-sm"
                     aria-label="Nome do novo grupo do {{ $categoria->rotulo }}">
              <button type="submit" class="btn btn-sm btn-success text-nowrap"><i class="bi bi-plus-lg"></i> Criar</button>
            </form>

            {{-- Quantos passam de cada grupo (chave): destacados na classificação --}}
            <form action="{{ route('admin.campeonatos.grupos.classificados', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
                  method="POST" class="mt-3">
              @csrf @method('PUT')
              <label for="classificados_{{ $categoria->id_categoria }}" class="form-label small mb-1">Classificam por grupo (chave)</label>
              <div class="d-flex gap-1">
                <input type="number" min="1" max="{{ \App\Models\Campeonato::CLASSIFICADOS_MAX }}" name="classificados"
                       id="classificados_{{ $categoria->id_categoria }}" value="{{ $categoria->pivot->classificados_campeonato_categoria }}"
                       class="form-control form-control-sm" style="max-width: 90px;" placeholder="—">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Salvar</button>
              </div>
              <span class="form-text">Ex.: 1 (só o primeiro) ou 2 (primeiro e segundo). Vazio: não destaca ninguém.</span>
            </form>
          </div>

          {{-- Grupo de cada participante --}}
          <div class="col-lg-8">
            <h3 class="h6 text-uppercase text-muted fw-bold">Times</h3>
            @if ($timesDaCat->isEmpty())
              <p class="text-muted small">Nenhum time participa desta categoria (inclua pelos participantes do campeonato).</p>
            @elseif ($doCategoria->isEmpty())
              <p class="text-muted small mb-0">{{ $timesDaCat->pluck('nome_time')->implode(', ') }}</p>
              <p class="text-muted small">Crie um grupo (chave) para separar os times.</p>
            @else
            <form action="{{ route('admin.campeonatos.grupos.times', [$campeonato->id_campeonato, $categoria->id_categoria]) }}" method="POST">
              @csrf @method('PUT')
              <div class="row g-2">
                @foreach ($timesDaCat as $time)
                {{-- Cada time numa caixa com o nome e o select juntos (em duas colunas, o select de um time ficava
                     encostado no nome do outro) --}}
                <div class="col-md-6" data-id-time="{{ $time->id_time }}">
                  <div class="border rounded px-2 py-1 h-100 d-flex align-items-center gap-2 js-time-do-grupo">
                  <label for="grupo_{{ $categoria->id_categoria }}_{{ $time->id_time }}" class="flex-grow-1 small fw-semibold mb-0">
                    {{ $time->nome_time }}
                    <span @class(['badge-status ms-1', 'ativo' => $time->tipo_time === 'INTERNO', 'inativo' => $time->tipo_time !== 'INTERNO'])
                          style="font-size:0.6rem;">{{ $time->tipo_time === 'INTERNO' ? 'INT' : 'EXT' }}</span>
                  </label>
                  <select name="grupos[{{ $time->id_time }}]" id="grupo_{{ $categoria->id_categoria }}_{{ $time->id_time }}"
                          class="form-select form-select-sm" style="max-width: 170px;">
                    <option value="">Sem grupo</option>
                    @foreach ($doCategoria as $grupo)
                    <option value="{{ $grupo->id_campeonato_grupo }}" @selected((int) $time->id_campeonato_grupo === (int) $grupo->id_campeonato_grupo)>
                      {{ $grupo->nome_campeonato_grupo }}
                    </option>
                    @endforeach
                  </select>
                  </div>
                </div>
                @endforeach
              </div>
              <button type="submit" class="btn btn-sm btn-primary mt-3"><i class="bi bi-check2"></i> Salvar grupos</button>
            </form>
            @endif
          </div>
        </div>

        {{-- Classificação de cada grupo --}}
        <h3 class="h6 text-uppercase text-muted fw-bold mt-4">Classificação</h3>
        <div class="row g-3">
          @foreach ($classificacao as $tabela)
          <div class="col-xl-6 js-tabela-classificacao">
            @if ($tabela['grupo'])
            <div class="fw-semibold mb-1">{{ $tabela['grupo'] }}</div>
            @endif
            @if (empty($tabela['tabela']))
              <p class="text-muted small">Nenhum jogo da fase de grupos com placar ainda.</p>
            @else
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>#</th><th>Time</th>
                    <th class="text-center" title="Pontos">PTS</th><th class="text-center" title="Jogos">PJ</th>
                    <th class="text-center" title="Vitórias">V</th><th class="text-center" title="Empates">E</th>
                    <th class="text-center" title="Derrotas">D</th><th class="text-center" title="Gols marcados">GM</th>
                    <th class="text-center" title="Gols sofridos">GC</th><th class="text-center" title="Saldo de gols">SG</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($tabela['tabela'] as $pos => $linha)
                  @php $classificado = $tabela['classificados'] && $pos < $tabela['classificados']; @endphp
                  <tr @class(['table-success js-classificado' => $classificado])>
                    <td>{{ $pos + 1 }}</td>
                    <td class="fw-semibold">
                      {{ $linha['nome'] }}@if ($linha['sorteio'])<span class="text-warning" title="Empatado em todos os critérios: no fim, decidir por sorteio">*</span>@endif
                      @if ($classificado)<span class="badge bg-success ms-1">Classificado</span>@endif
                    </td>
                    <td class="text-center fw-bold">{{ $linha['pontos'] }}</td>
                    <td class="text-center">{{ $linha['pj'] }}</td>
                    <td class="text-center">{{ $linha['v'] }}</td>
                    <td class="text-center">{{ $linha['e'] }}</td>
                    <td class="text-center">{{ $linha['d'] }}</td>
                    <td class="text-center">{{ $linha['gm'] }}</td>
                    <td class="text-center">{{ $linha['gc'] }}</td>
                    <td class="text-center">{{ $linha['gm'] - $linha['gc'] }}</td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @include('partials.legenda-desempate', ['linhas' => $tabela['tabela'], 'classe' => 'small text-muted mt-1 mb-0'])
            @endif
          </div>
          @endforeach
        </div>
      </div>
    </div>
    @empty
    <p class="text-muted">O campeonato não tem categorias.</p>
    @endforelse

  </div>
</main>
@endsection
