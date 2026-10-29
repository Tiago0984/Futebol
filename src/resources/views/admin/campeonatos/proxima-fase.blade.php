@extends('layout.admin')

@section('title', 'Próxima fase — ' . $campeonato->nome_campeonato)

{{--
    Montar a próxima fase do mata-mata (Fase I, Etapa I3; GeradorDeJogosController e App\Services\MontadorDaProximaFase).
    Formulário com o formato e a programação e os confrontos editáveis (GET: atualiza a prévia, nada é gravado) e o
    "Gerar" (POST, tudo ou nada). Em cada confronto, o 1º time é o mais bem colocado: mandante no jogo único; na
    ida e volta, decide em casa.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        @foreach ($caminho as [$rotulo, $url])
          @if ($url)
          <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
          @else
          <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
          @endif
        @endforeach
      </ol>
    </nav>

    @php
      $fase = $montador->fase();
      $daOrigem = $origem['tipo'] === 'tabela'
          ? 'os classificados da fase de grupos'
          : 'os vencedores da fase "' . \App\Models\Jogo::FASES[$origem['fase_anterior']] . '"';
    @endphp
    <div class="admin-page-header d-flex align-items-center gap-3">
      <a href="{{ route('admin.campeonatos.grupos', $campeonato->id_campeonato) }}#categoria-{{ $categoria->id_categoria }}"
         class="btn btn-sm btn-outline-secondary" title="Voltar" aria-label="Voltar">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h1 class="page-title mb-0">
          Próxima fase{{ $fase ? ': ' . \App\Models\Jogo::FASES[$fase] : '' }} · {{ $categoria->rotulo }} · {{ $campeonato->nome_campeonato }}
        </h1>
        <p class="page-subtitle mb-0">Entram {{ $daOrigem }}. Prévia: nada foi gravado ainda.</p>
      </div>
    </div>

    @include('admin.jogos._mensagens')

    @if ($erros)
    <div class="alert alert-danger" id="errosDaFase">
      <strong>Não dá para montar:</strong>
      <ul class="mb-0">@foreach ($erros as $erro)<li>{{ $erro }}</li>@endforeach</ul>
    </div>
    @endif

    @foreach ($previa['avisos'] as $aviso)
    <div class="alert alert-info py-2">{{ $aviso }}</div>
    @endforeach

    {{-- Formato, programação e confrontos: mudam a prévia (GET) --}}
    @php $times = collect($origem['times']); @endphp
    <form method="GET" action="{{ route('admin.campeonatos.proxima-fase', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
          class="card shadow-sm mb-3" id="formOpcoesDaFase">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="ida_e_volta">Formato</label>
            <select name="ida_e_volta" id="ida_e_volta" class="form-select">
              <option value="0" @selected(! $opcoes['ida_e_volta'])>Jogo único (empate: prorrogação e pênaltis)</option>
              <option value="1" @selected($opcoes['ida_e_volta'])>Ida e volta (empate no agregado: pênaltis)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="data">Data {{ $opcoes['ida_e_volta'] ? 'da ida' : 'dos jogos' }}</label>
            <input type="date" name="data" id="data" class="form-control" required value="{{ $opcoes['data'] }}">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="dias_volta">Dias até a volta</label>
            <input type="number" name="dias_volta" id="dias_volta" class="form-control" min="1" max="60" value="{{ $opcoes['dias_volta'] }}">
            <small class="text-muted">Só na ida e volta</small>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="horario">Horário do 1º jogo</label>
            <input type="time" name="horario" id="horario" class="form-control" required value="{{ $opcoes['horario'] }}">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="intervalo">Minutos entre jogos do mesmo dia</label>
            <input type="number" name="intervalo" id="intervalo" class="form-control" min="0" max="600" required value="{{ $opcoes['intervalo'] }}">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="duracao">Duração do jogo (min)</label>
            <input type="number" name="duracao" id="duracao" class="form-control" min="10" max="600" placeholder="—" value="{{ $opcoes['duracao'] }}">
          </div>
          <div class="col-md-6">
            @include('admin.partials.select-local', [
                'idCampo' => 'local', 'valorEscolhido' => $opcoes['id_local'],
                'vazio' => $campeonato->local_evento ? '— O local principal: ' . $campeonato->local_evento . ' —' : '— Sem local —',
            ])
            @if ($opcoes['local'] !== '')<input type="hidden" name="local" value="{{ $opcoes['local'] }}">@endif
          </div>
          @if ($montador->ofereceTerceiro())
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input type="checkbox" name="terceiro" value="1" id="terceiro" class="form-check-input" @checked($opcoes['terceiro'])>
              <label class="form-check-label" for="terceiro">Criar também a disputa de 3º lugar (os perdedores das semifinais)</label>
            </div>
          </div>
          @endif
        </div>

        {{-- Confrontos: o 1º é o mais bem colocado (mandante; na ida e volta, decide em casa). Dá para trocar --}}
        @if ($times->isNotEmpty() && $fase)
        <hr>
        <h2 class="h6 text-uppercase text-muted fw-bold">Confrontos ({{ \App\Models\Jogo::FASES[$fase] }})</h2>
        <p class="small text-muted mb-2">
          O 1º time de cada confronto {{ $opcoes['ida_e_volta'] ? 'decide em casa (joga a volta em casa; o outro joga a ida em casa)' : 'é o mandante' }}.
          Sugestão: {{ $origem['tipo'] === 'tabela' ? 'o mais bem colocado contra o pior (com grupos, o cruzamento 1º A × 2º B)' : 'o vencedor de um confronto contra o do seguinte' }}.
          Para sorteio ou outro arranjo, troque os times e clique em "Atualizar prévia".
        </p>
        <div class="row g-2" id="confrontosDaFase">
          @foreach ($montador->pares() as $i => $par)
          <div class="col-lg-6">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small" style="width: 1.5rem;">{{ $i + 1 }}.</span>
              @foreach ([0, 1] as $lado)
              <select name="pares[{{ $i }}][{{ $lado }}]" class="form-select form-select-sm" aria-label="Confronto {{ $i + 1 }}, time {{ $lado + 1 }}">
                @foreach ($times as $time)
                <option value="{{ $time->id_time }}" @selected((int) ($par[$lado] ?? 0) === (int) $time->id_time)>{{ $time->nome_time }} ({{ $time->rotulo }})</option>
                @endforeach
              </select>
              @if ($lado === 0)<span class="fw-bold text-muted">x</span>@endif
              @endforeach
            </div>
          </div>
          @endforeach
        </div>
        @endif

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Atualizar prévia</button>
        </div>
      </div>
    </form>

    @php $totais = $previa['totais']; @endphp
    @if ($totais['jogos'])
    <div class="alert alert-light border" id="resumoDaFase">
      <strong>{{ $totais['confrontos'] }}</strong> confronto(s) ·
      <strong>{{ $totais['jogos'] }}</strong> jogo(s)
      ({{ $totais['rascunhos'] }} com time da AACJ, em rascunho; {{ $totais['publicados'] }} entre externos, publicados)
      <div class="text-muted small mt-1">
        Ninguém é avisado agora: os jogos com time da AACJ nascem em rascunho, com os convocados inscritos. Revise e publique
        cada um na tela do jogo para avisar os atletas.
      </div>
    </div>
    @endif

    {{-- Conflito de horário dos convocados --}}
    @php $conflitos = $previa['conflitos']; @endphp
    @if ($conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]->isNotEmpty())
    <div class="alert alert-warning" id="conflitosDaFase">
      <strong><i class="bi bi-exclamation-triangle"></i> Conflito de horário:</strong>
      {{ $conflitos['totais']['reais'] }} inscrição(ões) de convocados batem com outros eventos.
      @include('admin.calendario._grupos_conflito', ['grupos' => $conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]])
    </div>
    @endif

    {{-- Jogos que serão criados --}}
    @if ($previa['jogos'])
    <div class="card shadow-sm" id="jogosDaFase">
      <ul class="list-group list-group-flush">
        @foreach ($previa['jogos'] as $jogo)
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
          <span>
            <strong>{{ $jogo['data']->locale('pt_BR')->isoFormat('ddd, DD/MM') }}</strong>
            <span class="text-muted">{{ $jogo['inicio'] }}{{ $jogo['fim'] ? ' às ' . $jogo['fim'] : '' }}</span>
            · {{ $jogo['casa']->nome_time }} <span class="text-muted">x</span> {{ $jogo['visitante']->nome_time }}
          </span>
          <span class="d-flex gap-1">
            <span class="badge bg-body-tertiary text-body border">
              {{ \App\Models\Jogo::FASES[$jogo['colunas']['fase_jogo']] }}{{ $jogo['perna'] ? ' · ' . $jogo['perna'] : '' }}
            </span>
            @if ($jogo['acj'])<span class="badge bg-warning text-dark" title="Nasce em rascunho, com os convocados inscritos">Rascunho</span>@endif
          </span>
        </li>
        @endforeach
      </ul>
    </div>
    @endif

    {{-- Gerar: as mesmas opções e confrontos da prévia --}}
    @if (! $erros && $totais['jogos'])
    @php $comConflito = $conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]->isNotEmpty(); @endphp
    <form method="POST" action="{{ route('admin.campeonatos.proxima-fase.gerar', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
          class="mt-4 d-flex justify-content-end gap-2" id="formGerarFase"
          onsubmit="return confirm('Criar {{ $totais['jogos'] }} jogo(s) da fase {{ \App\Models\Jogo::FASES[$fase] }}?')">
      @csrf
      @foreach (\Illuminate\Support\Arr::except($opcoes, ['pares']) as $campo => $valor)
      <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
      @endforeach
      @foreach ($montador->pares() as $i => $par)
      <input type="hidden" name="pares[{{ $i }}][0]" value="{{ $par[0] }}">
      <input type="hidden" name="pares[{{ $i }}][1]" value="{{ $par[1] }}">
      @endforeach
      @if ($comConflito)
      <input type="hidden" name="confirmar_conflitos" value="1">
      @endif
      <a href="{{ route('admin.campeonatos.grupos', $campeonato->id_campeonato) }}" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn {{ $comConflito ? 'btn-warning' : 'btn-primary' }}">
        <i class="bi bi-diagram-2"></i>
        {{ $comConflito ? 'Confirmar mesmo assim e gerar' : "Criar {$totais['jogos']} jogo(s)" }}
      </button>
    </form>
    @endif
  </div>
</main>
@include('admin.partials.sugestoes')
@endsection
