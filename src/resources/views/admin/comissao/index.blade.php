@extends('layout.admin')

@section('title', 'Comissão técnica')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Comissão técnica</h1>
                <p class="page-subtitle">Os profissionais que conduzem as atividades. O app mostra o perfil de quem é o responsável por cada compromisso.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.comissao.create') }}" class="btn-admin-primary">
                    <i class="bi bi-plus-lg"></i> Novo profissional
                </a>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ $profissionais->count() }} profissional(is) · {{ $profissionais->where('status_comissao_tecnica', 'ATIVO')->count() }} ativo(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Foto</th>
                            <th>Nome</th>
                            <th>Categorias</th>
                            <th>Na AACJ desde</th>
                            <th>Contato institucional</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profissionais as $p)
                        <tr>
                            <td>
                                @if($p->urlFoto())
                                <img src="{{ $p->urlFoto() }}" alt="{{ $p->nome_comissao_tecnica }}" style="width:40px;height:40px;object-fit:cover;border-radius:50%;"
                                     title="{{ $p->foto_autorizada_comissao_tecnica ? 'Foto autorizada para o app' : 'Foto sem autorização: não vai para o app' }}">
                                @else
                                <div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;" class="bg-body-secondary">
                                    <i class="bi bi-person text-muted"></i>
                                </div>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $p->nome_comissao_tecnica }}</span>
                                <div class="text-muted" style="font-size:0.75rem;">
                                    {{ $p->cargo_label }}@if($p->especialidade_comissao_tecnica) · {{ $p->especialidade_comissao_tecnica }}@endif
                                    @if($p->urlFoto() && ! $p->foto_autorizada_comissao_tecnica) · <span class="text-warning">foto sem autorização</span>@endif
                                </div>
                            </td>
                            <td>
                                @foreach($p->categorias as $cat)
                                <span class="badge-cat {{ (int) $cat->id_categoria === (int) $p->id_categoria ? 'fw-bold' : '' }}"
                                      @if((int) $cat->id_categoria === (int) $p->id_categoria) title="Categoria principal" @endif>{{ $cat->rotulo }}</span>
                                @endforeach
                                @if($p->categorias->isEmpty())<span class="text-muted">—</span>@endif
                            </td>
                            <td class="text-muted">{{ $p->data_inicio_comissao_tecnica?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-muted" style="font-size:0.82rem;">
                                @if($p->telefone_institucional_comissao_tecnica)<div><i class="bi bi-telephone"></i> {{ $p->telefone_formatado }}</div>@endif
                                @if($p->email_institucional_comissao_tecnica)<div><i class="bi bi-envelope"></i> {{ $p->email_institucional_comissao_tecnica }}</div>@endif
                                @if(! $p->telefone_institucional_comissao_tecnica && ! $p->email_institucional_comissao_tecnica)—@endif
                            </td>
                            <td class="text-center">
                                @if($p->estaAtivo())
                                    <span class="badge-status ativo">Ativo</span>
                                @else
                                    <span class="badge-status inativo">Inativo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.comissao.edit', $p->id_comissao_tecnica) }}" class="btn-tbl edit" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('admin.comissao.toggleStatus', $p->id_comissao_tecnica) }}" method="POST" style="display:inline"
                                          onsubmit="return confirm(@js($p->estaAtivo() ? 'Desativar ' . $p->nome_comissao_tecnica . '? Não aparece mais para novas escolhas nem no app; continua nos eventos em que já está.' : 'Reativar ' . $p->nome_comissao_tecnica . '?'))">
                                        @csrf @method('PATCH')
                                        @if($p->estaAtivo())
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
                            <td colspan="7" class="text-center text-muted py-4">Nenhum profissional cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
@endsection
