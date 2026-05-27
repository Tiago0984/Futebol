<div class="section banner">
	<div id="carousel-example-generic" class="carousel slide" data-ride="carousel">
		<!-- Indicators -->
		<ol class="carousel-indicators">
			@foreach ($banners as $index => $banner)
			<li data-target="#carousel-example-generic" data-slide-to="{{ $index }}" class="{{ $index === 0 ? 'active' : '' }}"></li>
			@endforeach
		</ol>

		<!-- Wrapper for slides -->
		<div class="carousel-inner" role="listbox">
			@foreach ($banners as $index => $banner)
			<div class="item {{ $index === 0 ? 'active' : '' }}">
				<img src="{{ asset('futebol/images/banner/' . $banner->foto_banner) }}" alt="">
				<div class="carousel-caption">
					<div class="container">
						<div class="wrap-caption">
							<div class="caption-heading">{{ $banner->titulo_banner }}</div>
							<div class="caption-desc">{{ $banner->subtitulo_banner }}</div>
						</div>
					</div>
				</div>
			</div>
			@endforeach
		</div>

		<!-- Controls -->
		<a class="left carousel-control" href="#carousel-example-generic" role="button" data-slide="prev">
			<span class="fa fa-chevron-left"></span>
			<span class="sr-only">Previous</span>
		</a>
		<a class="right carousel-control" href="#carousel-example-generic" role="button" data-slide="next">
			<span class="fa fa-chevron-right"></span>
			<span class="sr-only">Next</span>
		</a>
	</div>
	<!-- END CAROUSEL -->
</div>