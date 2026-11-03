{{-- Nossas categorias (03/11/2026, no estilo das escalações de videogame): à esquerda as categorias com atletas
     ativos; à direita a escalação da escolhida, num campo, pelas posições do cadastro (EscalacaoDaCategoria), e o
     banco com o resto. Não é a escalação de um jogo (essa fica só no admin). Trocar de categoria não recarrega a
     página (script.js, "Nossas categorias"). Estilos no estilo.css, seção 78 --}}
@php
    $iniciais = fn ($nome) => collect(preg_split('/\s+/', trim($nome)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))
        ->pipe(fn ($l) => $l->count() > 1 ? $l->first() . $l->last() : $l->first());
@endphp
<section class="section-escalacao">
	<div class="container">

		<header class="teams-clean-header escalacao-header">
			<h2>NOSSAS CATEGORIAS</h2>
			<div class="clean-divider"></div>
		</header>

		@if($categoriasDaAcj->isEmpty())
			<p class="escalacao-vazia">Nenhum atleta cadastrado no momento.</p>
		@else
		<div class="escalacao-painel js-escalacao">

			<nav class="escalacao-categorias" role="tablist" aria-label="Categorias da AACJ">
				@foreach($categoriasDaAcj as $categoria)
				<button type="button" role="tab" id="escalacao-aba-{{ $categoria->id_categoria }}"
					class="escalacao-categoria js-escalacao-categoria{{ $loop->first ? ' ativa' : '' }}"
					data-escalacao="{{ $categoria->id_categoria }}"
					aria-controls="escalacao-{{ $categoria->id_categoria }}"
					aria-selected="{{ $loop->first ? 'true' : 'false' }}">
					<img src="{{ asset('futebol/images/logo2.png') }}" alt="" class="escalacao-categoria-logo">
					<span class="escalacao-categoria-texto">
						<span class="escalacao-categoria-nome">{{ mb_strtoupper($categoria->rotulo) }}</span>
						<span class="escalacao-categoria-total">{{ $categoria->total_atletas }} {{ $categoria->total_atletas == 1 ? 'atleta' : 'atletas' }}</span>
					</span>
					<i class="fa fa-angle-right" aria-hidden="true"></i>
				</button>
				@endforeach
			</nav>

			<div class="escalacao-conteudo">
				@foreach($categoriasDaAcj as $categoria)
				@php $escalacao = $escalacoes[$categoria->id_categoria]; @endphp
				<div class="escalacao-aba js-escalacao-aba" id="escalacao-{{ $categoria->id_categoria }}" role="tabpanel"
					aria-labelledby="escalacao-aba-{{ $categoria->id_categoria }}"
					data-escalacao-aba="{{ $categoria->id_categoria }}" @unless($loop->first) hidden @endunless>

					<header class="escalacao-topo">
						<img src="{{ asset('futebol/images/logo2.png') }}" alt="AACJ" class="escalacao-topo-logo">
						<div class="escalacao-topo-titulo">
							<span>AACJ</span>
							<h3>{{ mb_strtoupper($categoria->rotulo) }}</h3>
						</div>
						<span class="escalacao-formacao" title="Formação">{{ $escalacao['formacao'] }}</span>
					</header>

					<div class="escalacao-corpo">
						<div class="escalacao-campo">
							<div class="escalacao-gramado" aria-hidden="true">
								<span class="escalacao-linha-meio"></span>
								<span class="escalacao-circulo"></span>
								<span class="escalacao-area escalacao-area-cima"></span>
								<span class="escalacao-area escalacao-area-baixo"></span>
								<span class="escalacao-pequena-area escalacao-area-cima"></span>
								<span class="escalacao-pequena-area escalacao-area-baixo"></span>
							</div>

							@foreach($escalacao['titulares'] as $vaga)
							<div class="escalacao-jogador{{ $vaga['atleta'] ? '' : ' vazio' }}"
								style="--x: {{ $vaga['x'] }}; --y: {{ $vaga['y'] }}; --i: {{ $loop->index }};"
								@if($vaga['atleta']) title="{{ $vaga['atleta']->nome_atleta }}" @endif>
								<span class="escalacao-foto">
									@if($vaga['atleta'] && $vaga['atleta']->urlFoto())
										<img src="{{ $vaga['atleta']->urlFoto() }}" alt="" loading="lazy">
									@elseif($vaga['atleta'])
										{{ mb_strtoupper($iniciais($vaga['atleta']->nome_atleta)) }}
									@else
										?
									@endif
									<span class="escalacao-sigla">{{ $vaga['sigla'] }}</span>
								</span>
								<span class="escalacao-placa">
									@if($vaga['camisa'])<b>{{ $vaga['camisa'] }}</b>@endif
									{{ $vaga['atleta'] ? \App\Services\EscalacaoDaCategoria::nomeCurto($vaga['atleta']->nome_atleta) : 'A definir' }}
								</span>
							</div>
							@endforeach
						</div>

						<aside class="escalacao-banco">
							<h4>RESERVAS</h4>
							@forelse($escalacao['reservas'] as $reserva)
							<div class="escalacao-reserva" title="{{ $reserva['atleta']->nome_atleta }}">
								<span class="escalacao-reserva-numero">{{ $reserva['camisa'] ?? '' }}</span>
								<span class="escalacao-reserva-nome">{{ \App\Services\EscalacaoDaCategoria::nomeCurto($reserva['atleta']->nome_atleta) }}</span>
								<span class="escalacao-reserva-sigla">{{ $reserva['sigla'] }}</span>
							</div>
							@empty
							<p class="escalacao-banco-vazio">Sem reservas nesta categoria.</p>
							@endforelse

							<a href="{{ route('jogadores.vitrine', ['categoria' => $categoria->id_categoria]) }}" class="escalacao-ver-todos">
								VER JOGADORES <i class="fa fa-angle-right" aria-hidden="true"></i>
							</a>
						</aside>
					</div>

					<p class="escalacao-nota">Escalação montada pelas posições do cadastro de cada atleta.</p>
				</div>
				@endforeach
			</div>

		</div>
		@endif
	</div>
</section>
