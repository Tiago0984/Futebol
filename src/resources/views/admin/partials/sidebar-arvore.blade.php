{{--
  Renderiza um menu em árvore de forma recursiva.
  Cada item: titulo, icone (opcional), rota (opcional), ativo (padrão de rota, opcional), filhos (opcional).
--}}
@php
  $ramoAtivo = function (array $item) use (&$ramoAtivo) {
    if (!empty($item['ativo']) && request()->routeIs($item['ativo'])) {
      return true;
    }
    foreach ($item['filhos'] ?? [] as $filho) {
      if ($ramoAtivo($filho)) {
        return true;
      }
    }
    return false;
  };
  $nivel = $nivel ?? 0;
@endphp

@foreach ($itens as $item)
  @php
    $temFilhos = !empty($item['filhos']);
    $aberto = $temFilhos && $ramoAtivo($item);
    $ativo = $nivel === 0 ? $aberto : (!empty($item['ativo']) && request()->routeIs($item['ativo']));
    $href = !empty($item['rota']) ? route($item['rota']) : 'javascript:void(0)';
  @endphp
  <li class="nav-item {{ $aberto ? 'menu-open' : '' }}">
    <a href="{{ $href }}" class="nav-link {{ $ativo ? 'active' : '' }}">
      <i class="nav-icon bi {{ $item['icone'] ?? 'bi-circle' }}"></i>
      <p>
        {{ $item['titulo'] }}
        @if ($temFilhos)
          <i class="nav-arrow bi bi-chevron-right"></i>
        @endif
      </p>
    </a>
    @if ($temFilhos)
      <ul class="nav nav-treeview">
        @include('admin.partials.sidebar-arvore', ['itens' => $item['filhos'], 'nivel' => $nivel + 1])
      </ul>
    @endif
  </li>
@endforeach
