{{-- Foto do atleta com prévia (10/11/2026), nos modais de Novo e Editar atleta. Mostra a foto escolhida (ou, no Editar,
     a atual, preenchida pelo JS da lista em .js-foto-atleta-atual) no círculo em que ela aparece na lista e no site.
     Acima de 2 MB (o limite do servidor), avisa já ao escolher e descarta o arquivo. JS na lista de Atletas.
     Parâmetros: rotulo, ajuda (opcional) --}}
<div class="js-campo-foto-atleta" data-max-kb="2048">
    <label class="form-label">{{ $rotulo }} <span class="text-muted fw-normal">(opcional)</span></label>
    <div class="d-flex align-items-center gap-3">
        <div class="foto-atleta-previa">
            <img class="js-foto-atleta-previa" alt="Prévia da foto" hidden>
            <i class="bi bi-person js-foto-atleta-vazia" aria-hidden="true"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <input type="file" name="foto_atleta" class="form-control js-foto-atleta-arquivo"
                accept="image/jpeg,image/png,image/webp">
            <div class="form-text js-foto-atleta-texto">{{ $ajuda ?? 'JPG, PNG ou WebP, até 2 MB.' }}</div>
            <div class="alert alert-danger py-1 px-2 small mt-1 mb-0 js-foto-atleta-erro" hidden></div>
            {{-- Só no Novo: o Editar usa a bag "edicao", com os erros no topo do modal --}}
            @if(!empty($comErro))
            @error('foto_atleta')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endif
        </div>
    </div>
</div>
