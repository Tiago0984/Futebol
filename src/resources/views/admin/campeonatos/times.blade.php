@extends('layout.admin')

@section('title', $campeonato ? 'Jogos — ' . $campeonato->nome_campeonato : 'Jogos dos campeonatos')

{{--
    Jogos do campeonato (o "Jogos" do campeonato no menu), só para ver, como nos Times dos amistosos: um bloco
    por jogo (próximos e últimos realizados) com o cartão do mandante e o do visitante, que abre os jogadores
    escalados por aquele time naquele jogo; no fim, os participantes que não aparecem em nenhum desses jogos,
    que abrem os convocados do time no campeonato. O filtro "Campeonato" troca de campeonato (cada opção é a URL da tela dele);
    "Todos" ($campeonato null, admin.campeonatos.jogos) mostra os jogos de todos os campeonatos, cada bloco com o
    nome do campeonato, sem a lista de participantes.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    {{-- Linha de caminho: cada parte leva à lista dela; a última é esta tela --}}
    <x-admin.cabecalho :titulo="$campeonato ? 'Jogos · ' . $campeonato->nome_campeonato : 'Jogos de todos os campeonatos'"
                       :caminho="$caminho" id-caminho="caminhoTimes"
                       :logo="$campeonato?->logo_url" :logo-alt="$campeonato?->nome_campeonato ?? ''">
      <x-slot:subtitulo>Cada jogo com os dois times; clique num time interno para ver os jogadores escalados</x-slot:subtitulo>
      <x-slot:acoes>
      @if ($campeonato)
      {{-- Grupos e classificação por categoria (Fase H, Etapa H4) --}}
      <a href="{{ route('admin.campeonatos.grupos', $campeonato->id_campeonato) }}" class="btn btn-sm btn-outline-primary" id="btnGrupos">
        <i class="bi bi-diagram-3"></i> Grupos (chaves) e classificação
      </a>
      @endif
      <div>
        <label for="filtroCampeonato" class="form-label small text-muted mb-1">Campeonato</label>
        <select id="filtroCampeonato" class="form-select form-select-sm"
                onchange="if (this.value) window.location.href = this.value">
          <option value="{{ route('admin.campeonatos.jogos') }}" @selected(! $campeonato)>Todos</option>
          {{-- Ativos primeiro; os inativos num grupo à parte, no fim (21/10/2026) --}}
          @foreach ($campeonatos->groupBy(fn ($c) => $c->estaAtivo() ? 'ativos' : 'inativos')->sortKeys() as $grupo => $opcoes)
          @if ($grupo === 'inativos')<optgroup label="Inativos">@endif
          @foreach ($opcoes as $opcao)
          <option value="{{ route('admin.campeonatos.times', $opcao->id_campeonato) }}" @selected($opcao->id_campeonato === $campeonato?->id_campeonato)>
            {{ $opcao->nome_campeonato }}{{ $grupo === 'inativos' ? ' (inativo)' : '' }}
          </option>
          @endforeach
          @if ($grupo === 'inativos')</optgroup>@endif
          @endforeach
        </select>
      </div>
      </x-slot:acoes>
    </x-admin.cabecalho>

    @include('admin.jogos._mensagens')

    {{-- Convocação (Fase G): uma linha por time interno e categoria em que ele joga (a AACJ no Sub-13 e no Sub-15:
         duas linhas, desde 27/10/2026), com os convocados ativos e o botão da tela --}}
    @if ($convocacao->isNotEmpty())
    <x-admin.secao titulo="Convocação" icone="person-lines-fill" id="blocoConvocacao"
                   ajuda="Os atletas de cada time da AACJ em cada categoria, com a camisa. Os jogos inscrevem estes convocados.">
        @foreach ($convocacao as $participacao)
        <div @class(['d-flex align-items-center gap-2 py-1', 'border-top' => ! $loop->first]) data-id-time="{{ $participacao->time->id_time }}"
             data-id-categoria="{{ $participacao->categoria->id_categoria }}">
          <span class="fw-semibold">{{ $participacao->time->nome_time }}</span>
          <span class="text-muted small">· {{ $participacao->categoria->rotulo }}</span>
          <span @class(['badge', 'bg-secondary' => $participacao->total, 'bg-warning text-dark' => ! $participacao->total])>
            {{ $participacao->total ? $participacao->total . ' convocado(s)' : 'Sem convocados' }}
          </span>
          <a href="{{ route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $participacao->time->id_time, 'categoria' => $participacao->categoria->id_categoria]) }}"
             class="btn btn-sm btn-outline-primary ms-auto">
            <i class="bi bi-person-lines-fill"></i> Convocados
          </a>
        </div>
        @endforeach
    </x-admin.secao>
    @endif

    @include('admin.partials.blocos-de-jogos', ['rotulo' => 'jogo'])

    {{-- Participantes que não aparecem em nenhum jogo acima (ainda sem jogo marcado, ou só em jogos mais
         antigos), um cartão por time e categoria (28/10/2026): o cartão da AACJ abre os convocados daquela categoria --}}
    @if ($semJogo->isNotEmpty())
    <x-admin.secao titulo="Participantes sem jogo na lista" icone="people" class="mt-4"
                   ajuda="Times do campeonato que ainda não aparecem nos jogos acima. O cartão da AACJ abre os convocados.">
    <div class="row g-3" id="participantesSemJogo">
      @foreach ($semJogo as $participacao)
      <div class="col-md-4 col-lg-3" data-id-time="{{ $participacao->time->id_time }}" data-id-categoria="{{ $participacao->categoria->id_categoria }}">
        @include('admin.partials.cartao-time', [
          'time' => $participacao->time,
          'link' => route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $participacao->time->id_time, 'categoria' => $participacao->categoria->id_categoria]),
          'contagem' => $participacao->total . ' convocado(s)',
          'categoriaDoCampeonato' => $participacao->categoria,
        ])
      </div>
      @endforeach
    </div>
    </x-admin.secao>
    @endif

  </div>
</main>

{{-- Editar jogo pelas ações de cada bloco: salva e volta para esta tela --}}
@include('admin.jogos.modals.edit', [...$formularioJogo, 'voltarParaPagina' => true])
@include('admin.partials.sugestoes')
@include('admin.jogos._script_form')
@endsection
