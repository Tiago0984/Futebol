@extends('layout.site')

@section('content')

@include('site.calendario.banner') 
@include('site.calendario.calendar-view')
@include('site.calendario.calendar-schedule')
@include('site.calendario.calendar-footer') 

@endsection