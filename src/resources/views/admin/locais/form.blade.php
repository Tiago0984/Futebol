{{-- Novo e Editar local (Etapa L1): o mesmo formulário; $local novo (sem id) = cadastro --}}
@extends('layout.admin')

@php $editando = $local->exists; @endphp

@section('title', $editando ? 'Editar local' : 'Novo local')

@section('content')
<main class="app-main">
    <div class="container-fluid py-4">

        <div class="admin-page-header">
            <div>
                <h1 class="page-title">{{ $editando ? $local->nome_local : 'Novo local' }}</h1>
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
        @if($editando && ($soltos ?? collect())->isNotEmpty())
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

        <form method="POST" action="{{ $editando ? route('admin.locais.update', $local->id_local) : route('admin.locais.store') }}">
            @csrf
            @if($editando) @method('PUT') @endif
            @unless($editando)
            <input type="hidden" name="texto_origem" value="{{ old('texto_origem', $textoOrigem ?? '') }}">
            @endunless

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="lc_nome">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="lc_nome" name="nome_local" class="form-control" required maxlength="100"
                                placeholder="Ex: Campo AACJ" value="{{ old('nome_local', $local->nome_local) }}">
                            <small class="text-muted">O nome que aparece nos eventos e no app.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="lc_cep">CEP</label>
                            <input type="text" id="lc_cep" name="cep_local" class="form-control" maxlength="9" inputmode="numeric"
                                placeholder="00000-000" value="{{ old('cep_local', $local->cep_local) }}">
                            <small class="text-muted" id="lc_cep_ajuda">Opcional. Preenche a rua, o bairro e a cidade.</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="lc_rua">Rua <span class="text-danger">*</span></label>
                            <input type="text" id="lc_rua" name="rua_local" class="form-control" required maxlength="100"
                                value="{{ old('rua_local', $local->rua_local) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="lc_numero">Número <span class="text-danger">*</span></label>
                            <input type="text" id="lc_numero" name="numero_local" class="form-control" required maxlength="10"
                                placeholder="Ex: 123 ou S/N" value="{{ old('numero_local', $local->numero_local) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="lc_complemento">Complemento</label>
                            <input type="text" id="lc_complemento" name="complemento_local" class="form-control" maxlength="100"
                                placeholder="Ex: Portão 2, quadra coberta" value="{{ old('complemento_local', $local->complemento_local) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="lc_bairro">Bairro <span class="text-danger">*</span></label>
                            <input type="text" id="lc_bairro" name="bairro_local" class="form-control" required maxlength="50"
                                value="{{ old('bairro_local', $local->bairro_local) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="lc_cidade">Cidade <span class="text-danger">*</span></label>
                            <input type="text" id="lc_cidade" name="cidade_local" class="form-control" required maxlength="50"
                                value="{{ old('cidade_local', $local->cidade_local) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="lc_uf">UF <span class="text-danger">*</span></label>
                            <input type="text" id="lc_uf" name="estado_local" class="form-control text-uppercase" required maxlength="2"
                                placeholder="SP" value="{{ old('estado_local', $local->estado_local) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <label class="form-label" for="lc_mapa">Link do Google Maps</label>
                    <input type="url" id="lc_mapa" name="mapa_url_local" class="form-control" maxlength="500"
                        placeholder="https://maps.app.goo.gl/..." value="{{ old('mapa_url_local', $local->mapa_url_local) }}">
                    <small class="text-muted d-block mt-1">
                        Opcional, mas deixa o "Abrir mapa" exato. No Google Maps, ache o lugar, toque em <strong>Compartilhar</strong> e em
                        <strong>Copiar link</strong>, e cole aqui. Só links do Google Maps são aceitos. Sem link, o app busca pelo nome e pelo endereço.
                    </small>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="{{ route('admin.locais.index') }}" class="btn-modal-cancel">Cancelar</a>
                <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> {{ $editando ? 'Atualizar' : 'Salvar' }}</button>
            </div>
        </form>

    </div>
</main>

<script>
// CEP: máscara 00000-000 e, com 8 dígitos, a busca no ViaCEP (a mesma do cadastro do site). Só preenche os campos
// (dá para corrigir à mão); falhou ou não achou, só avisa
(() => {
    const cep = document.getElementById('lc_cep'), ajuda = document.getElementById('lc_cep_ajuda');
    const textoAjuda = ajuda.textContent;
    cep.addEventListener('input', () => {
        const d = cep.value.replace(/\D/g, '').slice(0, 8);
        cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        ajuda.textContent = textoAjuda;
        if (d.length !== 8) return;
        fetch('https://viacep.com.br/ws/' + d + '/json/')
            .then(r => r.json())
            .then(e => {
                if (e.erro) { ajuda.textContent = 'CEP não encontrado: preencha o endereço à mão.'; return; }
                [['lc_rua', e.logradouro], ['lc_bairro', e.bairro], ['lc_cidade', e.localidade], ['lc_uf', e.uf]]
                    .forEach(([id, valor]) => { if (valor) document.getElementById(id).value = valor; });
                document.getElementById('lc_numero').focus();
            })
            .catch(() => { ajuda.textContent = 'Não foi possível buscar o CEP agora: preencha o endereço à mão.'; });
    });
})();
</script>
@endsection
