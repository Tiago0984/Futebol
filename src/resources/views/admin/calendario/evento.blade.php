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
                                        @if ($jogo && $escalaveis->isNotEmpty())<th>Time</th>@endif
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
                                        @if ($jogo && $escalaveis->isNotEmpty())
                                        <td>
                                            <form action="{{ route('admin.calendario.eventos.inscricoes.time', [$evento->id_evento_calendario, $inscricao->id_atleta]) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <select name="id_time" class="form-select form-select-sm" onchange="this.form.submit()"
                                                        aria-label="Time de {{ $inscricao->atleta->nome_atleta }}">
                                                    <option value="">— Sem time —</option>
                                                    @foreach ($escalaveis as $time)
                                                    <option value="{{ $time->id_time }}" @selected((int) $inscricao->id_time === (int) $time->id_time)>{{ $time->nome_time }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                            @php $doElenco = $escalaveis->whereIn('id_time', $elencos[$inscricao->id_atleta] ?? [])->pluck('nome_time'); @endphp
                                            @if ($doElenco->isNotEmpty())
                                                <small class="text-muted">Elenco: {{ $doElenco->implode(', ') }}</small>
                                            @else
                                                {{-- Não é do elenco de nenhum time do jogo (saiu do elenco ou foi inscrito à mão) --}}
                                                <small class="text-warning fw-semibold">Fora do elenco</small>
                                            @endif
                                        </td>
                                        @endif
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
                                        <td colspan="6" class="text-center text-muted py-4">Nenhum atleta inscrito.</td>
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
                    {{-- No jogo, os inscritos vêm do elenco ("Preencher pelo elenco"), não da categoria --}}
                    @if ($evento->categoria && ! $jogo)
                    <div class="card shadow-sm mb-4">
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
                    @endif

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
