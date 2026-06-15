<aside class="scout-sidebar-filters">
    <h3 class="sidebar-title"><i class="fa fa-sliders"></i> Filtrar Deck</h3>

    {{-- Filtros client-side: todos os eventos são capturados por JS em /jogadores/index.blade.php.
         Não há action/method pois a filtragem acontece em tempo real sem reload de página. --}}
    <div class="filter-sidebar-form">

        <div class="filter-group">
            <label class="filter-field-label" for="filter-nome">Nome do Atleta</label>
            <div class="search-box-component">
                <input type="text" id="filter-nome" placeholder="Ex: João Silva..." class="form-control-scout" autocomplete="off">
                <span class="search-inline-btn" aria-hidden="true"><i class="fa fa-search"></i></span>
            </div>
        </div>

        <div class="filter-group">
            <label class="filter-field-label" for="filter-posicao">Posição de Jogo</label>
            <select id="filter-posicao" class="form-control-scout select-scout">
                <option value="">Todas as Posições</option>
                @foreach($posicoes as $pos)
                <option value="{{ $pos }}">{{ $pos }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-field-label" for="filter-time">Clube / Vínculo</label>
            <select id="filter-time" class="form-control-scout select-scout">
                <option value="">Todos os Times</option>
                {{-- Exibe apenas times internos, pois são os vínculos relevantes para atletas da academia --}}
                @foreach($times->filter(fn($t) => strtolower($t->tipo_time) === 'interno') as $time)
                <option value="{{ $time->id_time }}">{{ $time->nome_time }}</option>
                @endforeach
            </select>
        </div>

        <div class="sidebar-actions">
            <button type="button" id="btn-limpar-filtros" class="btn-sidebar-clear">
                <i class="fa fa-refresh"></i> Limpar Filtros
            </button>
        </div>
    </div>
</aside>
