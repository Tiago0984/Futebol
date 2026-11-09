@php
    $fotoAtual = $profissional->urlFoto();
    $removerFoto = (bool) old('remover_foto');
@endphp
<div class="js-foto-comissao" data-atual="{{ $fotoAtual }}" data-max-kb="2048">
    <label class="form-label" for="{{ $prefixo }}foto">Foto do profissional</label>
    <input type="file" name="foto_comissao_tecnica" id="{{ $prefixo }}foto" class="form-control js-arquivo-foto"
        accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $prefixo }}foto_ajuda {{ $prefixo }}foto_erro">
    <span class="form-text" id="{{ $prefixo }}foto_ajuda">JPG, PNG ou WEBP, até 2 MB.{{ $fotoAtual ? ' Em branco, mantém a atual.' : '' }}</span>
    <p id="{{ $prefixo }}foto_erro" class="text-danger small mt-2 mb-0 js-erro-foto" role="alert" hidden></p>
    @if($fotoAtual)
    <div class="form-check mt-1">
        <input class="form-check-input js-remover-foto" type="checkbox" name="remover_foto" value="1" id="{{ $prefixo }}remover_foto" @checked($removerFoto)>
        <label class="form-check-label" for="{{ $prefixo }}remover_foto">Remover a foto</label>
    </div>
    @endif
    <div class="mt-3 js-previa-foto" @if(! $fotoAtual || $removerFoto) hidden @endif>
        <p class="modal-section-label mb-2"><i class="bi bi-eye"></i> Como vai ficar</p>
        <img class="previa-comissao-foto rounded-circle border bg-body-tertiary js-imagem-foto"
            @if($fotoAtual) src="{{ $fotoAtual }}" @endif alt="Prévia da foto do profissional">
        <span class="form-text">Na lista de profissionais, a foto aparece em círculo.</span>
    </div>
</div>

@once
    @push('scripts')
    <script src="{{ asset('coderatech/js/comissao-tecnica.js') }}?v={{ filemtime(public_path('coderatech/js/comissao-tecnica.js')) }}"></script>
    @endpush
@endonce
