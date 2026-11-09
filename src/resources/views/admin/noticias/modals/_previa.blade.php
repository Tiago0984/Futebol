{{--
  "Como vai ficar" da capa da notícia (09/11/2026): a capa (a atual, a nova ou o recorte em andamento) nos quatro lugares
  do site em que ela aparece, com o título, a categoria, o autor e a data do formulário. Usa as classes do próprio site
  (news-hero-*, post-*, card-noticia-*, recent-*), que ficam no estilo.css, numa escala menor. Cada lugar corta a capa
  de um jeito (object-fit/background cover, pelo centro), como lá. JS em coderatech/js/previa-das-imagens.js; estilos
  no estilo.css, seção 80.
--}}
<div class="js-previa-noticia mt-3">
  <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>

  <div class="previa-camp-grade">
    <figure class="previa-camp previa-camp-topo">
      <figcaption>Site: topo da página da notícia (computador)</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="1366">
        <div class="previa-camp-escala">
          <header class="news-hero-banner previa-noticia-topo js-previa-fundo">
            <div class="previa-camp-menu">Menu do site</div>
            <div class="news-hero-overlay"></div>
            <div class="container-news hero-content-wrapper">
              <span class="news-hero-category js-previa-categoria"></span>
              <h1 class="news-hero-title js-previa-titulo"></h1>
              <div class="news-hero-meta">
                <span class="meta-item">Por: <strong class="js-previa-autor"></strong></span>
                <span class="meta-item">Publicado em: <strong class="js-previa-data-longa"></strong></span>
              </div>
            </div>
          </header>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Site: lista de notícias</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="725">
        <div class="previa-camp-escala previa-noticia-papel">
          <article class="news-post-card">
            <div class="post-image-box">
              <img class="js-previa-capa previa-noticia-cobrir" alt="" hidden>
              <div class="post-image-placeholder js-previa-sem-capa"><span>[ <span class="js-previa-categoria"></span> ]</span></div>
              <div class="post-date-badge">
                <span class="day js-previa-dia"></span>
                <span class="month js-previa-mes"></span>
              </div>
            </div>
            <div class="post-meta-info">
              <span>Por: <strong class="js-previa-autor"></strong></span>
              <span>Categoria: <strong class="js-previa-categoria"></strong></span>
            </div>
            <h2 class="post-main-title js-previa-titulo"></h2>
          </article>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Home: Últimas notícias</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="360">
        <div class="previa-camp-escala previa-noticia-papel">
          <div class="card-noticia-item">
            <div class="container-imagem">
              <div class="tag-data-moderna"><i class="bi bi-calendar"></i> <span class="js-previa-data"></span></div>
              <img class="img-responsive imagem-noticia js-previa-capa" alt="" hidden>
            </div>
            <div class="corpo-noticia-item">
              <h3 class="titulo-noticia-link"><a class="js-previa-titulo" data-limite="70"></a></h3>
            </div>
          </div>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Site: posts recentes (miniatura)</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="300">
        <div class="previa-camp-escala previa-noticia-papel">
          <div class="recent-post-item">
            <div class="recent-thumb-placeholder">
              <img class="js-previa-capa previa-noticia-cobrir" alt="" hidden>
            </div>
            <div class="recent-item-info">
              <h4 class="js-previa-titulo" data-limite="55"></h4>
              <span class="recent-date js-previa-data-curta"></span>
            </div>
          </div>
        </div>
      </div>
    </figure>
  </div>
  <p class="form-text mb-0 js-previa-sem-capa-aviso" hidden>
    Sem capa, a notícia fica assim no site: a lista mostra a categoria num quadro cinza, o topo da notícia fica sem
    imagem e o cartão da home fica sem foto. Para ficar bonita em todos os lugares, envie uma capa.
  </p>
</div>

@once
  @push('scripts')
  <script src="{{ asset('coderatech/js/previa-das-imagens.js') }}?v={{ filemtime(public_path('coderatech/js/previa-das-imagens.js')) }}"></script>
  @endpush
@endonce
