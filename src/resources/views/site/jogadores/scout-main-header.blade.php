<header class="scout-main-header">
    <div class="scout-header-content">
        <div class="scout-branding">
            <h2 class="title-scout">Painel de Monitoramento</h2>
            <p class="subtitle-scout">Resultados baseados nos filtros selecionados</p>
        </div>

        <div class="scout-quick-stats">
            <div class="stat-counter-pill">
                <span class="counter-number">{{ $atletas->count() }}</span>
                <span class="counter-label">Atletas</span>
            </div>
            <div class="stat-counter-pill">
                <span class="counter-number">{{ $categorias->count() }}</span>
                <span class="counter-label">Categorias</span>
            </div>
        </div>
    </div>

    @if(request('nome') || request('posicao'))
    <div class="active-filters-wrapper">
        <span class="active-filters-label">Filtros ativos:</span>
        <div class="active-filters-tags">
            @if(request('nome'))
            <span class="filter-tag">
                Nome: "{{ request('nome') }}"
                <a href="{{ route('jogadores.vitrine', request()->except('nome')) }}" class="remove-filter-btn">&times;</a>
            </span>
            @endif

            @if(request('posicao'))
            <span class="filter-tag">
                Posição: {{ request('posicao') }}
                <a href="{{ route('jogadores.vitrine', request()->except('posicao')) }}" class="remove-filter-btn">&times;</a>
            </span>
            @endif
            <a href="{{ route('jogadores.vitrine') }}" class="clear-all-tag-btn">Limpar Tudo</a>
        </div>
    </div>
    @endif

    <div class="header-luxury-divider">
        <span class="luxury-marker"></span>
    </div>
</header>