{{--
  "Como vai ficar" (06/11/2026): a logo e o banner do campeonato nos lugares em que aparecem, com o nome, o tipo, as
  datas e as categorias do formulário. Usa as classes do próprio site (camp-hero-*, camp-card-*, home-camp-*), numa
  escala menor, para acompanhar o visual de lá. O app fica em outro repositório: a prévia dele é aproximada.
  Fica dentro do formulário, junto dos campos do partial _imagens. JS em coderatech/js/previa-das-imagens.js; estilos
  no estilo.css, seção 80.
--}}
<div class="col-12 js-previa-campeonato">
  <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>

  <div class="previa-camp-grade">
    <figure class="previa-camp previa-camp-topo">
      <figcaption>Site: topo da página do campeonato (computador)</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="1366">
        <div class="previa-camp-escala">
          <section class="camp-hero-section sem-imagem js-previa-banner">
            <div class="previa-camp-menu">Menu do site</div>
            <div class="camp-hero-overlay"></div>
            <div class="camp-hero-inner">
              <div class="camp-hero-logo-wrap js-previa-com-logo" hidden>
                <img class="camp-hero-logo js-previa-logo" alt="">
              </div>
              <div class="camp-hero-info">
                <span class="camp-hero-badge js-previa-tipo"></span>
                <h1 class="camp-hero-title js-previa-nome"></h1>
                <div class="camp-hero-cats js-previa-categorias" data-classe="camp-hero-cat"></div>
                <div class="camp-hero-meta">
                  <div class="camp-hero-meta-row">
                    <i class="bi bi-calendar"></i>
                    <span class="js-previa-datas" data-separador=" até "></span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Site: cartão da lista de campeonatos</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="380">
        <div class="previa-camp-escala">
          <article class="camp-card">
            <div class="camp-card-banner sem-imagem js-previa-banner">
              <div class="camp-card-overlay"></div>
              <span class="camp-card-type-badge js-previa-tipo"></span>
              <div class="camp-card-subs-banner js-previa-categorias" data-classe="camp-sub-badge-banner"></div>
            </div>
            <div class="camp-card-body">
              <h3 class="camp-card-title js-previa-nome"></h3>
              <div class="camp-card-meta">
                <span class="camp-meta-item"><i class="bi bi-calendar"></i> <span class="js-previa-datas" data-separador=" — "></span></span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>App: carrossel da Home (aproximado)</figcaption>
      <div class="previa-app-slide sem-imagem js-previa-banner">
        <div class="previa-app-degrade"></div>
        <img class="previa-app-logo js-previa-logo js-previa-com-logo" alt="" hidden>
        <strong class="previa-app-nome js-previa-nome"></strong>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Site: lista de campeonatos da home</figcaption>
      <div class="previa-camp-home">
        <div class="home-camp-card is-active">
          <img class="home-camp-logo js-previa-logo js-previa-com-logo" alt="" hidden>
          <span class="home-camp-logo home-camp-logo-iniciais js-previa-iniciais" aria-hidden="true"></span>
          <div class="home-camp-info">
            <span class="home-camp-type js-previa-tipo"></span>
            <strong class="home-camp-name js-previa-nome"></strong>
          </div>
        </div>
      </div>
    </figure>
  </div>
</div>

@once
  @push('scripts')
  <script src="{{ asset('coderatech/js/previa-das-imagens.js') }}?v={{ filemtime(public_path('coderatech/js/previa-das-imagens.js')) }}"></script>
  @endpush
@endonce
