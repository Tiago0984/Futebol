{{--
    Seção padrão das telas do painel (30/10/2026): um card com o título (e o ícone) à esquerda e as ações da
    seção à direita, no mesmo visual em todas as telas e nos dois temas. Uso:

    <x-admin.secao titulo="Convocação" icone="person-lines-fill" id="blocoConvocacao">
        <x-slot:acoes>...</x-slot:acoes>
        ...conteúdo...
    </x-admin.secao>

    - ajuda: uma linha curta embaixo do título, dizendo para que serve a seção.
    - Os outros atributos (id, data-*) vão no card.
--}}
@props(['titulo', 'icone' => null, 'ajuda' => null])

<section {{ $attributes->class('card admin-secao mb-4') }}>
  <header class="admin-secao__topo">
    <div>
      <h2 class="admin-secao__titulo">@if ($icone)<i class="bi bi-{{ $icone }}"></i> @endif{{ $titulo }}</h2>
      @if ($ajuda)<p class="admin-secao__ajuda">{{ $ajuda }}</p>@endif
    </div>
    @isset($acoes)
    <div class="admin-secao__acoes">{{ $acoes }}</div>
    @endisset
  </header>
  <div class="admin-secao__corpo">
    {{ $slot }}
  </div>
</section>
