{{-- Categoria do time no cartão (admin.partials.cartao-time, que calcula $outraCategoria) --}}
@if($time->categoria)
  @if($outraCategoria)
    <span class="escal-team-cat js-categoria-do-time"
          title="Categoria do cadastro do time. O campeonato é {{ $categoriaDoCampeonato->rotulo }}.">
      Time: {{ $time->categoria->rotulo }} <i class="bi bi-info-circle"></i>
    </span>
  @else
    <span class="escal-team-cat">{{ $time->categoria->rotulo }}</span>
  @endif
@endif
