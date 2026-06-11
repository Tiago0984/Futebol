@extends('layout.admin')

@section('title', 'Matrículas Pendentes')

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700;">Matrículas Pendentes</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <span class="badge bg-warning text-dark" style="font-size: 0.9rem; padding: 8px 14px;">
                        {{ $matriculas->count() }} pendente(s)
                    </span>
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
                                    <th>Data do Cadastro</th>
                                    <th class="text-center">Autorização</th>
                                    <th class="text-center" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($matriculas as $atleta)
                                    @php
                                        $partes = explode(' ', trim($atleta->nome_atleta));
                                        $iniciais = strtoupper(
                                            substr($partes[0], 0, 1) .
                                            (count($partes) > 1 ? substr(end($partes), 0, 1) : '')
                                        );
                                        $paleta = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#2ec4b6','#e76f51','#457b9d'];
                                        $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                                        $responsavel  = $atleta->responsaveis->first();
                                        $autorizacao  = $atleta->autorizacoes->first();
                                        $autPendente  = !$autorizacao || $autorizacao->status_autorizacao !== 'ASSINADO';
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
                                            <div class="text-muted" style="font-size:0.78rem;">
                                                {{ $atleta->email_atleta }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($responsavel)
                                                <div class="small fw-semibold">{{ $responsavel->nome_responsavel }}</div>
                                                <div class="text-muted" style="font-size:0.75rem;">
                                                    {{ $responsavel->whatsapp_responsavel }}
                                                </div>
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
                                        <td>
                                            <span class="small text-muted">
                                                {{ $atleta->criado_em ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if (!$autPendente)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-pen me-1"></i> Assinada
                                                </span>
                                            @else
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-hourglass-split me-1"></i> Pendente
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="{{ route('admin.matriculas.show', $atleta->id_atleta) }}"
                                                    class="btn btn-sm btn-primary d-flex flex-column align-items-center justify-content-center"
                                                    title="Ver detalhes"
                                                    style="width:70px; height:50px; padding:.25rem .3rem; font-size:11px; gap:2px;">
                                                    <i class="bi bi-eye" style="font-size:14px;"></i>
                                                    Visualizar
                                                </a>

                                                <form action="{{ route('admin.matriculas.aprovar', $atleta->id_atleta) }}"
                                                    method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Aprovar matrícula de {{ $atleta->nome_atleta }}?')">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success d-flex flex-column align-items-center justify-content-center"
                                                        style="width:70px; height:50px; padding:.25rem .3rem; font-size:11px; gap:2px;"
                                                        @if ($autPendente) disabled title="Aguardando assinatura da autorização" @else title="Aprovar" @endif>
                                                        <i class="bi bi-check-lg" style="font-size:14px;"></i>
                                                        Aprovar
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.matriculas.rejeitar', $atleta->id_atleta) }}"
                                                    method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Rejeitar matrícula de {{ $atleta->nome_atleta }}?')">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-danger d-flex flex-column align-items-center justify-content-center"
                                                        title="Rejeitar"
                                                        style="width:70px; height:50px; padding:.25rem .3rem; font-size:11px; gap:2px;">
                                                        <i class="bi bi-x-lg" style="font-size:14px;"></i>
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

        </div>
    </main>
@endsection
