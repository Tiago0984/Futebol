@extends('layout.admin')

@section('title', 'Evento: ' . $evento->titulo_evento_calendario)

@section('content')
    <main class="app-main pt-3">
        <div class="container-fluid">

            {{-- Linha de caminho: cada parte leva à lista dela; a última é o próprio evento --}}
            <nav aria-label="Caminho" class="mb-2">
                <ol class="breadcrumb mb-0 small" id="caminhoEvento">
                    @foreach ($caminho as [$rotulo, $url])
                        @if ($url)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
                        @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>

            {{-- Cabeçalho: título e ações (editar, placar do jogo, cancelar/reativar, ocultar/mostrar, voltar) --}}
            @php $rotuloEvento = $jogo ? 'jogo' : 'evento'; @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <h1 class="m-0 text-dark me-auto" style="font-size: 24px; font-weight: 700;">{{ $evento->titulo_evento_calendario }}</h1>

                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="{{ $jogo ? '#modalEditarJogo' : '#modalEditarEvento' }}" id="btnEditarEvento">
                    <i class="bi bi-pencil"></i> Editar
                </button>

                @if ($jogo)
                <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalPlacar">
                    <i class="bi bi-123"></i> Placar
                    @if ($jogo->temPlacar())<span class="badge bg-dark ms-1">{{ $jogo->placar_time_casa_jogos }} × {{ $jogo->placar_time_visitante_jogos }}</span>@endif
                </button>
                @endif

                {{-- Cancelar <-> reativar (oculto precisa ser mostrado antes) --}}
                @unless ($evento->estaOculto())
                <form action="{{ route('admin.calendario.eventos.cancelar', $evento->id_evento_calendario) }}" method="POST" class="d-inline"
                      onsubmit="return confirm(@js($evento->estaCancelado() ? "Reativar este {$rotuloEvento}?" : "Cancelar este {$rotuloEvento}? Ele continua visível, com o selo \"Cancelado\"."))">
                    @csrf @method('PATCH')
                    @if ($evento->estaCancelado())
                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Reativar</button>
                    @else
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Cancelar</button>
                    @endif
                </form>
                @endunless

                {{-- Ocultar <-> mostrar: substitui a exclusão (o registro fica) --}}
                <form action="{{ route('admin.calendario.eventos.ocultar', $evento->id_evento_calendario) }}" method="POST" class="d-inline"
                      onsubmit="return confirm(@js($evento->estaOculto() ? "Mostrar este {$rotuloEvento} de novo?" : "Ocultar este {$rotuloEvento}? Ele some das listas e do site, mas o registro fica."))">
                    @csrf @method('PATCH')
                    @if ($evento->estaOculto())
                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-eye"></i> Mostrar</button>
                    @else
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye-slash"></i> Ocultar</button>
                    @endif
                </form>

                <a href="{{ $voltar }}" class="btn btn-secondary btn-sm" id="btnVoltar">
                    <i class="bi bi-arrow-left"></i> {{ $jogo ? 'Voltar aos jogos' : 'Voltar ao calendário' }}
                </a>
            </div>

            @if (session('sucesso'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('sucesso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Time interno sem elenco ao criar ou trocar times (vem da tela de Jogos) --}}
            @if (session('aviso'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Atenção!</strong> {{ session('aviso') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @include('admin.calendario._conflitos')

            {{-- Atleta de outra categoria/sexo escalado ou inscrito no jogo: só avisa (CLAUDE.md, seção 8, pergunta 14) --}}
            @if ($avisosCategoria = session('avisos_categoria'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong><i class="bi bi-info-circle"></i> Fora da categoria do jogo</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($avisosCategoria as $aviso)
                        <li>{{ $aviso }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('erro'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('erro') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Jogo em rascunho (Fase 10, Etapa 4): ninguém foi avisado; publicar avisa cada inscrito uma vez, com o time --}}
            @unless ($evento->estaPublicado())
                @php $avisaAoPublicar = $evento->status_evento_calendario === 'ATIVO' && ! $evento->estaConcluido(); @endphp
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3" role="alert" id="faixaRascunho">
                    <div class="me-auto">
                        <strong><i class="bi bi-pencil-square"></i> Rascunho.</strong>
                        @if ($avisaAoPublicar)
                            Os atletas ainda não foram avisados e o {{ $rotuloEvento }} não aparece no app nem no site.
                            Confira os inscritos e a escalação e publique: cada atleta recebe um aviso só, já com o time.
                        @else
                            O {{ $rotuloEvento }} já aconteceu, está cancelado ou oculto: publicar não avisa ninguém.
                        @endif
                    </div>
                    @if ($jogo)
                    <form action="{{ route('admin.jogos.publicar', $jogo->id_jogo) }}" method="POST" class="d-inline"
                          onsubmit="return confirm(@js($avisaAoPublicar ? 'Publicar o jogo e avisar os atletas inscritos? Depois de publicado, ele não volta a ser rascunho.' : 'Publicar o jogo sem avisar ninguém?'))">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-warning btn-sm" id="btnPublicar">
                            <i class="bi bi-send"></i> {{ $avisaAoPublicar ? 'Publicar e avisar os atletas' : 'Publicar' }}
                        </button>
                    </form>
                    @endif
                </div>
            @endunless

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

            {{-- Escalação do jogo: cada inscrito em um time (mandante ou visitante interno) --}}
            @if ($jogo)
            @php
                $escalaveis = $jogo->timesEscalaveis();
                $porTime    = $inscricoes->countBy(fn ($i) => $i->id_time ?? 0);
            @endphp
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-semibold">
                    <i class="bi bi-diagram-3 me-2"></i> Escalação:
                    {{ $jogo->timeCasa->nome_time }} x {{ $jogo->timeVisitante->nome_time }}
                    @if ($jogo->ehAmistoso()) <span class="badge bg-secondary ms-1">Amistoso</span> @endif
                </div>
                <div class="card-body d-flex flex-wrap gap-4 align-items-center">
                    @forelse ($escalaveis as $time)
                        <div><span class="text-muted small d-block">{{ $time->nome_time }}</span><strong>{{ $porTime[$time->id_time] ?? 0 }}</strong> atleta(s)</div>
                    @empty
                        <div class="text-muted">Os dois times são externos: não há atletas da escolinha para escalar.</div>
                    @endforelse
                    @if ($escalaveis->isNotEmpty())
                        <div><span class="text-muted small d-block">Sem time</span><strong>{{ $porTime[0] ?? 0 }}</strong> inscrito(s)</div>
                        <form action="{{ route('admin.calendario.eventos.escalacao.elenco', $evento->id_evento_calendario) }}" method="POST" class="ms-auto d-flex align-items-center gap-2"
                              onsubmit="return confirm('Escalar pelo elenco dos times? Quem já tem time não muda; quem está no elenco e não está inscrito será inscrito.')">
                            @csrf
                            <span class="text-muted small">
                                @if ($faltantesDoElenco > 0)
                                    <strong>{{ $faltantesDoElenco }}</strong> atleta(s) do elenco ainda não {{ $faltantesDoElenco === 1 ? 'está inscrito' : 'estão inscritos' }}
                                @else
                                    Todo o elenco ativo está inscrito
                                @endif
                            </span>
                            <button type="submit" class="btn btn-success"><i class="bi bi-people"></i> Preencher pelo elenco</button>
                        </form>
                    @endif
                </div>
            </div>
            @endif

            {{-- Jogo com time interno: os inscritos lado a lado, um bloco por time escalável (mandante à esquerda,
                 visitante à direita, com a quantidade) e, embaixo, "Sem time" (só quando há alguém; quem está num
                 time que não é mais do jogo também cai aqui, como o select mostra). Outros eventos: lista única.
                 Nos dois casos, os formulários de inscrição ficam acima, entre a escalação e os inscritos --}}
            @php
              $porBlocos = $jogo && isset($escalaveis) && $escalaveis->isNotEmpty();
              $idsEscalaveis = $porBlocos ? $escalaveis->pluck('id_time')->map(fn ($id) => (int) $id) : collect();
              // Mandante e visitante nos seus lados; o time externo aparece travado (só para ver: sem elenco na
              // associação, ninguém é escalado nele)
              $blocosDosTimes = $porBlocos
                  ? collect([[$jogo->timeCasa, 'mandante'], [$jogo->timeVisitante, 'visitante']])
                      ->filter(fn ($par) => $par[0])
                      ->map(fn ($par) => [
                        'titulo'     => $par[0]->nome_time,
                        'logo'       => $par[0]->logo_time,
                        'papel'      => $par[1],
                        'externo'    => $par[0]->tipo_time !== 'INTERNO',
                        'categoria'  => $par[0]->categoria?->rotulo,
                        'inscricoes' => $inscricoes->filter(fn ($i) => (int) $i->id_time === (int) $par[0]->id_time),
                    ])->values()
                  : collect();
              $semTime = $porBlocos ? $inscricoes->reject(fn ($i) => $idsEscalaveis->contains((int) $i->id_time)) : collect();
            @endphp
            {{-- Adicionar: entre a escalação e os inscritos, nos jogos e nos outros eventos ("Pela categoria" só no
                 evento com categoria que não é jogo; no jogo, os inscritos vêm do elenco: "Preencher pelo elenco") --}}
            <div class="row g-4 mb-4">
                @if ($evento->categoria && ! $jogo)
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white fw-semibold">
                            <i class="bi bi-arrow-repeat me-2"></i> Pela categoria ({{ $evento->categoria->rotulo }})
                        </div>
                        <div class="card-body">
                            @if ($faltantesDaCategoria > 0)
                                <p class="mb-2">
                                    <strong>{{ $faltantesDaCategoria }}</strong> atleta(s) ativo(s) da categoria ainda não
                                    {{ $faltantesDaCategoria === 1 ? 'está inscrito' : 'estão inscritos' }}
                                    (entraram na categoria depois que o evento foi criado, ou foram removidos).
                                </p>
                                <form action="{{ route('admin.calendario.eventos.inscricoes.atualizar', $evento->id_evento_calendario) }}" method="POST"
                                      onsubmit="return confirm('Inscrever os atletas da categoria que faltam? Ninguém é removido.')">
                                    @csrf
                                    <button type="submit" class="btn btn-success">Atualizar inscritos pela categoria</button>
                                </form>
                            @else
                                <p class="text-muted mb-0">Todos os atletas ativos da categoria estão inscritos.</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
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
                                @if ($jogo && $escalaveis->isNotEmpty())
                                <select name="id_time" class="form-select" style="max-width:40%" aria-label="Time">
                                    <option value="">Sem time</option>
                                    @foreach ($escalaveis as $time)
                                    <option value="{{ $time->id_time }}">{{ $time->nome_time }}</option>
                                    @endforeach
                                </select>
                                @endif
                                <button type="submit" class="btn btn-success">Inscrever</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
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

            {{-- Inscritos (largura toda) --}}
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-semibold">
                    <i class="bi bi-people me-2"></i> Inscritos ({{ $inscricoes->count() }})
                </div>

                @if ($porBlocos)
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($blocosDosTimes as $bloco)
                        <div class="{{ $blocosDosTimes->count() > 1 ? 'col-md-6' : 'col-12' }}">
                            @if ($bloco['externo'])
                            {{-- Time externo: travado, só para ver (sem select, sem remover) --}}
                            <div class="border rounded h-100 js-bloco-externo" style="background:#fafafa;" title="Time externo: só para visualizar">
                                <div class="bg-light border-bottom px-3 py-2 fw-semibold d-flex align-items-center gap-2 text-muted">
                                    <img src="{{ asset('futebol/images/team/' . $bloco['logo']) }}" alt="" width="28" height="28"
                                         style="object-fit:contain;opacity:.7" onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
                                    {{ $bloco['titulo'] }}
                                    <span class="badge escal-badge-externo">Externo</span>
                                    <span class="fw-normal small">· {{ $bloco['papel'] }}</span>
                                    <i class="bi bi-lock ms-auto" style="color:#9ca3af;"></i>
                                </div>
                                <div class="px-3 py-3 text-muted small">
                                    @if ($bloco['categoria'])<div>{{ $bloco['categoria'] }}</div>@endif
                                    <i class="bi bi-lock-fill"></i> <em>Elenco não disponível nesta associação</em>
                                </div>
                            </div>
                            @else
                            <div class="js-bloco-time">
                            <div class="border rounded h-100">
                                <div class="bg-light border-bottom px-3 py-2 fw-semibold d-flex align-items-center gap-2">
                                    {{-- Logo do time (mesmo caminho e imagem padrão do cartão da tela de Times) --}}
                                    <img src="{{ asset('futebol/images/team/' . $bloco['logo']) }}" alt="" width="28" height="28"
                                         style="object-fit:contain" onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
                                    {{ $bloco['titulo'] }}
                                    <span class="fw-normal text-muted small">· {{ $bloco['papel'] }} ({{ $bloco['inscricoes']->count() }})</span>
                                </div>
                                @include('admin.calendario._inscritos_do_bloco', ['lista' => $bloco['inscricoes'], 'vazio' => 'Ninguém escalado neste time.'])
                            </div>
                            </div>
                            @endif
                        </div>
                        @endforeach

                        @if ($semTime->isNotEmpty())
                        <div class="col-12">
                            <div class="js-bloco-time">
                            <div class="border border-warning rounded">
                                <div class="bg-warning-subtle border-bottom border-warning px-3 py-2 fw-semibold">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Sem time
                                    <span class="fw-normal text-muted small">· falta escolher o time ({{ $semTime->count() }})</span>
                                </div>
                                @include('admin.calendario._inscritos_do_bloco', ['lista' => $semTime, 'vazio' => ''])
                            </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @else
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
                        <tbody class="js-bloco-time">
                            @forelse ($inscricoes as $inscricao)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $inscricao->atleta->nome_atleta }}</td>
                                <td class="text-muted">{{ $inscricao->atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</td>
                                <td><span class="badge-cat" style="cursor:help" title="{{ $inscricao->origem_dica }}">{{ $inscricao->origem_label }}</span></td>
                                <td class="text-muted small">
                                    {{ $inscricao->usuario?->nome_usuario ?? '—' }}<br>
                                    {{ $inscricao->data_evento_atleta?->format('d/m/Y H:i') }}
                                </td>
                                <td class="text-center">@include('admin.calendario._remover_inscricao')</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Nenhum atleta inscrito.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif

                @if ($inscritosInativos > 0)
                    <p class="text-muted small px-3 py-2 mb-0">
                        <i class="bi bi-info-circle"></i>
                        {{ $inscritosInativos }} inscrito(s) com atleta inativo, pendente ou rejeitado não aparece(m) aqui.
                        Se o atleta voltar a ficar ativo, a inscrição volta a valer.
                    </p>
                @endif
            </div>

            {{-- Notificações enviadas aos atletas sobre este evento (só no admin: dados de menores).
                 As de AGENDA (resumo do mês, mover inscrições) não têm evento: ficam na página geral. --}}
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-dark text-white fw-semibold d-flex align-items-center">
                    <span><i class="bi bi-bell me-2"></i> Notificações ({{ $notificacoes->count() }})</span>
                    <a href="{{ route('admin.notificacoes.index', ['evento' => $evento->id_evento_calendario]) }}"
                       class="ms-auto small text-white" id="verTodasNotificacoes">ver todas</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Quando</th>
                                <th>Tipo</th>
                                <th>Atleta</th>
                                <th>Mensagem</th>
                                <th>Por</th>
                                <th>Lida</th>
                                <th>Responsáveis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notificacoes as $notificacao)
                            <tr>
                                <td class="ps-3 text-nowrap">{{ $notificacao->data_notificacao->format('d/m/Y H:i') }}</td>
                                <td><span class="badge-cat">{{ $notificacao->tipo_label }}</span></td>
                                <td>{{ $notificacao->atleta?->nome_atleta ?? '—' }}</td>
                                <td class="small">
                                    <strong>{{ $notificacao->titulo_notificacao }}</strong><br>
                                    {{ $notificacao->mensagem_notificacao }}
                                </td>
                                <td class="text-muted small">{{ $notificacao->usuario?->nome_usuario ?? '—' }}</td>
                                <td class="small text-nowrap">
                                    {{ $notificacao->estaLida() ? $notificacao->data_leitura_notificacao->format('d/m/Y H:i') : 'Não lida' }}
                                </td>
                                {{-- Cada responsável tem a própria leitura no app (tbl_notificacao_leitura) --}}
                                <td class="small text-nowrap">
                                    @if($notificacao->atleta?->responsaveis_count)
                                        {{ $notificacao->leituras_dos_responsaveis_count }} de {{ $notificacao->atleta->responsaveis_count }} leram
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhuma notificação enviada sobre este evento.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    {{-- Edição sem sair da tela: o jogo pelo formulário do jogo; os outros pelo formulário do Calendário --}}
    @if ($jogo)
        @include('admin.jogos.modals.edit', ['jogoEmEdicao' => $jogo, 'campeonatos' => $campeonatosDoJogo, 'times' => $timesDoJogo])

        {{-- Placar rápido: só os dois números (os dois ou nenhum) --}}
        <div class="modal fade" id="modalPlacar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content text-start modal-admin">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-123"></i> Placar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('admin.jogos.placar', $jogo->id_jogo) }}" method="POST">
                        @csrf @method('PATCH')
                        <div class="modal-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-5">
                                    <label class="form-label small" for="placar_casa">{{ $jogo->timeCasa->nome_time }}</label>
                                    <input type="number" min="0" max="99" name="placar_time_casa_jogos" id="placar_casa" class="form-control text-center"
                                           placeholder="—" value="{{ old('placar_time_casa_jogos', $jogo->placar_time_casa_jogos) }}">
                                </div>
                                <div class="col-2 text-center fw-bold pb-2">×</div>
                                <div class="col-5">
                                    <label class="form-label small" for="placar_visitante">{{ $jogo->timeVisitante->nome_time }}</label>
                                    <input type="number" min="0" max="99" name="placar_time_visitante_jogos" id="placar_visitante" class="form-control text-center"
                                           placeholder="—" value="{{ old('placar_time_visitante_jogos', $jogo->placar_time_visitante_jogos) }}">
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Vazios = jogo ainda não jogado.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @include('admin.jogos._script_form')
    @else
        @include('admin.calendario.modals.editar-evento', ['eventoEmEdicao' => $evento])
    @endif

    @include('admin.partials.sugestoes')

    @php
        // Reabre o formulário que voltou com erro de validação (edição: voltar=evento; placar: só os placares)
        $reabrir = $errors->any()
            ? (old('voltar') === 'evento' ? ($jogo ? 'modalEditarJogo' : 'modalEditarEvento')
                : ($jogo && $errors->hasAny(['placar_time_casa_jogos', 'placar_time_visitante_jogos']) ? 'modalPlacar' : null))
            : null;
    @endphp
    <script>
    // "#editar" (botão Editar da lista do Calendário, no jogo) ou erro de validação: abre o formulário
    document.addEventListener('DOMContentLoaded', function () {
        const id = location.hash === '#editar' ? @js($jogo ? 'modalEditarJogo' : 'modalEditarEvento') : @js($reabrir);
        const modal = id && document.getElementById(id);
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    </script>
@endsection
