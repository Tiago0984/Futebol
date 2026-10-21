	<!-- NEWS SECTION -->
<div class="section singlepage">
    <div class="container">
        <div class="row pbot-main">

            {{-- Lista de notícias --}}
            <div class="col-xs-12 col-md-8">

                @forelse ($noticias as $noticia)
                <div class="post-item">
                    <div class="image-wrap">
                        <img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="{{ $noticia->titulo_noticia }}" class="img-responsive">
                        <div class="meta">
                            <div class="blog-author">
                                <div class="blog-thumb">
                                    <img class="img-responsive" src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}" alt="...">
                                </div>by {{ $noticia->autor_noticia }}
                            </div>
                            <div class="blog-date">
                                <span>{{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d') }}</span>
                                {{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->locale('pt_BR')->isoFormat('MMM') }}
                            </div>
                        </div>
                    </div>
                    <h3 class="post-title">
                        <a href="{{ route('noticias.show', $noticia->id_noticia) }}" title="">{{ $noticia->titulo_noticia }}</a>
                    </h3>
                    <p>{{ \Str::limit($noticia->conteudo_noticia, 200) }}</p>
                    <a href="{{ route('noticias.show', $noticia->id_noticia) }}" class="post-read-more" title="">
                        Leia Mais <i class="fa fa-chevron-circle-right"></i>
                    </a>
                </div>
                @empty
                <p>Nenhuma notícia cadastrada.</p>
                @endforelse

                {{-- Paginação --}}
                {{ $noticias->links() }}

            </div>

            {{-- Sidebar --}}
            <div class="col-xs-12 col-md-4">
                <div class="widget recent-post">
                    <h4 class="widget-heading">NOTICIAS RECENTES</h4>
                    <div class="widget-wrap">
                        @foreach ($recentes as $recente)
                        <div class="media">
                            <div class="media-left">
                                <a href="{{ route('noticias.show', $recente->id_noticia) }}" title="detail news">
                                    <img class="media-object" src="{{ asset('futebol/images/news/' . $recente->foto_noticia) }}" alt="...">
                                </a>
                            </div>
                            <div class="media-body">
                                <p>
                                    <a href="{{ route('noticias.show', $recente->id_noticia) }}" title="detail news">
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