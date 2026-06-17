<!-- GALLERY SECTION -->
<div class="home-gallery-section">
    <div class="home-gallery-bg-overlay">
        <div class="container">

            <div class="row">
                <div class="col-sm-12">
                    <div class="home-gallery-header">
                        <h2 class="home-gallery-title">GALERIA</h2>
                        <div class="home-gallery-divider"></div>
                    </div>
                </div>
            </div>

            <div class="row popup-gallery home-gallery-grid">

                @forelse ($galerias as $foto)
                <div class="col-xs-6 col-sm-4 col-md-3 home-gallery-col">
                    <a href="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                       title="{{ $foto->titulo_galeria }}"
                       class="home-gallery-item">
                        <img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                             alt="{{ $foto->titulo_galeria }}"
                             class="home-gallery-img" />
                        <div class="home-gallery-hover">
                            <div class="home-gallery-hover-icon">
                                <i class="fa fa-search-plus"></i>
                            </div>
                            @if($foto->titulo_galeria)
                            <div class="home-gallery-hover-title">{{ $foto->titulo_galeria }}</div>
                            @endif
                        </div>
                    </a>
                </div>
                @empty
                <div class="col-xs-12">
                    <p class="text-center home-gallery-empty">Nenhuma foto cadastrada.</p>
                </div>
                @endforelse

            </div>

            <div class="home-gallery-cta">
                <a href="{{ route('galeria.index') }}" class="btn-home-gallery-more">
                    <i class="fa fa-th"></i> VER GALERIA COMPLETA
                </a>
            </div>

        </div>
    </div>
</div>
