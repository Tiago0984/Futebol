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
            <ul class="nav navbar-nav navbar-right" style="display: flex; align-items: center; flex-wrap: wrap;">
                <li><a href="{{ route('home') }}">HOME</a></li>
                <li><a href="{{ route('sobre') }}">SOBRE</a></li>
                <li><a href="{{ route('calendario') }}">CALENDÁRIO</a></li>

                <li class="nav-highlight-scout">
                    <a href="{{ route('jogadores.vitrine') }}">
                        <span class="fa fa-search-plus" style="margin-right: 4px; font-size: 11px;"></span> JOGADORES
                    </a>
                </li>

                <li class="dropdown">
                    <a href="{{ route('campeonato') }}" class="dropdown-toggle" data-toggle="dropdown">
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
                <li><a href="{{ route('cadastro.index') }}" style="color:#fff; font-weight:bold;">MATRICULE-SE</a></li>
                <li><a href="{{ route('contato') }}">CONTATO</a></li>

                <li class="dropdown submenu-listras">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" style="font-size: 16px; padding: 15px 12px; display: flex; align-items: center;">
                        <span class="fa fa-bars"></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li><a href="{{ route('noticias.index') }}"><span class="fa fa-newspaper-o" style="margin-right: 8px;"></span> NOTÍCIAS</a></li>
                        <li><a href="{{ route('shopping') }}"><span class="fa fa-shopping-cart" style="margin-right: 8px;"></span> SHOPPING</a></li>
                        <li><a href="{{ route('parcerias') }}"><span class="fa fa-handshake-o" style="margin-right: 8px;"></span> PARCERIAS</a></li>
                    </ul>
                </li>

                <li>
                    <a href="{{ url('/admin') }}" title="Área Admin"
                        style="color:#fff; padding: 15px 12px; font-size:18px; display: flex; align-items: center;">
                        <span class="fa fa-user"></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>