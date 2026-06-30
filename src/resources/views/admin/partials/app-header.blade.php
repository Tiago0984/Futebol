@php
  $notifMatriculas = \App\Models\Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count();
  $notifTotal      = $notifMatriculas;
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
              <a class="nav-link" data-bs-toggle="dropdown" href="javascript:void(0)" aria-label="Notificações">
                <i class="bi bi-bell-fill"></i>
                @if(($notifTotal ?? 0) > 0)
                  <span class="navbar-badge badge text-bg-warning">{{ $notifTotal }}</span>
                @endif
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">
                  {{ $notifTotal ?? 0 }} {{ ($notifTotal ?? 0) == 1 ? 'Notificação' : 'Notificações' }}
                </span>

                @if(($notifMatriculas ?? 0) > 0)
                  <div class="dropdown-divider"></div>
                  <a href="{{ route('admin.matriculas.index') }}" class="dropdown-item">
                    <i class="bi bi-person-plus-fill me-2 text-danger"></i>
                    {{ $notifMatriculas }} matrícula{{ $notifMatriculas != 1 ? 's' : '' }} pendente{{ $notifMatriculas != 1 ? 's' : '' }}
                    <span class="float-end text-secondary fs-7">novo</span>
                  </a>
                @endif

                <div class="dropdown-divider"></div>
                <a href="{{ route('admin.matriculas.index') }}" class="dropdown-item dropdown-footer">
                  Ver todas as notificações
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
            @php $avatarUrl = $adminUser->foto_usuario ? asset('dash/assets/img/user/' . $adminUser->foto_usuario) : asset('dash/assets/img/user/avatar5.png'); @endphp
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
                <li class="user-header text-bg-primary">
                  <img
                    src="{{ $avatarUrl }}"
                    class="rounded-circle shadow"
                    alt="User Image"
                  />
                  <p>
                    {{ $adminUser->nome_usuario }}
                    <small>{{ $adminUser->email_usuario }}</small>
                    <small>Membro desde {{ \Carbon\Carbon::parse($adminUser->criado_em_usuarios)->format('d/m/Y') }}</small>
                  </p>
                </li>
                <!--end::User Image-->
                <!--begin::Menu Footer-->
                <li class="user-footer">
                  <a href="#" class="btn btn-outline-secondary">Perfil</a>
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