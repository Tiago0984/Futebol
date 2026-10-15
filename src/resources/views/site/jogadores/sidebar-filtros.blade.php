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
            <label class="filter-field-label" for="filter-categoria">Categoria</label>
            <select id="filter-categoria" class="form-control-scout select-scout">
                <option value="">Todas as Categorias</option>
                {{-- O atleta é da AACJ, na categoria dele (sem time fixo); ?categoria=ID chega já selecionado --}}
                @foreach($categorias as $categoria)
                <option value="{{ $categoria->id_categoria }}" @selected((string) request('categoria') === (string) $categoria->id_categoria)>{{ $categoria->rotulo }}</option>
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
