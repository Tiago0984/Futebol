<section class="section-teams-clean">
	<div class="container">

		<header class="teams-clean-header">
			<h2>NOSSOS TIMES</h2>
			<div class="clean-divider"></div>
		</header>

		<div class="row">
			<div id="player-caro" class="owl-carousel owl-theme teams-clean-carousel">

				@foreach($times as $time)
				@if(strtolower($time->tipo_time) === 'interno')
				<div class="item">
					<article class="team-clean-card" data-toggle="modal" data-target="#modalTime{{ $time->id_time }}">

						<span class="type-clean-badge interno">
							{{ $time->tipo_time }}
						</span>

						<figure class="team-clean-logo">
							<img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="Escudo {{ $time->nome_time }}" class="img-responsive">
						</figure>

						<div class="team-clean-info">
							<h3>{{ mb_strtoupper($time->nome_time) }}</h3>
							<span class="category-clean-id">Categoria ID: #{{ $time->id_categoriaint }}</span>
						</div>

						<footer class="team-clean-footer">
							<span>VER ELENCO</span>
							<i class="fa replace-fa-icon fa-angle-right" aria-hidden="true"></i>
						</footer>

					</article>
				</div>
				@endif
				@endforeach

			</div>
		</div>
	</div>
</section>

@include('partials.modal-times')