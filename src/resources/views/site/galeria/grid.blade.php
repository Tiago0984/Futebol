<!-- GALERIA -->
<div class="section singlepage">
    <div class="container">

        <div class="row">
            <div class="col-sm-12">
                <div class="page-title">
                    <h2 class="lead">GALERIA DE FOTOS</h2>
                    <div class="border-style"></div>
                </div>
            </div>
        </div>

        <div class="row popup-gallery galeria-grid">

            @forelse ($galerias as $foto)
            <div class="col-xs-6 col-sm-4 col-md-3 galeria-grid-col">
                <a href="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                   title="{{ $foto->titulo_galeria }}"
                   class="galeria-grid-item">
                    <img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                         alt="{{ $foto->titulo_galeria }}"
                         class="img-responsive galeria-grid-img" />
                    <div class="galeria-grid-hover">
                        <div class="galeria-grid-hover-icon">
                            <i class="fa fa-search-plus"></i>
                        </div>
                        @if($foto->titulo_galeria)
                        <div class="galeria-grid-hover-title">{{ $foto->titulo_galeria }}</div>
                        @endif
                    </div>
                </a>
            </div>
            @empty
            <div class="col-xs-12">
                <p class="text-center" style="padding: 60px 0; color: #999;">Nenhuma foto cadastrada.</p>
            </div>
            @endforelse

        </div>

    </div>
</div>
<!-- FIM GALERIA -->
