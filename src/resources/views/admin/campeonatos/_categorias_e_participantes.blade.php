{{--
  Categorias do campeonato e participantes por categoria (Fase H, Etapa H1). Usado nos modais de criar e
  editar da lista de Campeonatos e nas páginas create/edit.
  $prefixo        ids únicos na página ('c', 'e', 'p')
  $categorias     todas as categorias (CampeonatosController::categorias)
  $times          todos os times
  $principal      id da categoria principal (ou null)
  $selecionadas   ids das categorias do campeonato
  $participantes  [id_categoria => [id_time, ...]]
  A principal vale para a idade, a convocação e os jogos até a Etapa H3. Só os blocos das categorias marcadas
  aparecem; o servidor ignora o bloco de categoria desmarcada. O modal de edição preenche pelo
  window.preencherCategoriasDoCampeonato(caixa, principal, categorias, participantes).
--}}
@php
  $selecionadas  = array_map('intval', $selecionadas ?? []);
  $participantes = $participantes ?? [];
@endphp
<div class="col-12 js-cat-part">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="{{ $prefixo }}_id_categoria">Categoria principal <span class="text-danger">*</span></label>
      <select id="{{ $prefixo }}_id_categoria" name="id_categoria" class="form-select js-principal" required>
        <option value="">— Selecionar —</option>
        @foreach($categorias as $cat)
        <option value="{{ $cat->id_categoria }}"@selected((int) $principal === (int) $cat->id_categoria)>{{ $cat->rotulo }}</option>
        @endforeach
      </select>
      <span class="form-text">Vale para a idade, a convocação e os jogos.</span>
    </div>
    <div class="col-md-8">
      <label class="form-label">Outras categorias do campeonato</label>
      <div class="d-flex flex-wrap gap-3">
        @foreach($categorias as $cat)
        <div class="form-check mb-0">
          <input class="form-check-input js-categoria" type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
                 id="{{ $prefixo }}_cat_{{ $cat->id_categoria }}" @checked(in_array((int) $cat->id_categoria, $selecionadas, true))>
          <label class="form-check-label" for="{{ $prefixo }}_cat_{{ $cat->id_categoria }}">{{ $cat->rotulo }}</label>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <p class="modal-section-label mb-1 mt-3"><i class="bi bi-shield-fill"></i> Times participantes por categoria</p>
  <p class="form-text mt-0 mb-2">O mesmo time pode jogar em mais de uma categoria (um adversário com um cadastro só).</p>
  @foreach($categorias as $cat)
  @php $doBloco = array_map('intval', $participantes[$cat->id_categoria] ?? []); @endphp
  <div class="border rounded p-2 mb-2 js-bloco-categoria" data-categoria="{{ $cat->id_categoria }}">
    <div class="fw-semibold small mb-1">{{ $cat->rotulo }}</div>
    <div class="row g-2">
      @foreach($times as $time)
      <div class="col-md-4 col-6">
        <div class="form-check mb-0">
          <input class="form-check-input js-time" type="checkbox" name="participantes[{{ $cat->id_categoria }}][]"
                 value="{{ $time->id_time }}" id="{{ $prefixo }}_p_{{ $cat->id_categoria }}_{{ $time->id_time }}"
                 @checked(in_array((int) $time->id_time, $doBloco, true))>
          <label class="form-check-label" for="{{ $prefixo }}_p_{{ $cat->id_categoria }}_{{ $time->id_time }}">
            {{ $time->nome_time }}
            @if($time->tipo_time === 'INTERNO')
            <span class="badge-status ativo ms-1" style="font-size:0.6rem;">INT</span>
            @else
            <span class="badge-status inativo ms-1" style="font-size:0.6rem;">EXT</span>
            @endif
          </label>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endforeach
</div>

@once
@push('scripts')
<script>
(function () {
  // Principal sempre marcada (e travada) entre as categorias; só os blocos das categorias marcadas aparecem
  function atualizar(caixa) {
    const principal = caixa.querySelector('.js-principal').value;
    caixa.querySelectorAll('.js-categoria').forEach(chk => {
      if (chk.value === principal) chk.checked = true;
      chk.disabled = chk.value === principal;
    });
    caixa.querySelectorAll('.js-bloco-categoria').forEach(bloco => {
      const chk = caixa.querySelector(`.js-categoria[value="${bloco.dataset.categoria}"]`);
      bloco.hidden = !(chk && chk.checked);
    });
  }

  // Trocar a principal num campeonato de uma categoria só é trocar a categoria: a antiga sai e os times passam
  // para a nova (se ela ainda não tiver times). Com outras categorias marcadas, a antiga continua como "outra".
  function trocarPrincipal(caixa, anterior) {
    const nova = caixa.querySelector('.js-principal').value;
    if (!anterior || !nova || anterior === nova) return;
    const outras = [...caixa.querySelectorAll('.js-categoria:checked')].filter(chk => chk.value !== anterior && chk.value !== nova);
    if (outras.length) return;

    const timesDe = id => caixa.querySelectorAll(`.js-bloco-categoria[data-categoria="${id}"] .js-time`);
    const novaVazia = ![...timesDe(nova)].some(chk => chk.checked);
    timesDe(anterior).forEach(chk => {
      if (novaVazia && chk.checked) {
        const destino = caixa.querySelector(`.js-bloco-categoria[data-categoria="${nova}"] .js-time[value="${chk.value}"]`);
        if (destino) destino.checked = true;
      }
      chk.checked = false;
    });
    const chkAnterior = caixa.querySelector(`.js-categoria[value="${anterior}"]`);
    if (chkAnterior) chkAnterior.checked = false;
  }

  function ligar(caixa) {
    const select = caixa.querySelector('.js-principal');
    select.dataset.anterior = select.value;
    select.addEventListener('change', () => {
      trocarPrincipal(caixa, select.dataset.anterior);
      select.dataset.anterior = select.value;
      atualizar(caixa);
    });
    caixa.querySelectorAll('.js-categoria').forEach(chk => chk.addEventListener('change', () => atualizar(caixa)));
    atualizar(caixa);
  }

  window.preencherCategoriasDoCampeonato = function (caixa, principal, categorias, participantes) {
    const select = caixa.querySelector('.js-principal');
    select.value = principal ? String(principal) : '';
    select.dataset.anterior = select.value;
    const marcadas = (categorias || []).map(String);
    caixa.querySelectorAll('.js-categoria').forEach(chk => { chk.checked = marcadas.includes(chk.value); });
    caixa.querySelectorAll('.js-bloco-categoria').forEach(bloco => {
      const ids = ((participantes || {})[bloco.dataset.categoria] || []).map(String);
      bloco.querySelectorAll('.js-time').forEach(chk => { chk.checked = ids.includes(chk.value); });
    });
    atualizar(caixa);
  };

  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.js-cat-part').forEach(ligar));
})();
</script>
@endpush
@endonce
