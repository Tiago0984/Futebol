<section class="gal-section">
    <div class="container">

        <div class="gal-header">
            <span class="section-subtitle-tag">Momentos AACJ</span>
            <h2>Galeria de Fotos</h2>
            <p class="gal-count"><i class="fa fa-picture-o"></i> {{ $galerias->count() }} {{ $galerias->count() === 1 ? 'foto registrada' : 'fotos registradas' }}</p>
        </div>

        @if($categorias->count() >= 1)
        <div class="gal-filter-bar">
            <button class="btn-gal-filter is-active" data-filter="all">
                <i class="fa fa-th"></i> Geral <span class="filter-count">{{ $galerias->count() }}</span>
            </button>
            @foreach($categorias as $cat)
            <button class="btn-gal-filter" data-filter="{{ $cat }}">
                <i class="fa fa-tag"></i> {{ $cat }} <span class="filter-count">{{ $galerias->where('categoria_galeria', $cat)->count() }}</span>
            </button>
            @endforeach
        </div>
        @endif

        <div class="gal-grid popup-gallery">
            @forelse($galerias as $foto)
            <a href="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
               title="{{ $foto->titulo_galeria }}"
               class="gal-item"
               data-categoria="{{ $foto->categoria_galeria }}">
                <img src="{{ asset('futebol/images/galeria/' . $foto->foto_galeria) }}"
                     alt="{{ $foto->titulo_galeria }}"
                     class="gal-img"
                     loading="lazy" />
                <div class="gal-overlay">
                    <i class="fa fa-search-plus gal-zoom-icon"></i>
                    @if($foto->titulo_galeria)
                    <span class="gal-title">{{ $foto->titulo_galeria }}</span>
                    @endif
                </div>
            </a>
            @empty
            <div class="gal-empty-state">
                <i class="fa fa-picture-o"></i>
                <p>Nenhuma foto cadastrada.</p>
            </div>
            @endforelse
        </div>

        <div class="gal-no-results" id="gal-no-results" style="display:none;">
            <i class="fa fa-picture-o"></i>
            <p>Nenhuma foto encontrada para esta categoria.</p>
        </div>

    </div>
</section>
