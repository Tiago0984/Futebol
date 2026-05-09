<!-- ABOUT SECTION -->
<div class="section about">
	<div class="container">

		<div class="row">
			<div class="col-sm-12 col-md-12">
				<div class="page-title">
					<h2 class="lead">SOBRE OS JOGOS</h2>
					<div class="border-style"></div>
				</div>
			</div>
		</div>

		<div class="row">

			<div class="col-sm-12 col-md-4">
				<div id="shop-caro" class="owl-carousel owl-theme">
					<div class="item shop-item">
						<div class="img">
							<img src="{{asset('futebol/images/about/item1.jpg')}}" alt="" class="img-responsive" />
						</div>
						<div class="description">
							<div class="collection-name">
								<strong>NEW</strong> COLLECTIONS
								<div class="category">T-shirts</div>
							</div>
							<div class="collection-callout">
								<a href="#" title=""><span class="fa fa-facebook"></span>SHOP NOW</a>
							</div>
						</div>
					</div>
					<div class="item shop-item">
						<div class="img">
							<img src="{{asset('futebol/images/about/item2.jpg')}}" alt="" class="img-responsive" />
						</div>
						<div class="description">
							<div class="collection-name">
								<strong>NEW</strong> COLLECTIONS
								<div class="category">Pin</div>
							</div>
							<div class="collection-callout">
								<a href="#" title=""><span class="fa fa-facebook"></span>SHOP NOW</a>
							</div>
						</div>
					</div>

				</div>

			</div>

			<div class="col-sm-12 col-md-8">

				<div class="bs-example bs-example-tabs" data-example-id="togglable-tabs">
					<ul id="myTabs" class="nav nav-tabs" role="tablist">
						<li class="active"><a href="#match" id="match-tab" role="tab" data-toggle="tab" aria-controls="match" aria-expanded="true">JOGOS</a></li>
						<li><a href="#training" role="tab" id="training-tab" data-toggle="tab" aria-controls="training">TREINAMENTOS</a></li>
						<li><a href="#league" role="tab" id="league-tab" data-toggle="tab" aria-controls="league">CLASSIFICAÇÃO</a></li>
					</ul>
					<div id="myTabContent" class="tab-content tab-content-bg">
						<div role="tabpanel" class="tab-pane fade in active" id="match" aria-labelledBy="match-tab">
							<div class="table-responsive">
								<table class="table table-striped">
									<tbody>
										@foreach($jogos as $jogo)
										<tr>
											<td class="tw40">
												<div class="match-date">
													{{ $jogo->data_jogo ? \Carbon\Carbon::parse($jogo->data_jogo)->format('d M H:i') : '-' }}
												</div>
											</td>
											<td>
												<div class="match-title text-right">{{ $jogo->timeCasa->nome_time ?? '-' }}</div>
											</td>
											<td>
												<div class="text-center">VS</div>
											</td>
											<td>
												<div class="match-title color-red">{{ $jogo->timeVisitante->nome_time ?? '-' }}</div>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>

							</div>
						</div>
						<div role="tabpanel" class="tab-pane fade" id="training" aria-labelledBy="training-tab">
							<div class="table-responsive">
								<table class="table table-striped">
									<tbody>
										@foreach($jogos as $jogo)
										<tr>
											<td class="tw40">
												<div class="match-date">{{ $jogo->data_jogo ? \Carbon\Carbon::parse($jogo->data_jogo)->format('d M H:i') : '-' }}</div>
											</td>
											<td>
												<div class="match-title text-right">{{ $jogo->timeCasa->nome_time ?? '-' }}</div>
											</td>
											<td>
												<div class="text-center">VS</div>
											</td>
											<td>
												<div class="match-title color-red">{{ $jogo->timeVisitante->nome_time ?? '-' }}</div>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
						<div role="tabpanel" class="tab-pane fade" id="league" aria-labelledBy="league-tab">
							<div class="table-responsive">
								<table class="table table-striped">
									<thead>
										<tr>
											<td class="tw50">TEAM</td>
											<td class="tw10">W</td>
											<td class="tw10">D</td>
											<td class="tw10">L</td>
											<td>POINT</td>
										</tr>
									</thead>
									<tbody>
										@foreach($classificacao as $i => $time)
										<tr>
											<td>
												<div class="match-title">{{ $i+1 }}. {{ $time['nome'] }}</div>
											</td>
											<td>{{ $time['v'] }}</td>
											<td>{{ $time['e'] }}</td>
											<td>{{ $time['d'] }}</td>
											<td>
												<div class="match-title">{{ $time['pontos'] }}</div>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>

					</div>
				</div>
				<!-- /example -->

			</div>


		</div>
	</div>
</div>