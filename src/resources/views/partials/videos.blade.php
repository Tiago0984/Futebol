{{--
    Player e miniaturas dos vídeos do site (home e campeonatos). Recebe $videos (Video::doSite): o primeiro abre
    no player; com mais de um, a faixa de miniaturas troca o vídeo do player (e o título e a descrição, nos
    elementos .js-video-titulo e .js-video-desc da página), sem recarregar. Um iframe só na página.
--}}
@php $atual = $videos->first(); @endphp

<div class="home-video-frame-wrap">
    <div class="embed-responsive embed-responsive-16by9">
        <iframe class="embed-responsive-item js-video-player"
                src="{{ $atual->embed_url }}"
                title="{{ $atual->titulo_video }}"
                allow="autoplay; encrypted-media; picture-in-picture"
                allowfullscreen></iframe>
    </div>
</div>

@if($videos->count() > 1)
<div class="site-videos-lista" role="list">
    @foreach($videos as $video)
    <button type="button" role="listitem"
            class="site-videos-item js-video-item {{ $loop->first ? 'is-active' : '' }}"
            data-embed="{{ $video->embed_url }}"
            data-titulo="{{ $video->titulo_no_site }}"
            data-desc="{{ $video->descricao_no_site }}"
            aria-label="Assistir: {{ $video->titulo_video }}">
        <span class="site-videos-thumb">
            @if($video->thumbnail)
                <img src="{{ $video->thumbnail }}" alt="" loading="lazy">
            @endif
            <i class="fa fa-play-circle"></i>
            @if($video->ao_vivo_video)
                <span class="site-videos-aovivo">Ao vivo</span>
            @endif
        </span>
        <span class="site-videos-nome">{{ $video->titulo_video }}</span>
    </button>
    @endforeach
</div>
@endif
