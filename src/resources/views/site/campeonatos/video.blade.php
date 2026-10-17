<!-- VIDEO SECTION -->
	<div class="section video bg-section js-videos" id="video">
		<div class="container">
			<div class="row">
				<div class="col-sm-12 col-md-12">
					<div class="page-title">
						@if($videos->isNotEmpty())
						<h2 class="lead js-video-titulo">{{ $videos->first()->titulo_no_site }}</h2>
						<p class="js-video-desc" @if($videos->first()->descricao_no_site === '') hidden @endif>{{ $videos->first()->descricao_no_site }}</p>
						@else
						<h2 class="lead">EM BREVE</h2>
						@endif
						<div class="border-style"></div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12 col-md-8 col-md-offset-2">
					@if($videos->isNotEmpty())
					@include('partials.videos')
					@else
					<div class="text-center" style="padding:3rem 0; color:rgba(255,255,255,.5);">
						<i class="fa fa-play-circle" style="font-size:4rem;"></i>
						<p class="mt-3">Nenhum vídeo disponível no momento.</p>
					</div>
					@endif
				</div>
			</div>
		</div>
	</div>
<!-- FIM VIDEO SECTION -->
