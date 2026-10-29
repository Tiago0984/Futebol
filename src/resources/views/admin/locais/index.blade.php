@extends('layout.admin')

@section('title', 'Locais')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Locais</h1>
                <p class="page-subtitle">Campos, quadras e arenas dos treinos, jogos e eventos. O app usa o endereço e o link do mapa no "Abrir mapa".</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.locais.create') }}" class="btn-admin-primary">
                    <i class="bi bi-plus-lg"></i> Novo local
                </a>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Textos de local já usados (eventos, grade, campeonatos) que ainda não são um local cadastrado --}}
        @if($semCadastro->isNotEmpty())
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 mb-1"><i class="bi bi-geo"></i> Locais usados que ainda não estão cadastrados</h2>
                <p class="small text-muted mb-3">Escritos à mão nos eventos, na grade de treinos ou no local principal dos campeonatos. Cadastrar não muda nada neles: o local passa a ser escolhido da lista nos formulários.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($semCadastro as $usado)
                    <a href="{{ route('admin.locais.create', ['nome' => $usado->texto]) }}" class="btn btn-sm btn-outline-secondary js-local-sem-cadastro"
                       title="Usado {{ $usado->usos }} vez(es). Cadastrar com este nome">
                        <i class="bi bi-plus-circle"></i> {{ $usado->texto }} <span class="text-muted">({{ $usado->usos }})</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="table-card">
            <div class="table-card-toolbar d-flex flex-wrap align-items-center gap-2">
                <span class="tbl-count">{{ $locais->count() }} local(is) · {{ $locais->where('status_local', 'ATIVO')->count() }} ativo(s)</span>
                <div class="ms-auto d-flex gap-2">
                    <input type="text" id="filtroLocal" class="form-control form-control-sm" placeholder="Buscar por nome ou cidade..." style="max-width:240px">
                    <select id="filtroStatusLocal" class="form-select form-select-sm" style="max-width:140px">
                        <option value="">Todos</option>
                        <option value="ATIVO">Ativos</option>
                        <option value="INATIVO">Inativos</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Endereço</th>
                            <th class="text-center">Mapa</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locais as $local)
                        <tr class="linha-local" data-busca="{{ mb_strtolower($local->nome_local . ' ' . $local->cidade_local) }}" data-status="{{ $local->status_local }}">
                            <td class="fw-semibold">{{ $local->nome_local }}</td>
                            <td class="text-muted" style="font-size:0.85rem;">{{ $local->enderecoEmUmaLinha() }}</td>
                            <td class="text-center">
                                <a href="{{ $local->mapaUrl() }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"
                                   title="{{ $local->mapa_url_local ? 'Link cadastrado' : 'Sem link: busca pelo nome e pelo endereço' }}">
                                    <i class="bi bi-map"></i> Abrir no mapa
                                </a>
                                @unless($local->mapa_url_local)<div class="text-muted" style="font-size:0.7rem;">pela busca</div>@endunless
                            </td>
                            <td class="text-center">
                                @if($local->estaAtivo())
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.locais.edit', $local->id_local) }}" class="btn-tbl edit" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('admin.locais.toggleStatus', $local->id_local) }}" method="POST" style="display:inline"
                                          onsubmit="return confirm(@js($local->estaAtivo() ? 'Desativar ' . $local->nome_local . '? Não aparece mais para novas escolhas; continua nos eventos em que já está.' : 'Reativar ' . $local->nome_local . '?'))">
                                        @csrf @method('PATCH')
                                        @if($local->estaAtivo())
                                            <button type="submit" class="btn-tbl deactivate" title="Desativar"><i class="bi bi-eye-slash"></i></button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Reativar"><i class="bi bi-eye"></i></button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Nenhum local cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
// Busca por nome ou cidade e filtro de status, na própria página
(() => {
    const busca = document.getElementById('filtroLocal'), status = document.getElementById('filtroStatusLocal');
    const filtrar = () => document.querySelectorAll('.linha-local').forEach(linha => {
        linha.style.display = (!busca.value.trim() || linha.dataset.busca.includes(busca.value.trim().toLowerCase()))
            && (!status.value || linha.dataset.status === status.value) ? '' : 'none';
    });
    busca.addEventListener('input', filtrar);
    status.addEventListener('change', filtrar);
})();
</script>
@endsection
