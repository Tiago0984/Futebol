{{--
    Select "Local" (Etapa L2, 30/10/2026): os locais ATIVOS do cadastro (CADASTROS › Locais), sem texto livre, com o
    botão "Novo local" (cadastra sem sair da tela, pela rota admin.locais.rapido, e já escolhe o novo).
    Parâmetros: $idCampo (id do select); opcionais $atual (EventoCalendario, GradeTreino ou Campeonato em edição) com
    $campoTexto (a coluna do texto do local nele), $valorEscolhido (valor já escolhido, quando não há $atual: os geradores),
    $nome (nome do campo, padrão id_local), $rotulo e $vazio (texto da opção vazia, padrão "— Sem local —").
    Extras do registro em edição (o servidor também confere, em Local::daEscolha):
    - local atual que foi desativado: "Nome (inativo)", só para manter;
    - texto antigo (escrito à mão antes da L2): "Quadra A (texto antigo)", mantido até alguém escolher da lista.
    Nos modais da lista, o JS do partial admin.partials.sugestoes cria esses extras (prepararSelectLocal).
--}}
@php
    $nome        = $nome ?? 'id_local';
    $atual       = $atual ?? null;
    $idAtual     = $atual?->id_local;
    $textoAtual  = $atual && isset($campoTexto) ? $atual->{$campoTexto} : null;
    $ativos      = \App\Models\Local::paraEscolher();
    $inativo     = $idAtual && ! $ativos->contains('id_local', $idAtual) ? $atual->local : null;
    $selecionado = isset($valorEscolhido)
        ? (string) $valorEscolhido
        : ($errors->any()
            ? (string) old($nome, '')
            : (string) ($idAtual ?: (filled($textoAtual) ? \App\Models\Local::TEXTO_ANTIGO : '')));
@endphp
<div class="js-campo-local">
    <label class="form-label" for="{{ $idCampo }}">{{ $rotulo ?? 'Local' }}</label>
    <div class="input-group">
        <select id="{{ $idCampo }}" name="{{ $nome }}" class="form-select js-select-local">
            <option value="">{{ $vazio ?? '— Sem local —' }}</option>
            @foreach ($ativos as $l)
            <option value="{{ $l->id_local }}" data-endereco="{{ $l->enderecoEmUmaLinha() }}" @selected($selecionado === (string) $l->id_local)>{{ $l->nome_local }}</option>
            @endforeach
            @if ($inativo)
            <option value="{{ $inativo->id_local }}" class="js-opcao-extra" data-endereco="{{ $inativo->enderecoEmUmaLinha() }}" @selected($selecionado === (string) $inativo->id_local)>{{ $inativo->nome_local }} (inativo)</option>
            @endif
            @if (! $idAtual && filled($textoAtual))
            <option value="{{ \App\Models\Local::TEXTO_ANTIGO }}" class="js-opcao-extra" @selected($selecionado === \App\Models\Local::TEXTO_ANTIGO)>{{ $textoAtual }} (texto antigo)</option>
            @endif
        </select>
        <button type="button" class="btn btn-outline-secondary js-novo-local" title="Cadastrar um local que não está na lista"><i class="bi bi-plus-lg"></i> Novo local</button>
    </div>
    <small class="text-muted d-block js-endereco-local"></small>
    @error($nome)<div class="text-danger small">{{ $message }}</div>@enderror

    {{-- Cadastro rápido: os campos não pertencem ao formulário de fora (form="sem-formulario"), então não vão no envio
         dele nem travam o "Salvar" com o required --}}
    <div class="js-painel-novo-local d-none border rounded p-3 mt-2 bg-body-tertiary"
         data-url="{{ route('admin.locais.rapido') }}" data-token="{{ csrf_token() }}">
        <p class="small fw-semibold mb-2"><i class="bi bi-geo-alt"></i> Novo local</p>
        <div class="row g-2">
            <div class="col-md-8"><input type="text" form="sem-formulario" data-campo="nome_local" class="form-control form-control-sm" maxlength="100" placeholder="Nome * (ex.: Arena Norte)"></div>
            <div class="col-md-4"><input type="text" form="sem-formulario" data-campo="cep_local" class="form-control form-control-sm js-cep-novo-local" maxlength="9" inputmode="numeric" placeholder="CEP (opcional)"></div>
            <div class="col-md-8"><input type="text" form="sem-formulario" data-campo="rua_local" class="form-control form-control-sm" maxlength="100" placeholder="Rua *"></div>
            <div class="col-md-4"><input type="text" form="sem-formulario" data-campo="numero_local" class="form-control form-control-sm" maxlength="10" placeholder="Número * ou S/N"></div>
            <div class="col-md-6"><input type="text" form="sem-formulario" data-campo="complemento_local" class="form-control form-control-sm" maxlength="100" placeholder="Complemento"></div>
            <div class="col-md-6"><input type="text" form="sem-formulario" data-campo="bairro_local" class="form-control form-control-sm" maxlength="50" placeholder="Bairro *"></div>
            <div class="col-md-8"><input type="text" form="sem-formulario" data-campo="cidade_local" class="form-control form-control-sm" maxlength="50" placeholder="Cidade *"></div>
            <div class="col-md-4"><input type="text" form="sem-formulario" data-campo="estado_local" class="form-control form-control-sm text-uppercase" maxlength="2" placeholder="UF *"></div>
            <div class="col-12"><input type="url" form="sem-formulario" data-campo="mapa_url_local" class="form-control form-control-sm" maxlength="500" placeholder="Link do Google Maps (opcional): Compartilhar → Copiar link"></div>
        </div>
        <ul class="text-danger small mb-0 mt-2 js-erros-novo-local"></ul>
        <div class="d-flex justify-content-end gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary js-cancelar-novo-local">Cancelar</button>
            <button type="button" class="btn btn-sm btn-primary js-salvar-novo-local"><i class="bi bi-check-lg"></i> Salvar local</button>
        </div>
    </div>
</div>
