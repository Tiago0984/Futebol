@extends('layout.admin')

@section('title', 'Gerenciar Atletas')

@section('content')
    <main class="app-main pt-3" style="text-align: center;">
        <div class="container-fluid">

            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700; text-align: left;">Gerenciar Atletas
                    </h1>
                </div>
                <div class="col-sm-6 text-end pr-4 d-flex align-items-center justify-content-end gap-2" style="text-align: right;">

                    {{-- Seletor de colunas --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary px-3 dropdown-toggle" type="button"
                            id="dropdownColunas" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false">
                            <i class="bi bi-funnel me-1"></i> Colunas
                        </button>
                        <div class="dropdown-menu p-2 shadow" aria-labelledby="dropdownColunas"
                            style="min-width:200px; z-index:1055;">
                            <input type="text" id="searchColuna" class="form-control form-control-sm mb-2"
                                placeholder="Pesquisar...">
                            <div class="dropdown-divider my-1"></div>
                            <label class="dropdown-item d-flex align-items-center gap-2 py-1 rounded" style="cursor:pointer;">
                                <input type="checkbox" id="col_todos" checked> <strong>Todos</strong>
                            </label>
                            <div class="dropdown-divider my-1"></div>
                            @foreach ([0 => 'Foto', 1 => 'Matrícula', 2 => 'Nome', 3 => 'Time', 4 => 'Responsável', 5 => 'Categoria', 6 => 'Posição', 7 => 'Status'] as $idx => $label)
                                <label class="dropdown-item d-flex align-items-center gap-2 py-1 rounded col-item"
                                    data-col="{{ $idx }}" style="cursor:pointer;">
                                    <input type="checkbox" class="col-check" data-col="{{ $idx }}" checked>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <button type="button" class="btn btn-success px-3" data-bs-toggle="modal"
                        data-bs-target="#modalNovoAtleta">
                        <i class="bi bi-person-plus"></i> Novo Atleta
                    </button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">

                    @if (session('sucesso'))
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert"
                            style="text-align: left;">
                            <strong>Sucesso!</strong> {{ session('sucesso') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                        </div>
                    @endif

                    @if (session('erro'))
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert"
                            style="text-align: left;">
                            <strong>Erro!</strong> {{ session('erro') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert"
                            style="text-align: left;">
                            <strong>Ops! Verifique os campos do formulário:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                                style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 70px;">Foto</th>
                                    <th class="text-center" style="padding-left: 50px;">Matrícula</th>
                                    <th style="text-align: left; padding-left: 300px;">Nome</th>
                                    <th>Time</th>
                                    <th>Responsável</th>
                                    <th>Categoria</th>
                                    <th>Posição</th>
                                    <th style="font-size: 14px;">Status</th>
                                    <th style="width: 130px;" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($atletas as $atleta)
                                    @php
                                        $partes = explode(' ', trim($atleta->nome_atleta));
                                        $iniciais = strtoupper(
                                            substr($partes[0], 0, 1) .
                                                (count($partes) > 1 ? substr(end($partes), 0, 1) : ''),
                                        );
                                        $paleta = [
                                            '#4361ee',
                                            '#3a0ca3',
                                            '#7209b7',
                                            '#f72585',
                                            '#4cc9f0',
                                            '#2ec4b6',
                                            '#e76f51',
                                            '#457b9d',
                                        ];
                                        $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                                        $ativo = strtolower($atleta->status_atleta ?? '') === 'ativo';

                                        $categoria = $atleta->categorias->first();
                                        $nomeCategoria = $categoria->nome_categoria ?? null;
                                        $idCategoria = $categoria->id_categoria ?? null;
                                        $responsavel = $atleta->responsaveis->first();
                                        $nomeResponsavel = $responsavel->nome_responsavel ?? null;
                                        $grauParentesco = $responsavel?->pivot->grau_parentesco_responsavel ?? null;
                                        $posicao = $atleta->posicao_atleta ?? null;

                                        $corCategoria = match (true) {
                                            str_contains($nomeCategoria ?? '', '11') => 'secondary',
                                            str_contains($nomeCategoria ?? '', '13') => 'success',
                                            str_contains($nomeCategoria ?? '', '15') => 'primary',
                                            str_contains($nomeCategoria ?? '', '17') => 'warning',
                                            str_contains($nomeCategoria ?? '', '19') => 'info',
                                            default => 'secondary',
                                        };

                                        $corPosicao = '#0dcaf0';

                                        $idade = $atleta->data_nasc_atleta?->age;
                                        $time = $atleta->times->first();
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($atleta->foto_atleta)
                                                <img src="{{ asset('futebol/images/atleta/' . $atleta->foto_atleta) }}"
                                                    alt="{{ $atleta->nome_atleta }}" class="rounded-circle object-fit-cover"
                                                    style="width:42px;height:42px;">
                                            @else
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                    style="width:42px;height:42px;background:{{ $corAvatar }};font-size:0.8rem;flex-shrink:0;">
                                                    {{ $iniciais }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center" style="padding-left: 50px;">
                                            @if ($atleta->numero_matricula_atleta)
                                                <span class="badge bg-secondary text-white fw-bold"
                                                    style="font-size:13px; padding:4px 10px;">{{ $atleta->numero_matricula_atleta }}</span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td style="text-align: left; padding-left: 300px;">
                                            @php $camisa = $time?->pivot->camisa_atleta_time ?: null; @endphp
                                            <strong class="text-dark">{{ $atleta->nome_atleta }}</strong>
                                            <div class="text-muted" style="font-size:0.78rem; line-height: 1.5;">
                                                {!! $camisa ? 'Camisa Nº ' . $camisa : '' !!}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($atleta->times->isNotEmpty())
                                                <div class="d-flex flex-column gap-1">
                                                    @foreach ($atleta->times as $t)
                                                        <span class="badge bg-dark text-white"
                                                            style="font-size: 12px; padding: 5px 10px; white-space: nowrap; text-transform: uppercase;">
                                                            {{ $t->nome_time }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($nomeResponsavel)
                                                <div class="small fw-semibold" style="line-height:1.3;">
                                                    {{ $nomeResponsavel }}</div>
                                                @if ($grauParentesco)
                                                    <div class="text-muted" style="font-size:0.75rem;">{{ $grauParentesco }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($nomeCategoria)
                                                <span class="badge bg-{{ $corCategoria }} text-white"
                                                    style="font-size: 13px; padding: 5px 10px; text-transform: uppercase;">
                                                    {{ $nomeCategoria }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary text-white"
                                                    style="font-size: 13px; padding: 5px 10px;">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($posicao)
                                                <span class="badge text-white fw-semibold"
                                                    style="background:{{ $corPosicao }}; font-size: 12px; padding: 5px 10px; text-transform: uppercase;">
                                                    {{ $posicao }}
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($ativo)
                                                <span class="badge bg-success text-white"
                                                    style="font-size: 12px; padding: 6px 10px; font-weight: 700;">ATIVO</span>
                                            @else
                                                <span class="badge bg-danger text-white"
                                                    style="font-size: 12px; padding: 6px 10px; font-weight: 700;">INATIVO</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap: 5px;">

                                                <button type="button" class="btn btn-sm btn-warning text-dark btn-editar"
                                                    title="Editar" style="padding: .25rem .4rem; line-height: 1;"
                                                    data-bs-toggle="modal" data-bs-target="#modalEditarAtleta"
                                                    data-id="{{ $atleta->id_atleta }}"
                                                    data-nome="{{ $atleta->nome_atleta }}"
                                                    data-matricula="{{ $atleta->numero_matricula_atleta }}"
                                                    data-data-nasc="{{ $atleta->data_nasc_atleta ? $atleta->data_nasc_atleta->format('Y-m-d') : '' }}"
                                                    data-cpf="{{ $atleta->cpf_atleta }}"
                                                    data-rg="{{ $atleta->rg_atleta }}"
                                                    data-sexo="{{ $atleta->sexo_atleta }}"
                                                    data-peso="{{ $atleta->peso_atleta }}"
                                                    data-altura="{{ $atleta->altura_atleta }}"
                                                    data-escola="{{ $atleta->escola_atleta }}"
                                                    data-serie="{{ $atleta->serie_atleta }}"
                                                    data-periodo="{{ $atleta->periodo_escolar_atleta }}"
                                                    data-descricao="{{ $atleta->descricao_atleta }}"
                                                    data-categoria="{{ $idCategoria }}"
                                                    data-posicao="{{ $atleta->posicao_atleta }}"
                                                    data-telefone="{{ $atleta->telefone_atleta }}"
                                                    data-sala="{{ $atleta->sala_atleta }}"
                                                    data-email="{{ $atleta->email_atleta }}"
                                                    data-times="{{ json_encode($atleta->times->pluck('id_time')->toArray()) }}"
                                                    data-camisa="{{ $time?->pivot->camisa_atleta_time ?? '' }}"
                                                    data-cep="{{ $atleta->endereco->cep_endereco ?? '' }}"
                                                    data-rua="{{ $atleta->endereco->rua_endereco ?? '' }}"
                                                    data-numero-end="{{ $atleta->endereco->numero_endereco ?? '' }}"
                                                    data-bairro="{{ $atleta->endereco->bairro_endereco ?? '' }}"
                                                    data-complemento="{{ $atleta->endereco->complemento_endereco ?? '' }}"
                                                    data-cidade="{{ $atleta->endereco->cidade_endereco ?? '' }}"
                                                    data-estado="{{ $atleta->endereco->estado_endereco ?? '' }}">
                                                    <i class="bi bi-pencil" style="font-size: 13px;"></i>
                                                </button>

                                                <form
                                                    action="{{ route('admin.atletas.toggleStatus', $atleta->id_atleta) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Deseja realmente alterar o status deste atleta?');"
                                                    style="display: inline-block; margin: 0;">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($ativo)
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                            title="Inativar Atleta"
                                                            style="padding: .25rem .4rem; line-height: 1;">
                                                            <i class="bi bi-eye-slash" style="font-size: 13px;"></i>
                                                        </button>
                                                    @else
                                                        <button type="submit" class="btn btn-sm btn-success"
                                                            title="Ativar Atleta"
                                                            style="padding: .25rem .4rem; line-height: 1;">
                                                            <i class="bi bi-eye" style="font-size: 13px;"></i>
                                                        </button>
                                                    @endif
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                                            Nenhum atleta cadastrado ainda.
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

    @include('admin.atletas.modals.create')
    @include('admin.atletas.modals.edit')

    @if ($errors->any() && old('form_origin') === 'create')
        <div class="modal-backdrop fade show"></div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Realça visualmente o card do time ao marcar/desmarcar
            document.querySelectorAll('.edit-time-checkbox').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    const card = this.closest('label');
                    if (this.checked) {
                        card.style.background = '#fff3cd';
                        card.style.borderColor = '#ffc107';
                    } else {
                        card.style.background = '';
                        card.style.borderColor = '';
                    }
                });
            });

            const botoesEditar = document.querySelectorAll('.btn-editar');
            const formEditar = document.getElementById('formEditarAtleta');

            botoesEditar.forEach(botao => {
                botao.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');

                    formEditar.action = `/admin/atletas/${id}`;

                    document.getElementById('edit_nome').value = this.getAttribute('data-nome') ??
                        '';
                    document.getElementById('edit_matricula').value = this.getAttribute(
                        'data-matricula') ?? '';
                    document.getElementById('edit_data_nasc').value = this.getAttribute(
                        'data-data-nasc') ?? '';
                    document.getElementById('edit_cpf').value = this.getAttribute('data-cpf') ?? '';
                    document.getElementById('edit_rg').value = this.getAttribute('data-rg') ?? '';
                    document.getElementById('edit_peso').value = this.getAttribute('data-peso') ??
                        '';
                    document.getElementById('edit_altura').value = this.getAttribute(
                        'data-altura') ?? '';
                    document.getElementById('edit_escola').value = this.getAttribute(
                        'data-escola') ?? '';
                    document.getElementById('edit_serie').value = this.getAttribute('data-serie') ??
                        '';
                    document.getElementById('edit_descricao').value = this.getAttribute(
                        'data-descricao') ?? '';

                    const sexoSelect = document.getElementById('edit_sexo');
                    if (sexoSelect) sexoSelect.value = this.getAttribute('data-sexo') ?? '';

                    const periodoSelect = document.getElementById('edit_periodo');
                    if (periodoSelect) periodoSelect.value = this.getAttribute('data-periodo') ??
                        '';

                    const categoriaSelect = document.getElementById('edit_categoria');
                    if (categoriaSelect) categoriaSelect.value = this.getAttribute(
                        'data-categoria') ?? '';

                    // Desmarca todos os checkboxes de time e marca os do atleta
                    document.querySelectorAll('.edit-time-checkbox').forEach(cb => {
                        cb.checked = false;
                        cb.closest('label').style.background = '';
                        cb.closest('label').style.borderColor = '';
                    });
                    const atletaTimes = JSON.parse(this.getAttribute('data-times') || '[]');
                    atletaTimes.forEach(function(timeId) {
                        const cb = document.getElementById('edit_time_' + timeId);
                        if (cb) {
                            cb.checked = true;
                            cb.closest('label').style.background = '#fff3cd';
                            cb.closest('label').style.borderColor = '#ffc107';
                        }
                    });

                    document.getElementById('edit_camisa').value = this.getAttribute('data-camisa') ?? '';

                    const posicaoSelect = document.getElementById('edit_posicao');
                    if (posicaoSelect) posicaoSelect.value = this.getAttribute('data-posicao') ??
                        '';

                    document.getElementById('edit_telefone').value = this.getAttribute(
                        'data-telefone') ?? '';
                    document.getElementById('edit_sala').value = this.getAttribute('data-sala') ??
                        '';
                    document.getElementById('edit_email').value = this.getAttribute('data-email') ??
                        '';

                    document.getElementById('edit_cep').value = this.getAttribute('data-cep') ?? '';
                    document.getElementById('edit_rua').value = this.getAttribute('data-rua') ?? '';
                    document.getElementById('edit_matricula_end').value = this.getAttribute(
                        'data-numero-end') ?? '';
                    document.getElementById('edit_bairro').value = this.getAttribute(
                        'data-bairro') ?? '';
                    document.getElementById('edit_complemento').value = this.getAttribute(
                        'data-complemento') ?? '';
                    document.getElementById('edit_cidade').value = this.getAttribute(
                        'data-cidade') ?? '';
                    document.getElementById('edit_estado').value = this.getAttribute(
                        'data-estado') ?? '';
                });
            });

            // --- Seletor de colunas ---
            const table        = document.querySelector('.table');
            const colChecks    = document.querySelectorAll('.col-check');
            const todosCheck   = document.getElementById('col_todos');
            const searchColuna = document.getElementById('searchColuna');

            function toggleColumn(index, show) {
                table.querySelectorAll('tr').forEach(function(row) {
                    const cells = row.querySelectorAll('th, td');
                    if (cells[index]) cells[index].style.display = show ? '' : 'none';
                });
            }

            function syncTodos() {
                const checked = Array.from(colChecks).filter(cb => cb.checked).length;
                todosCheck.checked       = checked === colChecks.length;
                todosCheck.indeterminate = checked > 0 && checked < colChecks.length;
            }

            colChecks.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    toggleColumn(parseInt(this.dataset.col), this.checked);
                    syncTodos();
                });
            });

            todosCheck.addEventListener('change', function() {
                colChecks.forEach(function(cb) {
                    cb.checked = todosCheck.checked;
                    toggleColumn(parseInt(cb.dataset.col), todosCheck.checked);
                });
            });

            searchColuna.addEventListener('input', function() {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.col-item').forEach(function(item) {
                    item.style.display = item.textContent.trim().toLowerCase().includes(q) ? '' : 'none';
                });
            });
        });
    </script>
@endsection
