@extends('layout.admin')
@section('title', 'Mensagem recebida')
@section('content')
<main class="app-main">
    <div class="container-fluid py-4">
        <div class="admin-page-header">
            <div>
                <h1 class="page-title">{{ $mensagem->assunto_fale_conosco }}</h1>
                <p class="page-subtitle">Protocolo #{{ $mensagem->id_fale_conosco }} · {{ $mensagem->data_fale_conosco->format('d/m/Y H:i') }}</p>
            </div>
            <a href="{{ route('admin.fale-conosco.index') }}" class="btn btn-outline-secondary">Voltar às mensagens</a>
        </div>
        @if(session('sucesso'))
            <div class="alert alert-success" role="status">{{ session('sucesso') }}</div>
        @endif
        <div class="card shadow-sm mb-3"><div class="card-body">
            <p><strong>Remetente:</strong> {{ $mensagem->nome_fale_conosco }} · {{ $mensagem->email_fale_conosco }}</p>
            <p><strong>Perfil / origem:</strong> {{ \App\Models\FaleConosco::PERFIS[$mensagem->perfil_fale_conosco] }} / {{ $mensagem->origem_fale_conosco === 'APP' ? 'App' : 'Site' }}</p>
            @if($mensagem->telefone_fale_conosco)
                <p><strong>Telefone informado:</strong> {{ $mensagem->telefone_fale_conosco }}</p>
            @endif
            <p><strong>Leitura:</strong> {{ $mensagem->data_leitura_fale_conosco ? 'Lida em ' . $mensagem->data_leitura_fale_conosco->format('d/m/Y H:i') : 'Não lida' }}</p>
            <p><strong>E-mail de aviso:</strong> {{ ['PENDENTE' => 'Envio pendente', 'ENVIADO' => 'Enviado', 'FALHOU' => 'Falha no envio; a mensagem está salva neste painel.'][$mensagem->status_email_fale_conosco] }}</p>
            <hr>
            <div style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $mensagem->mensagem_fale_conosco }}</div>
        </div></div>
        <form method="POST" action="{{ route('admin.fale-conosco.leitura', $mensagem->id_fale_conosco) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="lida" value="{{ $mensagem->data_leitura_fale_conosco ? '0' : '1' }}">
            <button class="btn btn-primary" type="submit">{{ $mensagem->data_leitura_fale_conosco ? 'Marcar como não lida' : 'Marcar como lida' }}</button>
        </form>
    </div>
</main>
@endsection
