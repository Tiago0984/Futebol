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
@endsection