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
                                <a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" class="header-news-link">
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
                <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5">
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
            <ul class="nav navbar-nav navbar-right nav-main-list">
                <li class="{{ request()->routeIs('home') ? 'active' : '' }}">
                    <a href="{{ route('home') }}">HOME</a>
                </li>
                <li class="{{ request()->routeIs('sobre') ? 'active' : '' }}">
                    <a href="{{ route('sobre') }}">SOBRE</a>
                </li>
                <li class="{{ request()->routeIs('calendario') ? 'active' : '' }}">
                    <a href="{{ route('calendario') }}">CALENDÁRIO</a>
                </li>

                <li class="nav-highlight-scout {{ request()->routeIs('jogadores.vitrine') ? 'active' : '' }}">
                    <a href="{{ route('jogadores.vitrine') }}">
                        <span class="fa fa-search-plus nav-scout-icon"></span> JOGADORES
                    </a>
                </li>

                <li class="dropdown {{ request()->routeIs('campeonato*') ? 'active' : '' }}">
                    <a href="{{ route('campeonato') }}" class="dropdown-toggle" data-toggle="dropdown">
                        CAMPEONATOS <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        @isset($campeonatosMenu)
                        <li class="{{ request()->routeIs('campeonato') ? 'active' : '' }}">
                            <a href="{{ route('campeonato') }}">
                                <span class="fa fa-list nav-submenu-icon"></span> VER TODOS
                            </a>
                        </li>
                        @if($campeonatosMenu->count() > 0)
                        <li class="dropdown-section-label">EDIÇÕES</li>
                        @foreach ($campeonatosMenu as $camp)
                        <li class="{{ url()->current() == route('campeonato.show', $camp->id_campeonato) ? 'active' : '' }}">
                            <a href="{{ route('campeonato.show', $camp->id_campeonato) }}">
                                <span class="fa fa-trophy nav-submenu-icon"></span> {{ $camp->nome_campeonato }}
                            </a>
                        </li>
                        @endforeach
                        @endif
                        @endisset
                    </ul>
                </li>

                <li class="nav-item-matricula {{ request()->routeIs('cadastro*') ? 'active' : '' }}">
                    <a href="{{ route('cadastro.index') }}">MATRICULE-SE</a>
                </li>

                <li class="{{ request()->routeIs('contato') ? 'active' : '' }}">
                    <a href="{{ route('contato') }}">CONTATO</a>
                </li>

                <li class="dropdown submenu-listras">
                    <a href="#" class="dropdown-toggle nav-link-bars" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                        <span class="fa fa-bars"></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li class="{{ request()->routeIs('noticias*') ? 'active' : '' }}">
                            <a href="{{ route('noticias.index') }}"><span class="fa fa-newspaper-o nav-submenu-icon"></span> NOTÍCIAS</a>
                        </li>
                        <li class="{{ request()->routeIs('shopping*') ? 'active' : '' }}">
                            <a href="{{ route('shopping') }}"><span class="fa fa-shopping-cart nav-submenu-icon"></span> SHOPPING</a>
                        </li>
                        <li class="{{ request()->routeIs('parcerias*') ? 'active' : '' }}">
                            <a href="{{ route('parcerias') }}"><span class="fa fa-handshake-o nav-submenu-icon"></span> PARCERIAS</a>
                        </li>
                    </ul>
                </li>

                <li>
                    <a href="{{ url('/admin') }}" title="Área Admin" class="nav-link-admin">
                        <span class="fa fa-user"></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>
