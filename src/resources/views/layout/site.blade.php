<!DOCTYPE html>
<html lang="pt-BR">

@include('partials.head')

<body>

    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.script')

    @include('partials.footer')

    <script src="{{ asset('coderatech/js/script.js') }}"></script>

</body>

</html>