<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Fale conosco - AACJ</title></head>
<body>
    <h1>Nova mensagem para a AACJ</h1>
    <p><strong>Protocolo:</strong> {{ $mensagem->id_fale_conosco }}</p>
    <p><strong>Remetente:</strong> {{ $mensagem->nome_fale_conosco }} ({{ $mensagem->email_fale_conosco }})</p>
    <p><strong>Perfil:</strong> {{ \App\Models\FaleConosco::PERFIS[$mensagem->perfil_fale_conosco] }}</p>
    <p><strong>Origem:</strong> {{ $mensagem->origem_fale_conosco === 'APP' ? 'Aplicativo' : 'Site' }}</p>
    @if($mensagem->telefone_fale_conosco)
    <p><strong>Telefone informado:</strong> {{ $mensagem->telefone_fale_conosco }}</p>
    @endif
    <p><strong>Data:</strong> {{ $mensagem->data_fale_conosco->format('d/m/Y H:i') }} (Brasília)</p>
    <h2>{{ $mensagem->assunto_fale_conosco }}</h2>
    <div style="white-space: pre-wrap">{{ $mensagem->mensagem_fale_conosco }}</div>
    <p>Para responder ao remetente, use a opção Responder do seu e-mail.</p>
</body>
</html>
