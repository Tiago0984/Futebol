{{-- Afastamento (16/10/2026): quem ficou fora de uma inscrição por estar afastado na data (middleware AvisaAfastadosIgnorados) --}}
@if ($avisosAfastamento = session('avisos_afastamento'))
  <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert" id="avisoAfastados">
    <strong><i class="bi bi-bandaid"></i> Afastado(s), não inscrito(s)</strong>
    <ul class="mb-0 mt-2">
      @foreach ($avisosAfastamento as $aviso)
      <li>{{ $aviso }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
