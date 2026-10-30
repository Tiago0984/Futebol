@extends('layout.admin')

@section('title', $campeonato ? 'Jogos — ' . $campeonato->nome_campeonato : 'Jogos dos campeonatos')

{{--
    Jogos de um campeonato: a aba "Jogos" da central do campeonato (desde 30/10/2026; a convocação e os
    participantes sem jogo foram para a aba "Participantes e convocação"). Um bloco por jogo (próximos e últimos
    realizados) com o cartão do mandante e o do visitante, que abre os jogadores escalados por aquele time naquele
    jogo. "Todos" ($campeonato null, admin.campeonatos.jogos, o "Jogos" do menu) mostra os jogos de todos os
    campeonatos, cada bloco com o nome do campeonato, e o filtro "Campeonato", que abre a central de cada um.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    @if ($campeonato)
    <x-admin.central-do-campeonato :campeonato="$campeonato" aba="jogos" id-caminho="caminhoTimes">
      <x-slot:acoes>
        {{-- A lista de Jogos já filtrada: filtros por situação e time, "Novo Jogo" e o placar rápido --}}
        <a href="{{ route('admin.jogos.index', ['campeonato' => $campeonato->id_campeonato]) }}" class="btn btn-sm btn-outline-primary"
           title="Lista de jogos deste campeonato, com filtros e o Novo Jogo">
          <i class="bi bi-list-ul"></i> Lista e novo jogo
        </a>
      </x-slot:acoes>
    </x-admin.central-do-campeonato>
    @else
    <x-admin.cabecalho titulo="Jogos de todos os campeonatos" :caminho="$caminho" id-caminho="caminhoTimes">
      <x-slot:subtitulo>Cada jogo com os dois times; escolha um campeonato para abrir a central dele</x-slot:subtitulo>
      <x-slot:acoes>
      <div>
        <label for="filtroCampeonato" class="form-label small text-muted mb-1">Campeonato</label>
        <select id="filtroCampeonato" class="form-select form-select-sm"
                onchange="if (this.value) window.location.href = this.value">
          <option value="{{ route('admin.campeonatos.jogos') }}" selected>Todos</option>
          {{-- Ativos primeiro; os inativos num grupo à parte, no fim (21/10/2026) --}}
          @foreach ($campeonatos->groupBy(fn ($c) => $c->estaAtivo() ? 'ativos' : 'inativos')->sortKeys() as $grupo => $opcoes)
          @if ($grupo === 'inativos')<optgroup label="Inativos">@endif
          @foreach ($opcoes as $opcao)
          <option value="{{ route('admin.campeonatos.times', $opcao->id_campeonato) }}">
            {{ $opcao->nome_campeonato }}{{ $grupo === 'inativos' ? ' (inativo)' : '' }}
          </option>
          @endforeach
          @if ($grupo === 'inativos')</optgroup>@endif
          @endforeach
        </select>
      </div>
      </x-slot:acoes>
    </x-admin.cabecalho>
    @endif

    @include('admin.jogos._mensagens')

    @include('admin.partials.blocos-de-jogos', ['rotulo' => 'jogo'])

  </div>
</main>

{{-- Editar jogo pelas ações de cada bloco: salva e volta para esta tela --}}
@include('admin.jogos.modals.edit', [...$formularioJogo, 'voltarParaPagina' => true])
@include('admin.partials.sugestoes')
@include('admin.jogos._script_form')
@endsection
