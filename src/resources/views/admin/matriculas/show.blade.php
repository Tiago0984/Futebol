@extends('layout.admin')

@section('title', 'Detalhes da Matrícula')

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700;">Detalhes da Matrícula</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="{{ route('admin.matriculas.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>

            @if (session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Sucesso!</strong> {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Cabeçalho do atleta --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex align-items-center gap-4">
                    @php
                        $partes = explode(' ', trim($atleta->nome_atleta));
                        $iniciais = strtoupper(substr($partes[0], 0, 1) . (count($partes) > 1 ? substr(end($partes), 0, 1) : ''));
                        $paleta = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#2ec4b6','#e76f51','#457b9d'];
                        $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                        $autorizacao = $atleta->autorizacoes->first();
                        $autorizacaoPendente = !$autorizacao || $autorizacao->status_autorizacao !== 'ASSINADO';
                    @endphp
                    @if ($atleta->foto_atleta)
                        <img src="{{ asset('storage/' . $atleta->foto_atleta) }}"
                            alt="{{ $atleta->nome_atleta }}"
                            class="rounded-circle object-fit-cover"
                            style="width:80px;height:80px;">
                    @else
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                            style="width:80px;height:80px;background:{{ $corAvatar }};font-size:1.5rem;">
                            {{ $iniciais }}
                        </div>
                    @endif
                    <div>
                        <h3 class="mb-1">{{ $atleta->nome_atleta }}</h3>
                        <span class="badge bg-warning text-dark" style="font-size:0.85rem;">PENDENTE</span>
                        @if ($autorizacao)
                            @if ($autorizacao->status_autorizacao === 'ASSINADO')
                                <span class="badge bg-success ms-1" style="font-size:0.85rem;">Autorização Assinada</span>
                            @else
                                <span class="badge bg-secondary ms-1" style="font-size:0.85rem;">Autorização Pendente</span>
                            @endif
                        @endif
                    </div>
                    <div class="ms-auto d-flex flex-column align-items-end gap-2">
                        <div class="d-flex gap-2">
                            <form action="{{ route('admin.matriculas.aprovar', $atleta->id_atleta) }}" method="POST"
                                onsubmit="return confirm('Aprovar matrícula de {{ $atleta->nome_atleta }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-success px-4"
                                    @if ($autorizacaoPendente) disabled title="Aguardando assinatura da autorização pelo responsável" @endif>
                                    <i class="bi bi-check-lg"></i> Aprovar
                                </button>
                            </form>
                            <form action="{{ route('admin.matriculas.rejeitar', $atleta->id_atleta) }}" method="POST"
                                onsubmit="return confirm('Rejeitar matrícula de {{ $atleta->nome_atleta }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="bi bi-x-lg"></i> Rejeitar
                                </button>
                            </form>
                        </div>
                        @if ($autorizacaoPendente)
                            <small class="text-warning fw-semibold">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Aprovação bloqueada — aguardando assinatura da autorização.
                            </small>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-4">

                {{-- Dados do Atleta --}}
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-person-badge me-2"></i> Dados do Atleta
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" style="width:45%">CPF</td><td>{{ $atleta->cpf_atleta }}</td></tr>
                                <tr><td class="text-muted">RG</td><td>{{ $atleta->rg_atleta }}</td></tr>
                                <tr><td class="text-muted">Data de Nasc.</td><td>{{ $atleta->data_nasc_atleta?->format('d/m/Y') ?? '—' }}</td></tr>
                                <tr><td class="text-muted">Sexo</td><td>{{ $atleta->sexo_atleta === 'M' ? 'Masculino' : 'Feminino' }}</td></tr>
                                <tr><td class="text-muted">Peso</td><td>{{ $atleta->peso_atleta ? $atleta->peso_atleta . ' kg' : '—' }}</td></tr>
                                <tr><td class="text-muted">Altura</td><td>{{ $atleta->altura_atleta ? $atleta->altura_atleta . ' m' : '—' }}</td></tr>
                                <tr><td class="text-muted">E-mail</td><td>{{ $atleta->email_atleta }}</td></tr>
                                <tr><td class="text-muted">Escola</td><td>{{ $atleta->escola_atleta }}</td></tr>
                                <tr><td class="text-muted">Série</td><td>{{ $atleta->serie_atleta }}</td></tr>
                                <tr><td class="text-muted">Período</td><td>{{ $atleta->periodo_escolar_atleta }}</td></tr>
                                @if ($atleta->descricao_atleta)
                                <tr><td class="text-muted">Observações</td><td>{{ $atleta->descricao_atleta }}</td></tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Endereço do Atleta --}}
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-geo-alt me-2"></i> Endereço do Atleta
                        </div>
                        <div class="card-body">
                            @if ($atleta->endereco)
                                <table class="table table-sm mb-0">
                                    <tr><td class="text-muted" style="width:45%">CEP</td><td>{{ $atleta->endereco->cep_endereco }}</td></tr>
                                    <tr><td class="text-muted">Rua</td><td>{{ $atleta->endereco->rua_endereco }}, {{ $atleta->endereco->numero_endereco }}</td></tr>
                                    @if ($atleta->endereco->complemento_endereco)
                                    <tr><td class="text-muted">Complemento</td><td>{{ $atleta->endereco->complemento_endereco }}</td></tr>
                                    @endif
                                    <tr><td class="text-muted">Bairro</td><td>{{ $atleta->endereco->bairro_endereco }}</td></tr>
                                    <tr><td class="text-muted">Cidade</td><td>{{ $atleta->endereco->cidade_endereco }} / {{ $atleta->endereco->estado_endereco }}</td></tr>
                                </table>
                            @else
                                <p class="text-muted">Endereço não informado.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Responsável --}}
                @foreach ($atleta->responsaveis as $responsavel)
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-people me-2"></i> Responsável
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" style="width:45%">Nome</td><td>{{ $responsavel->nome_responsavel }}</td></tr>
                                <tr><td class="text-muted">Parentesco</td><td>{{ $responsavel->pivot->grau_parentesco_responsavel ?? '—' }}</td></tr>
                                <tr><td class="text-muted">CPF</td><td>{{ $responsavel->cpf_responsavel }}</td></tr>
                                <tr><td class="text-muted">RG</td><td>{{ $responsavel->rg_responsavel }}</td></tr>
                                <tr><td class="text-muted">Telefone</td><td>{{ $responsavel->telefone_responsavel ?? '—' }}</td></tr>
                                <tr><td class="text-muted">WhatsApp</td><td>{{ $responsavel->whatsapp_responsavel }}</td></tr>
                                <tr>
                                    <td class="text-muted">Aceite</td>
                                    <td>
                                        @if ($responsavel->aceite_responsavel === 'S')
                                            <span class="badge bg-success">Assinou</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendente</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            @if ($responsavel->endereco)
                                <hr>
                                <p class="text-muted small mb-1 fw-semibold">Endereço do Responsável</p>
                                <p class="mb-0 small">
                                    {{ $responsavel->endereco->rua_endereco }}, {{ $responsavel->endereco->numero_endereco }} —
                                    {{ $responsavel->endereco->bairro_endereco }},
                                    {{ $responsavel->endereco->cidade_endereco }}/{{ $responsavel->endereco->estado_endereco }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- Autorização --}}
                @if ($autorizacao)
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-pen me-2"></i> Autorização
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="text-muted" style="width:45%">Status</td>
                                    <td>
                                        @if ($autorizacao->status_autorizacao === 'ASSINADO')
                                            <span class="badge bg-success">Assinado</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendente</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr><td class="text-muted">Data</td><td>{{ $autorizacao->data_assinatura_autorizacao?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                                @if ($autorizacao->status_autorizacao === 'ASSINADO' && $autorizacao->responsavel?->assinatura_responsavel)
                                <tr>
                                    <td class="text-muted align-top">Assinatura</td>
                                    <td>
                                        <div class="border rounded p-2 d-inline-block bg-white">
                                            <img src="{{ asset($autorizacao->responsavel->assinatura_responsavel) }}"
                                                alt="Assinatura do Responsável"
                                                style="max-width:300px; max-height:120px; object-fit:contain; display:block;">
                                        </div>
                                        <div class="text-muted mt-1" style="font-size:0.78rem;">
                                            {{ $autorizacao->responsavel->nome_responsavel }}
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @if ($autorizacao->status_autorizacao !== 'ASSINADO' && $autorizacao->token_assinatura)
                                <tr>
                                    <td class="text-muted" style="width:45%">Link de Autorização</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" readonly
                                                id="linkAutorizacao"
                                                value="{{ route('assinar.show', $autorizacao->token_assinatura) }}">
                                            <button class="btn btn-outline-secondary" type="button"
                                                id="btnCopiarLink"
                                                onclick="navigator.clipboard.writeText(document.getElementById('linkAutorizacao').value).then(function(){ var btn = document.getElementById('btnCopiarLink'); btn.innerHTML = '<i class=\'bi bi-check-lg\'></i>'; btn.classList.replace('btn-outline-secondary','btn-outline-success'); setTimeout(function(){ btn.innerHTML = '<i class=\'bi bi-clipboard\'></i>'; btn.classList.replace('btn-outline-success','btn-outline-secondary'); }, 2000); })"
                                                title="Copiar link">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size:0.78rem;">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Envie ao responsável caso tenha perdido o link original.
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
                @endif

            </div>{{-- /row --}}

        </div>
    </main>
@endsection
