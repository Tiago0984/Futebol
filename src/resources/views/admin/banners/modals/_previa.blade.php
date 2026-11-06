{{--
  "Como vai ficar" (06/11/2026): a foto do banner no carrossel da home, com o título e o subtítulo do formulário, no
  computador e no celular (onde o texto fica abaixo da foto). O carrossel usa o style.css do site, que o painel não
  carrega: as classes .previa-home-* repetem os valores dele (estilo.css, seção 80). JS em
  coderatech/js/previa-das-imagens.js.
--}}
<div class="js-previa-banner-home mt-3">
  <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>

  <div class="previa-camp-grade previa-home-grade">
    <figure class="previa-camp">
      <figcaption>Home: carrossel no computador</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="1366">
        <div class="previa-camp-escala">
          <div class="previa-home-slide sem-imagem js-previa-banner">
            <div class="previa-camp-menu">Menu do site</div>
            <div class="previa-home-legenda">
              <div class="previa-home-titulo js-previa-titulo"></div>
              <div class="previa-home-subtitulo js-previa-subtitulo" hidden></div>
            </div>
          </div>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Home: no celular</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="390">
        <div class="previa-camp-escala previa-home-celular">
          <div class="previa-home-slide sem-imagem js-previa-banner"></div>
          <div class="previa-home-legenda">
            <div class="previa-home-titulo js-previa-titulo"></div>
            <div class="previa-home-subtitulo js-previa-subtitulo" hidden></div>
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
