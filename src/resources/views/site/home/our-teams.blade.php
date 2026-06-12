<section class="section-teams-clean">
	<div class="container">

		<header class="teams-clean-header">
			<h2>NOSSOS TIMES</h2>
			<div class="clean-divider"></div>
		</header>

		<div class="row">
			<div id="player-caro" class="owl-carousel owl-theme teams-clean-carousel">

				@foreach($times as $time)
				@if(strtolower($time->tipo_time) === 'interno')
				<div class="item">
					<article class="team-clean-card" data-toggle="modal" data-target="#modalTime{{ $time->id_time }}">

						<span class="type-clean-badge interno">
							{{ $time->tipo_time }}
						</span>

						<figure class="team-clean-logo">
							<img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="Escudo {{ $time->nome_time }}" class="img-responsive">
						</figure>

						<div class="team-clean-info">
							<h3>{{ mb_strtoupper($time->nome_time) }}</h3>
							<span class="category-clean-id">Categoria ID: #{{ $time->id_categoriaint }}</span>
						</div>

						<footer class="team-clean-footer">
							<span>VER ELENCO</span>
							<i class="fa replace-fa-icon fa-angle-right" aria-hidden="true"></i>
						</footer>

					</article>
				</div>
				@endif
				@endforeach

			</div>
		</div>
	</div>
</section>

@php
$posicaoInfo = [
    'goleiro'      => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'zagueiro'     => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
    'lateral'      => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
    'volante'      => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
    'meia'         => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'centroavante' => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'atacante'     => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    // Aliases / abreviações que possam existir no banco
    'gr'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'gk'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'cb'  => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
    'zag' => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
    'lb'  => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
    'rb'  => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
    'lat' => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
    'dm'  => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
    'vo'  => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
    'am'  => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'mc'  => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'mei' => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'at'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'fw'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'cf'  => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
];
@endphp

@foreach($times as $time)
@if(strtolower($time->tipo_time) === 'interno')
<div class="modal fade sport-premium-modal" id="modalTime{{ $time->id_time }}" tabindex="-1" role="dialog" aria-labelledby="modalLabel{{ $time->id_time }}">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">

			<main class="modal-body sport-modal-wrapper">
				<button type="button" class="sport-close-btn" data-dismiss="modal" aria-label="Close">&times;</button>

				<header class="sport-modal-header">
					<div class="sport-brand-flex">
						<div class="sport-logo-wrapper">
							<img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="Escudo {{ $time->nome_time }}">
						</div>
						<div class="sport-title-meta">
							<h2>{{ mb_strtoupper($time->nome_time) }}</h2>
							<span class="sport-badge-type">{{ strtoupper($time->tipo_time) }}</span>
						</div>
					</div>

					<ul class="nav sport-minimal-tabs" role="tablist">
						<li role="presentation" class="active">
							<a href="#elencoPrincipal{{ $time->id_time }}" role="tab" data-toggle="tab">ELENCO PRINCIPAL</a>
						</li>
						<li role="presentation">
							<a href="#elencoReserva{{ $time->id_time }}" role="tab" data-toggle="tab">ELENCO RESERVA</a>
						</li>
					</ul>
				</header>

				<div class="tab-content sport-content-layout">

					<div role="tabpanel" class="tab-pane fade in active sport-split-pane" id="elencoPrincipal{{ $time->id_time }}">

						<aside class="sport-athletes-sidebar">
							<div class="sport-sidebar-scroll">
								@php $hasTitular = false; @endphp
								@foreach($time->atletas as $atleta)
								@php
								$status = isset($atleta->pivot->status_atleta_time) ? strtoupper(trim($atleta->pivot->status_atleta_time)) : '';

								$idade = 'Não informada';
								if(!empty($atleta->data_nasc_atleta)) {
								try {
								$idade = \Carbon\Carbon::parse($atleta->data_nasc_atleta)->age . ' anos';
								} catch(\Exception $e) { $idade = 'Inválida'; }
								}

								$posKeyTime  = strtolower(trim($atleta->pivot->posicao_atleta_time ?? ''));
								$posDataTime = $posicaoInfo[$posKeyTime] ?? [
								    'sigla' => $atleta->pivot->posicao_atleta_time ? strtoupper(substr(trim($atleta->pivot->posicao_atleta_time), 0, 3)) : 'ND',
								    'nome'  => ucfirst(strtolower(trim($atleta->pivot->posicao_atleta_time ?? 'ND'))),
								];
								@endphp

								@if($status === 'TITULAR' || $status === '')
								@php $hasTitular = true; @endphp
								<div class="sport-player-row js-atleta-btn"
									data-nome="{{ $atleta->nome_atleta ?? 'Jogador Sem Nome' }}"
									data-foto="{{ asset('futebol/images/our-teams/' . ($atleta->foto_atleta ?? 'default-player.jpg')) }}"
									data-posicao="{{ $atleta->pivot->posicao_atleta_time ?? 'Não Definida' }}"
									data-camisa="{{ $atleta->pivot->camisa_atleta_time ?? '00' }}"
									data-jogos="{{ $atleta->pivot->jogos_atleta_time ?? 0 }}"
									data-gols="{{ $atleta->pivot->gols_atleta_time ?? 0 }}"
									data-defesas="{{ $atleta->pivot->defesas_atleta_time ?? 0 }}"
									data-convocacoes="{{ $atleta->pivot->convocacao_atleta_time ?? 0 }}"
									data-idade="{{ $idade }}"
									data-peso="{{ !empty($atleta->peso_atleta) ? number_format($atleta->peso_atleta, 2, ',', '') . ' kg' : '--' }}"
									data-altura="{{ !empty($atleta->altura_atleta) ? number_format($atleta->altura_atleta, 2, ',', '') . ' m' : '--' }}"
									data-sexo="{{ isset($atleta->sexo_atleta) && strtoupper($atleta->sexo_atleta) == 'M' ? 'Masculino' : 'Feminino' }}"
									data-descricao="{{ $atleta->descricao_atleta ?? 'Sem histórico cadastrado profissionalmente no clube.' }}">

									<div class="player-row-content">
										<span class="sport-pos-badge {{ strtolower($posDataTime['sigla']) }}">
											{{ $posDataTime['sigla'] }}
										</span>
										<span class="sport-row-number">#{{ $atleta->pivot->camisa_atleta_time ?? '00' }}</span>
										<span class="sport-row-name">{{ $atleta->nome_atleta ?? 'Sem nome' }}</span>
									</div>
									<i class="fa fa-chevron-right sport-row-arrow"></i>
								</div>
								@endif
								@endforeach

								@if(!$hasTitular)
								<div class="sport-empty-state">
									<p>Nenhum atleta titular vinculado.</p>
								</div>
								@endif
							</div>
						</aside>

						<section class="sport-player-showcase">
							<div class="sport-card-premium">
								<div class="sport-profile-top">
									<div class="sport-avatar-container">
										<img src="{{ asset('futebol/images/our-teams/default-player.jpg') }}" alt="Atleta" class="js-player-foto img-responsive">
										<div class="sport-floating-number js-player-camisa">--</div>
									</div>
									<div class="sport-identity-box">
										<h3 class="js-player-nome">Selecione um atleta...</h3>
										<span class="sport-pos-label js-player-posicao"></span>
									</div>
								</div>

								<div class="sport-metrics-grid">
									<div class="metric-item"><span class="metric-label">IDADE</span><strong class="metric-value js-player-idade">-</strong></div>
									<div class="metric-item"><span class="metric-label">ALTURA</span><strong class="metric-value js-player-altura">-</strong></div>
									<div class="metric-item"><span class="metric-label">PESO</span><strong class="metric-value js-player-peso">-</strong></div>
									<div class="metric-item"><span class="metric-label">GÊNERO</span><strong class="metric-value js-player-sexo">-</strong></div>
								</div>

								<h4 class="sport-section-title">DESEMPENHO NA TEMPORADA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini"><span class="stat-title">PARTIDAS</span><span class="stat-counter js-player-jogos">0</span></div>
									<div class="stat-box-mini"><span class="stat-title">CONVOCAÇÕES</span><span class="stat-counter js-player-convocacoes">0</span></div>
									<div class="stat-box-mini highlight-stat js-box-gols"><span class="stat-title">GOLS</span><span class="stat-counter js-player-gols">0</span></div>
									<div class="stat-box-mini highlight-stat goalie js-box-defesas"><span class="stat-title">DEFESAS</span><span class="stat-counter js-player-defesas">0</span></div>
								</div>

								<div class="sport-bio-history">
									<h4 class="sport-section-title">HISTÓRICO & OBSERVAÇÕES TÉCNICAS</h4>
									<p class="js-player-descricao">Escolha um dos atletas do painel ao lado para ver o scout completo.</p>
								</div>
							</div>
						</section>

					</div>

					<div role="tabpanel" class="tab-pane fade sport-split-pane" id="elencoReserva{{ $time->id_time }}">

						<aside class="sport-athletes-sidebar">
							<div class="sport-sidebar-scroll">
								@php $hasReserva = false; @endphp
								@foreach($time->atletas as $atleta)
								@php
								$status = isset($atleta->pivot->status_atleta_time) ? strtoupper(trim($atleta->pivot->status_atleta_time)) : '';

								$idade = 'Não informada';
								if(!empty($atleta->data_nasc_atleta)) {
								try {
								// Carbon é uma biblioteca PHP para manipulação de datas, aqui estamos calculando a idade do atleta com base na data de nascimento. O método parse tenta criar uma instância de data a partir da string fornecida, e o método age calcula a idade em anos. Se a data for inválida ou ocorrer algum erro, capturamos a exceção e definimos a idade como 'Inválida'.
								$idade = \Carbon\Carbon::parse($atleta->data_nasc_atleta)->age . ' anos';
								} catch(\Exception $e) { $idade = 'Inválida'; }
								}

								$posKeyTime  = strtolower(trim($atleta->pivot->posicao_atleta_time ?? ''));
								$posDataTime = $posicaoInfo[$posKeyTime] ?? [
								    'sigla' => $atleta->pivot->posicao_atleta_time ? strtoupper(substr(trim($atleta->pivot->posicao_atleta_time), 0, 3)) : 'ND',
								    'nome'  => ucfirst(strtolower(trim($atleta->pivot->posicao_atleta_time ?? 'ND'))),
								];
								@endphp

								@if($status === 'RESERVA')
								@php $hasReserva = true; @endphp
								<div class="sport-player-row js-atleta-btn"
									data-nome="{{ $atleta->nome_atleta ?? 'Jogador Sem Nome' }}"
									data-foto="{{ asset('futebol/images/our-teams/' . ($atleta->foto_atleta ?? 'default-player.jpg')) }}"
									data-posicao="{{ $atleta->pivot->posicao_atleta_time ?? 'Não Definida' }}"
									data-camisa="{{ $atleta->pivot->camisa_atleta_time ?? '00' }}"
									data-jogos="{{ $atleta->pivot->jogos_atleta_time ?? 0 }}"
									data-gols="{{ $atleta->pivot->gols_atleta_time ?? 0 }}"
									data-defesas="{{ $atleta->pivot->defesas_atleta_time ?? 0 }}"
									data-convocacoes="{{ $atleta->pivot->convocacao_atleta_time ?? 0 }}"
									data-idade="{{ $idade }}"
									data-peso="{{ !empty($atleta->peso_atleta) ? number_format($atleta->peso_atleta, 2, ',', '') . ' kg' : '--' }}"
									data-altura="{{ !empty($atleta->altura_atleta) ? number_format($atleta->altura_atleta, 2, ',', '') . ' m' : '--' }}"
									data-sexo="{{ isset($atleta->sexo_atleta) && strtoupper($atleta->sexo_atleta) == 'M' ? 'Masculino' : 'Feminino' }}"
									data-descricao="{{ $atleta->descricao_atleta ?? 'Sem histórico cadastrado profissionalmente no clube.' }}">

									<div class="player-row-content">
										<span class="sport-pos-badge {{ strtolower($posDataTime['sigla']) }}">
											{{ $posDataTime['sigla'] }} {{-- Sigla da posição traduzida pelo mapa $posicaoInfo /// exibe GR, ZAG, LAT, VO, MEI ou CF --}}
										</span>
										<span class="sport-row-number">#{{ $atleta->pivot->camisa_atleta_time ?? '00' }}</span>
										<span class="sport-row-name">{{ $atleta->nome_atleta ?? 'Sem nome' }}</span>
									</div>
									<i class="fa fa-chevron-right sport-row-arrow"></i>
								</div>
								@endif
								@endforeach

								@if(!$hasReserva)
								<div class="sport-empty-state">
									<p>Nenhum atleta reserva vinculado.</p>
								</div>
								@endif
							</div>
						</aside>

						<section class="sport-player-showcase">
							<div class="sport-card-premium">
								<div class="sport-profile-top">
									<div class="sport-avatar-container">
										<img src="{{ asset('futebol/images/our-teams/default-player.jpg') }}" alt="Atleta" class="js-player-foto img-responsive">
										<div class="sport-floating-number js-player-camisa">--</div>
									</div>
									<div class="sport-identity-box">
										<h3 class="js-player-nome">Selecione um atleta...</h3>
										<span class="sport-pos-label js-player-posicao"></span>
									</div>
								</div>

								<div class="sport-metrics-grid">
									<div class="metric-item"><span class="metric-label">IDADE</span><strong class="metric-value js-player-idade">-</strong></div>
									<div class="metric-item"><span class="metric-label">ALTURA</span><strong class="metric-value js-player-altura">-</strong></div>
									<div class="metric-item"><span class="metric-label">PESO</span><strong class="metric-value js-player-peso">-</strong></div>
									<div class="metric-item"><span class="metric-label">GÊNERO</span><strong class="metric-value js-player-sexo">-</strong></div>
								</div>

								<h4 class="sport-section-title">DESEMPENHO NA TEMPORADA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini"><span class="stat-title">PARTIDAS</span><span class="stat-counter js-player-jogos">0</span></div>
									<div class="stat-box-mini"><span class="stat-title">CONVOCAÇÕES</span><span class="stat-counter js-player-convocacoes">0</span></div>
									<div class="stat-box-mini highlight-stat js-box-gols"><span class="stat-title">GOLS</span><span class="stat-counter js-player-gols">0</span></div>
									<div class="stat-box-mini highlight-stat goalie js-box-defesas"><span class="stat-title">DEFESAS</span><span class="stat-counter js-player-defesas">0</span></div>
								</div>

								<div class="sport-bio-history">
									<h4 class="sport-section-title">HISTÓRICO & OBSERVAÇÕES TÉCNICAS</h4>
									<p class="js-player-descricao">Escolha um dos atletas do painel ao lado para ver o scout completo.</p>
								</div>
							</div>
						</section>

					</div>

				</div>
			</main>
		</div>
	</div>
</div>
@endif
@endforeach