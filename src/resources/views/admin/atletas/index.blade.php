@extends('layout.admin')

@section('title', 'Gerenciar Atletas')

@section('content')
<main class="app-main pt-3" style="text-align: center;">
    <div class="container-fluid">

        <div class="row mb-3 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark" style="font-size: 24px; font-weight: 700; text-align: left;">Gerenciar Atletas</h1>
            </div>
            <div class="col-sm-6 text-end pr-4" style="text-align: right; display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline-secondary px-3" id="btnFiltros"
                        data-bs-toggle="collapse" data-bs-target="#filtroPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn btn-success px-3" data-bs-toggle="modal" data-bs-target="#modalNovoAtleta">
                    <i class="bi bi-person-plus"></i> Novo Atleta
                </button>
            </div>
        </div>

        {{-- Painel de filtros --}}
        <div class="collapse mb-3" id="filtroPanel">
            <div class="card card-body py-3 px-4" style="text-align:left;">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Nome</label>
                        <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Matrícula</label>
                        <input type="text" id="filtroMatricula" class="form-control form-control-sm" placeholder="Ex: A001">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Responsável</label>
                        <input type="text" id="filtroResponsavel" class="form-control form-control-sm" placeholder="Nome do responsável...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Categoria</label>
                        <select id="filtroCategoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categorias as $cat)
                            <option value="{{ strtoupper($cat->nome_categoria) }}">{{ strtoupper($cat->nome_categoria) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-semibold mb-1">Posição</label>
                        <select id="filtroPosicao" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach(['Goleiro','Zagueiro','Lateral','Volante','Meia','Atacante'] as $pos)
                            <option value="{{ strtoupper($pos) }}">{{ strtoupper($pos) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small fw-semibold mb-1">Status</label>
                        <select id="filtroStatus" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="ATIVO">ATIVO</option>
                            <option value="INATIVO">INATIVO</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-outline-danger w-100" id="btnLimparFiltros">
                            <i class="bi bi-x-circle"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted" id="filtroContador"></small>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">

                @if(session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show m-3" role="alert" style="text-align: left;">
                    <strong>Sucesso!</strong> {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                </div>
                @endif

                @if(session('erro'))
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert" style="text-align: left;">
                    <strong>Erro!</strong> {{ session('erro') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                </div>
                @endif

                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert" style="text-align: left;">
                    <strong>Ops! Verifique os campos do formulário:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: absolute; top: 0; right: 0; padding: 1.25rem 1rem; border: 0;"></button>
                </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 table-atletas">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 60px;">Foto</th>
                                <th>Matrícula</th>
                                <th style="text-align: left;">Nome</th>
                                <th>Responsável</th>
                                <th>Categoria</th>
                                <th>Posição</th>
                                <th>Status</th>
                                <th style="width: 130px;" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($atletas as $atleta)
                            @php
                                $partes = explode(' ', trim($atleta->nome_atleta));
                                $iniciais = strtoupper(substr($partes[0], 0, 1) . (count($partes) > 1 ? substr(end($partes), 0, 1) : ''));
                                $paleta = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0', '#2ec4b6', '#e76f51', '#457b9d'];
                                $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                                $ativo = strtolower($atleta->status_atleta ?? '') === 'ativo';

                                $categoria = $atleta->categorias->first();
                                $nomeCategoria = $categoria->nome_categoria ?? null;
                                $idCategoria = $categoria->id_categoria ?? null;
                                $responsavel = $atleta->responsaveis->first();
                                $nomeResponsavel = $responsavel->nome_responsavel ?? null;
                                $grauParentesco = $responsavel?->pivot->grau_parentesco_responsavel ?? null;
                                $time = $atleta->times->first();
                                $posicao = $time?->pivot->posicao_atleta_time ?: ($atleta->posicao_atleta ?: null);
                                $nomeTime = $time->nome_time ?? null;
                                $camisa = ($time?->pivot->camisa_atleta_time > 0) ? $time->pivot->camisa_atleta_time : null;

                                $corCategoria = match (true) {
                                    str_contains($nomeCategoria ?? '', '11') => 'secondary',
                                    str_contains($nomeCategoria ?? '', '13') => 'success',
                                    str_contains($nomeCategoria ?? '', '15') => 'primary',
                                    str_contains($nomeCategoria ?? '', '17') => 'warning',
                                    str_contains($nomeCategoria ?? '', '19') => 'info',
                                    default => 'secondary',
                                };

                                $posicaoUpper = strtoupper($posicao ?? '');
                            @endphp
                            <tr class="linha-atleta"
                                data-nome="{{ strtolower($atleta->nome_atleta) }}"
                                data-matricula="{{ strtolower($atleta->numero_matricula_atleta ?? '') }}"
                                data-responsavel="{{ strtolower($nomeResponsavel ?? '') }}"
                                data-categoria="{{ strtoupper($nomeCategoria ?? '') }}"
                                data-posicao="{{ strtoupper($posicao ?? '') }}"
                                data-status="{{ strtoupper($atleta->status_atleta ?? '') }}">
                                <td>
                                    <div style="position:relative;width:42px;height:42px;flex-shrink:0;">
                                        <div class="avatar-iniciais rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                            style="width:42px;height:42px;background:{{ $corAvatar }};font-size:0.8rem;position:absolute;top:0;left:0;">
                                            {{ $iniciais }}
                                        </div>
                                        @if($atleta->foto_atleta)
                                        <img src="{{ asset('storage/' . $atleta->foto_atleta) }}"
                                            alt="{{ $atleta->nome_atleta }}"
                                            class="rounded-circle object-fit-cover"
                                            style="width:42px;height:42px;position:absolute;top:0;left:0;"
                                            onerror="this.style.display='none'">
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($atleta->numero_matricula_atleta)
                                        <span class="fw-semibold text-dark" style="font-size:0.85rem;">{{ $atleta->numero_matricula_atleta }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td style="text-align: left;">
                                    <strong class="text-dark">{{ $atleta->nome_atleta }}</strong>
                                    @foreach($atleta->times->unique('id_time') as $t)
                                        @if((int)$t->pivot->camisa_atleta_time > 0)
                                            <div class="text-muted" style="font-size:0.78rem;">Camisa Nº {{ $t->pivot->camisa_atleta_time }}</div>
                                        @endif
                                    @endforeach
                                </td>
                                <td>
                                    @if($nomeResponsavel)
                                        <div class="small fw-semibold" style="line-height:1.3;">{{ $nomeResponsavel }}</div>
                                        @if($grauParentesco)
                                            <div class="text-muted" style="font-size:0.75rem;">{{ $grauParentesco }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($nomeCategoria)
                                        <span class="badge bg-{{ $corCategoria }} text-white" style="font-size: 13px; padding: 5px 10px;">
                                            {{ strtoupper($nomeCategoria) }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary text-white" style="font-size: 13px; padding: 5px 10px;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($posicao)
                                        <span class="badge text-white" style="background:#0dcaf0; font-size: 13px; padding: 5px 10px;">
                                            {{ strtoupper($posicao) }}
                                        </span>
                                    @else
                                        <span class="badge text-white" style="background:#0dcaf0; font-size: 13px; padding: 5px 10px;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ativo)
                                        <span class="badge bg-success text-white" style="font-size: 12px; padding: 6px 10px; font-weight: 700;">ATIVO</span>
                                    @else
                                        <span class="badge bg-danger text-white" style="font-size: 12px; padding: 6px 10px; font-weight: 700;">INATIVO</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap: 5px;">

                                        <button type="button"
                                                class="btn btn-sm btn-warning text-dark btn-editar"
                                                title="Editar"
                                                style="padding: .25rem .4rem; line-height: 1;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarAtleta"
                                                data-id="{{ $atleta->id_atleta }}"
                                                data-nome="{{ $atleta->nome_atleta }}"
                                                data-numero="{{ $camisa > 0 ? $camisa : '' }}"
                                                data-times="{{ $atleta->times->unique('id_time')->pluck('id_time')->values()->toJson() }}"
                                                data-posicao-atleta="{{ $time?->pivot->posicao_atleta_time ?: $atleta->posicao_atleta }}"
                                                data-data-nasc="{{ $atleta->data_nasc_atleta?->format('Y-m-d') }}"
                                                data-cpf="{{ $atleta->cpf_atleta }}"
                                                data-rg="{{ $atleta->rg_atleta }}"
                                                data-status="{{ $atleta->status_atleta }}"
                                                data-sexo="{{ $atleta->sexo_atleta }}"
                                                data-peso="{{ $atleta->peso_atleta }}"
                                                data-altura="{{ $atleta->altura_atleta }}"
                                                data-escola="{{ $atleta->escola_atleta }}"
                                                data-serie="{{ $atleta->serie_atleta }}"
                                                data-periodo="{{ $atleta->periodo_escolar_atleta }}"
                                                data-sala="{{ $atleta->sala_atleta }}"
                                                data-descricao="{{ $atleta->descricao_atleta }}"
                                                data-categoria="{{ $idCategoria }}"
                                                data-nome-responsavel="{{ $responsavel->nome_responsavel ?? '' }}"
                                                data-grau-responsavel="{{ $grauParentesco ?? '' }}"
                                                data-whatsapp-responsavel="{{ $responsavel->whatsapp_responsavel ?? '' }}"
                                                data-cpf-responsavel="{{ $responsavel->cpf_responsavel ?? '' }}"
                                                data-cep="{{ $atleta->endereco->cep_endereco ?? '' }}"
                                                data-rua="{{ $atleta->endereco->rua_endereco ?? '' }}"
                                                data-numero-endereco="{{ $atleta->endereco->numero_endereco ?? '' }}"
                                                data-bairro="{{ $atleta->endereco->bairro_endereco ?? '' }}"
                                                data-complemento="{{ $atleta->endereco->complemento_endereco ?? '' }}"
                                                data-cidade="{{ $atleta->endereco->cidade_endereco ?? '' }}"
                                                data-estado="{{ $atleta->endereco->estado_endereco ?? '' }}">
                                            <i class="bi bi-pencil" style="font-size: 13px;"></i>
                                        </button>

                                        <form action="{{ route('admin.atletas.toggleStatus', $atleta->id_atleta) }}" method="POST"
                                            onsubmit="return confirm('Deseja realmente alterar o status deste atleta?');" style="display: inline-block; margin: 0;">
                                            @csrf
                                            @method('PATCH')
                                            @if($ativo)
                                                <button type="submit" class="btn btn-sm btn-danger" title="Inativar Atleta" style="padding: .25rem .4rem; line-height: 1;">
                                                    <i class="bi bi-eye-slash" style="font-size: 13px;"></i>
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-success" title="Ativar Atleta" style="padding: .25rem .4rem; line-height: 1;">
                                                    <i class="bi bi-eye" style="font-size: 13px;"></i>
                                                </button>
                                            @endif
                                        </form>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
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

@if($errors->any())
    <div class="modal-backdrop fade show"></div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const botoesEditar = document.querySelectorAll('.btn-editar');
        const formEditar = document.getElementById('formEditarAtleta');

        botoesEditar.forEach(botao => {
            botao.addEventListener('click', function () {
                const id = this.getAttribute('data-id');

                formEditar.action = `/admin/atletas/${id}`;

                const g = attr => this.getAttribute(attr) ?? '';

                // Dados do atleta
                document.getElementById('edit_nome').value      = g('data-nome');
                document.getElementById('edit_numero').value    = g('data-numero');
                document.getElementById('edit_data_nasc').value = g('data-data-nasc');
                document.getElementById('edit_cpf').value       = g('data-cpf');
                document.getElementById('edit_rg').value        = g('data-rg');
                document.getElementById('edit_peso').value      = g('data-peso');
                document.getElementById('edit_altura').value    = g('data-altura');
                document.getElementById('edit_escola').value    = g('data-escola');
                document.getElementById('edit_serie').value     = g('data-serie');
                document.getElementById('edit_sala').value      = g('data-sala');
                document.getElementById('edit_descricao').value = g('data-descricao');

                document.getElementById('edit_sexo').value      = g('data-sexo');
                document.getElementById('edit_periodo').value   = g('data-periodo');
                document.getElementById('edit_categoria').value = g('data-categoria');
                document.getElementById('edit_status').value    = g('data-status');
                document.getElementById('edit_posicao').value   = g('data-posicao-atleta');

                // Responsável
                document.getElementById('edit_nome_responsavel').value    = g('data-nome-responsavel');
                document.getElementById('edit_grau_responsavel').value    = g('data-grau-responsavel');
                document.getElementById('edit_whatsapp_responsavel').value = g('data-whatsapp-responsavel');
                document.getElementById('edit_cpf_responsavel').value     = g('data-cpf-responsavel');

                // Endereço
                document.getElementById('edit_cep_endereco').value         = g('data-cep');
                document.getElementById('edit_rua_endereco').value         = g('data-rua');
                document.getElementById('edit_numero_endereco').value      = g('data-numero-endereco');
                document.getElementById('edit_bairro_endereco').value      = g('data-bairro');
                document.getElementById('edit_complemento_endereco').value = g('data-complemento');
                document.getElementById('edit_cidade_endereco').value      = g('data-cidade');
                document.getElementById('edit_estado_endereco').value      = g('data-estado');

                // Times — pré-marca checkboxes
                const atletaTimes = JSON.parse(g('data-times') || '[]');
                document.querySelectorAll('.time-checkbox').forEach(cb => {
                    cb.checked = atletaTimes.includes(parseInt(cb.value));
                });
            });
        });
    });

    // ── Filtros ──────────────────────────────────────────────────────────────
    function aplicarFiltros() {
        const nome       = document.getElementById('filtroNome').value.toLowerCase().trim();
        const matricula  = document.getElementById('filtroMatricula').value.toLowerCase().trim();
        const responsavel= document.getElementById('filtroResponsavel').value.toLowerCase().trim();
        const categoria  = document.getElementById('filtroCategoria').value.toUpperCase();
        const posicao    = document.getElementById('filtroPosicao').value.toUpperCase();
        const status     = document.getElementById('filtroStatus').value.toUpperCase();

        const linhas = document.querySelectorAll('.linha-atleta');
        let visiveis = 0;

        linhas.forEach(row => {
            const match =
                (!nome       || row.dataset.nome.includes(nome)) &&
                (!matricula  || row.dataset.matricula.includes(matricula)) &&
                (!responsavel|| row.dataset.responsavel.includes(responsavel)) &&
                (!categoria  || row.dataset.categoria === categoria) &&
                (!posicao    || row.dataset.posicao.includes(posicao)) &&
                (!status     || row.dataset.status === status);

            row.style.display = match ? '' : 'none';
            if (match) visiveis++;
        });

        const contador = document.getElementById('filtroContador');
        if (nome || matricula || responsavel || categoria || posicao || status) {
            contador.textContent = `${visiveis} atleta(s) encontrado(s)`;
        } else {
            contador.textContent = '';
        }
    }

    ['filtroNome','filtroMatricula','filtroResponsavel','filtroCategoria','filtroPosicao','filtroStatus']
        .forEach(id => document.getElementById(id)?.addEventListener('input', aplicarFiltros));

    document.getElementById('btnLimparFiltros')?.addEventListener('click', function () {
        ['filtroNome','filtroMatricula','filtroResponsavel'].forEach(id => document.getElementById(id).value = '');
        ['filtroCategoria','filtroPosicao','filtroStatus'].forEach(id => document.getElementById(id).value = '');
        aplicarFiltros();
    });
</script>

<style>
    .table-atletas th,
    .table-atletas td {
        padding-left: 1.25rem;
        padding-right: 1.25rem;
    }
</style>
@endsection
