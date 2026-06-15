<main class="scout-deck-content">
    @php
    $posicaoInfo = [
        // Nomes canônicos (salvos no banco em maiúsculo)
        'goleiro'      => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
        'zagueiro'     => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
        'lateral'      => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
        'volante'      => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
        'meia'         => ['sigla' => 'MEI', 'nome' => 'MEIA'],
        'atacante'     => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
        'centroavante' => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
        // Aliases internacionais vindos do banco (posicao_atleta_time)
        'gr'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
        'gk'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
        'cb'  => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
        'lb'  => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
        'rb'  => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
        'dm'  => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
        'am'  => ['sigla' => 'MEI', 'nome' => 'MEIA'],
        'mc'  => ['sigla' => 'MEI', 'nome' => 'MEIA'],
        'at'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
        'fw'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
        'cf'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
        'vo'  => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
    ];
    @endphp
    <div class="player-grid-container">
        @forelse($atletas as $atleta)
        @php
            $posKey  = strtolower(trim($atleta->posicao_atleta ?? ''));
            $posData = $posicaoInfo[$posKey] ?? [
                'sigla' => $atleta->posicao_atleta ? strtoupper(substr(trim($atleta->posicao_atleta), 0, 3)) : 'ATL',
                'nome'  => ucfirst(strtolower(trim($atleta->posicao_atleta ?? 'Atleta'))),
            ];
        @endphp

        <article class="player-sticker-card"
            data-toggle="modal"
            data-target="#modalAtleta{{ $atleta->id_atleta }}"
            data-atleta-id="{{ $atleta->id_atleta }}"
            data-nome="{{ strtolower($atleta->nome_atleta) }}"
            data-posicao="{{ strtolower($atleta->posicao_atleta ?? '') }}"
            data-times="{{ $atleta->times->pluck('id_time')->implode(',') }}"
            role="button" tabindex="0">
            <div class="sticker-inner-frame">
                <figure class="sticker-photo-box">
                    @if($atleta->foto_atleta && $atleta->foto_atleta != 'default-player.jpg')
                    <img src="{{ asset('futebol/images/our-teams/' . $atleta->foto_atleta) }}" alt="Foto do atleta {{ $atleta->nome_atleta }}">
                    @else
                    <img src="{{ asset('futebol/images/our-teams/default-player.jpg') }}" alt="Foto padrão">
                    @endif
                    <figcaption class="sticker-pos-tag">{{ $posData['sigla'] }}</figcaption>
                </figure>
                <footer class="sticker-meta-data">
                    <h3 class="sticker-player-name">{{ $atleta->nome_atleta }}</h3>
                    <span class="sticker-player-age">{{ \Carbon\Carbon::parse($atleta->data_nasc_atleta)->age }} anos</span>
                    @if($atleta->times->isNotEmpty())
                    <div class="sticker-teams-strip">
                        @foreach($atleta->times as $time)
                        <div class="sticker-team-item">
                            <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" class="sticker-team-crest" alt="{{ $time->nome_time }}">
                            <span class="sticker-team-name">{{ $time->nome_time }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </footer>
            </div>
        </article>

        <div class="modal fade modal-scout-premium" id="modalAtleta{{ $atleta->id_atleta }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content text-left">
                    <button type="button" class="close-premium-modal" data-dismiss="modal" aria-label="Fechar">&times;</button>
                    <div class="modal-body custom-modal-body">
                        <div class="row">

                            <div class="col-xs-12 col-md-4 mb-4-mobile">
                                <div class="modal-avatar-frame text-center">
                                    <img src="{{ $atleta->foto_atleta && $atleta->foto_atleta != 'default-player.jpg' ? asset('futebol/images/our-teams/' . $atleta->foto_atleta) : asset('futebol/images/our-teams/default-player.jpg') }}" class="img-responsive profile-picture" alt="{{ $atleta->nome_atleta }}">
                                </div>
                            </div>

                            <div class="col-xs-12 col-md-8">
                                <header class="profile-header">
                                    <span class="profile-badge-pos">{{ $posData['nome'] }}</span>
                                    <h2 class="profile-main-title">{{ $atleta->nome_atleta }}</h2>
                                </header>

                                <h4 class="scout-block-title"><i class="fa fa-bar-chart"></i> Desempenho Geral Acumulado</h4>
                                <section class="performance-stats-row">
                                    <div class="perf-stat-box highlight-stat">
                                        <span class="perf-value">{{ $atleta->times->sum('pivot.gols_atleta_time') }}</span>
                                        <span class="perf-label">Gols Totais</span>
                                    </div>
                                    <div class="perf-stat-box">
                                        <span class="perf-value">{{ $atleta->times->sum('pivot.jogos_atleta_time') }}</span>
                                        <span class="perf-label">Partidas</span>
                                    </div>
                                    <div class="perf-stat-box">
                                        <span class="perf-value">{{ $atleta->times->sum('pivot.convocacao_atleta_time') }}</span>
                                        <span class="perf-label">Convocações</span>
                                    </div>
                                    @if(in_array($posKey, ['goleiro', 'gk', 'gl']))
                                    <div class="perf-stat-box defensive-stat">
                                        <span class="perf-value">{{ $atleta->times->sum('pivot.defesas_atleta_time') }}</span>
                                        <span class="perf-label">Defesas</span>
                                    </div>
                                    @endif
                                    {{-- Total de cartões independente de time, somados de tbl_cartoes --}}
                                    <div class="perf-stat-box cartao-amarelo-perf">
                                        <span class="perf-value">{{ $atleta->cartoes->where('tipo_cartao', 'AMARELO')->count() }}</span>
                                        <span class="perf-label">C. Amarelos</span>
                                    </div>
                                    <div class="perf-stat-box cartao-vermelho-perf">
                                        <span class="perf-value">{{ $atleta->cartoes->where('tipo_cartao', 'VERMELHO')->count() }}</span>
                                        <span class="perf-label">C. Vermelhos</span>
                                    </div>
                                </section>

                                <h4 class="scout-block-title"><i class="fa fa-info-circle"></i> Ficha Antropométrica</h4>
                                <section class="profile-stats-grid">
                                    <div class="stat-item-box">
                                        <span class="stat-label">Idade</span>
                                        <span class="stat-value">{{ \Carbon\Carbon::parse($atleta->data_nasc_atleta)->age }} anos</span>
                                    </div>
                                    <div class="stat-item-box">
                                        <span class="stat-label">Gênero</span>
                                        <span class="stat-value">{{ $atleta->sexo_atleta == 'M' ? 'Masculino' : ($atleta->sexo_atleta == 'F' ? 'Feminino' : '-') }}</span>
                                    </div>
                                    <div class="stat-item-box">
                                        <span class="stat-label">Altura / Peso</span>
                                        <span class="stat-value">{{ $atleta->altura_atleta ?? '-' }}m / {{ $atleta->peso_atleta ?? '-' }}kg</span>
                                    </div>
                                    <div class="stat-item-box">
                                        <span class="stat-label">Escola / Série</span>
                                        <span class="stat-value text-truncate-custom">{{ $atleta->escola_atleta ?? '-' }} - {{ $atleta->serie_atleta ?? '-' }}</span>
                                    </div>
                                    <div class="stat-item-box">
                                        <span class="stat-label">Período Escolar</span>
                                        <span class="stat-value">{{ $atleta->periodo_escolar_atleta ?? '-' }}</span>
                                    </div>
                                </section>

                                <footer class="profile-linked-teams">
                                    <h4 class="linked-teams-title"><i class="fa fa-shield"></i> Clubes e Vínculos Atuais</h4>
                                    <div class="team-badges-flex">
                                        @forelse($atleta->times as $time)
                                        <button type="button"
                                            class="badge-team-action-btn js-open-team-from-player"
                                            data-time-id="{{ $time->id_time }}"
                                            data-atleta-id="{{ $atleta->id_atleta }}"
                                            data-atleta-nome="{{ $atleta->nome_atleta }}">
                                            <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" class="team-crest-mini" alt="Escudo">
                                            <div class="team-meta-inline">
                                                <span class="team-name-span">{{ $time->nome_time }}</span>
                                                <small class="team-pivot-status">{{ $time->pivot->status_atleta_time }} | nº {{ $time->pivot->camisa_atleta_time }}</small>
                                            </div>
                                            <i class="fa fa-angle-right badge-team-arrow"></i>
                                        </button>
                                        @empty
                                        <p class="no-records-text">Nenhum vínculo ativo registrado.</p>
                                        @endforelse
                                    </div>
                                </footer>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        @empty
        <div class="no-results-box text-center">
            <i class="fa fa-user-times fa-3x em-muted"></i>
            <p>Nenhum atleta ativo cadastrado no sistema.</p>
        </div>
        @endforelse

        {{-- Exibido pelo JS quando nenhum card passa nos filtros client-side --}}
        <div class="no-results-box text-center js-no-results-filter" style="display:none;">
            <i class="fa fa-search fa-3x em-muted"></i>
            <p>Nenhum atleta encontrado com essas informações fornecidas.</p>
        </div>
    </div>
</main>