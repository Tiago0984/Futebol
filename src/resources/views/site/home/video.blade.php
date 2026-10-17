<!-- VIDEO SECTION -->
<div class="home-video-section js-videos">
    <div class="home-video-overlay">
        <div class="container">

            <div class="row">
                <div class="col-sm-12 col-md-8 col-md-offset-2">

                    @if($videos->isNotEmpty())
                    <div class="home-video-header">
                        <span class="home-video-sup">AACJ FUTEBOL</span>
                        <h2 class="home-video-title js-video-titulo">{{ $videos->first()->titulo_no_site }}</h2>
                        <p class="home-video-desc js-video-desc" @if($videos->first()->descricao_no_site === '') hidden @endif>{{ $videos->first()->descricao_no_site }}</p>
                        <div class="home-video-divider"></div>
                    </div>

                    @include('partials.videos')
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
