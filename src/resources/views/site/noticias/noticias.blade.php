@extends('layout.site')

@section('content')

@include('site.noticias.subbanner')
@include('site.noticias.singlepage', ['noticias' => $noticias, 'recentes' => $recentes])

@endsection