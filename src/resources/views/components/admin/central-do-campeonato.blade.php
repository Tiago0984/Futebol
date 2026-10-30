{{--
    Topo da central do campeonato (Fase 3 do padrão das telas, 30/10/2026): o cabeçalho (caminho, logo, nome, tipo,
    período e categorias; "Editar campeonato" e "Ver no site") e as abas (App\Support\CentralDoCampeonato::ABAS),
    cada aba uma tela com o seu endereço. Uso:

    <x-admin.central-do-campeonato :campeonato="$campeonato" aba="grupos" id-caminho="caminhoGrupos">
        <x-slot:acoes>(botões a mais desta aba, antes do "Editar campeonato")</x-slot:acoes>
    </x-admin.central-do-campeonato>
--}}
@props([
    'campeonato',
    'aba',
    'idCaminho' => null,
])

@php
  $inicio  = $campeonato->data_inicio_campeonato?->format('d/m/Y');
  $fim     = $campeonato->data_fim_campeonato?->format('d/m/Y');
  $periodo = $inicio && $fim ? "{$inicio} a {$fim}" : ($inicio ?? $fim);
  $resumo  = collect([$campeonato->tipo_label ?: null, $periodo, $campeonato->rotulo_das_categorias ?: null])->filter()->implode(' · ');
@endphp

<x-admin.cabecalho :titulo="$campeonato->nome_campeonato" :caminho="\App\Support\CentralDoCampeonato::caminho($campeonato, $aba)"
                   :id-caminho="$idCaminho" :logo="$campeonato->logo_url" :logo-alt="$campeonato->nome_campeonato">
  <x-slot:subtitulo>
    {{ $resumo }}
    @unless ($campeonato->estaAtivo())
    <span class="badge-status inativo ms-1" title="Fora do site e do app até reativar (lista de Campeonatos)">Inativo</span>
    @endunless
  </x-slot:subtitulo>
  <x-slot:acoes>
    {{ $acoes ?? '' }}
    <a href="{{ route('admin.campeonatos.edit', [$campeonato->id_campeonato, 'voltar' => 'central']) }}"
       class="btn btn-sm btn-outline-secondary" id="btnEditarCampeonato">
      <i class="bi bi-pencil"></i> Editar campeonato
    </a>
    @if ($campeonato->estaAtivo())
    <a href="{{ route('campeonato.show', $campeonato->id_campeonato) }}" target="_blank" rel="noopener"
       class="btn btn-sm btn-outline-secondary" title="Abre a página do campeonato no site, em outra aba">
      <i class="bi bi-box-arrow-up-right"></i> Ver no site
    </a>
    @endif
  </x-slot:acoes>
</x-admin.cabecalho>

<nav class="admin-abas" aria-label="Partes do campeonato" id="abasDoCampeonato">
  @foreach (\App\Support\CentralDoCampeonato::ABAS as $chave => [$rotulo, $rota, $icone])
  @if ($chave === $aba)
  <a href="{{ route($rota, $campeonato->id_campeonato) }}" class="admin-abas__item ativa" aria-current="page" data-aba="{{ $chave }}">
  @else
  <a href="{{ route($rota, $campeonato->id_campeonato) }}" class="admin-abas__item" data-aba="{{ $chave }}">
  @endif
    <i class="bi bi-{{ $icone }}"></i> {{ $rotulo }}
  </a>
  @endforeach
</nav>
