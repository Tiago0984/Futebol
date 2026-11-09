@extends('layout.admin')

@section('title', 'Individuais')

{{--
    Individuais (Eventos › Individuais, 09/11/2026; IndividuaisController): exames, avaliações, peneiras e reuniões
    com os atletas escolhidos um a um. Um cartão por atividade (data, tipo, horário, local, responsável, o que o
    atleta vai fazer, quem vai e quantos leram o aviso), em duas abas: Próximos (todos) e Já realizados (por
    página). Filtros na URL: tipo e busca por título ou atleta. Os ocultos não aparecem (o "Mostrar ocultos" saiu,
    09/11/2026: o oculto fica no filtro "Situação" do Calendário). O cartão abre a tela do evento (atletas,
    editar); cancelar e ocultar voltam para esta tela.
--}}
@section('content')
@php
    $classeSituacao = [
        'ATIVO' => 'ativo', 'ALTERADO' => 'pendente', 'CONCLUIDO' => 'concluido',
        'CANCELADO' => 'inativo', 'INATIVO' => 'rejeitado',
    ];
    $comFiltro = $filtros['tipo'] !== '' || $filtros['busca'] !== '';
    $urlDaAba = fn (string $aba) => route('admin.individuais.index', [...array_filter($filtros), 'aba' => $aba === 'realizados' ? 'realizados' : null]);
@endphp
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho titulo="Individuais" :caminho="$caminho" id-caminho="caminhoIndividuais">
      <x-slot:subtitulo>Exames, avaliações e reuniões com os atletas escolhidos um a um. Só os escolhidos veem na agenda do app.</x-slot:subtitulo>
      <x-slot:acoes>
        <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalEventoIndividual">
          <i class="bi bi-person-plus"></i> Novo evento individual
        </button>
      </x-slot:acoes>
    </x-admin.cabecalho>

    @if(session('sucesso'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
      <strong>Sucesso!</strong> {{ session('sucesso') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('erro'))
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
      <strong>Erro!</strong> {{ session('erro') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
      <strong>Ops! Verifique os campos do formulário:</strong>
      <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @include('admin.partials.avisos-afastamento')
    @include('admin.calendario._conflitos')

    {{-- Filtros (na URL, para o link e o "Voltar" manterem): tipo, busca e ocultos --}}
    <form method="GET" action="{{ route('admin.individuais.index') }}" class="filter-panel mb-3" id="filtrosIndividuais">
      @if($aba === 'realizados')<input type="hidden" name="aba" value="realizados">@endif
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="filter-label" for="filtroBusca">Buscar</label>
          <input type="search" name="busca" id="filtroBusca" class="form-control form-control-sm" value="{{ $filtros['busca'] }}"
                 placeholder="Título ou nome do atleta">
        </div>
        <div class="col-md-3">
          <label class="filter-label" for="filtroTipoIndividual">Tipo</label>
          <select name="tipo" id="filtroTipoIndividual" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach(\App\Http\Controllers\Admin\IndividuaisController::FILTROS_DE_TIPO as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($filtros['tipo'] === $valor)>{{ $rotulo }}</option>
            @endforeach
            @if($tiposCadastrados->isNotEmpty())
            <optgroup label="Tipos cadastrados">
              @foreach($tiposCadastrados as $cadastrado)
              <option value="{{ $cadastrado->valorNoSelect() }}" @selected($filtros['tipo'] === $cadastrado->valorNoSelect())>
                {{ $cadastrado->nome_tipo_evento_individual }}{{ $cadastrado->status_tipo_evento_individual === 'ATIVO' ? '' : ' (inativo)' }}
              </option>
              @endforeach
            </optgroup>
            @endif
          </select>
        </div>
        <div class="col-md-auto d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Buscar</button>
          @if($comFiltro)
          <a href="{{ route('admin.individuais.index', ['aba' => $aba === 'realizados' ? 'realizados' : null]) }}" class="btn-filter-clear text-decoration-none">
            <i class="bi bi-x-circle"></i> Limpar
          </a>
          @endif
        </div>
      </div>
    </form>

    {{-- Abas com o seu endereço (os já realizados vêm por página) --}}
    <nav class="admin-abas" aria-label="Próximos ou já realizados" id="abasIndividuais">
      <a href="{{ $urlDaAba('proximos') }}" class="admin-abas__item {{ $aba === 'proximos' ? 'ativa' : '' }}"
         @if($aba === 'proximos') aria-current="page" @endif>
        <i class="bi bi-calendar-event"></i> Próximos <span class="badge bg-secondary">{{ $proximos->count() }}</span>
      </a>
      <a href="{{ $urlDaAba('realizados') }}" class="admin-abas__item {{ $aba === 'realizados' ? 'ativa' : '' }}"
         @if($aba === 'realizados') aria-current="page" @endif>
        <i class="bi bi-check2-circle"></i> Já realizados <span class="badge bg-secondary">{{ $realizados->total() }}</span>
      </a>
    </nav>

    <div class="individuais-lista" id="listaIndividuais">
      @forelse($eventos as $ev)
      @php
        $inscritos = $ev->inscricoes->sortBy(fn ($i) => $i->atleta->nome_atleta, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $nomes = $inscritos->map(fn ($i) => $i->atleta->nome_atleta);
        $limite = \App\Http\Controllers\Admin\IndividuaisController::ATLETAS_NO_CARTAO;
        $leitura = $leituras[$ev->id_evento_calendario] ?? null;
        $data = $ev->data_evento_calendario->copy()->locale('pt_BR');
      @endphp
      <article class="individual-cartao {{ $ev->estaCancelado() ? 'cancelado' : '' }} {{ $ev->estaOculto() ? 'oculto' : '' }}"
               data-evento="{{ $ev->id_evento_calendario }}">
        <div class="individual-data" aria-hidden="true">
          <span class="individual-data__dia">{{ $data->format('d') }}</span>
          <span class="individual-data__mes">{{ \Illuminate\Support\Str::upper(rtrim($data->isoFormat('MMM'), '.')) }}</span>
          <span class="individual-data__semana">{{ rtrim($data->isoFormat('ddd'), '.') }}</span>
        </div>

        <div class="individual-corpo">
          <div class="individual-topo">
            <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="individual-titulo">
              {{ $ev->titulo_evento_calendario }}
            </a>
            @if($ev->tipoIndividual)
              {{-- Tipo cadastrado: o nome dele (que é também o subtipo) --}}
              <span class="badge-cat">{{ $ev->tipoIndividual->nome_tipo_evento_individual }}</span>
            @elseif($ev->ehPeneira())
              <span class="badge bg-info text-dark"><i class="bi bi-binoculars"></i> Peneira</span>
            @else
              <span class="badge-cat">{{ $ev->tipo_label }}</span>
              @if($ev->subtipo_evento_calendario)<span class="text-muted small">{{ $ev->subtipo_evento_calendario }}</span>@endif
            @endif
            @if($ev->situacao !== 'ATIVO')
              <span class="badge-status {{ $classeSituacao[$ev->situacao] }}">{{ $ev->situacao_label }}</span>
            @endif
          </div>

          <div class="individual-meta">
            <span><i class="bi bi-calendar3"></i> {{ \Illuminate\Support\Str::ucfirst($data->isoFormat('dddd, DD/MM/YYYY')) }}</span>
            <span><i class="bi bi-clock"></i> {{ $ev->horario_texto }}</span>
            <span><i class="bi bi-geo-alt"></i> {{ $ev->local_evento_calendario ?: 'Local a definir' }}</span>
            @if($ev->nomeDoResponsavel())
            <span><i class="bi bi-person-badge"></i> {{ $ev->nomeDoResponsavel() }}</span>
            @endif
          </div>

          @if($ev->descricao_evento_calendario)
          <p class="individual-descricao" title="{{ $ev->descricao_evento_calendario }}">
            {{ \Illuminate\Support\Str::limit($ev->descricao_evento_calendario, 180) }}
          </p>
          @endif

          <div class="individual-atletas">
            <span class="individual-atletas__total">
              <i class="bi bi-people"></i> {{ $nomes->count() }} {{ $nomes->count() === 1 ? 'atleta' : 'atletas' }}
            </span>
            @foreach($nomes->take($limite) as $nome)
            <span class="individual-atleta">{{ $nome }}</span>
            @endforeach
            @if($nomes->count() > $limite)
            <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="individual-atleta mais"
               title="{{ $nomes->slice($limite)->implode(', ') }}">+{{ $nomes->count() - $limite }}</a>
            @endif
            @if($leitura && $leitura['avisados'] > 0)
            <span class="individual-leitura" title="Atletas que abriram o aviso da inscrição no app">
              <i class="bi bi-envelope-open"></i> {{ $leitura['leram'] }} de {{ $leitura['avisados'] }} leram o aviso
            </span>
            @endif
          </div>
        </div>

        <div class="individual-acoes">
          <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="btn-tbl view" title="Abrir: atletas, inscrever e remover">
            <i class="bi bi-people"></i>
          </a>
          <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}#editar" class="btn-tbl edit" title="Editar">
            <i class="bi bi-pencil"></i>
          </a>
          @unless($ev->estaOculto())
          <form action="{{ route('admin.calendario.eventos.cancelar', $ev->id_evento_calendario) }}" method="POST" class="d-inline"
                onsubmit="return confirm(@js($ev->estaCancelado() ? 'Reativar esta atividade? Os atletas são avisados.' : 'Cancelar esta atividade? Os atletas são avisados e ela fica na agenda com o selo "Cancelado".'))">
            @csrf @method('PATCH')
            @if($ev->estaCancelado())
            <button type="submit" class="btn-tbl activate" title="Reativar"><i class="bi bi-check-circle"></i></button>
            @else
            <button type="submit" class="btn-tbl deactivate" title="Cancelar"><i class="bi bi-x-circle"></i></button>
            @endif
          </form>
          @endunless
          <form action="{{ route('admin.calendario.eventos.ocultar', $ev->id_evento_calendario) }}" method="POST" class="d-inline"
                onsubmit="return confirm(@js($ev->estaOculto() ? 'Mostrar esta atividade de novo?' : 'Ocultar esta atividade? Ela sai da agenda dos atletas, mas o registro fica.'))">
            @csrf @method('PATCH')
            @if($ev->estaOculto())
            <button type="submit" class="btn-tbl activate" title="Mostrar"><i class="bi bi-eye"></i></button>
            @else
            <button type="submit" class="btn-tbl deactivate" title="Ocultar"><i class="bi bi-eye-slash"></i></button>
            @endif
          </form>
        </div>
      </article>
      @empty
      <div class="individuais-vazio">
        <i class="bi bi-person-check"></i>
        @if($comFiltro)
          <p>Nenhuma atividade com estes filtros.</p>
        @elseif($aba === 'proximos')
          <p>Nenhuma atividade individual marcada.</p>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEventoIndividual">
            <i class="bi bi-person-plus"></i> Novo evento individual
          </button>
        @else
          <p>Nenhuma atividade individual realizada ainda.</p>
        @endif
      </div>
      @endforelse
    </div>

    @if($aba === 'realizados' && $realizados->hasPages())
    <div class="mt-3">{{ $realizados->links('pagination::bootstrap-5') }}</div>
    @endif

  </div>
</main>

@include('admin.calendario.modals.evento-individual')
@include('admin.partials.sugestoes')
@endsection
