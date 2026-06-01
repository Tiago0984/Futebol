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


</body>

</html>