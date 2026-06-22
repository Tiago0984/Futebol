<!DOCTYPE html>
<html lang="pt-BR">

<head>
  @include('admin.partials.head')
</head>

<body>

  <div class="app-wrapper">
    @include('admin.partials.app-header')

    @include('admin.partials.app-sidebar')

    @yield('content')

    @include('admin.partials.app-footer')


  </div>


  @include('admin.partials.script')

  @stack('scripts')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var userMenu = document.querySelector('.navbar-nav > .user-menu');
      if (!userMenu) return;
      var toggle = userMenu.querySelector('[data-bs-toggle="dropdown"]');
      if (!toggle) return;
      var dropdown = bootstrap.Dropdown.getOrCreateInstance(toggle);
      var hideTimer;
      userMenu.addEventListener('mouseenter', function () {
        clearTimeout(hideTimer);
        dropdown.show();
      });
      userMenu.addEventListener('mouseleave', function () {
        hideTimer = setTimeout(function () { dropdown.hide(); }, 150);
      });
    });
  </script>

</body>

</html>