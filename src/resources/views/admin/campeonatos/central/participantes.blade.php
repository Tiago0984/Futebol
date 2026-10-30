@extends('layout.admin')

@section('title', 'Participantes — ' . $campeonato->nome_campeonato)

{{--
    Central do campeonato, aba "Participantes e convocação" (CentralDoCampeonatoController::participantes; padrão
    das telas, Fase 3, 30/10/2026): uma seção por categoria com os times dela (Campeonato::participacoes: um por
    time e categoria, a AACJ primeiro), o grupo (chave), quantos jogos ele tem na categoria e, no time da AACJ, os
    convocados, que abrem a tela Convocados daquela categoria. Juntou o bloco "Convocação" e os "Participantes sem
    jogo na lista" que ficavam na tela de jogos. Incluir ou tirar times: Editar campeonato.
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.central-do-campeonato :campeonato="$campeonato" aba="participantes" id-caminho="caminhoParticipantes" />

    @include('admin.jogos._mensagens')

    <p class="text-body-secondary small mb-3">
      <i class="bi bi-info-circle"></i> Para incluir ou tirar times de uma categoria, use <strong>Editar campeonato</strong>, no alto.
      Os jogos de campeonato inscrevem os convocados de cada time da AACJ.
    </p>

    @forelse ($campeonato->categorias as $categoria)
    @php
      $idCategoria = (int) $categoria->id_categoria;
      $daCategoria = $participacoes[$idCategoria] ?? collect();
      $temGrupos   = $daCategoria->contains(fn ($p) => $grupoDe["{$p->time->id_time}|{$idCategoria}"] ?? null);
    @endphp
    <x-admin.secao :titulo="$categoria->rotulo" icone="people" id="categoria-{{ $idCategoria }}"
                   :ajuda="$daCategoria->count() . ' time(s) nesta categoria'">
      @if ($daCategoria->isEmpty())
      <p class="text-body-secondary mb-0">
        Nenhum time nesta categoria ainda.
        <a href="{{ route('admin.campeonatos.edit', [$campeonato->id_campeonato, 'voltar' => 'central']) }}">Incluir times</a>
      </p>
      @else
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 js-participantes">
          <thead>
            <tr>
              <th>Time</th>
              <th>Grupo (chave)</th>
              <th class="text-center">Jogos</th>
              <th>Convocação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($daCategoria as $participacao)
            @php
              $time    = $participacao->time;
              $interno = $time->tipo_time === 'INTERNO';
              $idGrupo = $grupoDe["{$time->id_time}|{$idCategoria}"] ?? null;
              $jogos   = ($jogosPorTime["{$time->id_time}|{$idCategoria}"] ?? 0) + ($jogosPorTime["{$time->id_time}|0"] ?? 0);
            @endphp
            <tr data-id-time="{{ $time->id_time }}" data-id-categoria="{{ $idCategoria }}">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $time->logo_url ?? asset('futebol/images/team/default-team.png') }}" alt="" class="admin-logo-28"
                       onerror="this.onerror=null;this.src='{{ asset('futebol/images/team/default-team.png') }}'">
                  <span class="fw-semibold">{{ $time->nome_time }}</span>
                  <span @class(['badge-status mini', 'ativo' => $interno, 'inativo' => ! $interno])>{{ $interno ? 'AACJ' : 'Externo' }}</span>
                </div>
              </td>
              <td>
                @if ($idGrupo)
                  {{ $nomesDosGrupos[$idGrupo] ?? '—' }}
                @elseif ($temGrupos)
                  <span class="badge bg-warning text-dark" title="A categoria tem grupos (chaves): defina o deste time na aba Grupos">Sem grupo</span>
                @else
                  <span class="text-body-secondary">—</span>
                @endif
              </td>
              <td class="text-center">
                @if ($jogos)
                  {{ $jogos }}
                @else
                  <span class="badge bg-secondary js-sem-jogo">Sem jogo</span>
                @endif
              </td>
              <td>
                @if ($interno)
                <div class="d-flex align-items-center gap-2">
                  <span @class(['badge', 'bg-secondary' => $participacao->total, 'bg-warning text-dark' => ! $participacao->total])>
                    {{ $participacao->total ? $participacao->total . ' convocado(s)' : 'Sem convocados' }}
                  </span>
                  <a href="{{ route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $time->id_time, 'categoria' => $idCategoria]) }}"
                     class="btn btn-sm btn-outline-primary ms-auto js-convocados">
                    <i class="bi bi-person-lines-fill"></i> Convocados
                  </a>
                </div>
                @else
                <span class="text-body-secondary small">Sem atletas na AACJ</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </x-admin.secao>
    @empty
    <p class="text-body-secondary">O campeonato não tem categorias.</p>
    @endforelse

  </div>
</main>
@endsection
