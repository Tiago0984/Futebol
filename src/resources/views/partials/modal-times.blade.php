@php
$posicaoInfoModal = [
    'goleiro'      => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'zagueiro'     => ['sigla' => 'ZAG', 'nome' => 'ZAGUEIRO'],
    'lateral'      => ['sigla' => 'LAT', 'nome' => 'LATERAL'],
    'volante'      => ['sigla' => 'VO',  'nome' => 'VOLANTE'],
    'meia'         => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'meio'         => ['sigla' => 'MEI', 'nome' => 'MEIA'],
    'centroavante' => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'atacante'     => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'ataque'       => ['sigla' => 'CF',  'nome' => 'CENTROAVANTE'],
    'gr'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'gk'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
    'gl'  => ['sigla' => 'GR',  'nome' => 'GOLEIRO'],
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
							<span class="sport-badge-type">{{ strtoupper($time->categoria->nome_categoria ?? $time->tipo_time) }}</span>
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
								$posDataTime = $posicaoInfoModal[$posKeyTime] ?? [
								    'sigla' => $atleta->pivot->posicao_atleta_time ? strtoupper(substr(trim($atleta->pivot->posicao_atleta_time), 0, 3)) : 'ND',
								    'nome'  => ucfirst(strtolower(trim($atleta->pivot->posicao_atleta_time ?? 'ND'))),
								];

								// Filtra os cartões do atleta apenas para jogos onde este time participou
								// (como time da casa ou visitante), isolando a disciplina por vínculo.
								$cartoesNoTime = $atleta->cartoes->filter(function($cartao) use ($time) {
								    if (!$cartao->jogo) return true; // cartão manual (sem jogo vinculado), sempre inclui
								    return $cartao->jogo->id_time_casa == $time->id_time ||
								           $cartao->jogo->id_time_visitante == $time->id_time;
								});
								$amarelosNoTime  = $cartoesNoTime->where('tipo_cartao', 'AMARELO')->count();
								$vermelhosNoTime = $cartoesNoTime->where('tipo_cartao', 'VERMELHO')->count();
								@endphp

								@if($status === 'TITULAR' || $status === 'ATIVO' || $status === '')
								@php $hasTitular = true; @endphp
								<div class="sport-player-row js-atleta-btn"
									data-atleta-id="{{ $atleta->id_atleta }}"
									data-nome="{{ $atleta->nome_atleta ?? 'Jogador Sem Nome' }}"
									data-foto="{{ asset('futebol/images/our-teams/' . ($atleta->foto_atleta ?? 'default-player.jpg')) }}"
									data-posicao="{{ $atleta->pivot->posicao_atleta_time ?? 'Não Definida' }}"
									data-camisa="{{ $atleta->pivot->camisa_atleta_time ?? '00' }}"
									data-jogos="{{ $atleta->pivot->jogos_atleta_time ?? 0 }}"
									data-gols="{{ $atleta->pivot->gols_atleta_time ?? 0 }}"
									data-defesas="{{ $atleta->pivot->defesas_atleta_time ?? 0 }}"
									data-convocacoes="{{ $atleta->pivot->convocacao_atleta_time ?? 0 }}"
									data-amarelos="{{ $amarelosNoTime }}"
									data-vermelhos="{{ $vermelhosNoTime }}">

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

								<h4 class="sport-section-title">DESEMPENHO NA TEMPORADA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini"><span class="stat-title">PARTIDAS</span><span class="stat-counter js-player-jogos">0</span></div>
									<div class="stat-box-mini"><span class="stat-title">CONVOCAÇÕES</span><span class="stat-counter js-player-convocacoes">0</span></div>
									<div class="stat-box-mini highlight-stat js-box-gols"><span class="stat-title">GOLS</span><span class="stat-counter js-player-gols">0</span></div>
									<div class="stat-box-mini highlight-stat goalie js-box-defesas"><span class="stat-title">DEFESAS</span><span class="stat-counter js-player-defesas">0</span></div>
								</div>

								<h4 class="sport-section-title">DISCIPLINA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini cartao-amarelo"><span class="stat-title">AMARELOS</span><span class="stat-counter js-player-amarelos">0</span></div>
									<div class="stat-box-mini cartao-vermelho"><span class="stat-title">VERMELHOS</span><span class="stat-counter js-player-vermelhos">0</span></div>
								</div>

								<div class="sport-ver-completo-wrapper">
									<a href="{{ route('jogadores.vitrine') }}" class="btn-ver-atleta-completo js-ver-atleta-completo" data-atleta-id="">
										<i class="fa fa-search-plus"></i> ver informações completas sobre esse atleta...
									</a>
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
								$idade = \Carbon\Carbon::parse($atleta->data_nasc_atleta)->age . ' anos';
								} catch(\Exception $e) { $idade = 'Inválida'; }
								}

								$posKeyTime  = strtolower(trim($atleta->pivot->posicao_atleta_time ?? ''));
								$posDataTime = $posicaoInfoModal[$posKeyTime] ?? [
								    'sigla' => $atleta->pivot->posicao_atleta_time ? strtoupper(substr(trim($atleta->pivot->posicao_atleta_time), 0, 3)) : 'ND',
								    'nome'  => ucfirst(strtolower(trim($atleta->pivot->posicao_atleta_time ?? 'ND'))),
								];

								// Filtra os cartões do atleta apenas para jogos onde este time participou
								// (como time da casa ou visitante), isolando a disciplina por vínculo.
								$cartoesNoTime = $atleta->cartoes->filter(function($cartao) use ($time) {
								    if (!$cartao->jogo) return true; // cartão manual (sem jogo vinculado), sempre inclui
								    return $cartao->jogo->id_time_casa == $time->id_time ||
								           $cartao->jogo->id_time_visitante == $time->id_time;
								});
								$amarelosNoTime  = $cartoesNoTime->where('tipo_cartao', 'AMARELO')->count();
								$vermelhosNoTime = $cartoesNoTime->where('tipo_cartao', 'VERMELHO')->count();
								@endphp

								@if($status === 'RESERVA')
								@php $hasReserva = true; @endphp
								<div class="sport-player-row js-atleta-btn"
									data-atleta-id="{{ $atleta->id_atleta }}"
									data-nome="{{ $atleta->nome_atleta ?? 'Jogador Sem Nome' }}"
									data-foto="{{ asset('futebol/images/our-teams/' . ($atleta->foto_atleta ?? 'default-player.jpg')) }}"
									data-posicao="{{ $atleta->pivot->posicao_atleta_time ?? 'Não Definida' }}"
									data-camisa="{{ $atleta->pivot->camisa_atleta_time ?? '00' }}"
									data-jogos="{{ $atleta->pivot->jogos_atleta_time ?? 0 }}"
									data-gols="{{ $atleta->pivot->gols_atleta_time ?? 0 }}"
									data-defesas="{{ $atleta->pivot->defesas_atleta_time ?? 0 }}"
									data-convocacoes="{{ $atleta->pivot->convocacao_atleta_time ?? 0 }}"
									data-amarelos="{{ $amarelosNoTime }}"
									data-vermelhos="{{ $vermelhosNoTime }}">

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

								<h4 class="sport-section-title">DESEMPENHO NA TEMPORADA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini"><span class="stat-title">PARTIDAS</span><span class="stat-counter js-player-jogos">0</span></div>
									<div class="stat-box-mini"><span class="stat-title">CONVOCAÇÕES</span><span class="stat-counter js-player-convocacoes">0</span></div>
									<div class="stat-box-mini highlight-stat js-box-gols"><span class="stat-title">GOLS</span><span class="stat-counter js-player-gols">0</span></div>
									<div class="stat-box-mini highlight-stat goalie js-box-defesas"><span class="stat-title">DEFESAS</span><span class="stat-counter js-player-defesas">0</span></div>
								</div>

								<h4 class="sport-section-title">DISCIPLINA</h4>
								<div class="sport-stats-dashboard">
									<div class="stat-box-mini cartao-amarelo"><span class="stat-title">AMARELOS</span><span class="stat-counter js-player-amarelos">0</span></div>
									<div class="stat-box-mini cartao-vermelho"><span class="stat-title">VERMELHOS</span><span class="stat-counter js-player-vermelhos">0</span></div>
								</div>

								<div class="sport-ver-completo-wrapper">
									<a href="{{ route('jogadores.vitrine') }}" class="btn-ver-atleta-completo js-ver-atleta-completo" data-atleta-id="">
										<i class="fa fa-search-plus"></i> ver informações completas sobre esse atleta...
									</a>
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
