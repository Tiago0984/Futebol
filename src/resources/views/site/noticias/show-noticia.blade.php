@extends('layout.site')

@section('content')

@include('site.noticias.show-noticia-content') {{-- o conteúdo atual do show-noticia --}}
@include('site.noticias.newsletter')

@endsection