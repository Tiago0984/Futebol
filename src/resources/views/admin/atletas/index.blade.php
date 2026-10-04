@extends('layout.admin')

@section('title', 'Gerenciar Atletas')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">Atletas</h1>
                <p class="page-subtitle">Gerencie o cadastro de atletas da associação</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-filter-toggle" id="btnFiltros"
                        data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <button type="button" class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#modalNovoAtleta">
                    <i class="bi bi-person-plus"></i> Novo Atleta
                </button>
            </div>
        </div>

        @if(session('sucesso'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Sucesso!</strong> {{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        @if(session('erro'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Erro!</strong> {{ session('erro') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        {{-- Troca de categoria: eventos futuros a mover (só com a confirmação do admin) --}}
        @if($mover = session('mover_inscricoes'))
        <div class="alert alert-warning mb-3" role="alert">
            <strong>{{ $mover['nome'] }} mudou de {{ $mover['de_rotulo'] }} para {{ $mover['para_rotulo'] }}.</strong>
            @if(count($mover['sair']))
                <div class="mt-2">Está inscrito em {{ count($mover['sair']) }} evento(s) futuro(s) da categoria antiga:
                    {{ implode('; ', $mover['sair']) }}.</div>
            @endif
            @if(count($mover['entrar']))
                <div class="mt-1">Não está em {{ count($mover['entrar']) }} evento(s) futuro(s) da categoria nova:
                    {{ implode('; ', $mover['entrar']) }}.</div>
            @endif
            @if(count($mover['conflitos'] ?? []))
                <div class="mt-1"><i class="bi bi-exclamation-triangle"></i> <strong>Conflito de horário ao mover:</strong>
                    <ul class="mb-0">
                        @foreach($mover['conflitos'] as $conflito)<li>{{ $conflito }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @if(count($mover['mesmo_dia'] ?? []))
                <div class="mt-1"><i class="bi bi-info-circle"></i> <strong>Mesmo dia — confira o horário:</strong>
                    <ul class="mb-0">
                        @foreach($mover['mesmo_dia'] as $aviso)<li>{{ $aviso }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('admin.atletas.moverInscricoes', $mover['id_atleta']) }}" method="POST" class="mt-2"
                  onsubmit="return confirm('Mover as inscrições? Sai dos eventos da categoria antiga (só os automáticos) e entra nos da nova.')">
                @csrf
                <input type="hidden" name="de" value="{{ $mover['de'] }}">
                <input type="hidden" name="para" value="{{ $mover['para'] }}">
                <button type="submit" class="btn btn-warning btn-sm">Mover inscrições</button>
                <span class="small text-muted ms-2">Se não mover, nada muda nos eventos.</span>
            </form>
        </div>
        @endif

        @if ($errors->any() || $errors->edicao->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Ops! Verifique os campos do formulário:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                @foreach ($errors->edicao->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        @endif

        {{-- Filtros --}}
        <div class="collapse" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="filter-label">Nome</label>
                        <input type="text" id="filtroNome" class="form-control form-control-sm" placeholder="Buscar por nome...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Matrícula</label>
                        <input type="text" id="filtroMatricula" class="form-control form-control-sm" placeholder="Ex: A001">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Responsável</label>
                        <input type="text" id="filtroResponsavel" class="form-control form-control-sm" placeholder="Nome do responsável...">
                    </div>
                    <div class="col-md-2">
                        <label class="filter-label">Categoria</label>
                        <select id="filtroCategoria" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($categorias as $cat)
                            <option value="{{ strtoupper($cat->rotulo) }}">{{ $cat->rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="filter-label">Posição</label>
                        <select id="filtroPosicao" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach(['Goleiro','Zagueiro','Lateral','Volante','Meia','Atacante'] as $pos)
                            <option value="{{ strtoupper($pos) }}">{{ strtoupper($pos) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
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
                <div class="mt-2">
                    <small class="text-muted" id="filtroContador"></small>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-card-toolbar">
                <span class="tbl-count">{{ $atletas->count() }} atleta(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">Foto</th>
                            <th>Matrícula</th>
                            <th>Nome</th>
                            <th>Responsável</th>
                            <th>Categoria</th>
                            <th>Posição</th>
                            <th>Status</th>
                            <th class="text-center" style="width:90px">Ações</th>
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
                            $categoria = $atleta->categoriasAtivas->first();
                            $nomeCategoria = $categoria?->rotulo;
                            $idCategoria = $categoria->id_categoria ?? null;
                            $responsavel = $atleta->responsaveis->first();
                            $nomeResponsavel = $responsavel->nome_responsavel ?? null;
                            // Normalizado para a opção do select ("PAI" -> "Pai", "MAE" -> "Mãe")
                            $grauParentesco = \App\Models\Responsavel::normalizarGrau($responsavel?->pivot->grau_parentesco_responsavel);
                            $time = $atleta->times->first();
                            $posicao = $time?->pivot->posicao_atleta_time ?: ($atleta->posicao_atleta ?: null);
                            $camisa = ($time?->pivot->camisa_atleta_time > 0) ? $time->pivot->camisa_atleta_time : null;
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
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                         style="width:42px;height:42px;background:{{ $corAvatar }};font-size:0.8rem;position:absolute;top:0;left:0;">
                                        {{ $iniciais }}
                                    </div>
                                    @if($atleta->foto_atleta)
                                    <img src="{{ asset('futebol/images/our-teams/' . $atleta->foto_atleta) }}"
                                         alt="{{ $atleta->nome_atleta }}"
                                         class="rounded-circle object-fit-cover"
                                         style="width:42px;height:42px;position:absolute;top:0;left:0;"
                                         onerror="this.style.display='none'">
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($atleta->numero_matricula_atleta)
                                    <span class="fw-semibold" style="font-size:0.85rem;">{{ $atleta->numero_matricula_atleta }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $atleta->nome_atleta }}</strong>
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
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($nomeCategoria)
                                    <span class="badge-cat">{{ strtoupper($nomeCategoria) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($posicao)
                                    <span class="badge-cat">{{ strtoupper($posicao) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php $statusAtleta = strtoupper($atleta->status_atleta ?? ''); @endphp
                                <span class="badge-status {{ strtolower($statusAtleta) }}">
                                    {{ ['ATIVO' => 'Ativo', 'INATIVO' => 'Inativo', 'PENDENTE' => 'Pendente', 'REJEITADO' => 'Rejeitado'][$statusAtleta] ?? $statusAtleta }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button type="button"
                                            class="btn-tbl edit btn-editar"
                                            title="Editar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarAtleta"
                                            data-id="{{ $atleta->id_atleta }}"
                                            data-nome="{{ $atleta->nome_atleta }}"
                                            data-numero="{{ $camisa > 0 ? $camisa : '' }}"
                                            data-times="{{ $atleta->times->unique('id_time')->pluck('id_time')->values()->toJson() }}"
                                            data-posicao-atleta="{{ $time?->pivot->posicao_atleta_time ?: $atleta->posicao_atleta }}"
                                            data-data-nasc="{{ $atleta->data_nasc_atleta?->format('Y-m-d') }}"
                                            data-cpf="{{ $atleta->cpf_atleta }}"
                                            data-email="{{ $atleta->email_atleta }}"
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
                                            data-motivo-categoria="{{ $categoria?->pivot->observacao_categoria_atleta }}"
                                            data-nome-responsavel="{{ $responsavel->nome_responsavel ?? '' }}"
                                            data-grau-responsavel="{{ $grauParentesco ?? '' }}"
                                            data-whatsapp-responsavel="{{ $responsavel->whatsapp_responsavel ?? '' }}"
                                            data-cpf-responsavel="{{ $responsavel->cpf_responsavel ?? '' }}"
                                            data-email-responsavel="{{ $responsavel->email_responsavel ?? '' }}"
                                            data-cep="{{ $atleta->endereco->cep_endereco ?? '' }}"
                                            data-rua="{{ $atleta->endereco->rua_endereco ?? '' }}"
                                            data-numero-endereco="{{ $atleta->endereco->numero_endereco ?? '' }}"
                                            data-bairro="{{ $atleta->endereco->bairro_endereco ?? '' }}"
                                            data-complemento="{{ $atleta->endereco->complemento_endereco ?? '' }}"
                                            data-cidade="{{ $atleta->endereco->cidade_endereco ?? '' }}"
                                            data-estado="{{ $atleta->endereco->estado_endereco ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    {{-- Ativar/inativar só para quem já foi aprovado; pendente e rejeitado vão por Matrículas --}}
                                    @if($atleta->foiAprovado())
                                    <form action="{{ route('admin.atletas.toggleStatus', $atleta->id_atleta) }}"
                                          method="POST"
                                          onsubmit="return confirm('Deseja realmente alterar o status deste atleta?');"
                                          style="display:inline">
                                        @csrf
                                        @method('PATCH')
                                        @if($ativo)
                                            <button type="submit" class="btn-tbl deactivate" title="Inativar">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn-tbl activate" title="Ativar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        @endif
                                    </form>
                                    @endif
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
</main>

@include('admin.atletas.modals.create')
@include('admin.atletas.modals.edit')

@if($errors->any())
    <div class="modal-backdrop fade show"></div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Modal editar ---
    const formEditar = document.getElementById('formEditarAtleta');
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function () {
            const g = attr => this.getAttribute(attr) ?? '';
            formEditar.action = `{{ url('admin/atletas') }}/${g('data-id')}`;

            document.getElementById('edit_nome').value      = g('data-nome');
            document.getElementById('edit_numero').value    = g('data-numero');
            document.getElementById('edit_data_nasc').value = g('data-data-nasc');
            document.getElementById('edit_cpf').value       = g('data-cpf');
            document.getElementById('edit_email').value     = g('data-email');
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
            document.getElementById('edit_id_atleta').value = g('data-id');
            document.getElementById('edit_motivo_categoria').value = '';

            // Categoria atual e motivo gravado: o motivo só é exigido se a categoria MUDAR para uma acima
            document.getElementById('edit_categoria').dataset.atual       = g('data-categoria');
            document.getElementById('edit_categoria').dataset.motivoAtual = g('data-motivo-categoria');

            // Pendente/rejeitado: status só muda em Matrículas (select desabilitado não é enviado)
            const statusEl   = document.getElementById('edit_status');
            const statusAviso = document.getElementById('edit_status_aviso');
            const aprovado   = ['ATIVO', 'INATIVO'].includes(g('data-status').toUpperCase());
            statusEl.disabled = !aprovado;
            statusEl.value    = aprovado ? g('data-status').toUpperCase() : '';
            statusAviso.classList.toggle('d-none', aprovado);
            statusAviso.textContent = aprovado ? '' : `${g('data-status')}: o status muda pela tela de Matrículas.`;
            document.getElementById('edit_posicao').value   = g('data-posicao-atleta');

            document.getElementById('edit_nome_responsavel').value     = g('data-nome-responsavel');
            selecionarSemDiferenciar(document.getElementById('edit_grau_responsavel'), g('data-grau-responsavel'));
            document.getElementById('edit_whatsapp_responsavel').value = g('data-whatsapp-responsavel');
            document.getElementById('edit_cpf_responsavel').value      = g('data-cpf-responsavel');
            document.getElementById('edit_email_responsavel').value    = g('data-email-responsavel');

            document.getElementById('edit_cep_endereco').value         = g('data-cep');
            document.getElementById('edit_rua_endereco').value         = g('data-rua');
            document.getElementById('edit_numero_endereco').value      = g('data-numero-endereco');
            document.getElementById('edit_bairro_endereco').value      = g('data-bairro');
            document.getElementById('edit_complemento_endereco').value = g('data-complemento');
            document.getElementById('edit_cidade_endereco').value      = g('data-cidade');
            document.getElementById('edit_estado_endereco').value      = g('data-estado');

            const atletaTimes = JSON.parse(g('data-times') || '[]');
            document.querySelectorAll('.time-checkbox').forEach(cb => {
                cb.checked = atletaTimes.includes(parseInt(cb.value));
            });

            sugestaoEdicao.atualizar(false); // mantém a categoria atual; só mostra a dica
        });
    });

    // Seleciona a opção cujo valor casa com o informado sem diferenciar maiúsculas e, se preciso, acentos
    // ("PAI" -> "Pai", "MAE" -> "Mãe"). Sem correspondência, o select fica vazio.
    function selecionarSemDiferenciar(select, valor) {
        const maiusculo = t => (t || '').trim().toUpperCase();
        const semAcento = t => maiusculo(t).normalize('NFD').replace(/[̀-ͯ]/g, '');
        const opcoes    = Array.from(select.options).filter(opt => opt.value);

        const achada = opcoes.find(opt => maiusculo(opt.value) === maiusculo(valor))
                    || opcoes.find(opt => semAcento(opt.value) === semAcento(valor));

        select.value = achada ? achada.value : '';
    }

    // --- Sugestão de categoria (regra do ano: idade = ano atual − ano de nascimento) ---
    // Opções de outro sexo ou abaixo da idade ficam desabilitadas (abaixo é bloqueado);
    // acima da idade é permitido, mas só exige motivo se a categoria MUDAR (igual ao servidor).
    // A categoria atual do atleta (select.dataset.atual, na edição) nunca é desabilitada nem limpa:
    // na virada do ano ela pode ficar "abaixo", e limpar o select encerraria a categoria ao salvar.
    const IDADE_MIN = @js(\App\Models\Atleta::IDADE_MINIMA);
    const IDADE_MAX = @js(\App\Models\Atleta::IDADE_MAXIMA);

    function ligarSugestaoCategoria(ids) {
        const nasc   = document.getElementById(ids.nasc);
        const sexo   = document.getElementById(ids.sexo);
        const select = document.getElementById(ids.categoria);
        const motivo = document.getElementById(ids.motivo);
        const dica   = document.getElementById(ids.dica);

        function atualizar(selecionarSugerida) {
            const ano       = nasc.value ? parseInt(nasc.value.slice(0, 4), 10) : null;
            const idade     = ano ? new Date().getFullYear() - ano : null;
            const foraFaixa = idade !== null && (idade < IDADE_MIN || idade > IDADE_MAX);
            const atual     = select.dataset.atual || '';
            let sugerida = null;

            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                const min = parseInt(opt.dataset.min, 10), max = parseInt(opt.dataset.max, 10);
                const outroSexo = sexo.value && opt.dataset.sexo !== sexo.value;
                const abaixo    = idade !== null && idade > max;
                opt.disabled = opt.value !== atual && (outroSexo || abaixo || foraFaixa);
                if (!outroSexo && !foraFaixa && idade !== null && idade >= min && idade <= max) sugerida = opt;
            });

            // Mudou data ou sexo: vai para a sugerida; sem sugerida, volta para "— Selecionar —"
            if (selecionarSugerida) select.value = sugerida ? sugerida.value : '';
            if (select.selectedOptions[0]?.disabled) select.value = '';

            const escolhida = select.selectedOptions[0];
            const trocou    = (escolhida?.value || '') !== atual;
            const acima     = !!(escolhida?.value && idade !== null && idade < parseInt(escolhida.dataset.min, 10));
            const pedeMotivo = acima && trocou;
            motivo.classList.toggle('d-none', !pedeMotivo);
            motivo.required = pedeMotivo;

            if (idade === null || !sexo.value) {
                dica.textContent = '';
            } else if (foraFaixa) {
                dica.textContent = `${idade} anos no ano: fora da faixa de ${IDADE_MIN} a ${IDADE_MAX} anos.`;
            } else {
                dica.textContent = `${idade} anos no ano. Sugerida: ${sugerida ? sugerida.textContent.trim() : 'nenhuma'}`
                    + (pedeMotivo ? ' · Acima da idade: informe o motivo.' : '')
                    + (!trocou && select.dataset.motivoAtual ? ` · Motivo atual: ${select.dataset.motivoAtual}` : '');
            }
        }

        // Mudou data ou sexo: sugere de novo. Mudou só a categoria: respeita a escolha.
        [nasc, sexo].forEach(el => el.addEventListener('change', () => atualizar(true)));
        select.addEventListener('change', () => atualizar(false));

        return { atualizar };
    }

    const sugestaoCadastro = ligarSugestaoCategoria({
        nasc: 'cad_data_nasc', sexo: 'cad_sexo', categoria: 'cad_categoria',
        motivo: 'cad_motivo_categoria', dica: 'cad_dica_categoria',
    });
    const sugestaoEdicao = ligarSugestaoCategoria({
        nasc: 'edit_data_nasc', sexo: 'edit_sexo', categoria: 'edit_categoria',
        motivo: 'edit_motivo_categoria', dica: 'edit_dica_categoria',
    });
    sugestaoCadastro.atualizar(!@js(old('id_categoria')));

    // Erro na edição: reabre o modal do mesmo atleta e devolve o que foi digitado na categoria
    @if ($errors->edicao->any() && old('_editar_id'))
    const btnReabrir = document.querySelector(`.btn-editar[data-id="{{ (int) old('_editar_id') }}"]`);
    if (btnReabrir) {
        btnReabrir.click();
        document.getElementById('edit_categoria').value        = @js(old('id_categoria', ''));
        document.getElementById('edit_motivo_categoria').value = @js(old('motivo_categoria', ''));
        document.getElementById('edit_email').value            = @js(old('email_atleta', ''));
        document.getElementById('edit_email_responsavel').value = @js(old('email_responsavel', ''));
        sugestaoEdicao.atualizar(false);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarAtleta')).show();
    }
    @endif

    // --- Filtros ---
    function aplicarFiltros() {
        const nome        = document.getElementById('filtroNome').value.toLowerCase().trim();
        const matricula   = document.getElementById('filtroMatricula').value.toLowerCase().trim();
        const responsavel = document.getElementById('filtroResponsavel').value.toLowerCase().trim();
        const categoria   = document.getElementById('filtroCategoria').value.toUpperCase();
        const posicao     = document.getElementById('filtroPosicao').value.toUpperCase();
        const status      = document.getElementById('filtroStatus').value.toUpperCase();
        let visiveis = 0;
        document.querySelectorAll('.linha-atleta').forEach(row => {
            const ok = (!nome        || row.dataset.nome.includes(nome))
                    && (!matricula   || row.dataset.matricula.includes(matricula))
                    && (!responsavel || row.dataset.responsavel.includes(responsavel))
                    && (!categoria   || row.dataset.categoria === categoria)
                    && (!posicao     || row.dataset.posicao.includes(posicao))
                    && (!status      || row.dataset.status === status);
            row.style.display = ok ? '' : 'none';
            if (ok) visiveis++;
        });
        const total = document.querySelectorAll('.linha-atleta').length;
        const contador = document.getElementById('filtroContador');
        contador.textContent = (nome || matricula || responsavel || categoria || posicao || status)
            ? `${visiveis} de ${total} atleta(s) encontrado(s)` : '';
    }

    ['filtroNome','filtroMatricula','filtroResponsavel','filtroCategoria','filtroPosicao','filtroStatus']
        .forEach(id => document.getElementById(id)?.addEventListener('input', aplicarFiltros));

    document.getElementById('btnLimparFiltros')?.addEventListener('click', () => {
        ['filtroNome','filtroMatricula','filtroResponsavel'].forEach(id => document.getElementById(id).value = '');
        ['filtroCategoria','filtroPosicao','filtroStatus'].forEach(id => document.getElementById(id).value = '');
        aplicarFiltros();
    });
});
</script>
@endsection
