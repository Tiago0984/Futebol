@extends('layout.admin')

@section('title', 'Gerenciar Jogos')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Jogos</h1>
                <p class="page-subtitle">Registre e gerencie partidas e resultados</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarJogo">
                    <i class="bi bi-plus-lg"></i> Novo Jogo
                </button>
            </div>
        </div>

        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label">Time</label>
                        <input type="text" id="filtroTime" class="form-control form-control-sm" placeholder="Buscar mandante ou visitante...">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">Campeonato</label>
                        <select id="filtroCampeonato" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach($campeonatos as $camp)
                            <option value="{{ $camp->id_campeonato }}">{{ $camp->nome_campeonato }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Status</label>
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="ATIVO">Ativo</option>
                            <option value="INATIVO">Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn-filter-clear" id="btnLimparFiltros">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2"><small class="text-muted" id="filtroContador"></small></div>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
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

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($jogos) }} jogo(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Campeonato</th>
                            <th class="text-center">Mandante</th>
                            <th class="text-center" style="width:90px">Placar</th>
                            <th class="text-center">Visitante</th>
                            <th>Data</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jogos as $jogo)
                        <tr class="linha-jogo"
                            data-time="{{ strtolower(($jogo->timeCasa->nome_time ?? '') . ' ' . ($jogo->timeVisitante->nome_time ?? '')) }}"
                            data-campeonato="{{ $jogo->id_campeonato }}"
                            data-status="{{ strtoupper($jogo->status_jogo) }}">
                            <td><span class="badge-cat">{{ $jogo->campeonato->nome_campeonato ?? '—' }}</span></td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <span class="fw-semibold">{{ $jogo->timeCasa->nome_time ?? '—' }}</span>
                                    @if($jogo->timeCasa?->logo_time)
                                    <img src="{{ asset('futebol/images/team/' . $jogo->timeCasa->logo_time) }}"
                                         style="width:28px;height:28px;object-fit:contain;">
                                    @endif
                                </div>
                            </td>
                            <td class="text-center fw-bold">
                                @if($jogo->placar_time_casa_jogos !== null && $jogo->placar_time_visitante_jogos !== null)
                                    <span class="badge bg-dark">{{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}</span>
                                @else
                                    <span class="text-muted">VS</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-start gap-2">
                                    @if($jogo->timeVisitante?->logo_time)
                                    <img src="{{ asset('futebol/images/team/' . $jogo->timeVisitante->logo_time) }}"
                                         style="width:28px;height:28px;object-fit:contain;">
                                    @endif
                                    <span class="fw-semibold">{{ $jogo->timeVisitante->nome_time ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="text-muted">
                                {{ $jogo->data_jogo ? \Carbon\Carbon::parse($jogo->data_jogo)->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="text-center">
                                @if(strtoupper($jogo->status_jogo) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button" class="btn-tbl edit btn-editar-jogo"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarJogo"
                                        data-id="{{ $jogo->id_jogo }}"
                                        data-campeonato="{{ $jogo->id_campeonato }}"
                                        data-casa="{{ $jogo->id_time_casa }}"
                                        data-visitante="{{ $jogo->id_time_visitante }}"
                                        data-data="{{ $jogo->data_jogo ? \Carbon\Carbon::parse($jogo->data_jogo)->format('Y-m-d\TH:i') : '' }}"
                                        data-placar-casa="{{ $jogo->placar_time_casa_jogos }}"
                                        data-placar-visitante="{{ $jogo->placar_time_visitante_jogos }}"
                                        title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.jogos.toggleStatus', $jogo->id_jogo) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($jogo->status_jogo) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($jogo->status_jogo) !== 'ATIVO')
                                    <form action="{{ route('admin.jogos.destroy', $jogo->id_jogo) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente este jogo?');" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-tbl delete" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nenhum jogo registrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.jogos.modals.create')
@include('admin.jogos.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {

    function aplicarFiltros() {
        const time       = document.getElementById('filtroTime')?.value.toLowerCase().trim() ?? '';
        const campeonato = document.getElementById('filtroCampeonato')?.value ?? '';
        const status     = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-jogo').forEach(row => {
            const ok = (!time       || row.dataset.time.includes(time))
                    && (!campeonato || row.dataset.campeonato === campeonato)
                    && (!status     || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-jogo').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (time || campeonato || status)
            ? `${visiveis} de ${total} jogo(s) encontrado(s)` : '';
    }
    ['filtroTime','filtroCampeonato','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicarFiltros));
    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroTime').value = '';
        document.getElementById('filtroCampeonato').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });

});

document.querySelectorAll('.btn-editar-jogo').forEach(btn => {
    btn.addEventListener('click', function () {
        const f = document.getElementById('formEditarJogo');
        f.action = `{{ url('admin/jogos') }}/${this.dataset.id}`;
        document.getElementById('edit_id_campeonato').value            = this.dataset.campeonato;
        document.getElementById('edit_id_time_casa').value             = this.dataset.casa;
        document.getElementById('edit_id_time_visitante').value        = this.dataset.visitante;
        document.getElementById('edit_data_jogo').value                = this.dataset.data;
        document.getElementById('edit_placar_casa').value              = this.dataset.placarCasa;
        document.getElementById('edit_placar_visitante').value         = this.dataset.placarVisitante;
    });
});
</script>
@endsection
