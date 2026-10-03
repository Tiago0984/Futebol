@extends('layout.admin')

@section('title', 'Evento: ' . $evento->titulo_evento_calendario)

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-8">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700;">{{ $evento->titulo_evento_calendario }}</h1>
                </div>
                <div class="col-sm-4 text-end">
                    <a href="{{ route('admin.calendario.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar ao calendário
                    </a>
                </div>
            </div>

            @if (session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Dados do evento --}}
            @php
                $classeSituacao = [
                    'ATIVO' => 'ativo', 'ALTERADO' => 'pendente', 'CONCLUIDO' => 'concluido',
                    'CANCELADO' => 'inativo', 'INATIVO' => 'rejeitado',
                ][$evento->situacao];
            @endphp
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap gap-4 align-items-center">
                    <div><span class="text-muted small d-block">Data</span>{{ $evento->data_evento_calendario->format('d/m/Y') }}</div>
                    <div><span class="text-muted small d-block">Horário</span>{{ $evento->horario_texto }}</div>
                    <div><span class="text-muted small d-block">Local</span>{{ $evento->local_evento_calendario ?? '—' }}</div>
                    <div><span class="text-muted small d-block">Tipo</span><span class="badge-cat">{{ $evento->tipo_label }}</span></div>
                    <div><span class="text-muted small d-block">Categoria</span>{{ $evento->categoria?->rotulo ?? 'Sem categoria (individual)' }}</div>
                    <div><span class="text-muted small d-block">Responsável</span>{{ $evento->responsavel?->nome_usuario ?? '—' }}</div>
                    <div><span class="text-muted small d-block">Situação</span><span class="badge-status {{ $classeSituacao }}">{{ $evento->situacao_label }}</span></div>
                </div>
            </div>

            <div class="row g-4">

                {{-- Inscritos --}}
                <div class="col-lg-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-people me-2"></i> Inscritos ({{ $inscricoes->count() }})
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Atleta</th>
                                        <th>Categoria</th>
                                        <th>Origem</th>
                                        <th>Inscrito por</th>
                                        <th class="text-center" style="width:60px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($inscricoes as $inscricao)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $inscricao->atleta->nome_atleta }}</td>
                                        <td class="text-muted">{{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</td>
                                        <td><span class="badge-cat">{{ $inscricao->origem_label }}</span></td>
                                        <td class="text-muted small">
                                            {{ $inscricao->usuario?->nome_usuario ?? '—' }}<br>
                                            {{ $inscricao->data_evento_atleta?->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="text-center">
                                            <form action="{{ route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $inscricao->id_atleta]) }}"
                                                  method="POST" style="display:inline"
                                                  onsubmit="return confirm(@js('Remover a inscrição de ' . $inscricao->atleta->nome_atleta . '?'))">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-tbl delete" title="Remover inscrição">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Nenhum atleta inscrito.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            @if ($inscritosInativos > 0)
                                <p class="text-muted small px-3 py-2 mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    {{ $inscritosInativos }} inscrito(s) com atleta inativo, pendente ou rejeitado não aparece(m) aqui.
                                    Se o atleta voltar a ficar ativo, a inscrição volta a valer.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Adicionar --}}
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-person-plus me-2"></i> Inscrever atleta
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                <select name="id_atleta" class="form-select" required>
                                    <option value="">— Escolher atleta ativo —</option>
                                    @foreach ($disponiveis as $rotuloCategoria => $atletasDaCategoria)
                                    <optgroup label="{{ $rotuloCategoria }}">
                                        @foreach ($atletasDaCategoria as $atleta)
                                        <option value="{{ $atleta->id_atleta }}">{{ $atleta->nome_atleta }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-success">Inscrever</button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-people-fill me-2"></i> Adicionar todos de uma categoria
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Para eventos de várias categorias (ex.: avaliação física). Pode usar várias vezes;
                                quem já está inscrito é ignorado.
                            </p>
                            <form action="{{ route('admin.calendario.eventos.inscricoes.categoria', $evento->id_evento_calendario) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                <select name="id_categoria" class="form-select" required>
                                    <option value="">— Escolher categoria —</option>
                                    @foreach ($categorias as $cat)
                                    <option value="{{ $cat->id_categoria }}">{{ $cat->rotulo }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-success">Adicionar</button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
@endsection
