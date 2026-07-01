<!-- VIDEO SECTION -->
	<div class="section video bg-section" id="video">
		<div class="container">
			<div class="row">
				<div class="col-sm-12 col-md-12">
					<div class="page-title">
						@if($videoDestaque ?? null)
						<h2 class="lead">
							{{ $videoDestaque->ao_vivo_video ? 'ASSISTA AO VIVO' : strtoupper($videoDestaque->titulo_video) }}
						</h2>
						@if(!$videoDestaque->ao_vivo_video && $videoDestaque->descricao_video)
							<p>{{ $videoDestaque->descricao_video }}</p>
						@endif
						@else
						<h2 class="lead">EM BREVE</h2>
						@endif
						<div class="border-style"></div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12 col-md-8 col-md-offset-2">
					@if($videoDestaque ?? null)
					<!-- 16:9 aspect ratio -->
					<div class="embed-responsive embed-responsive-16by9">
					  <iframe class="embed-responsive-item" src="{{ $videoDestaque->embed_url }}" allowfullscreen></iframe>
					</div>
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