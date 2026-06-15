@extends('layout.site')

@section('content')
<div class="scout-page-shift">

    @include('site.jogadores.banner')

    <div class="scout-page-container">

        @include('site.jogadores.scout-main-header')

        <div class="scout-layout-wrapper">

            @include('site.jogadores.sidebar-filtros')

            @include('site.jogadores.grid-atletas')

        </div>
    </div>
</div>

@include('partials.modal-times')

<script>
    // Lê o parâmetro ?open=ID ANTES do jQuery carregar (JS puro é suficiente aqui)
    // e expõe em variável global para o coderatech/js/script.js consumir após o Bootstrap estar pronto.
    window._autoOpenAtletaId = new URLSearchParams(window.location.search).get('open');
</script>
@endsection