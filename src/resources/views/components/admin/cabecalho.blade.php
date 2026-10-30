{{--
    Cabeçalho padrão das telas do painel (30/10/2026): linha de caminho, seta de voltar, logo, título,
    subtítulo e as ações da tela, sempre no mesmo lugar. Uso:

    <x-admin.cabecalho titulo="Grupos e classificação" :caminho="$caminho" :voltar="route(...)">
        <x-slot:subtitulo>Texto curto do que a tela faz.</x-slot:subtitulo>
        <x-slot:acoes><a class="btn btn-sm btn-outline-primary" ...>Ação</a></x-slot:acoes>
    </x-admin.cabecalho>

    - caminho: [[rótulo, url|null], ...] (a última parte, sem url, é esta tela); id-caminho e id-voltar são os
      ids que os testes e o JS já usavam.
    - logo: endereço da imagem (time ou campeonato); logo-padrao: a imagem se ela não abrir.
    - O subtítulo aceita atributos (<x-slot:subtitulo id="resumoConvocados">).
--}}
@props([
    'titulo',
    'caminho'    => null,
    'idCaminho'  => null,
    'voltar'     => null,
    'idVoltar'   => null,
    'logo'       => null,
    'logoAlt'    => '',
    'logoPadrao' => null,
])

@if ($caminho)
<nav aria-label="Caminho" class="admin-caminho">
  <ol class="breadcrumb mb-0 small" @if ($idCaminho) id="{{ $idCaminho }}" @endif>
    @foreach ($caminho as [$parte, $url])
      @if ($url)
      <li class="breadcrumb-item"><a href="{{ $url }}">{{ $parte }}</a></li>
      @else
      <li class="breadcrumb-item active" aria-current="page">{{ $parte }}</li>
      @endif
    @endforeach
  </ol>
</nav>
@endif

<div {{ $attributes->class('admin-page-header') }}>
  <div class="admin-page-header__titulo">
    @if ($voltar)
    <a href="{{ $voltar }}" class="btn btn-sm btn-outline-secondary" title="Voltar" aria-label="Voltar" @if ($idVoltar) id="{{ $idVoltar }}" @endif>
      <i class="bi bi-arrow-left"></i>
    </a>
    @endif
    @if ($logo)
    <img src="{{ $logo }}" alt="{{ $logoAlt }}" class="admin-page-header__logo"
         @if ($logoPadrao) onerror="this.onerror=null;this.src='{{ $logoPadrao }}'" @endif>
    @endif
    <div class="admin-page-header__textos">
      <h1 class="page-title">{{ $titulo }}</h1>
      @isset($subtitulo)
      <p {{ $subtitulo->attributes->class('page-subtitle') }}>{{ $subtitulo }}</p>
      @endisset
    </div>
  </div>
  @isset($acoes)
  <div class="admin-page-header__acoes">{{ $acoes }}</div>
  @endisset
</div>
