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
            @php
                // Selos: as categorias do campeonato (Fase H, Etapa H5; antes, as do cadastro dos times)
                $subs = $camp->categorias;
            @endphp
            <article class="camp-card">

                <a href="{{ route('campeonato.show', $camp->id_campeonato) }}" class="camp-card-banner-link">
                    <div class="camp-card-banner {{ $camp->banner_url ? '' : 'sem-imagem' }}"
                         @if($camp->banner_url) style="background-image: url('{{ $camp->banner_url }}');" @endif>
                        <div class="camp-card-overlay"></div>
                        <span class="camp-card-type-badge">{{ $camp->tipo_campeonato }}</span>
                        @if($subs->isNotEmpty())
                        <div class="camp-card-subs-banner">
                            @foreach($subs as $sub)
                            <span class="camp-sub-badge-banner">{{ mb_strtoupper($sub->rotulo) }}</span>
                            @endforeach
                        </div>
                        @endif
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
                        {{-- O local principal ou os locais dos jogos (Campeonato::textoDosLocais) --}}
                        @if($local = $locais[$camp->id_campeonato] ?? $camp->local_evento)
                        <span class="camp-meta-item">
                            <i class="fa fa-map-marker"></i>
                            {{ $local }}
                        </span>
                        @endif
                        @if($camp->organizador_campeonato)
                        <span class="camp-meta-item">
                            <i class="fa fa-users"></i>
                            {{ $camp->organizador_campeonato }}
                        </span>
                        @endif
                    </div>

                    @include('partials.campeoes', ['campeoes' => $campeoes[$camp->id_campeonato] ?? [], 'classe' => 'camp-campeao-linha'])

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
