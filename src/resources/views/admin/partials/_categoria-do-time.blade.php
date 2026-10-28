{{-- Categoria do time no cartão (admin.partials.cartao-time, que calcula $outraCategoria). Com a categoria do jogo
     ou do campeonato, só ela; se o cadastro do time não a tem marcada (jogo de antes de 27/10/2026), as do cadastro
     embaixo, menores. Sem ela (amistoso), as categorias do cadastro do time --}}
@if($categoriaDoCampeonato)
  <span @class(['escal-team-cat', 'escal-team-cat-joga js-categoria-do-time' => $outraCategoria])>{{ $categoriaDoCampeonato->rotulo }}</span>
  @if($outraCategoria)
    <span class="escal-team-cat">Cadastro do time: {{ $time->categorias->pluck('rotulo')->implode(', ') }}</span>
  @endif
@elseif($time->categorias->isNotEmpty())
  <span class="escal-team-cat">{{ $time->categorias->pluck('rotulo')->implode(', ') }}</span>
@endif
