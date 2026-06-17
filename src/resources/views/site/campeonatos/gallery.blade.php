<section class="camp-listing-section">
    <div class="camp-listing-container">

        <div class="section-title-block">
            <span class="section-subtitle-tag">AACJ Futebol</span>
            <h2>Campeonatos</h2>
            <p>Acompanhe todas as edições e competições em que nossos times estão presentes</p>
        </div>

        @if($campeonatos->isEmpty())
        <p class="camp-empty-msg">Nenhum campeonato cadastrado no momento.</p>
        @else
        <div class="camp-grid">
            @foreach ($campeonatos as $camp)
            <article class="camp-card">

                <a href="{{ route('campeonato.show', $camp->id_campeonato) }}" class="camp-card-banner-link">
                    <div class="camp-card-banner"
                         style="background-image: url('{{ asset('futebol/images/campeonatos/' . $camp->banner_evento) }}');">
                        <div class="camp-card-overlay"></div>
                        <span class="camp-card-type-badge">{{ $camp->tipo_campeonato }}</span>
                    </div>
                </a>

                <div class="camp-card-body">
                    <h3 class="camp-card-title">{{ $camp->nome_campeonato }}</h3>

                    <div class="camp-card-meta">
                        <span class="camp-meta-item">
                            <i class="fa fa-calendar"></i>
                            {{ \Carbon\Carbon::parse($camp->data_inicio_campeonato)->format('d/m/Y') }}
                            &mdash;
                            {{ \Carbon\Carbon::parse($camp->data_fim_campeonato)->format('d/m/Y') }}
                        </span>
                        <span class="camp-meta-item">
                            <i class="fa fa-map-marker"></i>
                            {{ $camp->local_evento }}
                        </span>
                        @if($camp->organizador_campeonato)
                        <span class="camp-meta-item">
                            <i class="fa fa-users"></i>
                            {{ $camp->organizador_campeonato }}
                        </span>
                        @endif
                    </div>

                    <a href="{{ route('campeonato.show', $camp->id_campeonato) }}" class="btn-camp-card">
                        Ver Campeonato <i class="fa fa-arrow-right"></i>
                    </a>
                </div>

            </article>
            @endforeach
        </div>
        @endif

    </div>
</section>
