<!--begin::Sidebar-->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand">
    <a href="{{ route('admin.dashboard') }}" class="brand-link">
      <img src="{{ asset('dash/assets/img/AdminLTELogo.png') }}" alt="Logo" class="brand-image opacity-75 shadow" />
      <span class="brand-text fw-light">Futebol Admin</span>
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
              <span class="badge text-bg-warning ms-1 me-auto" style="font-size:0.65rem;">pendente</span>
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
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-image"></i>
            <p>
              Banners
              <span class="badge text-bg-warning ms-1 me-auto" style="font-size:0.65rem;">pendente</span>
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar banners</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Adicionar banner</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-images"></i>
            <p>
              Galeria
              <span class="badge text-bg-warning ms-1 me-auto" style="font-size:0.65rem;">pendente</span>
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
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon bi bi-clipboard-check"></i>
            <p>
              Matrículas
              <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-circle"></i>
                <p>Listar matrículas</p>
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
        {{-- <li class="nav-item">
          <a href="#" class="nav-link" style="color: #dc3545;">
            <i class="nav-icon bi bi-box-arrow-right" style="color: #dc3545;"></i>
            <p>Sair</p>
          </a>
        </li> --}}

      </ul>
      <!--end::Sidebar Menu-->
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->

  <!--begin::Sidebar User-->
  <div
    style="position: absolute; bottom: 0; left: 0; right: 0; padding: 0.75rem 1rem; border-top: 1px solid rgba(255,255,255,0.1); background: inherit;">
    <div class="d-flex align-items-center gap-2">
      <div
        class="rounded-circle bg-success d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
        style="width:36px;height:36px;font-size:0.8rem;">AD</div>
      <div style="min-width:0;">
        <div class="text-white fw-semibold" style="font-size:0.875rem;line-height:1.3;">Admin</div>
        <div style="font-size:0.7rem;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          admin@futebol.com</div>
      </div>
    </div>
  </div>
  <!--end::Sidebar User-->
</aside>
<!--end::Sidebar-->