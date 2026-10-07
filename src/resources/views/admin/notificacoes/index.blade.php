@extends('layout.admin')

@section('title', 'Notificações')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Notificações</h1>
                <p class="page-subtitle">Tudo o que foi enviado aos atletas pelo app, inclusive a agenda do mês (só leitura)</p>
            </div>
        </div>

        {{-- Filtros na URL (convivem com o mês e com a paginação); mudar um select recarrega a lista --}}
        @php $filtrosAtivos = array_filter($filtros, fn ($v) => $v !== ''); @endphp
        <form method="GET" action="{{ route('admin.notificacoes.index') }}" class="filter-panel mb-3" id="formFiltros">
            <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="filter-label" for="filtroAtleta">Atleta</label>
                    <select id="filtroAtleta" name="atleta" class="form-select form-select-sm js-filtro-url">
                        <option value="">Todos</option>
                        @foreach($atletas as $atleta)
                        <option value="{{ $atleta->id_atleta }}" @selected((string) $filtros['atleta'] === (string) $atleta->id_atleta)>{{ $atleta->nome_atleta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="filter-label" for="filtroTipo">Tipo</label>
                    <select id="filtroTipo" name="tipo" class="form-select form-select-sm js-filtro-url">
                        <option value="">Todos</option>
                        @foreach(\App\Models\Notificacao::TIPOS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['tipo'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="filter-label" for="filtroLeitura">Leitura do atleta</label>
                    <select id="filtroLeitura" name="leitura" class="form-select form-select-sm js-filtro-url">
                        <option value="">Todas</option>
                        @foreach(\App\Http\Controllers\Admin\NotificacoesController::LEITURAS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['leitura'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <a href="{{ route('admin.notificacoes.index', ['mes' => $mes->format('Y-m')]) }}" class="btn-filter-clear text-decoration-none">
                        <i class="bi bi-x-circle"></i> Limpar
                    </a>
                </div>
            </div>
        </form>

        <div class="table-card">
            <div class="table-card-toolbar d-flex flex-wrap align-items-center gap-2">
                {{-- Mês do envio: setas e select (como no Calendário); trocar de mês mantém os filtros --}}
                @php
                    $mesAnterior = $mes->copy()->subMonthNoOverflow()->format('Y-m');
                    $mesSeguinte = $mes->copy()->addMonthNoOverflow()->format('Y-m');
                @endphp
                <form method="GET" action="{{ route('admin.notificacoes.index') }}" class="d-flex align-items-center gap-1" id="formMesLista">
                    @foreach($filtrosAtivos as $campo => $valor)
                    <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                    @endforeach
                    <a href="{{ route('admin.notificacoes.index', ['mes' => $mesAnterior, ...$filtrosAtivos]) }}" class="btn btn-sm btn-outline-secondary"
                       title="Mês anterior" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></a>
                    <select name="mes" class="form-select form-select-sm" style="width:auto" aria-label="Mês" onchange="this.form.submit()">
                        @foreach($mesesLista as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($valor === $mes->format('Y-m'))>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('admin.notificacoes.index', ['mes' => $mesSeguinte, ...$filtrosAtivos]) }}" class="btn btn-sm btn-outline-secondary"
                       title="Próximo mês" aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></a>
                </form>
                <span class="tbl-count">{{ $notificacoes->total() }} notificação(ões) em {{ \App\Models\EventoCalendario::rotuloDoMes($mes) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Tipo</th>
                            <th>Atleta</th>
                            <th>Título e mensagem</th>
                            <th>Evento</th>
                            <th>Por</th>
                            <th>Lida pelo atleta</th>
                            <th>Responsáveis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notificacoes as $notificacao)
                        <tr class="linha-notificacao">
                            <td class="text-muted text-nowrap" style="font-size:0.82rem;">{{ $notificacao->data_notificacao->format('d/m/Y H:i') }}</td>
                            <td><span class="badge-cat">{{ $notificacao->tipo_label }}</span></td>
                            <td>{{ $notificacao->atleta?->nome_atleta ?? '—' }}</td>
                            <td class="small">
                                <strong>{{ $notificacao->titulo_notificacao }}</strong><br>
                                {{ $notificacao->mensagem_notificacao }}
                            </td>
                            {{-- AGENDA (resumo do mês, mover inscrições) não tem evento --}}
                            <td class="small">
                                @if($notificacao->evento)
                                <a href="{{ route('admin.calendario.eventos.show', $notificacao->id_evento_calendario) }}">{{ $notificacao->evento->titulo_evento_calendario }}</a>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $notificacao->usuario?->nome_usuario ?? '—' }}</td>
                            <td class="small text-nowrap">
                                {{ $notificacao->estaLida() ? $notificacao->data_leitura_notificacao->format('d/m/Y H:i') : 'Não lida' }}
                            </td>
                            {{-- Cada responsável tem a própria leitura no app (tbl_notificacao_leitura) --}}
                            <td class="small text-nowrap">
                                @if($notificacao->atleta?->responsaveis_count)
                                    {{ $notificacao->leituras_dos_responsaveis_count }} de {{ $notificacao->atleta->responsaveis_count }} leram
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                {{ $filtrosAtivos ? 'Nenhuma notificação com estes filtros neste mês.' : 'Nenhuma notificação enviada neste mês.' }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($notificacoes->hasPages())
            <div class="p-3 border-top">
                {{ $notificacoes->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-filtro-url').forEach(sel => sel.addEventListener('change', () => sel.form.submit()));
});
</script>
@endsection
