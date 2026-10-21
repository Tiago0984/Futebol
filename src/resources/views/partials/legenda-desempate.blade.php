{{-- Legenda do desempate (Jogo::ordenarClassificacao), no admin e no site: só quando há empate em todos os critérios.
     Recebe $linhas (linhas da tabela) e $classe (classe do parágrafo). --}}
@if(collect($linhas)->contains('sorteio', true))
<p class="{{ $classe }}">* Empatados em pontos, vitórias, saldo, gols marcados e confronto direto: se continuar assim no fim, a ordem sai por sorteio.</p>
@endif
