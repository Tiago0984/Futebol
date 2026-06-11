<div class="section nova-secao-noticias">
    <div class="container">
        <div class="row">
            <div class="col-sm-12 col-md-6 col-md-offset-3">
                <div class="page-title-moderno text-center">
                    <h2 class="lead">ÚLTIMAS NOTÍCIAS</h2>
                    <div class="linha-detalhe-vermelha"></div>
                </div>
            </div>
        </div>

        <div class="row bloco-cards-noticias">
            @forelse ($noticias as $noticia)
            <div class="col-sm-12 col-md-4">
                <div class="card-noticia-item">
                    <div class="container-imagem">
                        <div class="tag-data-moderna">
                            <i class="fa fa-calendar"></i> {{ \Carbon\Carbon::parse($noticia->data_publicacao_noticia)->format('d/m/Y') }}
                        </div>
                        <a href="{{ route('site.noticias.show-noticia', $noticia->id_noticia) }}">
                            <img src="{{ asset('futebol/images/news/' . $noticia->foto_noticia) }}"
                                alt="{{ $noticia->titulo_noticia }}" class="img-responsive imagem-noticia" />
                        </a>
                    </div>
                    <div class="corpo-noticia-item">
                        <h3 class="titulo-noticia-link">
                            <a href="{{ route('site.noticias.show-noticia', $noticia->id_noticia) }}">
                                {{ \Str::limit($noticia->titulo_noticia, 70) }}
                            </a>
                        </h3>
                        <div class="rodape-card-noticia">
                            <a href="{{ route('site.noticias.show-noticia', $noticia->id_noticia) }}" class="btn-leia-mais-moderno">
                                Leia Mais <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-xs-12">
                <p class="text-center aviso-vazio">Nenhuma notícia cadastrada no momento.</p>
            </div>
            @endforelse
        </div>

        <div class="row text-center mt-4">
            <div class="col-xs-12">
                <div class="container-ver-todas">
                    <a href="{{ route('noticias.index') }}" class="btn-ver-todas-noticias">Ver Todas as Notícias</a>
                </div>
            </div>
        </div>
    </div>
</div>