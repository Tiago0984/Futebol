<section class="news-feed-section">
	<div class="container-news feed-layout">

		<main class="news-main-content">

			@forelse($listaNoticias as $noticia)
			<article class="news-post-card">

				<div class="post-image-box">
					@if($noticia->foto_noticia)
					<img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="{{ $noticia->titulo_noticia }}" style="width:100%; height:100%; object-fit:cover; display:block;">
					@else
					<div class="post-image-placeholder">
						<span>[ {{ $noticia->categoria_noticia }} ]</span>
					</div>
					@endif

					<div class="post-date-badge">
						<span class="day">{{ $noticia->data_publicacao_noticia->format('d') }}</span>
						<span class="month">{{ $noticia->data_publicacao_noticia->translatedFormat('M') }}</span>
					</div>
				</div>

				<div class="post-meta-info">
					<span>Por: <strong>{{ $noticia->autor_noticia }}</strong></span>
					<span>Categoria: <strong>{{ $noticia->categoria_noticia }}</strong></span>
				</div>

				<h2 class="post-main-title">
					{{ $noticia->titulo_noticia }}
				</h2>

				<p class="post-excerpt">
					{{ Str::limit($noticia->conteudo_noticia, 180, '...') }}
				</p>

				<a href="{{ route('site.noticias.show-noticia', $noticia->id_noticia) }}" class="btn-read-more">
					Ler Mais <span class="arrow">→</span>
				</a>

			</article>
			@empty
			<article class="news-post-card" style="text-align: center; padding: 40px 0;">
				<h2 class="post-main-title">Nenhuma notícia localizada</h2>
				<p class="post-excerpt">Fique atento! Em breve nossa equipe de jornalismo trará novidades fresquinhas sobre o clube.</p>
			</article>
			@endforelse

		</main>

		<aside class="news-sidebar">

			<div class="sidebar-news-widget">
				<h3>Posts Recentes</h3>
				<div class="recent-posts-list">
					@foreach($noticiasRecentes as $recente)
					<a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" class="recent-post-item">
						<div class="recent-thumb-placeholder">
							@if($recente->foto_noticia)
							<img src="{{ asset('futebol/images/news/' . $recente->foto_noticia) }}" alt="" style="width:100%; height:100%; object-fit:cover; border-radius:4px;">
							@endif
						</div>
						<div class="recent-item-info">
							<h4>{{ Str::limit($recente->titulo_noticia, 55, '...') }}</h4>
							<span class="recent-date">{{ $recente->data_publicacao_noticia->translatedFormat('d M, Y') }}</span>
						</div>
					</a>
					@endforeach
				</div>
			</div>

			<div class="sidebar-news-widget">
				<h3>Filtrar por Assunto</h3>
				<ul class="news-cat-list">

					<li>
						<a href="{{ route('noticias') }}">
							Todas as matérias
							<span class="badge-count">{{ $totalTodasNoticias }}</span>
						</a>
					</li>
					
					@foreach($filtroCategoria as $linha)
					<li>
						<a href="{{ route('site.noticias.categoria', $linha->categoria_noticia) }}">
							{{ $linha->categoria_noticia }}
							<span class="badge-count">{{ $linha->total }}</span>
						</a>
					</li>
					@endforeach
				</ul>
			</div>

		</aside>

	</div>
</section>