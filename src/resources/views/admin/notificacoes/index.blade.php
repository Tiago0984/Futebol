@extends('layout.admin')

@section('title', 'Notificações')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Notificações</h1>
                <p class="page-subtitle">Tudo o que foi enviado aos atletas pelo app, inclusive a agenda do mês e os avisos escritos aqui</p>
            </div>
            <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalNovoAviso">
                <i class="bi bi-megaphone"></i> Novo aviso
            </button>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Filtros na URL (convivem com o mês e com a paginação); mudar um select recarrega a lista --}}
        @php
            $filtrosAtivos = array_filter($filtros, fn ($v) => $v !== '');
            $semEvento     = \Illuminate\Support\Arr::except($filtrosAtivos, 'evento');
        @endphp

        {{-- Filtro por evento ("ver todas" da tela do evento): todos os meses de envio; o mês fica sem efeito --}}
        @if($eventoFiltrado)
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 mb-3" role="status" id="filtroEvento">
            <i class="bi bi-funnel"></i>
            <span>
                Só as notificações do evento
                <a href="{{ route('admin.calendario.eventos.show', $eventoFiltrado->id_evento_calendario) }}" class="fw-semibold">{{ $eventoFiltrado->titulo_evento_calendario }}</a>
                ({{ $eventoFiltrado->data_evento_calendario->format('d/m/Y') }}), de todos os meses de envio.
            </span>
            <a href="{{ route('admin.notificacoes.index', ['mes' => $mes->format('Y-m'), ...$semEvento]) }}" class="ms-auto btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpar filtro do evento
            </a>
        </div>
        @endif

        <form method="GET" action="{{ route('admin.notificacoes.index') }}" class="filter-panel mb-3" id="formFiltros">
            <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
            @if($eventoFiltrado)
            <input type="hidden" name="evento" value="{{ $eventoFiltrado->id_evento_calendario }}">
            @endif
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
                {{-- Mês do envio: setas e select (como no Calendário); trocar de mês mantém os filtros. Com o
                     filtro de evento, o mês não vale: some, e a contagem é do evento --}}
                @if($eventoFiltrado)
                <span class="tbl-count">{{ $notificacoes->total() }} envio(s), {{ $totalNotificacoes }} notificação(ões) deste evento</span>
                @else
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
                <span class="tbl-count">{{ $notificacoes->total() }} envio(s), {{ $totalNotificacoes }} notificação(ões) em {{ \App\Models\EventoCalendario::rotuloDoMes($mes) }}</span>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Tipo</th>
                            <th>Título e mensagem</th>
                            <th>Evento</th>
                            <th>Por</th>
                            <th>Atletas</th>
                            <th>Responsáveis</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Uma linha por envio; os atletas que receberam (e quem leu) abrem na coluna Atletas --}}
                        @forelse($notificacoes as $notificacao)
                        <tr class="linha-notificacao">
                            <td class="text-muted text-nowrap" style="font-size:0.82rem;">{{ $notificacao->data_notificacao->format('d/m/Y H:i') }}</td>
                            <td><span class="badge-cat">{{ $notificacao->tipo_label }}</span></td>
                            <td class="small">
                                <strong>{{ $notificacao->titulo_notificacao }}</strong><br>
                                {{ $notificacao->mensagem_notificacao }}
                            </td>
                            {{-- AGENDA (resumo do mês, mover inscrições) e AVISO não têm evento --}}
                            <td class="small">
                                @if($notificacao->evento)
                                <a href="{{ route('admin.calendario.eventos.show', $notificacao->id_evento_calendario) }}">{{ $notificacao->evento->titulo_evento_calendario }}</a>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $notificacao->usuario?->nome_usuario ?? '—' }}</td>
                            <td class="small" style="min-width:170px;">
                                <details class="js-atletas-do-envio">
                                    <summary class="text-nowrap">
                                        {{ $notificacao->total_atletas }} atleta(s) · {{ $notificacao->total_lidas }} leu(ram)
                                    </summary>
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($notificacao->destinatarios as $destinatario)
                                        <li class="text-nowrap">
                                            {{ $destinatario->atleta?->nome_atleta ?? '—' }}
                                            <span class="text-muted">· {{ $destinatario->estaLida() ? 'leu em ' . $destinatario->data_leitura_notificacao->format('d/m H:i') : 'não leu' }}</span>
                                        </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                            {{-- Cada responsável tem a própria leitura no app (tbl_notificacao_leitura): somadas no envio --}}
                            <td class="small text-nowrap">
                                @if($notificacao->total_responsaveis > 0)
                                    {{ (int) $notificacao->leituras_responsaveis }} de {{ (int) $notificacao->total_responsaveis }} leram
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ $eventoFiltrado ? 'Nenhuma notificação enviada sobre este evento.' : ($filtrosAtivos ? 'Nenhuma notificação com estes filtros neste mês.' : 'Nenhuma notificação enviada neste mês.') }}
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

{{-- Novo aviso: recado escrito livremente para os atletas escolhidos (tipo AVISO, sem evento) --}}
<div class="modal fade" id="modalNovoAviso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone"></i> Novo aviso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.notificacoes.store') }}" method="POST" id="formNovoAviso">
                @csrf
                <div class="modal-body">
                    @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
                    </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="aviso_mensagem">Mensagem <span class="text-danger">*</span></label>
                            <textarea name="mensagem_notificacao" id="aviso_mensagem" class="form-control" rows="4" required
                                maxlength="{{ \App\Models\Notificacao::MENSAGEM_MAX }}"
                                placeholder="Ex: Na terça, traga um documento com foto para a carteirinha do campeonato.">{{ old('mensagem_notificacao') }}</textarea>
                            <small class="text-muted">Até {{ \App\Models\Notificacao::MENSAGEM_MAX }} caracteres. Chega no app do atleta e dos responsáveis
                                como "{{ \App\Models\Notificacao::TITULO_AVISO }}".</small>
                        </div>
                        <div class="col-12">
                            @include('admin.partials.escolher-atletas', [
                                'prefixo'    => 'aviso',
                                'grupos'     => $atletasParaAviso,
                                'escolhidos' => array_map('strval', (array) old('atletas', [])),
                                'ajuda'      => 'Tique quem recebe o aviso. Só atletas ativos aparecem.',
                            ])
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-send"></i> Enviar aviso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-filtro-url').forEach(sel => sel.addEventListener('change', () => sel.form.submit()));

    // Voltou com erro de validação: reabre o aviso com o que foi preenchido
    @if($errors->any())
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoAviso')).show();
    @endif
});
</script>
@endsection
