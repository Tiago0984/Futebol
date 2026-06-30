<!-- VIDEO SECTION -->
<div class="home-video-section">
    <div class="home-video-overlay">
        <div class="container">

            <div class="row">
                <div class="col-sm-12 col-md-8 col-md-offset-2">

                    @if($videoDestaque ?? null)
                    <div class="home-video-header">
                        <span class="home-video-sup">AACJ FUTEBOL</span>
                        <h2 class="home-video-title">
                            {{ $videoDestaque->ao_vivo_video ? 'ASSISTA AO VIVO' : strtoupper($videoDestaque->titulo_video) }}
                        </h2>
                        @if(!$videoDestaque->ao_vivo_video && $videoDestaque->descricao_video)
                            <p class="home-video-desc">{{ $videoDestaque->descricao_video }}</p>
                        @endif
                        <div class="home-video-divider"></div>
                    </div>

                    <div class="home-video-frame-wrap">
                        <div class="embed-responsive embed-responsive-16by9">
                            <iframe class="embed-responsive-item"
                                    src="{{ $videoDestaque->embed_url }}"
                                    allowfullscreen></iframe>
                        </div>
                    </div>
                    @else
                    <div class="home-video-header">
                        <span class="home-video-sup">AACJ FUTEBOL</span>
                        <h2 class="home-video-title">EM BREVE</h2>
                        <div class="home-video-divider"></div>
                    </div>
                    <div class="home-video-frame-wrap text-center" style="padding:3rem 0; color:rgba(255,255,255,.5);">
                        <i class="fa fa-play-circle" style="font-size:4rem;"></i>
                        <p class="mt-3">Nenhum vídeo disponível no momento.</p>
                    </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>