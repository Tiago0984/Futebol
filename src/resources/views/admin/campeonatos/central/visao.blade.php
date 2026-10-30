@extends('layout.admin')

@section('title', $campeonato->nome_campeonato)

{{--
    Central do campeonato, aba "Visão geral" (CentralDoCampeonatoController::visaoGeral; padrão das telas, Fase 3,
    30/10/2026): o que precisa de atenção (os passos pendentes de todas as categorias), um card por categoria com
    os seis passos (SituacaoDoCampeonato) e o próximo, e os próximos jogos. Só mostra: cada passo leva à aba onde
    se resolve.
--}}
@php
  $icones = [
      'feito'     => 'check-circle-fill',
      'pendente'  => 'exclamation-circle-fill',
      'andamento' => 'hourglass-split',
      'opcional'  => 'dash-circle',
      'bloqueado' => 'lock',
  ];
@endphp

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.central-do-campeonato :campeonato="$campeonato" aba="visao" id-caminho="caminhoCentral" />

    @include('admin.jogos._mensagens')

    <x-admin.secao titulo="Precisa da sua atenção" icone="bell" id="pendencias"
                   ajuda="O que falta fazer, de todas as categorias. Cada botão abre a tela onde se resolve.">
      @forelse ($pendencias as $pendencia)
      <div @class(['admin-pendencia js-pendencia', 'border-top' => ! $loop->first])>
        <i class="bi bi-exclamation-circle-fill text-warning"></i>
        <div class="flex-grow-1">
          <span class="fw-semibold">{{ $pendencia['categoria'] }} · {{ $pendencia['titulo'] }}:</span> {{ $pendencia['detalhe'] }}
        </div>
        <a href="{{ $pendencia['url'] }}" class="btn btn-sm btn-outline-primary text-nowrap">{{ $pendencia['acao'] }}</a>
      </div>
      @empty
      <p class="mb-0 text-body-secondary"><i class="bi bi-check2-circle text-success"></i> Nada pendente agora. Os passos de cada categoria estão abaixo.</p>
      @endforelse
    </x-admin.secao>

    <div class="row g-4">
      @forelse ($categorias as $linha)
      @php $idCategoria = (int) $linha['categoria']->id_categoria; @endphp
      <div class="col-xl-6">
        <x-admin.secao :titulo="$linha['categoria']->rotulo" icone="trophy" id="categoria-{{ $idCategoria }}" class="h-100 mb-0">
          <ol class="admin-passos">
            @foreach ($linha['etapas'] as $chave => $etapa)
            <li class="admin-passos__item {{ $etapa['situacao'] }}" data-etapa="{{ $chave }}" data-situacao="{{ $etapa['situacao'] }}">
              <i class="bi bi-{{ $icones[$etapa['situacao']] }}" aria-hidden="true"></i>
              <div class="admin-passos__texto">
                <span class="admin-passos__titulo">{{ $etapa['titulo'] }}</span>
                <span class="admin-passos__detalhe">{{ $etapa['detalhe'] }}</span>
              </div>
              @if ($etapa['url'])
              <a href="{{ $etapa['url'] }}" class="admin-passos__link">{{ $etapa['acao'] }}</a>
              @endif
            </li>
            @endforeach
          </ol>
          <div class="admin-passos__rodape">
            @if ($proximo = $linha['proximo'])
            <span class="text-body-secondary small">Próximo passo:</span>
            <a href="{{ $proximo['url'] }}" class="btn btn-sm btn-primary js-proximo-passo">
              <i class="bi bi-arrow-right-circle"></i> {{ $proximo['acao'] }} <span class="small">({{ $proximo['titulo'] }})</span>
            </a>
            @else
            <span class="text-body-secondary small"><i class="bi bi-check2-circle text-success"></i> Nada pendente nesta categoria.</span>
            @endif
          </div>
        </x-admin.secao>
      </div>
      @empty
      <div class="col-12">
        <p class="text-body-secondary">O campeonato não tem categorias. Marque ao menos uma em
          <a href="{{ route('admin.campeonatos.edit', [$campeonato->id_campeonato, 'voltar' => 'central']) }}">Editar campeonato</a>.</p>
      </div>
      @endforelse
    </div>

    <x-admin.secao titulo="Próximos jogos" icone="calendar-event" class="mt-4" id="proximosJogos">
      <x-slot:acoes>
        <a href="{{ route('admin.campeonatos.times', $campeonato->id_campeonato) }}" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-calendar2-week"></i> Todos os jogos
        </a>
      </x-slot:acoes>
      @forelse ($proximosJogos as $jogo)
      @php $ev = $jogo->evento; @endphp
      <div @class(['admin-jogo-linha', 'border-top' => ! $loop->first]) data-id-jogo="{{ $jogo->id_jogo }}">
        <div class="admin-jogo-linha__quando">
          <span class="fw-semibold">{{ $ev->data_evento_calendario->format('d/m/Y') }}</span>
          <span class="text-body-secondary small">{{ $ev->horario_inicio_evento_calendario ? substr((string) $ev->horario_inicio_evento_calendario, 0, 5) : 'Horário a definir' }}</span>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">{{ $jogo->timeCasa?->nome_time }} <span class="text-body-secondary">×</span> {{ $jogo->timeVisitante?->nome_time }}</div>
          <div class="text-body-secondary small">{{ collect([$ev->categoria?->rotulo, $jogo->fase_completa, $ev->local_evento_calendario])->filter()->implode(' · ') }}</div>
        </div>
        @unless ($ev->estaPublicado())
        <span class="badge bg-warning text-dark" title="Os atletas ainda não foram avisados">Rascunho</span>
        @endunless
        <a href="{{ route('admin.calendario.eventos.show', $ev->id_evento_calendario) }}" class="btn btn-sm btn-outline-secondary">Abrir</a>
      </div>
      @empty
      <p class="mb-0 text-body-secondary">Nenhum jogo marcado daqui para a frente.</p>
      @endforelse
    </x-admin.secao>

  </div>
</main>
@endsection
