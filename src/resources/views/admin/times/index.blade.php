@extends('layout.admin')

@section('title', 'Gerenciar Times')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Times</h1>
                <p class="page-subtitle">Gerencie os times internos e externos da AACJ</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarTime">
                    <i class="bi bi-plus-lg"></i> Novo Time
                </button>
            </div>
        </div>

        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="filter-label">Nome</label>
                        <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Tipo</label>
                        <select id="filtroTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="INTERNO">Interno</option>
                            <option value="EXTERNO">Externo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Categoria</label>
                        <select id="filtroCategoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categorias as $cat)
                            <option value="{{ $cat->id_categoria }}">{{ $cat->rotulo }}</option>
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

        @include('admin.partials.avisos-imagem')

        @if(session('erro'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong> {{ session('erro') }}
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
                <span class="tbl-count">{{ count($times) }} time(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Logo</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Categorias</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:150px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($times as $time)
                        @php $idsCategorias = $time->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all(); @endphp
                        <tr class="linha-time"
                            data-nome="{{ strtolower($time->nome_time) }}"
                            data-tipo="{{ strtoupper($time->tipo_time) }}"
                            data-categorias=",{{ implode(',', $idsCategorias) }},"
                            data-status="{{ strtoupper($time->status_time) }}">
                            <td>
                                @if($time->logo_time)
                                <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                                     alt="{{ $time->nome_time }}"
                                     style="width:40px;height:40px;object-fit:contain;">
                                @else
                                <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-shield text-muted"></i>
                                </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $time->nome_time }}</span></td>
                            <td>
                                @if(strtoupper($time->tipo_time) === 'INTERNO')
                                    <span class="badge-status ativo">Interno</span>
                                @else
                                    <span class="badge-status inativo">Externo</span>
                                @endif
                            </td>
                            <td>
                                @forelse($time->categorias as $cat)
                                    <span class="badge bg-body-secondary text-body border fw-normal me-1 mb-1"
                                          title="{{ $cat->rotulo }}">{{ $cat->nome_categoria }} {{ $cat->sexo_categoria }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td class="text-center">
                                @if(strtoupper($time->status_time) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    {{-- Sem Elenco (Fase G, Etapa 4): quem joga pelo time é a convocação de cada campeonato --}}
                                    <button type="button" class="btn-tbl edit btn-editar-time"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarTime"
                                        data-id="{{ $time->id_time }}"
                                        data-nome="{{ $time->nome_time }}"
                                        data-tipo="{{ $time->tipo_time }}"
                                        data-categorias="{{ json_encode($idsCategorias) }}"
                                        data-logo="{{ $time->logo_time }}"
                                        title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.times.toggleStatus', $time->id_time) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($time->status_time) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($time->status_time) !== 'ATIVO')
                                    <form action="{{ route('admin.times.destroy', $time->id_time) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente o time {{ addslashes($time->nome_time) }}?');" style="display:inline">
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
                            <td colspan="6" class="text-center text-muted py-4">Nenhum time cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

@include('admin.times.modals.create')
@include('admin.times.modals.edit')

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Filtros ---
    function aplicarFiltros() {
        const nome      = document.getElementById('filtroNome')?.value.toLowerCase().trim() ?? '';
        const tipo      = document.getElementById('filtroTipo')?.value ?? '';
        const categoria = document.getElementById('filtroCategoria')?.value ?? '';
        const status    = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-time').forEach(row => {
            const ok = (!nome      || row.dataset.nome.includes(nome))
                    && (!tipo      || row.dataset.tipo === tipo)
                    && (!categoria || row.dataset.categorias.includes(`,${categoria},`))
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-time').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (nome || tipo || categoria || status)
            ? `${visiveis} de ${total} time(s) encontrado(s)` : '';
    }
    ['filtroNome','filtroTipo','filtroCategoria','filtroStatus'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', aplicarFiltros));
    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroCategoria').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });

});

// Masculino e feminino em cadastros separados: com alguma caixa marcada numa coluna, a outra fica desabilitada
function travarSexoDasCategorias(caixa) {
    const colunas = caixa.querySelectorAll('.js-col-sexo');
    const marcada = [...colunas].find(col => col.querySelector('.js-categoria-time:checked'));
    colunas.forEach(col => col.querySelectorAll('.js-categoria-time').forEach(cx => {
        cx.disabled = !!marcada && col !== marcada;
    }));
}
document.querySelectorAll('.js-categorias-do-time').forEach(caixa => {
    caixa.addEventListener('change', () => travarSexoDasCategorias(caixa));
    travarSexoDasCategorias(caixa);
});

// Preenche o modal de edição: nome, tipo e as caixas das categorias (a inativa só aparece se o time a tem)
function preencherEdicaoDoTime(id, nome, tipo, categorias, logo) {
    const f = document.getElementById('formEditarTime');
    f.action = `{{ url('admin/times') }}/${id}`;
    document.getElementById('edit_editar_id').value = id;
    document.getElementById('edit_nome_time').value = nome;
    document.getElementById('edit_tipo_time').value = tipo;
    const marcadas = categorias.map(Number);
    f.querySelectorAll('.js-categoria-time').forEach(cx => {
        cx.checked = marcadas.includes(Number(cx.value));
        cx.closest('.js-cat-inativa')?.classList.toggle('d-none', !cx.checked);
    });
    travarSexoDasCategorias(f.querySelector('.js-categorias-do-time'));
    const logoInfo = document.getElementById('edit_logo_atual');
    logoInfo.textContent = logo ? `Atual: ${logo}` : 'Sem logo cadastrado';
}

document.querySelectorAll('.btn-editar-time').forEach(btn => {
    btn.addEventListener('click', function () {
        preencherEdicaoDoTime(this.dataset.id, this.dataset.nome, this.dataset.tipo,
            JSON.parse(this.dataset.categorias || '[]'), this.dataset.logo);
    });
});

@if($errors->any() && old('_editar_id'))
// Erro na edição (ex.: categoria que o time ainda usa num campeonato): reabre o modal com o que foi digitado
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.btn-editar-time[data-id="{{ (int) old('_editar_id') }}"]');
    if (!btn) return;
    preencherEdicaoDoTime(btn.dataset.id, @json(old('nome_time')), @json(old('tipo_time')),
        @json(array_map('intval', old('categorias', []))), btn.dataset.logo);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarTime')).show();
});
@elseif($errors->any())
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCriarTime')).show();
});
@endif
</script>
@endsection
