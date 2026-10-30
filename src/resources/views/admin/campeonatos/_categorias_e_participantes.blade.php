{{--
  Categorias do campeonato e participantes por categoria (Fase H, Etapa H1). Usado nos modais de criar e
  editar da lista de Campeonatos e nas páginas create/edit.
  $prefixo        ids únicos na página ('c', 'e', 'p')
  $categorias     todas as categorias (CampeonatosController::categorias)
  $times          todos os times
  $selecionadas   ids das categorias do campeonato
  $participantes  [id_categoria => [id_time, ...]]
  Todas as categorias valem igual (sem "principal"; a de referência até a Etapa H3 é escolhida pelo sistema,
  Campeonato::categoriaDeReferencia). Só os blocos das categorias marcadas aparecem; o servidor ignora o bloco
  de categoria desmarcada. O modal de edição preenche pelo
  window.preencherCategoriasDoCampeonato(caixa, categorias, participantes).
--}}
@php
  $selecionadas  = array_map('intval', $selecionadas ?? []);
  $participantes = $participantes ?? [];
@endphp
<div class="col-12 js-cat-part">
  <label class="form-label">Categorias do campeonato <span class="text-danger">*</span></label>
  <div class="d-flex flex-wrap gap-3">
    @foreach($categorias as $cat)
    <div class="form-check mb-0">
      <input class="form-check-input js-categoria" type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
             id="{{ $prefixo }}_cat_{{ $cat->id_categoria }}" @checked(in_array((int) $cat->id_categoria, $selecionadas, true))>
      <label class="form-check-label" for="{{ $prefixo }}_cat_{{ $cat->id_categoria }}">{{ $cat->rotulo }}</label>
    </div>
    @endforeach
  </div>
  <span class="form-text">Marque uma ou mais.</span>

  <p class="modal-section-label mb-1 mt-3"><i class="bi bi-shield-fill"></i> Times participantes por categoria</p>
  <p class="form-text mt-0 mb-2">Em cada categoria aparecem os times que a têm marcada no cadastro (tela de Times). O mesmo time (a AACJ, o Botafogo) pode jogar em várias categorias.</p>
  @foreach($categorias as $cat)
  @php
    $doBloco = array_map('intval', $participantes[$cat->id_categoria] ?? []);
    // Os times com a categoria no cadastro e, por segurança, quem já é participante nela
    $timesDaCategoria = $times->filter(fn ($t) => $t->categorias->contains('id_categoria', $cat->id_categoria)
        || in_array((int) $t->id_time, $doBloco, true));
  @endphp
  <div class="border rounded p-2 mb-2 js-bloco-categoria" data-categoria="{{ $cat->id_categoria }}">
    <div class="fw-semibold small mb-1">{{ $cat->rotulo }}</div>
    @if($timesDaCategoria->isEmpty())
    <div class="small text-muted">Nenhum time tem o {{ $cat->rotulo }} marcado. Marque a categoria na tela de Times.</div>
    @endif
    <div class="row g-2">
      @foreach($timesDaCategoria as $time)
      <div class="col-md-4 col-6">
        <div class="form-check mb-0">
          <input class="form-check-input js-time" type="checkbox" name="participantes[{{ $cat->id_categoria }}][]"
                 value="{{ $time->id_time }}" id="{{ $prefixo }}_p_{{ $cat->id_categoria }}_{{ $time->id_time }}"
                 @checked(in_array((int) $time->id_time, $doBloco, true))>
          <label class="form-check-label" for="{{ $prefixo }}_p_{{ $cat->id_categoria }}_{{ $time->id_time }}">
            {{ $time->nome_time }}
            @if($time->tipo_time === 'INTERNO')
            <span class="badge-status ativo mini ms-1">INT</span>
            @else
            <span class="badge-status inativo mini ms-1">EXT</span>
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
  // Só os blocos das categorias marcadas aparecem
  function atualizar(caixa) {
    caixa.querySelectorAll('.js-bloco-categoria').forEach(bloco => {
      const chk = caixa.querySelector(`.js-categoria[value="${bloco.dataset.categoria}"]`);
      bloco.hidden = !(chk && chk.checked);
    });
  }

  function ligar(caixa) {
    caixa.querySelectorAll('.js-categoria').forEach(chk => chk.addEventListener('change', () => atualizar(caixa)));
    atualizar(caixa);
  }

  window.preencherCategoriasDoCampeonato = function (caixa, categorias, participantes) {
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
