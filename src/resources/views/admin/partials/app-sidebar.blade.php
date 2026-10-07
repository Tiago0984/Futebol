<!--begin::Sidebar-->
<aside class="app-sidebar shadow">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand">
    <a href="{{ route('admin.dashboard') }}" class="brand-link">
      <img src="{{ asset('futebol/images/logo2.png') }}" alt="AACJ Futebol" class="brand-image" style="width:36px;height:36px;object-fit:contain;" />
      <span class="brand-text"><span class="brand-aacj">AACJ</span> <span class="brand-futebol">Futebol</span></span>
    </a>
  </div>
  <!--end::Sidebar Brand-->

  <!--begin::Sidebar Wrapper-->
  <div class="sidebar-wrapper">
    <nav class="mt-2">
      <!--begin::Sidebar Menu-->
      <ul class="nav sidebar-menu flex-column" role="navigation"
        aria-label="Navegação principal" id="navigation">

        <li class="nav-header">GERAL</li>
        <li class="nav-item">
          <a href="{{ route('admin.dashboard') }}"
            class="nav-link {{ request()->routeIs('admin.dash', 'admin.dashboard') ? 'active' : '' }}">
            <i class="nav-icon bi bi-speedometer2"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">CONTEÚDO DO SITE</li>

        {{-- Notícias --}}
        <li class="nav-item {{ request()->routeIs('admin.noticias.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.noticias.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-newspaper"></i>
            <p>Notícias <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.noticias.index') }}"
                class="nav-link {{ request()->routeIs('admin.noticias.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar notícias</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Banners --}}
        <li class="nav-item {{ request()->routeIs('admin.banners.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-image"></i>
            <p>Banners <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.banners.index') }}"
                class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar banners</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Galeria --}}
        <li class="nav-item {{ request()->routeIs('admin.galeria.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.galeria.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-images"></i>
            <p>Galeria <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.galeria.index') }}"
                class="nav-link {{ request()->routeIs('admin.galeria.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar fotos</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Vídeos --}}
        <li class="nav-item {{ request()->routeIs('admin.videos.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.videos.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-camera-video"></i>
            <p>Vídeos <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.videos.index') }}"
                class="nav-link {{ request()->routeIs('admin.videos.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar vídeos</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-header">ESPORTE</li>

        {{-- Eventos: só os ramos; o detalhe (jogos, times, atletas) fica nas páginas. Dados e item ativo
             vêm do App\View\Composers\MenuAdminComposer --}}
        @php
          $ativo = fn (string $chave) => $menuAtivo === $chave;
          $emCampeonatos = $ativo('ramo:campeonatos') || $ativo('campeonatos') || str_starts_with((string) $menuAtivo, 'campeonato:');
          $emEventos = $emCampeonatos || $ativo('calendario') || str_starts_with((string) $menuAtivo, 'ramo:');
          $ramosSimples = [
            'amistosos'   => 'bi-hand-thumbs-up',
            'treinos'     => 'bi-stopwatch',
            'individuais' => 'bi-person-check',
            'outros'      => 'bi-three-dots',
          ];
        @endphp
        <li class="nav-item {{ $emEventos ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ $emEventos ? 'active' : '' }}">
            <i class="nav-icon bi bi-calendar-event"></i>
            <p>Eventos <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.calendario.index') }}" class="nav-link {{ $ativo('calendario') ? 'active' : '' }}">
                <i class="nav-icon bi bi-calendar3"></i>
                <p>Calendário</p>
              </a>
            </li>

            {{-- Campeonatos: o texto abre os eventos do ramo; a seta mostra os em andamento e "Ver todos" --}}
            <li class="nav-item {{ $emCampeonatos ? 'menu-open' : '' }}">
              <a href="{{ \App\Models\EventoCalendario::urlDoRamo('campeonatos') }}"
                class="nav-link {{ $ativo('ramo:campeonatos') ? 'active' : '' }}">
                <i class="nav-icon bi bi-trophy"></i>
                <p>Campeonatos <i class="nav-arrow bi bi-chevron-right"></i></p>
              </a>
              <ul class="nav nav-treeview">
                @foreach ($campeonatosEmAndamento as $camp)
                <li class="nav-item">
                  <a href="{{ route('admin.jogos.index', ['campeonato' => $camp->id_campeonato]) }}"
                    class="nav-link {{ $ativo('campeonato:' . $camp->id_campeonato) ? 'active' : '' }}"
                    title="Jogos de {{ $camp->nome_campeonato }}">
                    <i class="nav-icon bi bi-award"></i>
                    <p>{{ $camp->nome_campeonato }}</p>
                  </a>
                </li>
                @endforeach
                <li class="nav-item">
                  <a href="{{ route('admin.campeonatos.index') }}" class="nav-link {{ $ativo('campeonatos') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-list-ul"></i>
                    <p>Ver todos</p>
                  </a>
                </li>
              </ul>
            </li>

            @foreach ($ramosSimples as $ramo => $icone)
            <li class="nav-item">
              <a href="{{ \App\Models\EventoCalendario::urlDoRamo($ramo) }}"
                class="nav-link {{ $ativo('ramo:' . $ramo) ? 'active' : '' }}">
                <i class="nav-icon bi {{ $icone }}"></i>
                <p>{{ \App\Models\EventoCalendario::RAMOS[$ramo] }}</p>
              </a>
            </li>
            @endforeach
          </ul>
        </li>

        {{-- Jogos --}}
        <li class="nav-item">
          <a href="{{ route('admin.jogos.index') }}" class="nav-link {{ $ativo('jogos') ? 'active' : '' }}">
            <i class="nav-icon bi bi-flag-fill"></i>
            <p>Jogos</p>
          </a>
        </li>

        {{-- Grade de treino: o texto abre a grade; a seta mostra "Gerar agenda do mês" --}}
        <li class="nav-item {{ $ativo('grade') ? 'menu-open' : '' }}">
          <a href="{{ route('admin.calendario.index', ['tab' => 'grade']) }}"
            class="nav-link {{ $ativo('grade') && ! request()->routeIs('admin.calendario.grade.previa') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clock"></i>
            <p>Grade de treino <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.calendario.grade.previa', ['mes' => now()->format('Y-m')]) }}"
                class="nav-link {{ request()->routeIs('admin.calendario.grade.previa') ? 'active' : '' }}">
                <i class="nav-icon bi bi-calendar-plus"></i>
                <p>Gerar agenda do mês</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Notificações enviadas aos atletas (todas, inclusive as de agenda do mês) --}}
        <li class="nav-item">
          <a href="{{ route('admin.notificacoes.index') }}" class="nav-link {{ $ativo('notificacoes') ? 'active' : '' }}">
            <i class="nav-icon bi bi-bell"></i>
            <p>Notificações</p>
          </a>
        </li>

        <li class="nav-header">CADASTROS</li>

        <li class="nav-item">
          <a href="{{ route('admin.categorias.index') }}" class="nav-link {{ $ativo('categorias') ? 'active' : '' }}">
            <i class="nav-icon bi bi-tags"></i>
            <p>Categorias</p>
          </a>
        </li>

        {{-- Times (o elenco de cada time interno abre pela linha dele) --}}
        <li class="nav-item">
          <a href="{{ route('admin.times.index') }}" class="nav-link {{ $ativo('times') ? 'active' : '' }}">
            <i class="nav-icon bi bi-shield-fill"></i>
            <p>Times</p>
          </a>
        </li>

        <li class="nav-header">PESSOAS</li>

        {{-- Atletas --}}
        <li class="nav-item {{ request()->routeIs('admin.atletas.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.atletas.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-badge"></i>
            <p>Atletas <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.atletas.index') }}"
                class="nav-link {{ request()->routeIs('admin.atletas.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar atletas</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Matrículas (contador de pendentes vem do MenuAdminComposer) --}}
        <li class="nav-item {{ request()->routeIs('admin.matriculas.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.matriculas.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clipboard-check"></i>
            <p>
              Matrículas
              @if ($matriculasPendentes > 0)
                <span class="badge text-bg-danger ms-1 me-auto" style="font-size:0.65rem;">{{ $matriculasPendentes }}</span>
              @endif
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.matriculas.index') }}"
                class="nav-link {{ request()->routeIs('admin.matriculas.index') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar matrículas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="{{ route('admin.matriculas.rejeitadas') }}"
                class="nav-link {{ request()->routeIs('admin.matriculas.rejeitadas') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Matrículas Rejeitadas</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-header">SISTEMA</li>
        <li class="nav-item">
          <a href="{{ url('/') }}" class="nav-link" target="_blank">
            <i class="nav-icon bi bi-box-arrow-up-right"></i>
            <p>Ver site</p>
          </a>
        </li>
        <li class="nav-item {{ request()->routeIs('admin.configuracoes.*') ? 'menu-open' : '' }}">
          <a href="{{ route('admin.configuracoes.index') }}" class="nav-link {{ request()->routeIs('admin.configuracoes.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-gear"></i>
            <p>Configurações</p>
          </a>
        </li>

      </ul>
      <!--end::Sidebar Menu-->
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->

  <!--begin::Sidebar User-->
  @php
    $sidebarUser = auth('admin')->user();
    $sidebarInitials = 'AD';
    if ($sidebarUser) {
      $parts = explode(' ', trim($sidebarUser->nome_usuario));
      $sidebarInitials = strtoupper(
        substr($parts[0], 0, 1) .
        (isset($parts[1]) ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1))
      );
    }
  @endphp
  <div class="sidebar-user-wrap">
    <div class="d-flex align-items-center gap-2">
      <div class="user-avatar">{{ $sidebarInitials }}</div>
      <div style="min-width:0;">
        <div class="user-name">{{ $sidebarUser?->nome_usuario ?? 'Admin' }}</div>
        <div class="user-email">{{ $sidebarUser?->email_usuario ?? '' }}</div>
      </div>
    </div>
  </div>
  <!--end::Sidebar User-->
</aside>
<!--end::Sidebar-->

<script>
// Abre/fecha submenus sem recarregar página, preservando estado da rota ativa
// Itens sem página: o link inteiro alterna. Itens com página: só a seta alterna, o texto navega.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sidebar-menu .nav-item > a.nav-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var parent = this.closest('.nav-item');
      if (!parent.querySelector(':scope > .nav-treeview')) return;
      var semPagina = this.getAttribute('href') === 'javascript:void(0)';
      if (!semPagina && !e.target.closest('.nav-arrow')) return;
      e.preventDefault();
      parent.classList.toggle('menu-open');
    });
  });
});
</script>