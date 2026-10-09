{{--
    Mensagens das telas Jogos do campeonato e Jogos dos amistosos, que agora têm as ações do jogo (editar,
    cancelar, ocultar): sucesso (com "N atleta(s) notificado(s)."), aviso (ex.: time sem elenco), erro e
    erros de validação do modal de edição, e o alerta de conflito de horário da edição.
--}}
@if(session('sucesso'))
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <strong>Sucesso!</strong> {{ session('sucesso') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(session('aviso'))
  <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
    <strong>Atenção!</strong> {{ session('aviso') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(session('erro'))
  <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('erro') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <strong>Erro!</strong>
    <ul class="mb-0 mt-1">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@include('admin.calendario._conflitos')
