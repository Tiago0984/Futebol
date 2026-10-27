{{--
  Campo de imagem com recorte e prévia (26/10/2026; o servidor trata: App\Services\TratadorDeImagem).
  - tipo "capa": recorte na proporção (Cropper.js), começando pelo centro; as coordenadas vão em "recorte_{name}"
    e o servidor confere (sem JavaScript, ou inválidas: o centro). Prévias com "cover", como o site e o app mostram.
  - tipo "logo": sem recorte (a logo vai inteira, num quadrado com fundo transparente); só a prévia.
  Aviso (sem bloquear) para imagem pequena, igual ao do servidor.
  Parâmetros: name, rotulo, tipo, proporcao (largura/altura, na capa), previas ([rótulo, proporção]), minimo
  (px: largura do recorte na capa, maior lado na logo), ajuda, miniatura (endereço da imagem atual) ou idAtual (id do
  bloco da imagem atual, que o JavaScript da tela preenche), obrigatorio.
--}}
@php
  $tipo = $tipo ?? 'capa';
  $previas = $previas ?? [];
@endphp
<div class="js-campo-imagem" data-tipo="{{ $tipo }}" data-proporcao="{{ $proporcao ?? 1 }}" data-minimo="{{ $minimo }}">
  <label class="form-label">{{ $rotulo }} @if(!empty($obrigatorio))<span class="text-danger">*</span>@endif</label>
  {{-- Imagem atual em miniatura (quem usa o painel não entende o nome do arquivo); no modal, o JavaScript da tela
       preenche pelo id (idAtual) --}}
  @if(!empty($miniatura) || !empty($idAtual))
    <div class="d-flex align-items-center gap-2 mb-2" @if(!empty($idAtual)) id="{{ $idAtual }}" @endif @if(empty($miniatura)) hidden @endif>
      <img src="{{ $miniatura ?? '' }}" alt="Imagem atual" class="recorte-imagem-atual {{ $tipo === 'logo' ? 'logo' : '' }}">
      <small class="text-muted">Imagem atual</small>
    </div>
  @endif
  <input type="file" name="{{ $name }}" class="form-control js-arquivo" accept="image/*" @if(!empty($obrigatorio)) required @endif>
  @if($tipo === 'capa')
    <input type="hidden" name="recorte_{{ $name }}" class="js-recorte">
  @endif
  <span class="form-text">{{ $ajuda }}</span>

  <div class="js-area-imagem mt-2" hidden>
    @if($tipo === 'capa')
      <div class="small text-muted mb-1">Arraste ou redimensione o quadro para escolher a parte que aparece.</div>
      <div class="recorte-imagem-palco"><img class="js-imagem-recorte" alt=""></div>
    @endif
    <div class="d-flex flex-wrap gap-2 mt-2">
      @foreach($previas as [$rotuloPrevia, $proporcaoPrevia])
        <figure class="mb-0 recorte-imagem-previa-fig">
          <div class="recorte-imagem-previa js-previa {{ $tipo === 'logo' ? 'logo' : '' }}" style="aspect-ratio: {{ $proporcaoPrevia }};"></div>
          <figcaption class="small text-muted">{{ $rotuloPrevia }}</figcaption>
        </figure>
      @endforeach
    </div>
    <div class="alert alert-warning py-1 px-2 small mt-2 mb-0 js-aviso-pequena" hidden></div>
  </div>
</div>

@once
  @push('scripts')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
  <style>
    .recorte-imagem-palco { max-height: 320px; background: var(--bs-tertiary-bg); }
    .recorte-imagem-palco img { display: block; max-width: 100%; }
    .recorte-imagem-previa-fig { width: 170px; }
    .recorte-imagem-atual { height: 64px; max-width: 160px; object-fit: cover; border-radius: 6px; border: 1px solid var(--bs-border-color); }
    .recorte-imagem-atual.logo { width: 64px; object-fit: contain; background: #fff; }
    .recorte-imagem-previa { width: 100%; border-radius: 6px; background: #0a0a0a center / cover no-repeat; border: 1px solid var(--bs-border-color); }
    /* Logo: inteira (contain), num fundo claro e num escuro, para ver como fica a transparência */
    .recorte-imagem-previa.logo { background-color: #fff; background-size: contain; }
    .recorte-imagem-previa-fig:nth-child(2) .recorte-imagem-previa.logo { background-color: #111; }
  </style>
  <script>
    // Campo de imagem (partial admin.partials.campo-de-imagem): recorte da capa e prévias
    (function () {
      function iniciar(campo) {
        const arquivo = campo.querySelector('.js-arquivo');
        const area = campo.querySelector('.js-area-imagem');
        const recorte = campo.querySelector('.js-recorte');
        const imagem = campo.querySelector('.js-imagem-recorte');
        const previas = campo.querySelectorAll('.js-previa');
        const aviso = campo.querySelector('.js-aviso-pequena');
        const minimo = parseInt(campo.dataset.minimo, 10);
        let cropper = null, url = null, espera = null;

        function avisar(pixels, texto) {
          aviso.hidden = pixels >= minimo;
          aviso.textContent = texto;
        }

        function limpar() {
          if (cropper) { cropper.destroy(); cropper = null; }
          if (url) { URL.revokeObjectURL(url); url = null; }
          if (recorte) recorte.value = '';
          area.hidden = true;
        }

        // As prévias usam "cover" (capa) ou "contain" (logo), como o site e o app
        function mostrar(fonte) {
          previas.forEach(p => { p.style.backgroundImage = 'url("' + fonte + '")'; });
        }

        function atualizarCapa() {
          const d = cropper.getData(true);
          recorte.value = [Math.max(0, d.x), Math.max(0, d.y), d.width, d.height].join(',');
          avisar(d.width, 'Imagem pequena (' + d.width + ' × ' + d.height + ' px no recorte): pode ficar sem nitidez no site. O ideal é pelo menos ' + minimo + ' px de largura.');
          clearTimeout(espera);
          espera = setTimeout(() => mostrar(cropper.getCroppedCanvas({ maxWidth: 640, maxHeight: 640 }).toDataURL('image/jpeg', 0.8)), 120);
        }

        arquivo.addEventListener('change', function () {
          limpar();
          const escolhido = arquivo.files && arquivo.files[0];
          if (!escolhido || !escolhido.type.startsWith('image/') || typeof Cropper === 'undefined' && campo.dataset.tipo === 'capa') {
            return;
          }

          url = URL.createObjectURL(escolhido);
          area.hidden = false;

          if (campo.dataset.tipo === 'logo') {
            const teste = new Image();
            teste.onload = () => {
              const maior = Math.max(teste.naturalWidth, teste.naturalHeight);
              avisar(maior, 'Logo pequena: o lado maior tem ' + maior + ' px. Pode ficar sem nitidez no site; o ideal é o lado maior com pelo menos ' + minimo + ' px.');
              mostrar(url);
            };
            teste.src = url;
            return;
          }

          imagem.onload = () => {
            // checkOrientation: false, porque o navegador já mostra a foto de celular virada certa (o servidor faz o mesmo)
            cropper = new Cropper(imagem, {
              aspectRatio: parseFloat(campo.dataset.proporcao), viewMode: 1, autoCropArea: 1, checkOrientation: false,
              dragMode: 'none', movable: false, zoomable: false, rotatable: false, scalable: false,
              ready: atualizarCapa, crop: () => cropper && atualizarCapa(),
            });
          };
          imagem.src = url;
        });

        // Modal fechado: limpa o recorte (o arquivo é escolhido de novo ao reabrir)
        const modal = campo.closest('.modal');
        if (modal) {
          modal.addEventListener('hidden.bs.modal', () => { arquivo.value = ''; limpar(); });
        }
      }

      document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.js-campo-imagem').forEach(iniciar));
    })();
  </script>
  @endpush
@endonce
