<!DOCTYPE html>
<html lang="pt-BR">

<head>
    @include('partials.head')

    <head>

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