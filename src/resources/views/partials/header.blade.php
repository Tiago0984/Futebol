<div class="navbar navbar-main navbar-fixed-top">
    <div class="header-top">
        <div class="container">
            <div class="top-bar-inner">

                {{-- NEWS TICKER --}}
                <div class="top-bar-news">
                    <span class="top-bar-news-label">NEWS:</span>
                    <div class="info-item top-bar-ticker">
                        @if(isset($noticiasRecentes) && $noticiasRecentes->count() > 0)
                        @foreach($noticiasRecentes as $recente)
                        <div>
                            <a href="{{ route('site.noticias.show-noticia', $recente->id_noticia) }}" class="header-news-link">
                                {{ \Illuminate\Support\Str::limit($recente->titulo_noticia, 70, '...') }}
                            </a>
                        </div>
                        @endforeach
                        @else
                        <div>Nenhuma notícia recente publicada.</div>
                        @endif
                    </div>
                </div>

                {{-- ÍCONES DE NAVEGAÇÃO --}}
                <nav class="top-bar-nav">
                    <a href="{{ route('home') }}" class="top-bar-nav-icon {{ request()->routeIs('home') ? 'active' : '' }}" title="Home"><i class="fa fa-home"></i></a>

                    <a href="{{ route('sobre') }}" class="top-bar-nav-icon {{ request()->routeIs('sobre') ? 'active' : '' }}" title="Sobre"><i class="fa fa-info-circle"></i></a>

                    <a href="{{ route('calendario') }}" class="top-bar-nav-icon {{ request()->routeIs('calendario') ? 'active' : '' }}" title="Calendário"><i class="fa fa-calendar"></i></a>

                    <a href="{{ route('noticias.index') }}" class="top-bar-nav-icon {{ request()->routeIs('noticias*') ? 'active' : '' }}" title="Notícias"><i class="fa fa-file-text-o"></i></a>

                    <!-- <a href="{{ route('shopping') }}"      class="top-bar-nav-icon {{ request()->routeIs('shopping*') ? 'active' : '' }}"     title="Shopping"><i class="fa fa-shopping-cart"></i></a> -->

                    <a href="{{ route('galeria.index') }}" class="top-bar-nav-icon {{ request()->routeIs('galeria*') ? 'active' : '' }}" title="Galeria"><i class="fa fa-picture-o"></i></a>

                    <a href="{{ route('contato') }}" class="top-bar-nav-icon {{ request()->routeIs('contato') ? 'active' : '' }}" title="Contato"><i class="fa fa-paper-plane"></i></a>

                    <a href="{{ url('/admin') }}" class="top-bar-nav-icon" title="Login / Área Admin"><i class="fa fa-user"></i></a>
                </nav>

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
                <li class="{{ request()->routeIs('jogadores.vitrine') ? 'active' : '' }}">
                    <a href="{{ route('jogadores.vitrine') }}">JOGADORES</a>
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

                <li class="{{ request()->routeIs('parcerias*') ? 'active' : '' }}">
                    <a href="{{ route('parcerias') }}">PARCERIAS</a>
                </li>
            </ul>
        </nav>
    </div>
</div>