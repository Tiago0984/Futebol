<!-- BLOG/NEWS SECTION -->
<div class="section blog">
	<div class="container">
		<div class="row">
			<div class="col-sm-12 col-md-6 col-md-offset-3">
				<div class="page-title">
					<h2 class="lead">LATEST NEWS</h2>
					<div class="border-style"></div>
				</div>
			</div>
		</div>

		<div class="row">
			@forelse ($noticias as $noticia)
			<div class="col-sm-12 col-md-4">
				<div class="blog-item">
					<div class="gambar">
						<div class="date">
							{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d M, Y') }}
						</div>
						<img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="{{ $noticia->titulo_noticia }}" class="img-responsive" />
					</div>
					<div class="item-body">
						<div class="description">
							<p class="lead">
								<a href="{{ route('noticias.show', $noticia->id_noticia) }}" title="">{{ $noticia->titulo_noticia }}</a>
							</p>
							<a href="{{ route('noticias.show', $noticia->id_noticia) }}" title="" class="readmore">Leia Mais</a>
						</div>
					</div>
				</div>
			</div>
			@empty
			<p class="text-center">Nenhuma notícia cadastrada.</p>
			@endforelse

			<div class="loadmore">
				<a href="{{ route('noticias.index') }}" title="">Ver Todas</a>
			</div>
		</div>
	</div>
</div>