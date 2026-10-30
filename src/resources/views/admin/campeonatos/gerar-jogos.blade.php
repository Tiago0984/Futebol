@extends('layout.admin')

@section('title', 'Gerar jogos — ' . $campeonato->nome_campeonato)

{{--
    Gerar os jogos de todos contra todos de uma categoria (Fase I, Etapa I2; GeradorDeJogosController e
    App\Services\GeradorDeRodadas). Formulário com o formato (GET: atualiza a prévia, nada é gravado) e o
    "Gerar" (POST, tudo ou nada). Conflito real de horário dos convocados: "Confirmar mesmo assim e gerar".
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho :titulo="'Gerar jogos · ' . $categoria->rotulo . ' · ' . $campeonato->nome_campeonato"
                       :caminho="$caminho"
                       :voltar="route('admin.campeonatos.grupos', $campeonato->id_campeonato) . '#categoria-' . $categoria->id_categoria"
                       :logo="$campeonato->logo_url" :logo-alt="$campeonato->nome_campeonato">
      <x-slot:subtitulo>Todos contra todos, na fase de grupos e com a rodada. Prévia: nada foi gravado ainda.</x-slot:subtitulo>
    </x-admin.cabecalho>

    @include('admin.jogos._mensagens')

    @if ($erros)
    <div class="alert alert-danger" id="errosDaGeracao">
      <strong>Não dá para gerar:</strong>
      <ul class="mb-0">@foreach ($erros as $erro)<li>{{ $erro }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- Formato: muda a prévia (GET) --}}
    <form method="GET" action="{{ route('admin.campeonatos.gerar-jogos', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
          class="card shadow-sm mb-3" id="formOpcoes">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="returno">Formato</label>
            <select name="returno" id="returno" class="form-select">
              <option value="0" @selected(! $opcoes['returno'])>Só turno (cada par uma vez)</option>
              <option value="1" @selected($opcoes['returno'])>Turno e returno (ida e volta, mando invertido)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="data">Data da 1ª rodada</label>
            <input type="date" name="data" id="data" class="form-control" required value="{{ $opcoes['data'] }}">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="dias">Dias entre rodadas</label>
            <input type="number" name="dias" id="dias" class="form-control" min="1" max="60" required value="{{ $opcoes['dias'] }}">
          </div>
          <div class="col-md-2">
            <label class="form-label" for="horario">Horário do 1º jogo</label>
            <input type="time" name="horario" id="horario" class="form-control" required value="{{ $opcoes['horario'] }}">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="intervalo">Minutos entre jogos do mesmo dia</label>
            <input type="number" name="intervalo" id="intervalo" class="form-control" min="0" max="600" required value="{{ $opcoes['intervalo'] }}">
            <small class="text-muted">0 = todos no mesmo horário</small>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="duracao">Duração do jogo (min)</label>
            <input type="number" name="duracao" id="duracao" class="form-control" min="10" max="600" placeholder="—" value="{{ $opcoes['duracao'] }}">
          </div>
          <div class="col-md-7">
            @include('admin.partials.select-local', [
                'idCampo' => 'local', 'valorEscolhido' => $opcoes['id_local'],
                'vazio' => $campeonato->local_evento ? '— O local principal: ' . $campeonato->local_evento . ' —' : '— Sem local —',
            ])
            @if ($opcoes['local'] !== '')<input type="hidden" name="local" value="{{ $opcoes['local'] }}">@endif
            <small class="text-muted">Depois de gerar, dá para mudar a data, o horário e o local de cada jogo.</small>
          </div>
          <div class="col-md-3 text-end">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Atualizar prévia</button>
          </div>
        </div>
      </div>
    </form>

    @php $totais = $previa['totais']; @endphp
    <div class="alert alert-light border" id="resumoDaGeracao">
      <strong>{{ $totais['times'] }}</strong> time(s) ·
      <strong>{{ $totais['rodadas'] }}</strong> rodada(s) ·
      <strong>{{ $totais['jogos'] }}</strong> jogo(s)
      @if ($totais['jogos'])
        ({{ $totais['rascunhos'] }} com time da AACJ, em rascunho; {{ $totais['publicados'] }} entre externos, publicados)
      @endif
      <div class="text-muted small mt-1">
        Ninguém é avisado na geração: os jogos com time da AACJ nascem em rascunho, com os convocados inscritos. Publique
        cada jogo ou a rodada inteira (tela de jogos do campeonato) para avisar os atletas.
      </div>
    </div>

    @foreach ($previa['avisos'] as $aviso)
    <div class="alert alert-info py-2">{{ $aviso }}</div>
    @endforeach

    {{-- Conflito de horário dos convocados (o mesmo cálculo em lote da geração da grade) --}}
    @php $conflitos = $previa['conflitos']; @endphp
    @if ($conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]->isNotEmpty())
    <div class="alert alert-warning" id="conflitosDaGeracao">
      <strong><i class="bi bi-exclamation-triangle"></i> Conflito de horário:</strong>
      {{ $conflitos['totais']['reais'] }} inscrição(ões) de convocados batem com outros eventos.
      @include('admin.calendario._grupos_conflito', ['grupos' => $conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]])
    </div>
    @endif
    @if ($conflitos[\App\Models\EventoCalendario::CONFLITO_FRACO]->isNotEmpty())
    <div class="alert alert-info">
      <strong>Mesmo dia, horário a definir:</strong>
      @include('admin.calendario._grupos_conflito', ['grupos' => $conflitos[\App\Models\EventoCalendario::CONFLITO_FRACO]])
    </div>
    @endif

    {{-- Rodadas --}}
    <div class="row g-3" id="rodadasDaPrevia">
      @foreach ($previa['rodadas'] as $rodada)
      <div class="col-lg-6">
        <div class="card shadow-sm h-100">
          <div class="card-header d-flex justify-content-between">
            <strong>Rodada {{ $rodada['numero'] }}</strong>
            <span class="text-muted">{{ $rodada['data']->locale('pt_BR')->isoFormat('ddd, DD/MM/YYYY') }}</span>
          </div>
          <ul class="list-group list-group-flush">
            @foreach ($rodada['jogos'] as $jogo)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
              <span>
                <span class="text-muted">{{ $jogo['inicio'] }}{{ $jogo['fim'] ? ' às ' . $jogo['fim'] : '' }}</span>
                · {{ $jogo['casa']->nome_time }} <span class="text-muted">x</span> {{ $jogo['visitante']->nome_time }}
              </span>
              <span class="d-flex gap-1">
                @if ($jogo['grupo'])<span class="badge bg-body-tertiary text-body border">{{ $jogo['grupo'] }}</span>@endif
                @if ($jogo['acj'])<span class="badge bg-warning text-dark" title="Nasce em rascunho, com os convocados inscritos">Rascunho</span>@endif
              </span>
            </li>
            @endforeach
            @foreach ($rodada['folgas'] as $folga)
            <li class="list-group-item text-muted small"><i class="bi bi-cup-hot"></i> Folga: {{ $folga }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Gerar: as mesmas opções da prévia --}}
    @if (! $erros && $totais['jogos'])
    @php $comConflito = $conflitos[\App\Models\EventoCalendario::CONFLITO_REAL]->isNotEmpty(); @endphp
    <form method="POST" action="{{ route('admin.campeonatos.gerar-jogos.gerar', [$campeonato->id_campeonato, $categoria->id_categoria]) }}"
          class="mt-4 d-flex justify-content-end gap-2" id="formGerar"
          onsubmit="return confirm('Gerar {{ $totais['jogos'] }} jogo(s) em {{ $totais['rodadas'] }} rodada(s)?')">
      @csrf
      @foreach ($opcoes as $campo => $valor)
      <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
      @endforeach
      @if ($comConflito)
      <input type="hidden" name="confirmar_conflitos" value="1">
      @endif
      <a href="{{ route('admin.campeonatos.grupos', $campeonato->id_campeonato) }}" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn {{ $comConflito ? 'btn-warning' : 'btn-primary' }}">
        <i class="bi bi-lightning-charge"></i>
        {{ $comConflito ? 'Confirmar mesmo assim e gerar' : "Gerar {$totais['jogos']} jogo(s)" }}
      </button>
    </form>
    @endif
  </div>
</main>
@include('admin.partials.sugestoes')
@endsection
