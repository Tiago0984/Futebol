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
      function hoverDropdown(selector) {
        var item = document.querySelector(selector);
        if (!item) return;
        var toggle = item.querySelector('[data-bs-toggle="dropdown"]');
        if (!toggle) return;
        var dd = bootstrap.Dropdown.getOrCreateInstance(toggle);
        var timer;
        item.addEventListener('mouseenter', function () { clearTimeout(timer); dd.show(); });
        item.addEventListener('mouseleave', function () { timer = setTimeout(function () { dd.hide(); }, 150); });
      }
      hoverDropdown('.navbar-nav > .user-menu');
      hoverDropdown('.navbar-nav > .nav-item.dropdown:not(.user-menu)');
    });
  </script>

</body>

</html>