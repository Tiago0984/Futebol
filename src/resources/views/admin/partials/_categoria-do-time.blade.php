{{-- Categoria do time no cartão (admin.partials.cartao-time, que calcula $outraCategoria). Outra que não a do
     cadastro (26/10/2026, dono do projeto): em destaque a categoria em que ele joga no campeonato, e a do cadastro
     embaixo, menor (antes, a do cadastro com uma dica escondida no ⓘ) --}}
@if($time->categoria)
  @if($outraCategoria)
    <span class="escal-team-cat escal-team-cat-joga js-categoria-do-time">{{ $categoriaDoCampeonato->rotulo }}</span>
    <span class="escal-team-cat">Cadastro do time: {{ $time->categoria->rotulo }}</span>
  @else
    <span class="escal-team-cat">{{ $time->categoria->rotulo }}</span>
  @endif
@endif
