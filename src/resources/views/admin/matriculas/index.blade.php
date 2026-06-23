@extends('layout.admin')

@section('title', 'Matrículas Pendentes')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Matrículas Pendentes</h1>
                <p class="page-subtitle">Revise e aprove ou rejeite os cadastros aguardando análise</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <span class="badge-status pendente d-flex align-items-center px-3" style="font-size:0.78rem;">
                    {{ $matriculas->count() }} pendente(s)
                </span>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        {{-- Filtros --}}
        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label">Nome do Atleta</label>
                        <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">Escola</label>
                        <input type="text" id="filtroEscola" class="form-control form-control-sm" placeholder="Buscar por escola...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Autorização</label>
                        <select id="filtroAutorizacao" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option value="assinada">Assinada</option>
                            <option value="pendente">Pendente</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn-filter-clear" id="btnLimpar">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted" id="filtroContador"></small>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ $matriculas->count() }} registro(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Foto</th>
                            <th>Atleta</th>
                            <th>Responsável</th>
                            <th>Escola</th>
                            <th>Cadastrado em</th>
                            <th class="text-center">Autorização</th>
                            <th class="text-center" style="width:190px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matriculas as $atleta)
                        @php
                            $partes = explode(' ', trim($atleta->nome_atleta));
                            $iniciais = strtoupper(substr($partes[0], 0, 1) . (count($partes) > 1 ? substr(end($partes), 0, 1) : ''));
                            $paleta = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#2ec4b6','#e76f51','#457b9d'];
                            $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                            $responsavel = $atleta->responsaveis->first();
                            $autorizacao = $atleta->autorizacoes->first();
                            $autPendente = !$autorizacao || $autorizacao->status_autorizacao !== 'ASSINADO';
                        @endphp
                        <tr class="linha-matricula"
                            data-nome="{{ strtolower($atleta->nome_atleta) }}"
                            data-escola="{{ strtolower($atleta->escola_atleta ?? '') }}"
                            data-autorizacao="{{ $autPendente ? 'pendente' : 'assinada' }}">
                            <td>
                                @if($atleta->foto_atleta)
                                    <img src="{{ asset('storage/' . $atleta->foto_atleta) }}"
                                         alt="{{ $atleta->nome_atleta }}"
                                         class="rounded-circle object-fit-cover"
                                         style="width:42px;height:42px;">
                                @else
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                         style="width:42px;height:42px;background:{{ $corAvatar }};font-size:0.8rem;">
                                        {{ $iniciais }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $atleta->nome_atleta }}</strong>
                                <div class="text-muted" style="font-size:0.78rem;">{{ $atleta->email_atleta }}</div>
                            </td>
                            <td>
                                @if($responsavel)
                                    <div class="small fw-semibold">{{ $responsavel->nome_responsavel }}</div>
                                    <div class="text-muted" style="font-size:0.75rem;">{{ $responsavel->whatsapp_responsavel }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $atleta->escola_atleta }}</div>
                                <div class="text-muted" style="font-size:0.75rem;">{{ $atleta->serie_atleta }} · {{ $atleta->periodo_escolar_atleta }}</div>
                            </td>
                            <td class="text-muted small">{{ $atleta->criado_em ?? '—' }}</td>
                            <td class="text-center">
                                @if(!$autPendente)
                                    <span class="badge-status ativo"><i class="bi bi-pen me-1"></i>Assinada</span>
                                @else
                                    <span class="badge-status pendente"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.matriculas.show', $atleta->id_atleta) }}"
                                       class="btn-tbl-action view" title="Ver detalhes">
                                        <i class="bi bi-eye"></i>
                                        Ver
                                    </a>

                                    <form action="{{ route('admin.matriculas.aprovar', $atleta->id_atleta) }}"
                                          method="POST" style="display:inline"
                                          onsubmit="return confirm('Aprovar matrícula de {{ $atleta->nome_atleta }}?')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-tbl-action approve"
                                                {{ $autPendente ? 'disabled title="Aguardando assinatura"' : 'title="Aprovar"' }}>
                                            <i class="bi bi-check-lg"></i>
                                            Aprovar
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.matriculas.rejeitar', $atleta->id_atleta) }}"
                                          method="POST" style="display:inline"
                                          onsubmit="return confirm('Rejeitar matrícula de {{ $atleta->nome_atleta }}?')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-tbl-action reject" title="Rejeitar">
                                            <i class="bi bi-x-lg"></i>
                                            Rejeitar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-check fs-2 d-block mb-2"></i>
                                Nenhuma matrícula pendente no momento.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function aplicar() {
        const nome        = document.getElementById('filtroNome').value.toLowerCase().trim();
        const escola      = document.getElementById('filtroEscola').value.toLowerCase().trim();
        const autorizacao = document.getElementById('filtroAutorizacao').value.toLowerCase();
        let count = 0;
        document.querySelectorAll('.linha-matricula').forEach(row => {
            const ok = (!nome        || row.dataset.nome.includes(nome))
                    && (!escola      || row.dataset.escola.includes(escola))
                    && (!autorizacao || row.dataset.autorizacao === autorizacao);
            row.style.display = ok ? '' : 'none';
            if (ok) count++;
        });
        const total = document.querySelectorAll('.linha-matricula').length;
        document.getElementById('filtroContador').textContent =
            (nome || escola || autorizacao) ? `${count} de ${total} matrícula(s) encontrada(s)` : '';
    }

    ['filtroNome','filtroEscola','filtroAutorizacao'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicar));

    document.getElementById('btnLimpar')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value        = '';
        document.getElementById('filtroEscola').value      = '';
        document.getElementById('filtroAutorizacao').value = '';
        aplicar();
    });
});
</script>
@endsection
