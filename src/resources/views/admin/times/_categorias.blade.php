{{--
  Categorias de que o time participa (27/10/2026): uma caixa por categoria, em duas colunas (Masculino e
  Feminino), no lugar do select de uma categoria só. Usado nos modais de criar e editar da lista de Times.
  $prefixo     ids únicos na página ('criar', 'editar')
  $categorias  todas as categorias (TimesController::index)
  $marcadas    ids marcados ao abrir (criar: o old() depois de um erro; editar: preenchido pelo JS da lista)
  A categoria inativa só aparece marcada, no time que já a tem (.js-cat-inativa, mostrada pelo JS da edição);
  o servidor recusa marcar uma inativa nova.
--}}
@php $marcadas = array_map('intval', $marcadas ?? []); @endphp
<div class="col-12">
  <label class="form-label">Categorias de que o time participa <span class="text-danger">*</span></label>
  <div class="row g-2">
    @foreach(['M' => 'Masculino', 'F' => 'Feminino'] as $sexo => $titulo)
    <div class="col-6">
      <div class="small text-muted fw-semibold mb-1">{{ $titulo }}</div>
      @foreach($categorias->where('sexo_categoria', $sexo) as $cat)
      @php $inativa = $cat->status_categoria !== 'ATIVO'; @endphp
      <div class="form-check mb-1 {{ $inativa ? 'js-cat-inativa d-none' : '' }}">
        <input class="form-check-input js-categoria-time" type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
               id="{{ $prefixo }}_cat_{{ $cat->id_categoria }}" @checked(in_array((int) $cat->id_categoria, $marcadas, true))>
        <label class="form-check-label" for="{{ $prefixo }}_cat_{{ $cat->id_categoria }}">
          {{ $cat->nome_categoria }}@if($inativa) <span class="text-muted">(inativa)</span>@endif
        </label>
      </div>
      @endforeach
    </div>
    @endforeach
  </div>
  <span class="form-text">Marque uma ou mais. Um cadastro só por time: o mesmo time (AACJ, Botafogo…) joga em várias categorias.</span>
</div>
