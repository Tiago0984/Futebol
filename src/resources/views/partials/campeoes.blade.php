{{-- Campeões já definidos de um campeonato (Fase I, Etapa I4), na home e na lista de campeonatos. Recebe $campeoes
     (["Sub-15 Masculino" => "Botafogo da Vila"], de Campeonato::textoDoCampeao) e $classe. Uma categoria só: sem o
     nome dela. --}}
@if(! empty($campeoes))
<p class="{{ $classe }}">
    <i class="fa fa-trophy"></i>
    @if(count($campeoes) === 1)
        Campeão: <strong>{{ reset($campeoes) }}</strong>
    @else
        Campeões:
        @foreach($campeoes as $rotulo => $texto)
            {{ $rotulo }}: <strong>{{ $texto }}</strong>@if(! $loop->last) · @endif
        @endforeach
    @endif
</p>
@endif
