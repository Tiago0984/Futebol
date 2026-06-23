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
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
        aria-label="Navegação principal" data-accordion="false" id="navigation">

        <li class="nav-header">GERAL</li>
        <li class="nav-item">
          <a href="{{ route('admin.dashboard') }}"
            class="nav-link {{ request()->routeIs('admin.dash', 'admin.dashboard') ? 'active' : '' }}">
            <i class="nav-icon bi bi-speedometer2"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header">CONTEÚDO DO SITE</li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-newspaper"></i>
            <p>
              Notícias
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.noticias.index') }}" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar notícias</p>
              </a>
          </ul>
        </li>
        <li class="nav-item {{ request()->routeIs('admin.banners.*') ? 'menu-open' : '' }}">
          <a href="#" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-image"></i>
            <p>
              Banners
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.banners.index') }}"
                class="nav-link {{ request()->routeIs('admin.banners.index') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar banners</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-images"></i>
            <p>
              Galeria
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.galeria.index') }}"
                class="nav-link {{ request()->routeIs('admin.galeria.index') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar fotos</p>
              </a>
            </li>
            {{-- <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Adicionar foto</p>
              </a>
            </li> --}}
          </ul>
        </li>

        <li class="nav-header">ESPORTE</li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-trophy"></i>
            <p>
              Campeonatos
              <span class="badge text-bg-warning ms-1 me-auto" style="font-size:0.65rem;">pendente</span>
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar campeonatos</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Adicionar campeonato</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Jogos / partidas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Tabela de classificação</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-shield-fill"></i>
            <p>
              Times
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar times</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Adicionar time</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-tags"></i>
            <p>
              Categorias
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Sub-13, Sub-15...</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-header">PESSOAS</li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-person-badge"></i>
            <p>
              Atletas
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.atletas.index') }}"
                class="nav-link {{ request()->routeIs('admin.atletas.index') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar atletas</p>
              </a>
            </li>
            <li class="nav-item">
              {{-- <a href="{{ route('admin.atletas.index') }}"
                class="nav-link {{ request()->routeIs('admin.atletas.create') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Cadastrar atleta</p>
              </a> --}}
            </li>
            {{-- <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Responsáveis</p>
              </a>
            </li> --}}
          </ul>
        </li>
        @php $pendentes = \App\Models\Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(); @endphp
        <li class="nav-item {{ request()->routeIs('admin.matriculas.*') ? 'menu-open' : '' }}">
          <a href="#" class="nav-link {{ request()->routeIs('admin.matriculas.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clipboard-check"></i>
            <p>
              Matrículas
              @if ($pendentes > 0)
                <span class="badge text-bg-danger ms-1 me-auto" style="font-size:0.65rem;">{{ $pendentes }}</span>
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
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-gear"></i>
            <p>
              Configurações
              <span class="badge text-bg-secondary ms-1 me-auto" style="font-size:0.65rem;">futuro</span>
            </p>
          </a>
        </li>

      </ul>
      <!--end::Sidebar Menu-->
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->

  <!--begin::Sidebar User-->
  @php
    $sidebarUser = auth()->user();
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