@extends('layout.site')

@section('content')

@include('site.noticias.subbanner')

<div class="section singlepage">
    <div class="container">
        <div class="row pbot-main">

            {{-- Detalhe da notícia --}}
            <div class="col-xs-12 col-md-8">
                <div class="post-item">
                    <div class="image-wrap">
                        <img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="{{ $noticia->titulo_noticia }}" class="img-responsive">
                        <div class="meta">
                            <div class="blog-author">
                                by {{ $noticia->autor_noticia }}
                            </div>
                            <div class="blog-date">
                                <span>{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d') }}</span>
                                {{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('M') }}
                            </div>
                        </div>
                    </div>
                    <h3 class="post-title">{{ $noticia->titulo_noticia }}</h3>
                    <p>{!! nl2br(e($noticia->conteudo_noticia)) !!}</p>
                    <a href="{{ route('noticias.index') }}" class="post-read-more" title="">
                        <i class="fa fa-chevron-circle-left"></i> Voltar para Notícias
                    </a>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-xs-12 col-md-4">
                <div class="widget recent-post">
                    <h4 class="widget-heading">RECENT POST</h4>
                    <div class="widget-wrap">
                        @foreach ($recentes as $recente)
                        <div class="media">
                            <div class="media-left">
                                <a href="{{ route('noticias.show', $recente->id_noticia) }}">
                                    <img class="media-object" src="{{ asset('futebol/images/news/' . $recente->foto_noticia) }}" alt="...">
                                </a>
                            </div>
                            <div class="media-body">
                                <p>
                                    <a href="{{ route('noticias.show', $recente->id_noticia) }}">
                                        {{ \Str::limit($recente->titulo_noticia, 60) }}
                                    </a>
                                </p>
                                <div class="meta-date">
                                    {{ \Carbon\Carbon::parse($recente->data_publicacao_noticia)->format('M d - Y') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection