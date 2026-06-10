<!-- GALLERY SECTION -->
<div class="section gallery bg-section">
	<div class="container">
		<div class="row">
			<div class="col-sm-12 col-md-12">
				<div class="page-title">
					<h2 class="lead">GALERIA</h2>
					<div class="border-style"></div>
				</div>
			</div>
		</div>
		<div class="row popup-gallery">
			@forelse ($galerias as $foto)
			<div class="col-xs-4 col-sm-3 col-md-3">
				<div class="w-item">
					<a href="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}" title="{{ $foto->titulo_galeria }}">
						<img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}" alt="" class="img-responsive" />
						<div class="project-info">
							<div class="project-icon">
								<span class="fa fa-search"></span>
							</div>
						</div>
					</a>
				</div>
			</div>
			@empty
			<p class="text-center">Nenhuma foto cadastrada.</p>
			@endforelse
		</div>

		{{-- <div class="loadmore">
			<a href="#" title="">See More</a>
		</div> --}}

	</div>
</div>