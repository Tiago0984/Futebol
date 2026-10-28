@extends('layout.admin')

@section('title', 'Convocados — ' . $time->nome_time)

{{--
    Convocados de um time interno num campeonato (Fase G, Etapa 1; ConvocacaoController): a lista com a camisa
    de cada um (editável na linha) e a remoção; embaixo, "Convocar atletas" com a caixa por categoria e a camisa
    de cada escolhido. Desabilitados na caixa: quem foi convocado por outro time na mesma categoria do campeonato e
    quem passa da idade da categoria do time (pelo ano de início). Atleta inativado fica na lista com o selo, sem contar.
--}}
@php
  $ativos = $convocados->filter(fn ($c) => $c->atleta->status_atleta === 'ATIVO')->count();
  $inativos = $convocados->count() - $ativos;
  // $categoria: a do time no campeonato (Fase H, Etapa H2; a convocação vale nela)
@endphp

@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <nav aria-label="Caminho" class="mb-2">
      <ol class="breadcrumb mb-0 small" id="caminhoConvocados">
        @foreach ($caminho as [$rotulo, $url])
          @if ($url)
          <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
          @else
          <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
          @endif
        @endforeach
      </ol>
    </nav>

    <div class="admin-page-header">
      <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.campeonatos.times', $campeonato->id_campeonato) }}" class="btn btn-sm btn-outline-secondary"
           title="Voltar" aria-label="Voltar" id="btnVoltarConvocados">
          <i class="bi bi-arrow-left"></i>
        </a>
        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}" alt="{{ $time->nome_time }}"
             style="width:40px;height:40px;object-fit:contain;border-radius:8px;"
             onerror="this.src='{{ asset('futebol/images/team/default-team.png') }}'">
        <div>
          <h1 class="page-title mb-0">Convocados · {{ $time->nome_time }} · {{ $categoria->rotulo }}</h1>
          <p class="page-subtitle mb-0" id="resumoConvocados">
            {{ $campeonato->nome_campeonato }}
            @if ($categoria)
              &bull; {{ $categoria->limite_de_idade }} (idade pelo ano de {{ $campeonato->anoDaIdade() }})
            @else
              &bull; sem limite de idade
            @endif
            &bull; {{ $ativos }} convocado(s){{ $inativos ? " ({$inativos} inativo(s), não contam)" : '' }}
          </p>
        </div>
      </div>
    </div>

    @include('admin.jogos._mensagens')

    <p class="text-muted small mb-3">
      <i class="bi bi-info-circle"></i> Convocação do {{ $time->nome_time }} no {{ $categoria->rotulo }} deste campeonato: a convocação
      e a camisa valem aqui (a camisa pode mudar no próximo campeonato). O atleta pode ser convocado também em outra categoria
      do campeonato, com outra camisa.
    </p>

    {{-- Lista dos convocados --}}
    @if ($convocados->isEmpty())
      <div class="text-center py-4 text-muted" id="semConvocados">
        <i class="bi bi-people" style="font-size:2.5rem; opacity:.3;"></i>
        <p class="mt-2 mb-0">Nenhum atleta convocado pelo {{ $time->nome_time }} neste campeonato.</p>
      </div>
    @else
    <div class="card shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tabelaConvocados">
          <thead>
            <tr>
              <th style="width:170px;">Camisa</th>
              <th>Atleta</th>
              <th>Categoria</th>
              <th>Convocado</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($convocados as $convocacao)
            @php
              $atleta = $convocacao->atleta;
              $inativo = $atleta->status_atleta !== 'ATIVO';
              $outroSexo = $categoria && $atleta->sexo_atleta !== $categoria->sexo_categoria;
            @endphp
            <tr data-id-atleta="{{ $atleta->id_atleta }}" @class(['text-muted' => $inativo])>
              <td>
                <form action="{{ route('admin.campeonatos.convocados.camisa', [$campeonato->id_campeonato, $time->id_time, $atleta->id_atleta, 'categoria' => $categoria->id_categoria]) }}"
                      method="POST" class="d-flex gap-1">
                  @csrf @method('PATCH')
                  <input type="number" min="1" max="99" name="camisa_convocacao" value="{{ $convocacao->camisa_convocacao }}" required
                         class="form-control form-control-sm" style="width:72px" aria-label="Camisa de {{ $atleta->nome_atleta }}">
                  <button type="submit" class="btn btn-sm btn-outline-secondary" title="Salvar camisa" aria-label="Salvar camisa">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </form>
              </td>
              <td>
                <span class="fw-semibold">{{ $atleta->nome_atleta }}</span>
                @if ($inativo)
                  <span class="badge bg-secondary ms-1" title="Atleta inativado: a convocação fica, mas não conta">Inativo</span>
                @endif
                @isset ($acimaDosConvocados[$atleta->id_atleta])
                  <span class="badge bg-danger ms-1" title="Passa da idade da categoria ({{ $categoria?->limite_de_idade }}); remova da convocação">
                    Acima da idade · {{ $acimaDosConvocados[$atleta->id_atleta] }} anos
                  </span>
                @endisset
                @if ($outroSexo)
                  <span class="badge bg-warning text-dark ms-1" title="Só aviso: o time joga no {{ $categoria->rotulo }}">Sexo diferente da categoria</span>
                @endif
              </td>
              <td>{{ $atleta->categoriasAtivas->first()?->rotulo ?? '—' }}</td>
              <td class="small">
                {{ $convocacao->data_convocacao?->format('d/m/Y H:i') }}
                <div class="text-muted">{{ $convocacao->usuario?->nome_usuario ?? '—' }}</div>
              </td>
              <td class="text-end">
                <form action="{{ route('admin.campeonatos.convocados.remover', [$campeonato->id_campeonato, $time->id_time, $atleta->id_atleta, 'categoria' => $categoria->id_categoria]) }}"
                      method="POST" class="d-inline"
                      onsubmit="return confirm(@js("Tirar {$atleta->nome_atleta} da convocação do {$time->nome_time}?"))">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover da convocação">
                    <i class="bi bi-person-dash"></i> Remover
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    {{-- Convocar atletas: caixa por categoria, com a camisa de cada escolhido --}}
    <form action="{{ route('admin.campeonatos.convocados.convocar', [$campeonato->id_campeonato, $time->id_time, 'categoria' => $categoria->id_categoria]) }}" method="POST"
          class="card card-body shadow-sm" id="formConvocar">
      @csrf
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-plus"></i> Convocar atletas</h2>
      @include('admin.partials.escolher-atletas', [
        'prefixo'    => 'conv',
        'grupos'     => $grupos,
        'escolhidos' => array_map('strval', old('atletas', [])),
        'bloqueados' => $bloqueados,
        'comCamisa'  => true,
        'camisas'    => old('camisas', []),
        'ajuda'      => 'Marque os atletas e informe a camisa de cada um. Um time por atleta em cada categoria do campeonato; '
                        . 'quem passa da idade da categoria não pode ser convocado (o mais novo pode subir).',
      ])
      <div class="mt-3">
        <button type="submit" class="btn btn-success btn-sm" @disabled($grupos->isEmpty())>
          <i class="bi bi-check2-circle"></i> Convocar
        </button>
      </div>
    </form>

  </div>
</main>
@endsection
