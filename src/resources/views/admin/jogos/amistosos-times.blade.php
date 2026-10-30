@extends('layout.admin')

@section('title', 'Jogos dos amistosos')

{{--
    Jogos dos amistosos (o item Amistosos do menu), só para ver: um bloco por jogo (data, horário, local,
    situação e o link para o jogo) com o cartão do mandante e o do visitante. Próximos primeiro, depois os
    últimos realizados. O cartão do time abre os jogadores escalados por ele naquele jogo.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho titulo="Jogos dos amistosos" :caminho="$caminho" id-caminho="caminhoTimes">
      <x-slot:subtitulo>Cada amistoso com os dois times; clique num time interno para ver os jogadores</x-slot:subtitulo>
    </x-admin.cabecalho>

    @include('admin.jogos._mensagens')

    @include('admin.partials.blocos-de-jogos', ['rotulo' => 'amistoso'])

  </div>
</main>

{{-- Editar jogo pelas ações de cada bloco: salva e volta para esta tela --}}
@include('admin.jogos.modals.edit', [...$formularioJogo, 'voltarParaPagina' => true])
@include('admin.partials.sugestoes')
@include('admin.jogos._script_form')
@endsection
