{{-- Lista de Campeonatos --}}
<div class="section singlepage">
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title">
                    <h2 class="lead">CAMPEONATOS</h2>
                    <div class="border-style"></div>
                </div>
            </div>
        </div>
        <div class="row">
            @forelse ($campeonatos as $camp)
            <div class="col-sm-6 col-md-4" style="margin-bottom: 30px;">
                <div class="blog-item">
                    <div class="gambar" style="display: flex; justify-content: center;">
                        <img src="{{ asset('futebol/images/campeonatos/' . $camp->banner_evento) }}" 
                             alt="{{ $camp->nome_campeonato }}" class="img-responsive">
                    </div>
                    <div class="item-body">
                        <div class="description">
                            <h4>{{ $camp->nome_campeonato }}</h4>
                            <p>{{ \Carbon\Carbon::parse($camp->data_inicio_campeonato)->format('d/m/Y') }} 
                               até 
                               {{ \Carbon\Carbon::parse($camp->data_fim_campeonato)->format('d/m/Y') }}</p>
                            <p><small>{{ $camp->local_evento }}</small></p>
                            <a href="{{ route('campeonato.show', $camp->id_campeonato) }}" class="readmore">
                                Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-center">Nenhum campeonato cadastrado.</p>
            @endforelse
        </div>
    </div>
</div>