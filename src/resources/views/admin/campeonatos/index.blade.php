@extends('layout.admin')

@section('title', 'Gerenciar Campeonatos')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Campeonatos</h1>
                <p class="page-subtitle">Gerencie competições, datas e times participantes</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalCriarCampeonato">
                    <i class="bi bi-plus-lg"></i> Novo Campeonato
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
                            @foreach(\App\Models\Campeonato::TIPOS as $t => $rotuloTipo)
                            <option value="{{ $t }}">{{ $rotuloTipo }}</option>
                            @endforeach
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

        {{-- Validação do modal de criar/editar (ex.: time com convocados não sai dos participantes) --}}
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Filtrada pelo nome do campeonato no menu (?campeonato=ID): mostra qual e como voltar a ver todos --}}
        @if($campeonatoFiltrado)
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 mb-3" role="status" id="filtroCampeonato">
            <i class="bi bi-funnel"></i>
            <span>Mostrando só o campeonato <strong>{{ $campeonatoFiltrado->nome_campeonato }}</strong>.</span>
            <a href="{{ route('admin.campeonatos.index') }}" class="ms-auto btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Ver todos os campeonatos
            </a>
        </div>
        @endif

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ count($campeonatos) }} campeonato(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Logo</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Período</th>
                            <th>Local</th>
                            <th>Categoria</th>
                            <th class="text-center">Times</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campeonatos as $camp)
                        <tr class="linha-camp"
                            data-nome="{{ strtolower($camp->nome_campeonato) }}"
                            data-tipo="{{ $camp->tipo_campeonato }}"
                            data-categorias=" {{ $camp->categorias->pluck('id_categoria')->implode(' ') }} "
                            data-status="{{ strtoupper($camp->status_campeonato) }}">
                            <td>
                                @if($camp->logo_evento)
                                <img src="{{ asset('futebol/images/campeonatos/' . $camp->logo_evento) }}"
                                     alt="{{ $camp->nome_campeonato }}"
                                     style="width:40px;height:40px;object-fit:contain;">
                                @else
                                <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-trophy text-muted"></i>
                                </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $camp->nome_campeonato }}</span></td>
                            <td><span class="badge-cat">{{ $camp->tipo_label ?: '—' }}</span></td>
                            <td class="text-muted" style="font-size:0.82rem;">
                                {{ $camp->data_inicio_campeonato ? \Carbon\Carbon::parse($camp->data_inicio_campeonato)->format('d/m/Y') : '—' }}
                                @if($camp->data_fim_campeonato)
                                    <br>até {{ \Carbon\Carbon::parse($camp->data_fim_campeonato)->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="text-muted">{{ $camp->local_evento ?? '—' }}</td>
                            <td class="text-muted" style="font-size:0.82rem;">
                                @forelse($camp->categorias as $catDoCamp)
                                    <div>{{ $catDoCamp->rotulo }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">{{ $camp->times->count() }}</span>
                            </td>
                            <td class="text-center">
                                @if(strtoupper($camp->status_campeonato) === 'ATIVO')
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    {{-- Jogos do campeonato: a tela de jogos (blocos com ações), com o filtro já nele --}}
                                    <a href="{{ route('admin.campeonatos.times', $camp->id_campeonato) }}"
                                       class="btn-tbl view" title="Jogos do campeonato">
                                        <i class="bi bi-calendar2-week"></i><small class="ms-1">Jogos</small>
                                    </a>
                                    <button class="btn-tbl edit" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarCampeonato"
                                        data-id="{{ $camp->id_campeonato }}"
                                        data-nome="{{ $camp->nome_campeonato }}"
                                        data-tipo="{{ $camp->tipo_campeonato }}"
                                        data-inicio="{{ $camp->data_inicio_campeonato?->format('Y-m-d') }}"
                                        data-fim="{{ $camp->data_fim_campeonato?->format('Y-m-d') }}"
                                        data-local="{{ $camp->local_evento }}"
                                        data-organizador="{{ $camp->organizador_campeonato }}"
                                        data-descricao="{{ $camp->descricao_campeonato }}"
                                        data-logo="{{ $camp->logo_evento }}"
                                        data-banner="{{ $camp->banner_evento }}"
                                        data-categorias="{{ json_encode($camp->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all()) }}"
                                        data-participantes="{{ json_encode((object) ($participantes[$camp->id_campeonato] ?? [])) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.campeonatos.toggleStatus', $camp->id_campeonato) }}" method="POST" style="display:inline">
                                        @csrf @method('PATCH')
                                        @if(strtoupper($camp->status_campeonato) === 'ATIVO')
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @if(strtoupper($camp->status_campeonato) !== 'ATIVO')
                                    <form action="{{ route('admin.campeonatos.destroy', $camp->id_campeonato) }}" method="POST"
                                          onsubmit="return confirm('Excluir permanentemente {{ addslashes($camp->nome_campeonato) }}?');" style="display:inline">
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
                            <td colspan="9" class="text-center text-muted py-4">Nenhum campeonato cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

{{-- ── Modal Criar Campeonato ─────────────────────────────────────────────── --}}
<div class="modal fade" id="modalCriarCampeonato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy"></i> Novo Campeonato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.campeonatos.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-info-circle"></i> Informações Gerais</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome_campeonato" class="form-control" maxlength="100"
                                placeholder="Ex: Liga Premiere 2025" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Campeonato::TIPOS as $tipo => $rotuloTipo)
                                <option value="{{ $tipo }}">{{ $rotuloTipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" name="data_inicio_campeonato" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Fim <span class="text-danger">*</span></label>
                            <input type="date" name="data_fim_campeonato" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Local principal</label>
                            <input type="text" name="local_evento" class="form-control" maxlength="100" placeholder="Ex: Campo AACJ" list="locaisUsados" autocomplete="off">
                            <span class="form-text">Opcional. Cada jogo tem o seu local.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Organizador</label>
                            <input type="text" name="organizador_campeonato" class="form-control" maxlength="100" placeholder="Ex: Liga Municipal">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao_campeonato" class="form-control" rows="2"
                                placeholder="Informações adicionais..."></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-image"></i> Imagens</p></div>

                        @include('admin.campeonatos._imagens')

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-tags"></i> Categorias e Times Participantes</p></div>

                        @include('admin.campeonatos._categorias_e_participantes', [
                            'prefixo' => 'c', 'selecionadas' => [], 'participantes' => [],
                        ])

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.partials.sugestoes')

{{-- ── Modal Editar Campeonato ────────────────────────────────────────────── --}}
<div class="modal fade" id="modalEditarCampeonato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Campeonato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCampeonato" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12"><p class="modal-section-label mb-0"><i class="bi bi-info-circle"></i> Informações Gerais</p></div>

                        <div class="col-md-6">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="edit_nome_campeonato" name="nome_campeonato" maxlength="100"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select id="edit_tipo_campeonato" name="tipo_campeonato" class="form-select" required>
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Campeonato::TIPOS as $tipo => $rotuloTipo)
                                <option value="{{ $tipo }}">{{ $rotuloTipo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" id="edit_data_inicio" name="data_inicio_campeonato"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Fim <span class="text-danger">*</span></label>
                            <input type="date" id="edit_data_fim" name="data_fim_campeonato"
                                class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Local principal</label>
                            <input type="text" id="edit_local_evento" name="local_evento" class="form-control" maxlength="100" list="locaisUsados" autocomplete="off">
                            <span class="form-text">Opcional. Cada jogo tem o seu local.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Organizador</label>
                            <input type="text" id="edit_organizador" name="organizador_campeonato" maxlength="100"
                                class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea id="edit_descricao" name="descricao_campeonato"
                                class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-image"></i> Imagens</p></div>

                        @include('admin.campeonatos._imagens', ['editando' => true, 'idLogoAtual' => 'edit_logo_atual', 'idBannerAtual' => 'edit_banner_atual'])

                        <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-tags"></i> Categorias e Times Participantes</p></div>

                        @include('admin.campeonatos._categorias_e_participantes', [
                            'prefixo' => 'e', 'selecionadas' => [], 'participantes' => [],
                        ])

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- Filtros ---
    function aplicarFiltros() {
        const nome      = document.getElementById('filtroNome')?.value.toLowerCase().trim() ?? '';
        const tipo      = document.getElementById('filtroTipo')?.value ?? '';
        const categoria = document.getElementById('filtroCategoria')?.value ?? '';
        const status    = document.getElementById('filtroStatus')?.value ?? '';
        let visiveis = 0;
        document.querySelectorAll('.linha-camp').forEach(row => {
            const ok = (!nome      || row.dataset.nome.includes(nome))
                    && (!tipo      || row.dataset.tipo === tipo)
                    && (!categoria || row.dataset.categorias.includes(` ${categoria} `))
                    && (!status    || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-camp').length;
        const contador = document.getElementById('filtroContador');
        if (contador) contador.textContent = (nome || tipo || categoria || status)
            ? `${visiveis} de ${total} campeonato(s) encontrado(s)` : '';
    }

    ['filtroNome','filtroTipo','filtroCategoria','filtroStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', aplicarFiltros);
    });

    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        document.getElementById('filtroNome').value = '';
        document.getElementById('filtroTipo').value = '';
        document.getElementById('filtroCategoria').value = '';
        document.getElementById('filtroStatus').value = '';
        aplicarFiltros();
    });


    document.querySelectorAll('[data-bs-target="#modalEditarCampeonato"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            document.getElementById('formEditarCampeonato').action = `{{ url('admin/campeonatos') }}/${id}`;

            document.getElementById('edit_nome_campeonato').value = this.dataset.nome        || '';
            document.getElementById('edit_data_inicio').value     = this.dataset.inicio      || '';
            document.getElementById('edit_data_fim').value        = this.dataset.fim          || '';
            document.getElementById('edit_local_evento').value    = this.dataset.local        || '';
            document.getElementById('edit_organizador').value     = this.dataset.organizador  || '';
            document.getElementById('edit_descricao').value       = this.dataset.descricao    || '';

            const logoEl = document.getElementById('edit_logo_atual');
            logoEl.textContent = this.dataset.logo ? 'Atual: ' + this.dataset.logo : '';

            const bannerEl = document.getElementById('edit_banner_atual');
            bannerEl.textContent = this.dataset.banner ? 'Atual: ' + this.dataset.banner : '';

            // Tipo gravado em maiúsculas (Campeonato::TIPOS); valor antigo fora da lista entra como opção
            const selTipo = document.getElementById('edit_tipo_campeonato');
            const tipo = (this.dataset.tipo || '').toUpperCase();
            selTipo.querySelectorAll('.js-tipo-antigo').forEach(opt => opt.remove());
            if (tipo && ![...selTipo.options].some(opt => opt.value === tipo)) {
                selTipo.add(new Option(tipo, tipo, false, false));
                selTipo.lastElementChild.classList.add('js-tipo-antigo');
            }
            selTipo.value = tipo;

            // Categorias e participantes por categoria (partial _categorias_e_participantes)
            window.preencherCategoriasDoCampeonato(
                document.querySelector('#formEditarCampeonato .js-cat-part'),
                JSON.parse(this.dataset.categorias || '[]'),
                JSON.parse(this.dataset.participantes || '{}'));
        });
    });
});
</script>
@endpush
@endsection
