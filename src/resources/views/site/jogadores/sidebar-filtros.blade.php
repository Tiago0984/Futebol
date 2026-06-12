<aside class="scout-sidebar-filters">
    <h3 class="sidebar-title"><i class="fa fa-sliders"></i> Filtrar Deck</h3>

    <form action="{{ route('jogadores.vitrine') }}" method="GET" class="filter-sidebar-form">

        <div class="filter-group">
            <label class="filter-field-label">Nome do Atleta</label>
            <div class="search-box-component">
                <input type="text" name="nome" value="{{ request('nome') }}" placeholder="Ex: João Silva..." class="form-control-scout">
                <button type="submit" class="search-inline-btn" aria-label="Pesquisar Atleta"><i class="fa fa-search"></i></button>
            </div>
        </div>

        <div class="filter-group">
            <label class="filter-field-label">Posição de Jogo</label>
            <select name="posicao" class="form-control-scout select-scout" onchange="this.form.submit()">
                <option value="">Todas as Posições</option>
                @foreach($posicoes as $pos)
                <option value="{{ $pos }}" {{ request('posicao') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-field-label">Clube / Vínculo</label>
            <select name="time" class="form-control-scout select-scout" onchange="this.form.submit()">
                <option value="">Todos os Times</option>
                @foreach($times as $time)
                <option value="{{ $time->id_time }}" {{ request('time') == $time->id_time ? 'selected' : '' }}>{{ $time->nome_time }}</option>
                @endforeach
            </select>
        </div>

        <div class="sidebar-actions">
            <a href="{{ route('jogadores.vitrine') }}" class="btn-sidebar-clear">
                <i class="fa fa-refresh"></i> Limpar Filtros
            </a>
        </div>
    </form>
</aside>