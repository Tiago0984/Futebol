@extends('layout.admin')

@section('content')
<!--begin::App Main-->
<main class="app-main">
  <!--begin::App Content Header-->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <p class="dash-greeting-label">Bem-vindo de volta,</p>
          <h3 class="dash-greeting-name">{{ Auth::guard('admin')->user()->nome_usuario }}</h3>
        </div>
        <div class="col-sm-5 text-sm-end">
          <p class="dash-date">{{ \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
        </div>
      </div>
    </div>
  </div>
  <!--end::App Content Header-->

  <!--begin::App Content-->
  <div class="app-content">
    <div class="container-fluid">

      <!--begin::Stats Row-->
      <div class="row mb-4 g-3">
        <div class="col-lg-3 col-sm-6">
          <div class="stat-card">
            <div>
              <div class="stat-card-number">{{ $stats['noticias'] }}</div>
              <div class="stat-card-label">Notícias</div>
              <a href="{{ route('admin.noticias.index') }}" class="stat-card-link">
                Gerenciar <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="stat-card-icon" style="background:var(--aacj-red);">
              <i class="bi bi-newspaper"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="stat-card">
            <div>
              <div class="stat-card-number">{{ $stats['campeonatos'] }}</div>
              <div class="stat-card-label">Campeonatos</div>
              <a href="#" class="stat-card-link">
                Gerenciar <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="stat-card-icon" style="background:var(--aacj-red-dark);">
              <i class="bi bi-trophy"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="stat-card">
            <div>
              <div class="stat-card-number">{{ $stats['times'] }}</div>
              <div class="stat-card-label">Times</div>
              <a href="#" class="stat-card-link">
                Gerenciar <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="stat-card-icon" style="background:#374151;">
              <i class="bi bi-shield-fill"></i>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="stat-card">
            <div>
              <div class="stat-card-number">{{ $stats['jogos'] }}</div>
              <div class="stat-card-label">Jogos</div>
              <a href="#" class="stat-card-link">
                Gerenciar <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <div class="stat-card-icon" style="background:#1e293b;">
              <i class="bi bi-calendar-event"></i>
            </div>
          </div>
        </div>
      </div>
      <!--end::Stats Row-->

      <!--begin::Seções-->
      <div class="row">
        <div class="col-12 mb-3">
          <h5 class="fw-semibold">Seções</h5>
        </div>

        <div class="col-md-6 mb-3">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <h6 class="card-title fw-semibold mb-1">Conteúdo do site</h6>
              <p class="card-text text-muted small">Notícias, banners e galeria — tudo que aparece no site público. CRUDs simples, sem joins.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <h6 class="card-title fw-semibold mb-1">Esporte</h6>
              <p class="card-text text-muted small">Campeonatos, times, categorias e jogos. Classificação já funciona dinamicamente.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <h6 class="card-title fw-semibold mb-1">Pessoas</h6>
              <p class="card-text text-muted small">Atletas é o mais complexo — form único que salva em 4 tabelas: atleta, responsável, endereço e pivot.</p>
            </div>
          </div>
        </div>

        <div class="col-md-6 mb-3">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex align-items-center gap-2 mb-1">
                <h6 class="card-title fw-semibold mb-0">Badges</h6>
                <span class="badge text-bg-warning" style="font-size:0.65rem;">pendente</span>
              </div>
              <p class="card-text text-muted small mb-1">CRUD ainda não feito</p>
              <p class="card-text text-muted small">Opcional / próxima fase</p>
            </div>
          </div>
        </div>

      </div>
      <!--end::Seções-->

    </div>
  </div>
  <!--end::App Content-->
</main>
<!--end::App Main-->
@endsection
