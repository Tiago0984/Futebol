@extends('layout.admin')

@section('title', 'Matrículas Rejeitadas')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Matrículas Rejeitadas</h1>
                <p class="page-subtitle">Cadastros rejeitados — reative ou exclua permanentemente</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="{{ route('admin.matriculas.index') }}" class="btn-filter-toggle">
                    <i class="bi bi-arrow-left"></i> Ver pendentes
                </a>
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
                <span class="tbl-count">{{ count($rejeitadas) }} registro(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Foto</th>
                            <th>Atleta</th>
                            <th>Responsável</th>
                            <th>Escola</th>
                            <th class="text-center">Autorização</th>
                            <th class="text-center" style="width:190px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rejeitadas as $atleta)
                        @php
                            $partes = explode(' ', trim($atleta->nome_atleta));
                            $iniciais = strtoupper(substr($partes[0], 0, 1) . (count($partes) > 1 ? substr(end($partes), 0, 1) : ''));
                            $paleta = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#2ec4b6','#e76f51','#457b9d'];
                            $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                            $responsavel = $atleta->responsaveis->first();
                            $autorizacao = $atleta->autorizacoes->first();
                            $autPendente = !$autorizacao || $autorizacao->status_autorizacao !== 'ASSINADO';
                        @endphp
                        <tr class="linha-rejeitada"
                            data-nome="{{ strtolower($atleta->nome_atleta) }}"
                            data-escola="{{ strtolower($atleta->escola_atleta ?? '') }}">
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

                                    <form action="{{ route('admin.matriculas.reativar', $atleta->id_atleta) }}"
                                          method="POST" style="display:inline"
                                          onsubmit="return confirm('Reativar matrícula de {{ $atleta->nome_atleta }}? Ela voltará para pendentes.')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-tbl-action reactivate"
                                                title="Reativar para pendentes">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            Reativar
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.matriculas.deletar', $atleta->id_atleta) }}"
                                          method="POST" style="display:inline"
                                          onsubmit="return confirm('Excluir permanentemente {{ $atleta->nome_atleta }}? Esta ação não pode ser desfeita.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-tbl-action delete" title="Excluir permanentemente">
                                            <i class="bi bi-trash"></i>
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-x fs-2 d-block mb-2"></i>
                                Nenhuma matrícula rejeitada.
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
        const nome   = document.getElementById('filtroNome').value.toLowerCase().trim();
        const escola = document.getElementById('filtroEscola').value.toLowerCase().trim();
        let count = 0;
        document.querySelectorAll('.linha-rejeitada').forEach(row => {
            const ok = (!nome   || row.dataset.nome.includes(nome))
                    && (!escola || row.dataset.escola.includes(escola));
            row.style.display = ok ? '' : 'none';
            if (ok) count++;
        });
        const total = document.querySelectorAll('.linha-rejeitada').length;
        document.getElementById('filtroContador').textContent =
            (nome || escola) ? `${count} de ${total} matrícula(s) encontrada(s)` : '';
    }

    ['filtroNome','filtroEscola'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicar));

    document.getElementById('btnLimpar')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value  = '';
        document.getElementById('filtroEscola').value = '';
        aplicar();
    });
});
</script>
@endsection
