@php
  // Sino do topo: o que chega de fora e pede resposta (InicioDoPainel::avisosDoSino, pelo MenuAdminComposer; as
  // mesmas contagens do menu lateral e do dashboard). Não confundir com o item "Notificações" do menu, que são os
  // avisos enviados aos atletas.
  $sino = $avisosDoSino ?? ['total' => 0, 'itens' => [], 'mensagens' => collect()];
@endphp
<!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list"></i>
              </a>
            </li>
          </ul>
          <!--end::Start Navbar Links-->

          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto">
            <!--begin::Notifications Dropdown Menu-->
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="javascript:void(0)" id="sinoDoPainel"
                 aria-label="Avisos do painel{{ $sino['total'] ? ': ' . $sino['total'] : '' }}" title="Avisos do painel">
                <i class="bi bi-bell-fill"></i>
                @if($sino['total'] > 0)
                  <span class="navbar-badge badge text-bg-warning">{{ $sino['total'] > 99 ? '99+' : $sino['total'] }}</span>
                @endif
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end sino-painel" id="avisosDoSino">
                <span class="dropdown-item dropdown-header">
                  @if($sino['total'])
                    {{ $sino['total'] }} {{ $sino['total'] == 1 ? 'aviso' : 'avisos' }} para ver
                  @else
                    Nenhum aviso novo
                  @endif
                </span>

                @foreach($sino['itens'] as $item)
                  <div class="dropdown-divider"></div>
                  <a href="{{ $item['url'] }}" class="dropdown-item sino-painel__item">
                    <i class="bi bi-{{ $item['icone'] }} me-2 text-{{ $item['nivel'] }}"></i>{{ $item['texto'] }}
                  </a>
                  @if(($item['chave'] ?? null) === 'fale')
                    @foreach($sino['mensagens'] as $mensagem)
                      <a href="{{ route('admin.fale-conosco.show', $mensagem->id_fale_conosco) }}" class="dropdown-item sino-painel__mensagem">
                        <span class="sino-painel__nome">{{ $mensagem->nome_fale_conosco }}</span>
                        <span class="sino-painel__quando">{{ $mensagem->data_fale_conosco->locale('pt_BR')->diffForHumans() }}</span>
                        <span class="sino-painel__assunto">{{ \Illuminate\Support\Str::limit($mensagem->assunto_fale_conosco, 45) }}</span>
                      </a>
                    @endforeach
                  @endif
                @endforeach

                @unless($sino['total'])
                  <div class="dropdown-divider"></div>
                  <span class="dropdown-item text-body-secondary sino-painel__vazio">
                    <i class="bi bi-check-circle me-2 text-success"></i>Sem mensagens, matrículas ou fotos para ver.
                  </span>
                @endunless

                <div class="dropdown-divider"></div>
                <a href="{{ route('admin.dashboard') }}" class="dropdown-item dropdown-footer">
                  Ver tudo no dashboard
                </a>
              </div>
            </li>
            <!--end::Notifications Dropdown Menu-->

            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
              </a>
            </li>
            <!--end::Fullscreen Toggle-->

            <!--begin::User Menu Dropdown-->
            @php $adminUser = Auth::guard('admin')->user(); @endphp
            @php $avatarUrl = $adminUser->urlFoto(); @endphp
            <li class="nav-item dropdown user-menu">
              <a href="javascript:void(0)" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="{{ $avatarUrl }}"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                />
                <span class="d-none d-md-inline">{{ $adminUser->nome_usuario }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!--begin::User Image-->
                <li class="user-header menu-usuario-topo">
                  <img
                    src="{{ $avatarUrl }}"
                    class="rounded-circle shadow"
                    alt="Foto de {{ $adminUser->nome_usuario }}"
                  />
                  <p>
                    {{ $adminUser->nome_usuario }}
                    <small>{{ \App\Models\User::NIVEIS[$adminUser->nivel_usuario] ?? $adminUser->nivel_usuario }}</small>
                    <small>{{ $adminUser->email_usuario }}</small>
                    @if ($adminUser->criado_em_usuarios)
                    <small>Membro desde {{ \Carbon\Carbon::parse($adminUser->criado_em_usuarios)->format('d/m/Y') }}</small>
                    @endif
                  </p>
                </li>
                <!--end::User Image-->
                <!--begin::Menu Footer-->
                <li class="user-footer">
                  <a href="{{ route('admin.perfil') }}" class="btn btn-outline-secondary"><i class="bi bi-person-gear"></i> Meu perfil</a>
                  <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger float-end">Sair</button>
                  </form>
                </li>
                <!--end::Menu Footer-->
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
        <!--end::Container-->
      </nav>
<!--end::Header-->