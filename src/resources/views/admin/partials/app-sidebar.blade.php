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

        <li class="nav-item">
          <a href="{{ route('admin.fale-conosco.index') }}" class="nav-link {{ request()->routeIs('admin.fale-conosco.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-envelope"></i>
            <p>
              Fale conosco
              @if ($faleNaoLidas > 0)
                <span class="badge text-bg-danger menu-contador" title="Mensagens não lidas">{{ $faleNaoLidas }}</span>
              @endif
            </p>
          </a>
        </li>

        <li class="nav-header">CONTEÚDO DO SITE</li>

        {{-- Notícias, Banners, Galeria e Vídeos: sem subitens, cada um abre a lista dele --}}
        <li class="nav-item">
          <a href="{{ route('admin.noticias.index') }}" class="nav-link {{ request()->routeIs('admin.noticias.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-newspaper"></i>
            <p>Notícias</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ route('admin.banners.index') }}" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-image"></i>
            <p>Banners</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ route('admin.galeria.index') }}" class="nav-link {{ request()->routeIs('admin.galeria.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-images"></i>
            <p>Galeria</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ route('admin.videos.index') }}" class="nav-link {{ request()->routeIs('admin.videos.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-camera-video"></i>
            <p>Vídeos</p>
          </a>
        </li>

        <li class="nav-header">ESPORTE</li>

        {{-- Eventos: só os ramos; o detalhe (jogos, times, atletas) fica nas páginas. Dados e item ativo
             vêm do App\View\Composers\MenuAdminComposer --}}
        @php
          $ativo = fn (string $chave) => $menuAtivo === $chave;
          $emCampeonatos = $ativo('ramo:campeonatos') || $ativo('campeonatos') || $ativo('jogos-dos-campeonatos');
          $emAmistosos = $ativo('ramo:amistosos') || $ativo('times-dos-amistosos');
          $emEventos = $emCampeonatos || $emAmistosos || $ativo('calendario') || str_starts_with((string) $menuAtivo, 'ramo:');
          $ramosSimples = [
            'individuais' => 'bi-person-check',
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

            {{-- Campeonatos: o texto abre a tela de Campeonatos com todos (sem filtro); a seta só abre e fecha o
                 "Jogos". Ativo e aberto em qualquer página do ramo Campeonatos --}}
            <li class="nav-item {{ $emCampeonatos ? 'menu-open' : '' }}">
              <a href="{{ route('admin.campeonatos.index') }}" aria-expanded="{{ $emCampeonatos ? 'true' : 'false' }}"
                class="nav-link {{ $emCampeonatos ? 'active' : '' }}" id="gavetaCampeonatos">
                <i class="nav-icon bi bi-trophy"></i>
                <p>Campeonatos <i class="nav-arrow bi bi-chevron-right"></i></p>
              </a>
              <ul class="nav nav-treeview">
                {{-- "Jogos", sem subitens: os jogos de todos os campeonatos (filtro "Todos"; o filtro da tela troca
                     para um campeonato). Ativo também na tela de um campeonato, nos jogadores de um time e na lista
                     de Jogos filtrada só por um campeonato. Sem atletas aqui --}}
                <li class="nav-item">
                  <a href="{{ route('admin.campeonatos.jogos') }}"
                    class="nav-link {{ $ativo('jogos-dos-campeonatos') ? 'active' : '' }}"
                    id="itemJogosCampeonatos" title="Jogos dos campeonatos">
                    <i class="nav-icon bi bi-flag"></i>
                    <p>Jogos</p>
                  </a>
                </li>
              </ul>
            </li>

            {{-- Amistosos, sem subitens: abre a tela "Jogos dos amistosos" (um bloco por jogo com os dois times, em
                 cartões; só leitura). Ativo também na lista de Jogos dos amistosos e na tela de um amistoso --}}
            <li class="nav-item">
              <a href="{{ route('admin.amistosos.times') }}"
                class="nav-link {{ $emAmistosos ? 'active' : '' }}" id="itemAmistosos" title="Jogos dos amistosos">
                <i class="nav-icon bi bi-hand-thumbs-up"></i>
                <p>Amistosos</p>
              </a>
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

        {{-- Grade de treino, sem subitens: o "Gerar agenda do mês" fica no botão da própria grade (ativo também nele) --}}
        <li class="nav-item">
          <a href="{{ route('admin.calendario.index', ['tab' => 'grade']) }}" class="nav-link {{ $ativo('grade') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clock"></i>
            <p>Grade de treino</p>
          </a>
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

        {{-- Times (cadastro; quem joga por eles é a convocação de cada campeonato) --}}
        <li class="nav-item">
          <a href="{{ route('admin.times.index') }}" class="nav-link {{ $ativo('times') ? 'active' : '' }}">
            <i class="nav-icon bi bi-shield-fill"></i>
            <p>Times</p>
          </a>
        </li>

        {{-- Comissão técnica (Etapa C1): o perfil que o app abre pelo responsável do compromisso --}}
        <li class="nav-item">
          <a href="{{ route('admin.comissao.index') }}" class="nav-link {{ $ativo('comissao') ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-badge"></i>
            <p>Comissão técnica</p>
          </a>
        </li>

        {{-- Locais (Etapa L1): o endereço e o link do mapa do "Abrir mapa" do app --}}
        <li class="nav-item">
          <a href="{{ route('admin.locais.index') }}" class="nav-link {{ $ativo('locais') ? 'active' : '' }}">
            <i class="nav-icon bi bi-geo-alt-fill"></i>
            <p>Locais</p>
          </a>
        </li>

        <li class="nav-header">PESSOAS</li>

        {{-- Atletas, sem subitens --}}
        <li class="nav-item">
          <a href="{{ route('admin.atletas.index') }}" class="nav-link {{ request()->routeIs('admin.atletas.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-person-badge"></i>
            <p>
              Atletas
              @if ($fotosParaAprovar > 0)
                <span class="badge text-bg-danger menu-contador" title="Fotos enviadas pelo app para aprovar">{{ $fotosParaAprovar }}</span>
              @endif
            </p>
          </a>
        </li>

        {{-- Matrículas (contador de pendentes vem do MenuAdminComposer) --}}
        <li class="nav-item {{ request()->routeIs('admin.matriculas.*') ? 'menu-open' : '' }}">
          <a href="javascript:void(0)" class="nav-link {{ request()->routeIs('admin.matriculas.*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clipboard-check"></i>
            <p>
              Matrículas
              @if ($matriculasPendentes > 0)
                <span class="badge text-bg-danger menu-contador" title="Matrículas aguardando aprovação">{{ $matriculasPendentes }}</span>
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

  {{-- Largura do menu: arrastar a borda direita (dois cliques voltam ao padrão) ou o botão de alargar/estreitar.
       Fica guardada no navegador (localStorage 'aacj-largura-menu'); o head aplica antes de pintar. --}}
  <div class="menu-alca" id="menuAlca" role="separator" aria-orientation="vertical"
    aria-label="Arraste para mudar a largura do menu" title="Arraste para alargar o menu (dois cliques: largura padrão)"></div>
  <button type="button" class="menu-alca-botao" id="menuAlcaBotao" aria-label="Alargar o menu" title="Alargar o menu">
    <i class="bi bi-chevron-double-right"></i>
  </button>
</aside>
<!--end::Sidebar-->

<script>
// Abre/fecha submenus sem recarregar página, preservando estado da rota ativa
// Itens sem página: o link inteiro alterna. Itens com página: só a seta alterna, o texto navega.
// A animação é a do AdminLTE (adminlte.Treeview). Antes só a classe era trocada: o item aberto no carregamento
// (o da página atual) ganha display:block inline do AdminLTE e não fechava; os outros abriam sem animação.
document.addEventListener('DOMContentLoaded', function () {
  var DURACAO = 300;

  // Item fechado com algo selecionado dentro: mostra, abaixo do nome, o caminho do selecionado
  // (ex.: "Eventos" fechado em Jogos dos campeonatos: "Campeonatos › Jogos"). Some quando o item abre.
  function textoDoLink(link) {
    var p = link.querySelector(':scope > p');
    if (!p) return '';
    for (var i = 0; i < p.childNodes.length; i++) {
      if (p.childNodes[i].nodeType === Node.TEXT_NODE && p.childNodes[i].textContent.trim()) {
        return p.childNodes[i].textContent.trim();
      }
    }
    return '';
  }

  document.querySelectorAll('.sidebar-menu .nav-item').forEach(function (item) {
    var sub = item.querySelector(':scope > .nav-treeview');
    var link = item.querySelector(':scope > a.nav-link');
    if (!sub || !link) return;
    var caminho = Array.prototype.map.call(sub.querySelectorAll('.nav-link.active'), textoDoLink).filter(Boolean);
    if (!caminho.length) return;
    item.classList.add('tem-selecionado');
    var p = link.querySelector(':scope > p');
    if (!p) return;
    var aviso = document.createElement('span');
    aviso.className = 'menu-selecionado';
    aviso.title = 'Selecionado: ' + caminho.join(' › ');
    aviso.innerHTML = '<i class="bi bi-arrow-return-right"></i> ';
    aviso.appendChild(document.createTextNode(caminho.join(' › ')));
    p.appendChild(aviso);
  });

  document.querySelectorAll('.sidebar-menu .nav-item > a.nav-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var parent = this.closest('.nav-item');
      if (!parent.querySelector(':scope > .nav-treeview')) return;
      var semPagina = this.getAttribute('href') === 'javascript:void(0)';
      if (!semPagina && !e.target.closest('.nav-arrow')) return;
      e.preventDefault();
      // Clique durante a animação é ignorado (as duas animações se atropelariam)
      if (parent.dataset.animando) return;
      var abrir = !parent.classList.contains('menu-open');
      if (window.adminlte && adminlte.Treeview) {
        parent.dataset.animando = '1';
        var arvore = new adminlte.Treeview(parent, { accordion: false, animationSpeed: DURACAO });
        abrir ? arvore.open() : arvore.close();
        setTimeout(function () { delete parent.dataset.animando; }, DURACAO + 20);
      } else {
        parent.classList.toggle('menu-open', abrir);
      }
      // Gaveta com estado (ex.: Campeonatos): aria-expanded acompanha o abrir/fechar
      if (this.hasAttribute('aria-expanded')) this.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    });
  });

  // Largura do menu (para ler o que ficou cortado com "…"). Padrão 250px (o do AdminLTE); alargado 360px;
  // arrastando, de 250px até 480px (ou metade da tela, o que for menor).
  var PADRAO = 250, ALARGADO = 360, MAXIMO = 480, CHAVE = 'aacj-largura-menu';
  var raiz = document.documentElement;
  var alca = document.getElementById('menuAlca');
  var botao = document.getElementById('menuAlcaBotao');
  if (!alca || !botao) return;

  function limite(px) {
    return Math.round(Math.max(PADRAO, Math.min(px, MAXIMO, Math.floor(window.innerWidth / 2))));
  }
  function larguraAtual() {
    return parseInt(getComputedStyle(raiz).getPropertyValue('--lte-sidebar-width'), 10) || PADRAO;
  }
  function aplicar(px, guardar) {
    px = limite(px);
    if (px === PADRAO) raiz.style.removeProperty('--lte-sidebar-width');
    else raiz.style.setProperty('--lte-sidebar-width', px + 'px');
    var alargado = px > PADRAO;
    botao.querySelector('i').className = 'bi ' + (alargado ? 'bi-chevron-double-left' : 'bi-chevron-double-right');
    botao.title = alargado ? 'Voltar o menu à largura padrão' : 'Alargar o menu';
    botao.setAttribute('aria-label', botao.title);
    if (guardar) {
      try {
        if (alargado) localStorage.setItem(CHAVE, String(px)); else localStorage.removeItem(CHAVE);
      } catch (e) {}
    }
  }
  aplicar(larguraAtual(), false);

  botao.addEventListener('click', function () {
    aplicar(larguraAtual() > PADRAO ? PADRAO : ALARGADO, true);
  });
  alca.addEventListener('dblclick', function () { aplicar(PADRAO, true); });

  // Arrastar a borda: sem a transição do AdminLTE (a barra acompanha o mouse) e sem selecionar texto
  alca.addEventListener('pointerdown', function (e) {
    if (e.button !== 0) return;
    e.preventDefault();
    alca.setPointerCapture(e.pointerId);
    raiz.classList.add('menu-redimensionando');
    var esquerda = alca.closest('.app-sidebar').getBoundingClientRect().left;
    function mover(ev) { aplicar(ev.clientX - esquerda, false); }
    function soltar(ev) {
      alca.removeEventListener('pointermove', mover);
      alca.removeEventListener('pointerup', soltar);
      alca.removeEventListener('pointercancel', soltar);
      raiz.classList.remove('menu-redimensionando');
      aplicar(larguraAtual(), true);
    }
    alca.addEventListener('pointermove', mover);
    alca.addEventListener('pointerup', soltar);
    alca.addEventListener('pointercancel', soltar);
  });
});
</script>
