{{-- Editar local (Etapa L1). O "Novo local" é um modal na lista (10/11/2026, admin.locais.modals.create); os campos
     ficam no partial admin.locais._campos, usado pelos dois --}}
@extends('layout.admin')

@section('title', 'Editar local')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">{{ $local->nome_local }}</h1>
                <p class="page-subtitle">Locais · o endereço e o link do mapa aparecem no "Abrir mapa" do app</p>
            </div>
            <a href="{{ route('admin.locais.index') }}" class="btn-filter-toggle"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <strong>Erro!</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        @if(session('sucesso'))
        <div class="alert alert-success mb-3" role="alert"><strong>Sucesso!</strong> {{ session('sucesso') }}</div>
        @endif
        @if(session('erro'))
        <div class="alert alert-danger mb-3" role="alert"><strong>Erro!</strong> {{ session('erro') }}</div>
        @endif

        {{-- "Ligar os usos" (L2): os eventos, horários da grade e campeonatos com um texto de local antigo (sem local)
             passam a usar este local, com o nome dele. Ninguém é avisado: o lugar é o mesmo --}}
        @if(($soltos ?? collect())->isNotEmpty())
        <div class="card shadow-sm mb-4 border-info">
            <div class="card-body">
                <h2 class="h6 mb-1"><i class="bi bi-link-45deg"></i> Ligar textos antigos a este local</h2>
                <p class="small text-muted mb-3">Eventos, horários da grade e campeonatos com o local escrito à mão passam a usar
                    <strong>{{ $local->nome_local }}</strong> (o texto vira o nome do local). Os atletas não são avisados e o evento não fica "Alterado";
                    o histórico do evento registra a troca do texto.</p>
                <form method="GET" action="{{ route('admin.locais.edit', $local->id_local) }}" class="row g-2 align-items-end mb-2">
                    <div class="col-md-8">
                        <label class="form-label small mb-1" for="lc_texto_ligar">Texto antigo</label>
                        <select id="lc_texto_ligar" name="texto" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Escolher —</option>
                            @foreach($soltos as $solto)
                            <option value="{{ $solto->texto }}" @selected($solto->texto === $textoLigar)>{{ $solto->texto }} ({{ $solto->usos }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                @if($textoLigar && $usosDoTexto)
                @php
                    $totalUsos = array_sum($usosDoTexto);
                    $descricaoUsos = implode(', ', array_filter([
                        $usosDoTexto['tbl_evento_calendario'] ? $usosDoTexto['tbl_evento_calendario'] . ' evento(s)' : null,
                        $usosDoTexto['tbl_grade_treino'] ? $usosDoTexto['tbl_grade_treino'] . ' horário(s) da grade' : null,
                        $usosDoTexto['tbl_campeonato'] ? $usosDoTexto['tbl_campeonato'] . ' campeonato(s)' : null,
                    ]));
                @endphp
                <form method="POST" action="{{ route('admin.locais.ligarUsos', $local->id_local) }}"
                      onsubmit="return confirm(@js("Ligar {$descricaoUsos} com \"{$textoLigar}\" a {$local->nome_local}?"))">
                    @csrf
                    <input type="hidden" name="texto" value="{{ $textoLigar }}">
                    <button type="submit" class="btn btn-sm btn-info js-ligar-usos">
                        <i class="bi bi-link-45deg"></i> Ligar os {{ $totalUsos }} uso(s) de "{{ $textoLigar }}" a este local
                    </button>
                    <span class="small text-muted ms-2">{{ $descricaoUsos }}</span>
                </form>
                @endif
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.locais.update', $local->id_local) }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    @include('admin.locais._campos', ['local' => $local])
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="{{ route('admin.locais.index') }}" class="btn-modal-cancel">Cancelar</a>
                <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Atualizar</button>
            </div>
        </form>

    </div>
</main>
@endsection
