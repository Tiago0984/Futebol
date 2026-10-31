@extends('layout.admin')
@section('title', 'Fale conosco')
@section('content')
<main class="app-main">
    <div class="container-fluid py-4">
        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Fale conosco</h1>
                <p class="page-subtitle">Mensagens recebidas pelo aplicativo e pelo site.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.fale-conosco.index') }}" class="d-flex gap-2 align-items-center mb-3">
            <label for="leitura">Leitura</label>
            <select name="leitura" id="leitura" class="form-select w-auto">
                <option value="">Todas</option>
                <option value="nao_lidas" @selected($leitura === 'nao_lidas')>Não lidas</option>
                <option value="lidas" @selected($leitura === 'lidas')>Lidas</option>
            </select>
            <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        </form>
        <div class="table-card">
            <div class="table-card-toolbar">{{ $mensagens->total() }} mensagem(ns)</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Data</th><th>Remetente</th><th>Perfil / origem</th><th>Assunto</th><th>Leitura</th><th>Ação</th></tr></thead>
                    <tbody>
                    @forelse($mensagens as $mensagem)
                        <tr>
                            <td class="text-nowrap">{{ $mensagem->data_fale_conosco->format('d/m/Y H:i') }}</td>
                            <td>{{ $mensagem->nome_fale_conosco }}<br><small>{{ $mensagem->email_fale_conosco }}</small></td>
                            <td>{{ \App\Models\FaleConosco::PERFIS[$mensagem->perfil_fale_conosco] }} / {{ $mensagem->origem_fale_conosco === 'APP' ? 'App' : 'Site' }}</td>
                            <td>{{ $mensagem->assunto_fale_conosco }}</td>
                            <td><span class="badge {{ $mensagem->data_leitura_fale_conosco ? 'text-bg-secondary' : 'text-bg-warning' }}">{{ $mensagem->data_leitura_fale_conosco ? 'Lida' : 'Não lida' }}</span></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.fale-conosco.show', $mensagem->id_fale_conosco) }}">Ver mensagem</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4">Nenhuma mensagem encontrada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $mensagens->links('pagination::bootstrap-5') }}</div>
    </div>
</main>
@endsection
