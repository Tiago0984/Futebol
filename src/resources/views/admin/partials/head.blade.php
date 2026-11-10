 <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Painel Administrativo | AACJ</title>

    {{-- Ícone do painel (10/11/2026): o escudo da AACJ com uma engrenagem, para separar a aba do painel da do site --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('dash/favicon/favicon-32.png') }}" />
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('dash/favicon/favicon-192.png') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('dash/favicon/apple-touch-icon.png') }}" />

    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#e31c1c" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <!--end::Accessibility Meta Tags-->

    <!--begin::Primary Meta Tags-->
    <meta name="title" content="Painel Administrativo | AACJ" />
    <meta name="author" content="AACJ" />
    <meta
      name="description"
      content="Sistema de gerenciamento interno da Associação Atlética Cohab Jucelino e Adjacência."
    />
    <meta
      name="keywords"
      content="bootstrap 5, bootstrap, bootstrap 5 admin dashboard, bootstrap 5 dashboard, bootstrap 5 charts, bootstrap 5 calendar, bootstrap 5 datepicker, bootstrap 5 tables, bootstrap 5 datatable, vanilla js datatable, colorlibhq, colorlibhq dashboard, colorlibhq admin dashboard, accessible admin panel, WCAG compliant"
    />
    <!--end::Primary Meta Tags-->

    <!--begin::Accessibility Features-->
    <!-- Skip links will be dynamically added by accessibility.js -->
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="{{asset('dash/css/adminlte.css')}}" as="style" />

    {{-- Aplica o modo escuro ANTES do CSS pintar a tela (evita flash) --}}
    <script>
      (function() {
        try {
          var saved = localStorage.getItem('adminlte-theme');
          document.documentElement.setAttribute('data-bs-theme', saved === 'dark' ? 'dark' : 'light');
        } catch (e) {
          document.documentElement.setAttribute('data-bs-theme', 'light');
        }
        // Largura do menu lateral escolhida por quem usa (alça e botão no app-sidebar), antes de pintar
        try {
          var largura = parseInt(localStorage.getItem('aacj-largura-menu'), 10);
          if (largura > 250 && largura <= 480) {
            document.documentElement.style.setProperty('--lte-sidebar-width', largura + 'px');
          }
        } catch (e) {}
      })();
    </script>
    <!--end::Accessibility Features-->

    <!--begin::Fonts-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Oswald:wght@400;600;700&display=swap" rel="stylesheet">
    <!--end::Fonts-->

    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
      crossorigin="anonymous"
    />
    <!--end::Third Party Plugin(OverlayScrollbars)-->

    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
      crossorigin="anonymous"
    />
    <!--end::Third Party Plugin(Bootstrap Icons)-->

    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="{{asset('dash/css/adminlte.css')}}" />
    <!--end::Required Plugin(AdminLTE)-->

    {{-- CSS customizado para o modo escuro do dashboard --}}
    <link rel="stylesheet" href="{{asset('dash/css/darkmode.css')}}" />

    <!--begin::Tema AACJ-->
    <link rel="stylesheet" href="{{ asset('coderatech/css/estilo.css') }}?v={{ filemtime(public_path('coderatech/css/estilo.css')) }}" />
    <!--end::Tema AACJ-->

    <!-- apexcharts -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
      integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0="
      crossorigin="anonymous"
    />

    <!-- jsvectormap -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css"
      integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4="
      crossorigin="anonymous"
    />
  </head>
<!--end::Head-->
