{{--
  "Como vai ficar" da foto da galeria (09/11/2026): a foto (a atual, a nova ou o recorte em andamento) nos três jeitos em
  que o site a mostra, com o título do formulário: o quadrado da página Galeria, o 4:3 da home (os dois cortam pelo
  centro: object-fit cover) e a foto inteira, ampliada, que abre ao clicar. Usa as classes do próprio site (gal-*,
  home-gallery-*), que ficam no estilo.css, numa escala menor; o título, que no site aparece ao passar o mouse,
  fica sempre à vista. JS em coderatech/js/previa-das-imagens.js; estilos no estilo.css, seção 80.
--}}
<div class="js-previa-galeria mt-3">
  <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>

  <div class="previa-camp-grade previa-galeria-grade">
    <figure class="previa-camp">
      <figcaption>Site: página Galeria (quadrado)</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="240">
        <div class="previa-camp-escala">
          <div class="gal-item previa-galeria-mostrar">
            <img class="gal-img js-previa-foto" alt="" hidden>
            <div class="gal-overlay">
              <i class="bi bi-zoom-in gal-zoom-icon"></i>
              <span class="gal-title js-previa-titulo"></span>
            </div>
          </div>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Home: galeria (4:3)</figcaption>
      <div class="previa-camp-moldura js-previa-moldura" data-largura="285">
        <div class="previa-camp-escala">
          <div class="home-gallery-item previa-galeria-mostrar">
            <img class="home-gallery-img js-previa-foto" alt="" hidden>
            <div class="home-gallery-hover">
              <div class="home-gallery-hover-icon"><i class="bi bi-zoom-in"></i></div>
              <div class="home-gallery-hover-title js-previa-titulo"></div>
            </div>
          </div>
        </div>
      </div>
    </figure>

    <figure class="previa-camp">
      <figcaption>Site: ao clicar (foto inteira)</figcaption>
      <div class="previa-galeria-ampliada">
        <img class="js-previa-foto" alt="" hidden>
        <span class="previa-galeria-vazia js-previa-sem-foto">Escolha uma foto</span>
        <div class="previa-galeria-legenda js-previa-titulo"></div>
      </div>
    </figure>
  </div>
</div>

@once
  @push('scripts')
  <script src="{{ asset('coderatech/js/previa-das-imagens.js') }}?v={{ filemtime(public_path('coderatech/js/previa-das-imagens.js')) }}"></script>
  @endpush
@endonce
