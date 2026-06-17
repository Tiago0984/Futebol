<footer class="novo-footer">
	<div class="footer-principal">
		<div class="container">
			<div class="row">

				<div class="col-xs-12 col-sm-12 col-md-4 col-lg-4">
					<div class="bloco-footer">
						<div class="footer-logo">
							<img src="{{ asset('futebol/images/logo2.png') }}" alt="Logo AACJ" class="img-responsive">
						</div>
						<p class="footer-texto-sobre">
							A Associação Atlética Cohab Juscelino (AACJ) atua fortemente no desenvolvimento do esporte, promovendo a integração da comunidade através do futebol, torneios de alto nível e a formação cidadã de novos atletas.
						</p>
						<div class="novas-redes-sociais">
							<a href="#" title="Facebook" class="rede-link"><i class="fa fa-facebook"></i></a>
							<a href="#" title="Twitter" class="rede-link"><i class="fa fa-twitter"></i></a>
							<a href="#" title="Instagram" class="rede-link"><i class="fa fa-instagram"></i></a>
							<a href="#" title="YouTube" class="rede-link"><i class="fa fa-youtube-play"></i></a>
						</div>
					</div>
				</div>

				<div class="col-xs-12 col-sm-6 col-md-4 col-lg-4">
					<div class="bloco-footer">
						<h4 class="titulo-bloco">NAVEGAÇÃO RÁPIDA</h4>
						<div class="row">
							<div class="col-xs-6">
								<ul class="links-rapidos">
									<li><a href="{{ route('home') }}"><i class="fa fa-angle-right"></i> Início</a></li>
									<li><a href="{{ route('sobre') }}"><i class="fa fa-angle-right"></i> O Clube</a></li>
									<li><a href="{{ route('calendario') }}"><i class="fa fa-angle-right"></i> Calendário</a></li>
									<li><a href="{{ route('campeonato') }}"><i class="fa fa-angle-right"></i> Campeonatos</a></li>
								</ul>
							</div>
							<div class="col-xs-6">
								<ul class="links-rapidos">
									<li><a href="{{ route('noticias.index') }}"><i class="fa fa-angle-right"></i> Notícias</a></li>
									<li><a href="{{ route('shopping') }}"><i class="fa fa-angle-right"></i> Loja / Shop</a></li>
									<li><a href="{{ route('parcerias') }}"><i class="fa fa-angle-right"></i> Parceiros</a></li>
									<li><a href="{{ route('contato') }}"><i class="fa fa-angle-right"></i> Contato</a></li>
								</ul>
							</div>
						</div>
						<div class="row mt-3">
							<div class="col-xs-12">
								<a href="{{ route('cadastro.index') }}" class="btn btn-matricula-footer">
									<i class="fa fa-pencil-square-o"></i> FAÇA SUA MATRÍCULA
								</a>
							</div>
						</div>
					</div>
				</div>

				<div class="col-xs-12 col-sm-6 col-md-4 col-lg-4">
					<div class="bloco-footer">
						<h4 class="titulo-bloco">ÚLTIMAS DO CLUBE</h4>

						@if(isset($noticiasRecentes) && $noticiasRecentes->count() > 0)
						@foreach($noticiasRecentes->take(2) as $recente)
						<div class="mini-post-footer">
							<div class="post-conteudo">
								<a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" class="post-titulo">
									{{ \Illuminate\Support\Str::limit($recente->titulo_noticia, 55, '...') }}
								</a>
								<span class="post-data"><i class="fa fa-calendar-o"></i> {{ $recente->created_at ? $recente->created_at->format('d/m/Y') : date('d/m/Y') }}</span>
							</div>
						</div>
						@endforeach
						@else
						<p class="text-muted" style="font-size: 13px;">Nenhuma novidade publicada recentemente.</p>
						@endif

						<div class="newsletter-clean">
							<form action="#" method="POST" class="form-inline">
								<div class="input-group">
									<input type="email" class="form-control input-news" placeholder="Receba novidades por e-mail" required>
									<span class="input-group-btn">
										<button class="btn btn-enviar-news" type="submit"><i class="fa fa-paper-plane"></i></button>
									</span>
								</div>
							</form>
						</div>

					</div>
				</div>

			</div>
		</div>
	</div>

	<div class="footer-copyright">
		<div class="container">
			<div class="row">
				<div class="col-xs-12 col-sm-6">
					<p class="texto-copy">&copy; {{ date('Y') }} AACJ Futebol. Todos os direitos reservados.</p>
				</div>
				<div class="col-xs-12 col-sm-6 text-right-desktop">
					<p class="texto-desenvolvedor">
						Desenvolvido por
						<a href="#" target="_blank" style="color: #ffffff; font-weight: bold; text-decoration: none; transition: color 0.2s ease;" onmouseover="this.style.color='#e60000'" onmouseout="this.style.color='#ffffff'">
							CoderaTech
						</a>
					</p>
				</div>
			</div>
		</div>
	</div>
</footer>