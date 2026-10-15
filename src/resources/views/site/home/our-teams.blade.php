{{-- Nossas categorias (Fase G, Etapa 4): o atleta é da AACJ, na categoria dele, não de um time. Um cartão por
     categoria com atletas ativos; abre a página Jogadores filtrada pela categoria --}}
<section class="section-teams-clean">
	<div class="container">

		<header class="teams-clean-header">
			<h2>NOSSAS CATEGORIAS</h2>
			<div class="clean-divider"></div>
		</header>

		<div class="row">
			<div id="player-caro" class="owl-carousel owl-theme teams-clean-carousel">

				@foreach($categoriasDaAcj as $categoria)
				<div class="item">
					<a href="{{ route('jogadores.vitrine', ['categoria' => $categoria->id_categoria]) }}" class="team-clean-card" style="display:block;color:inherit;text-decoration:none;">

						<span class="type-clean-badge interno">AACJ</span>

						<figure class="team-clean-logo">
							<img src="{{ asset('futebol/images/logo2.png') }}" alt="AACJ" class="img-responsive">
						</figure>

						<div class="team-clean-info">
							<h3>{{ mb_strtoupper($categoria->rotulo) }}</h3>
							<span class="category-clean-id">{{ $categoria->total_atletas }} {{ $categoria->total_atletas == 1 ? 'atleta' : 'atletas' }}</span>
						</div>

						<footer class="team-clean-footer">
							<span>VER JOGADORES</span>
							<i class="fa replace-fa-icon fa-angle-right" aria-hidden="true"></i>
						</footer>

					</a>
				</div>
				@endforeach

			</div>
		</div>
	</div>
</section>
