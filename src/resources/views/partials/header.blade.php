<div class="navbar navbar-main navbar-fixed-top">
	<div class="header-top">
		<div class="container">
			<div class="row">
				<div class="col-xs-12 col-sm-7 col-md-7 col-lg-7">
					<div class="info">
						<h3>News : </h3>
						<div class="info-item">
							@if(isset($noticiasRecentes) && $noticiasRecentes->count() > 0)
							@foreach($noticiasRecentes as $recente)
							<div>
								<a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" style="margin-left: 10px;color: #ffffff;text-decoration: none;">
									{{ \Illuminate\Support\Str::limit($recente->titulo_noticia, 47, '...') }}
								</a>
							</div>
							@endforeach
							@else
							<div>Nenhuma notícia recente publicada.</div>
							@endif
						</div>
					</div>
				</div>
				<div class=" col-xs-12 col-sm-5 col-md-5 col-lg-5">
					<div class="top-sosmed pull-right">
						<a href="#" title=""><span class="fa fa-facebook"></span></a>
						<a href="#" title=""><span class="fa fa-twitter"></span></a>
						<a href="#" title=""><span class="fa fa-instagram"></span></a>
						<a href="#" title=""><span class="fa fa-pinterest"></span></a>
					</div>
				</div>

			</div>
		</div>
	</div>
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="{{ route('home') }}">
				<img src="{{ asset('futebol/images/logo2.png') }}" alt="Logo AACJ" />
			</a>
		</div>
		<nav class="navbar-collapse collapse">
			<ul class="nav navbar-nav navbar-right">
				<li><a href="{{ route('home') }}">HOME</a></li>
				<li><a href="{{ route('sobre') }}">SOBRE</a></li>
				<li><a href="{{ route('calendario') }}">CALENDÁRIO</a></li>
				<li class="dropdown">
					<a href="{{ route('campeonato') }}" class="dropdown-toggle">
						CAMPEONATOS <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						@isset($campeonatosMenu)
						<li>
							<a href="{{ route('campeonato') }}">Ver Todos</a>
						</li>
						<li class="divider"></li>
						@foreach ($campeonatosMenu as $camp)
						<li>
							<a href="{{ route('campeonato.show', $camp->id_campeonato) }}">
								{{ $camp->nome_campeonato }}
							</a>
						</li>
						@endforeach
						@endisset
					</ul>
				</li>
				<li><a href="{{ route('noticias') }}">NOTÍCIAS</a></li>
				<li><a href="{{ route('shopping') }}">SHOPPING</a></li>
				<li><a href="{{ route('parcerias') }}">PARCERIAS</a></li>
				<li><a href="{{ route('contato') }}">CONTATO</a></li>
			</ul>
		</nav>
	</div>
</div>