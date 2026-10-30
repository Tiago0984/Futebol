@extends('layout.admin')

@section('title', 'Mata-mata e campeão — ' . $campeonato->nome_campeonato)

{{--
    Central do campeonato, aba "Mata-mata e campeão" (CentralDoCampeonatoController::mataMata; padrão das telas,
    Fase 3, 30/10/2026). Uma seção por categoria (cada Sub tem a sua final): o campeão (Campeonato::campeoes), o
    encerramento dos pontos corridos (o campeão pela tabela só aparece com a categoria encerrada) e os jogos do
    mata-mata por fase, com o "Montar próxima fase". O campeão e o encerramento ficavam na tela de grupos.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.central-do-campeonato :campeonato="$campeonato" aba="mata-mata" id-caminho="caminhoMataMata" />

    @include('admin.jogos._mensagens')

    @forelse ($classificacoes as $idCategoria => $classificacao)
    @php
      $categoria    = $classificacao['categoria'];
      $jogos        = $mataMata[$idCategoria] ?? collect();
      $encerradaEm  = $categoria->pivot->data_encerramento_campeonato_categoria;
      $erroEncerrar = $paraEncerrar[$idCategoria] ?? null;
      $rotaEncerrar = route('admin.campeonatos.grupos.encerramento', [$campeonato->id_campeonato, $idCategoria]);
    @endphp
    <x-admin.secao :titulo="$categoria->rotulo" icone="trophy" id="categoria-{{ $idCategoria }}">
      <x-slot:acoes>
        {{-- Fase I, Etapa I3: mata-mata pelos classificados da tabela ou pelos vencedores da fase anterior --}}
        <a href="{{ route('admin.campeonatos.proxima-fase', [$campeonato->id_campeonato, $idCategoria]) }}"
           class="btn btn-sm btn-outline-primary js-proxima-fase">
          <i class="bi bi-diagram-2"></i> Montar próxima fase
        </a>
      </x-slot:acoes>

      {{-- Campeão da categoria (Fase I, Etapa I4) --}}
      @if ($campeao = $campeoes[$idCategoria] ?? null)
      <div class="alert {{ $campeao['situacao'] === 'campeao' ? 'alert-success' : 'alert-warning' }} d-flex align-items-center gap-2 js-campeao">
        <i class="bi bi-trophy-fill"></i>
        <div>
          @if ($campeao['situacao'] === 'campeao')
          <strong>Campeão: {{ $campeao['campeao']['nome'] }}</strong>
          <span class="small">· {{ $campeao['como'] }}@if ($campeao['vice']) · Vice: {{ $campeao['vice']['nome'] }}@endif
            @if ($campeao['terceiro']) · 3º lugar: {{ $campeao['terceiro']['nome'] }}@endif</span>
          @else
          <strong>Campeão a definir:</strong> sorteio entre {{ collect($campeao['empatados'])->join(', ', ' e ') }} (empatados em todos os critérios no 1º lugar).
          @endif
        </div>
      </div>
      @endif

      {{-- Encerramento (pontos corridos, 25/10/2026): o campeão pela tabela só aparece com a categoria encerrada --}}
      @if ($encerradaEm)
      <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2 js-encerramento">
        <span>
          <i class="bi bi-flag-fill"></i> Categoria encerrada em {{ \Illuminate\Support\Carbon::parse($encerradaEm)->format('d/m/Y H:i') }}.
          @if ($erroEncerrar)<br><span class="text-danger small">O campeão não aparece: {{ $erroEncerrar }}</span>@endif
        </span>
        <form action="{{ $rotaEncerrar }}" method="POST" onsubmit="return confirm('Reabrir a categoria? O campeão sai do site até encerrar de novo.')">
          @csrf @method('PATCH')
          <input type="hidden" name="acao" value="reabrir">
          <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button>
        </form>
      </div>
      @elseif (! $erroEncerrar)
      <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 js-encerramento">
        <span><i class="bi bi-info-circle"></i> Todos os jogos têm placar. Se o campeonato acabou (sem returno nem mata-mata), encerre a categoria para declarar o campeão.</span>
        <form action="{{ $rotaEncerrar }}" method="POST" onsubmit="return confirm('Encerrar a categoria? O 1º da tabela aparece como campeão no site e no app.')">
          @csrf @method('PATCH')
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-flag"></i> Encerrar categoria</button>
        </form>
      </div>
      @endif

      @if ($jogos->isEmpty())
      <p class="text-body-secondary mb-0 js-sem-mata-mata">
        Nenhum jogo de mata-mata nesta categoria.
        @if ($classificacao['classificados'])
          Passam {{ $classificacao['classificados'] }} de cada grupo (chave): quando a fase de grupos terminar, use <strong>Montar próxima fase</strong>.
        @else
          Nos pontos corridos, o campeão é o 1º da tabela: encerre a categoria quando todos os jogos tiverem placar.
          Para ter mata-mata, defina quantos passam de cada grupo na aba
          <a href="{{ \App\Support\CentralDoCampeonato::url($campeonato, 'grupos', $idCategoria) }}">Grupos (chaves) e classificação</a>.
        @endif
      </p>
      @else
      @foreach ($jogos->groupBy('fase_jogo') as $fase => $daFase)
      <h3 class="admin-subtitulo">{{ \App\Models\Jogo::FASES[$fase] ?? $fase }}</h3>
      @foreach ($daFase as $jogo)
      @php $ev = $jogo->evento; @endphp
      <div @class(['admin-jogo-linha', 'border-top' => ! $loop->first]) data-id-jogo="{{ $jogo->id_jogo }}">
        <div class="admin-jogo-linha__quando">
          <span class="fw-semibold">{{ $ev->data_evento_calendario->format('d/m/Y') }}</span>
          <span class="text-body-secondary small">{{ $ev->horario_inicio_evento_calendario ? substr((string) $ev->horario_inicio_evento_calendario, 0, 5) : 'Horário a definir' }}</span>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">
            {{ $jogo->timeCasa?->nome_time }}
            <span class="admin-placar">{{ $jogo->temPlacar() ? $jogo->placar_time_casa_jogos . ' × ' . $jogo->placar_time_visitante_jogos : '×' }}</span>
            {{ $jogo->timeVisitante?->nome_time }}
          </div>
          @if ($jogo->id_jogo_ida || $jogo->jogoVolta)
          <div class="text-body-secondary small">{{ $jogo->id_jogo_ida ? 'Jogo de volta' : 'Jogo de ida' }}</div>
          @endif
          @include('admin.jogos._resultado')
          @if ($jogo->temPlacar() && ! $jogo->jogoVolta && ! $jogo->resultadoDoConfronto())
          <div class="text-warning-emphasis admin-texto-mini js-sem-vencedor"><i class="bi bi-exclamation-circle"></i> Sem vencedor: lance a prorrogação e os pênaltis (Abrir › Editar).</div>
          @endif
        </div>
        @if ($ev->status_evento_calendario === 'CANCELADO')
        <span class="badge bg-danger">Cancelado</span>
        @elseif (! $ev->estaPublicado())
        <span class="badge bg-warning text-dark" title="Os atletas ainda não foram avisados">Rascunho</span>
        @endif
        <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="btn btn-sm btn-outline-secondary">Abrir</a>
      </div>
      @endforeach
      @endforeach
      @endif
    </x-admin.secao>
    @empty
    <p class="text-body-secondary">O campeonato não tem categorias.</p>
    @endforelse

  </div>
</main>
@endsection
