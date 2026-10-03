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

        {{-- Eventos: árvore aninhada (Evento > Campeonato > Tipo > Jogos > Times > Jogadores) --}}
        {{-- TODO: dados FICTÍCIOS só para validar o visual; depois virão do banco --}}
        @php
          $jogadores = fn (array $nomes) => array_map(fn ($n) => ['titulo' => $n, 'icone' => 'bi-person-fill'], $nomes);
          $time = fn (string $nome, array $nomes) => ['titulo' => $nome, 'icone' => 'bi-shield-fill', 'filhos' => $jogadores($nomes)];
          $jogo = fn (string $titulo, array $times) => ['titulo' => $titulo, 'icone' => 'bi-calendar2-week', 'filhos' => $times];

          $aacj    = $time('AACJ Sub-13', ['Lucas Silva', 'Pedro Santos', 'Gabriel Lima']);
          $leoes   = $time('Leões FC', ['Rafael Costa', 'João Souza', 'Mateus Alves']);
          $estrela = $time('Estrela Azul', ['Davi Rocha', 'Enzo Pereira', 'Arthur Melo']);

          $arvoreEventos = [
            [
              'titulo' => 'Eventos', 'icone' => 'bi-calendar-event',
              'filhos' => [
                [
                  'titulo' => 'Campeonato', 'icone' => 'bi-trophy',
                  'filhos' => [
                    [
                      'titulo' => 'Copa Escola', 'icone' => 'bi-award',
                      'filhos' => [
                        $jogo('AACJ x Leões', [$aacj, $leoes]),
                        $jogo('AACJ x Estrela', [$aacj, $estrela]),
                      ],
                    ],
                    [
                      'titulo' => 'Copa Regional', 'icone' => 'bi-award',
                      'filhos' => [
                        $jogo('Leões x Estrela', [$leoes, $estrela]),
                      ],
                    ],
                  ],
                ],
                [
                  'titulo' => 'Amistoso', 'icone' => 'bi-hand-thumbs-up',
                  'filhos' => [
                    $jogo('AACJ x Leões', [$aacj, $leoes]),
                  ],
                ],
                [
                  'titulo' => 'Individual', 'icone' => 'bi-person',
                  'filhos' => [
                    [
                      'titulo' => 'Exame médico', 'icone' => 'bi-heart-pulse',
                      'filhos' => $jogadores(['Lucas Silva', 'Pedro Santos', 'Gabriel Lima']),
                    ],
                    [
                      'titulo' => 'Avaliação física', 'icone' => 'bi-activity',
                      'filhos' => $jogadores(['Rafael Costa', 'João Souza']),
                    ],
                  ],
                ],
              ],
            ],
          ];
        @endphp
        @include('admin.partials.sidebar-arvore', ['itens' => $arvoreEventos])

        {{-- Categorias --}}
        <li class="nav-item {{ request()->routeIs('admin.categorias.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.categorias.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-tags"></i>
            <p>Categorias <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.categorias.index') }}"
                class="nav-link {{ request()->routeIs('admin.categorias.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Sub-11, Sub-13, Sub-15...</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Calendário --}}
        <li class="nav-item {{ request()->routeIs('admin.calendario.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.calendario.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-calendar3"></i>
            <p>Calendário <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.calendario.index') }}"
                class="nav-link {{ request()->routeIs('admin.calendario.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Eventos e Grade</p>
              </a>
            </li>
          </ul>
        </li>

        {{-- Escalação --}}
        <li class="nav-item {{ request()->routeIs('admin.escalacao.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.escalacao.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-people-fill"></i>
            <p>Escalação <i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="{{ route('admin.escalacao.index') }}"
                class="nav-link {{ request()->routeIs('admin.escalacao.*') ? 'active' : '' }}">
                <i class="nav-icon bi bi-circle"></i>
                <p>Por time</p>
              </a>
            </li>
          </ul>
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

        {{-- Matrículas --}}
        @php $pendentes = \App\Models\Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(); @endphp
        <li class="nav-item {{ request()->routeIs('admin.matriculas.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.matriculas.*') ? 'active' : '' }}">
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