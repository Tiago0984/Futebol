@extends('layout.admin')

@section('title', 'Matrículas Rejeitadas')

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700;">Matrículas Rejeitadas</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="{{ route('admin.matriculas.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Pendentes
                    </a>
                </div>
            </div>

            @if (session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Sucesso!</strong> {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 70px;">Foto</th>
                                    <th>Nome do Atleta</th>
                                    <th>Responsável</th>
                                    <th>Escola</th>
                                    <th class="text-center" style="width: 160px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rejeitadas as $atleta)
                                    @php
                                        $partes = explode(' ', trim($atleta->nome_atleta));
                                        $iniciais = strtoupper(
                                            substr($partes[0], 0, 1) .
                                            (count($partes) > 1 ? substr(end($partes), 0, 1) : '')
                                        );
                                        $paleta = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#2ec4b6','#e76f51','#457b9d'];
                                        $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                                        $responsavel = $atleta->responsaveis->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($atleta->foto_atleta)
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
                                            @if ($responsavel)
                                                <div class="small fw-semibold">{{ $responsavel->nome_responsavel }}</div>
                                                <div class="text-muted" style="font-size:0.75rem;">{{ $responsavel->whatsapp_responsavel }}</div>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small">{{ $atleta->escola_atleta }}</div>
                                            <div class="text-muted" style="font-size:0.75rem;">
                                                {{ $atleta->serie_atleta }} · {{ $atleta->periodo_escolar_atleta }}
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">

                                                {{-- Aprovar --}}
                                                <form action="{{ route('admin.matriculas.aprovar', $atleta->id_atleta) }}"
                                                    method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Aprovar matrícula de {{ $atleta->nome_atleta }}?')">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success" title="Aprovar"
                                                        style="padding:.25rem .5rem;">
                                                        <i class="bi bi-check-lg" style="font-size:13px;"></i> Aprovar
                                                    </button>
                                                </form>

                                                {{-- Excluir permanentemente --}}
                                                <form action="{{ route('admin.matriculas.deletar', $atleta->id_atleta) }}"
                                                    method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Excluir permanentemente o cadastro de {{ $atleta->nome_atleta }}? Esta ação não pode ser desfeita.')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Excluir permanentemente"
                                                        style="padding:.25rem .5rem;">
                                                        <i class="bi bi-trash" style="font-size:13px;"></i> Excluir
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
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

        </div>
    </main>
@endsection
