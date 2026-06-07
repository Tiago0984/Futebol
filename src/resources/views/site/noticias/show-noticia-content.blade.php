<header class="news-hero-banner" style="background-image: url('{{ $noticia->foto_noticia ? asset('futebol/images/news/' . $noticia->foto_noticia) : asset('coderatech/images/placeholder-hero.jpg') }}');">
    <div class="news-hero-overlay"></div>
    <div class="container-news hero-content-wrapper">

        <span class="news-hero-category">{{ $noticia->categoria_noticia }}</span>

        <h1 class="news-hero-title">{{ $noticia->titulo_noticia }}</h1>

        <div class="news-hero-meta">
            <span class="meta-item font-weight-bold">Por: <strong>{{ $noticia->autor_noticia }}</strong></span>
            <span class="meta-item">Publicado em: <strong>{{ $noticia->data_publicacao_noticia->translatedFormat('d \d\e M, Y') }}</strong></span>
        </div>

    </div>
</header>

<section class="news-body-section">
    <div class="container-news feed-layout">

        <main class="news-main-content page-show-premium">
            <article class="news-article-body">

                <div class="post-full-content">
                    {!! nl2br(e($noticia->conteudo_noticia)) !!}
                </div>

                <div class="post-footer-row">
                    <a href="{{ route('noticias.index') }}" class="btn-back-to-list-premium">
                        <span class="arrow">←</span> Voltar para o Feed de Notícias
                    </a>
                </div>

            </article>
        </main>

        <aside class="news-sidebar sticky-sidebar">

            <div class="sidebar-news-widget">
                <h3>Mais Recentes</h3>
                <div class="recent-posts-list">
                    @foreach($noticiasRecentes as $recente)
                    <a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" class="recent-post-item-premium">
                        <div class="recent-item-info">
                            <h4>{{ Str::limit($recente->titulo_noticia, 60, '...') }}</h4>
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
                        <a href="{{ route('noticias.index') }}">
                            Todas as matérias
                            <span class="badge-count">{{ $totalTodasNoticias }}</span>
                        </a>
                    </li>
                    @foreach($filtroCategoria as $cat)
                    <li>
                        <a href="{{ route('site.noticias.categoria', $cat->categoria_noticia) }}">
                            {{ $cat->categoria_noticia }}
                            <span class="badge-count">{{ $cat->total }}</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

        </aside>

    </div>
</section>