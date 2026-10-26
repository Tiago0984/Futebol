{{-- Imagem enviada pequena (26/10/2026, TratadorDeImagem): salva assim mesmo, só o aviso --}}
@if ($avisosImagem = session('avisos_imagem'))
  <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert" id="avisoImagem">
    <strong><i class="bi bi-image"></i> Imagem salva, mas confira</strong>
    <ul class="mb-0 mt-2">
      @foreach ($avisosImagem as $aviso)
      <li>{{ $aviso }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
