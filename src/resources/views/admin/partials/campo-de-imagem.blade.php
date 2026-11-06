{{--
  Campo de imagem com recorte e prévia (26/10/2026; o servidor trata: App\Services\TratadorDeImagem).
  - tipo "capa": recorte na proporção (Cropper.js), começando pelo centro; as coordenadas vão em "recorte_{name}"
    e o servidor confere (sem JavaScript, ou inválidas: o centro).
  - tipo "logo": recorte livre, só para tirar as sobras (06/11/2026); o que ficar no quadro vai inteiro, num quadrado
    com fundo transparente.
  Aviso (sem bloquear) para imagem pequena, igual ao do servidor.
  Parâmetros: name, rotulo, tipo, proporcao (largura/altura, na capa), minimo (px: largura do recorte na capa, maior
  lado na logo), ajuda, miniatura (endereço da imagem atual) ou idAtual (id do bloco da imagem atual, que o JavaScript
  da tela preenche), obrigatorio, editavel (botão "Editar imagem": recorta a imagem atual sem enviar de novo; quem usa
  precisa aceitar o recorte sem arquivo, com TratadorDeImagem::origemDoEnvio).
  A tela acompanha pelo evento "imagem:mudou" (a imagem, recortada ou não, fica em campo.fonteDaPrevia) e informa a
  imagem atual pelo evento "imagem:atual" (detail.url), no modal de edição.
--}}
@php
  $tipo = $tipo ?? 'capa';
@endphp
<div class="js-campo-imagem" data-tipo="{{ $tipo }}" data-proporcao="{{ $proporcao ?? '' }}" data-minimo="{{ $minimo }}"
     data-max-kb="{{ \App\Services\TratadorDeImagem::ENVIO_MAX_KB }}">
  <label class="form-label">{{ $rotulo }} @if(!empty($obrigatorio))<span class="text-danger">*</span>@endif</label>
  {{-- Imagem atual em miniatura (quem usa o painel não entende o nome do arquivo); no modal, o JavaScript da tela
       preenche pelo id (idAtual). O "hidden" fica fora do d-flex, que o venceria --}}
  @if(!empty($miniatura) || !empty($idAtual))
    <div class="js-imagem-atual" @if(!empty($idAtual)) id="{{ $idAtual }}" @endif @if(empty($miniatura)) hidden @endif>
      <div class="d-flex align-items-center gap-2 mb-2">
        <img src="{{ $miniatura ?? '' }}" alt="Imagem atual" class="recorte-imagem-atual {{ $tipo === 'logo' ? 'logo' : '' }}">
        <small class="text-muted">Imagem atual</small>
        @if(!empty($editavel))
          <button type="button" class="btn btn-sm btn-outline-secondary js-editar-atual"><i class="bi bi-crop"></i> Editar imagem</button>
        @endif
      </div>
    </div>
  @endif
  <input type="file" name="{{ $name }}" class="form-control js-arquivo" accept="image/*" @if(!empty($obrigatorio)) required @endif>
  <input type="hidden" name="recorte_{{ $name }}" class="js-recorte">
  <span class="form-text">{{ $ajuda }}</span>
  {{-- Arquivo acima do limite: avisado já ao escolher (o servidor confere de novo) --}}
  <div class="alert alert-danger py-1 px-2 small mt-2 mb-0 js-erro-tamanho" hidden></div>

  <div class="js-area-imagem mt-2" hidden>
    <div class="small text-muted mb-1">
      {{ $tipo === 'logo' ? 'Puxe os cantos do quadro para tirar as sobras: o que ficar dentro dele vai para o site e para o app.' : 'Arraste ou redimensione o quadro para escolher a parte que aparece.' }}
    </div>
    <div class="recorte-imagem-palco"><img class="js-imagem-recorte" alt=""></div>
    <div class="alert alert-warning py-1 px-2 small mt-2 mb-0 js-aviso-pequena" hidden></div>
    @if(!empty($editavel))
      <button type="button" class="btn btn-sm btn-link px-0 mt-1 js-cancelar-edicao">Cancelar edição</button>
    @endif
  </div>
</div>

@once
  @push('scripts')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
  <style>
    .recorte-imagem-palco { max-height: 320px; background: var(--bs-tertiary-bg); }
    .recorte-imagem-palco img { display: block; max-width: 100%; }
    .recorte-imagem-atual { height: 64px; max-width: 160px; object-fit: cover; border-radius: 6px; border: 1px solid var(--bs-border-color); }
    .recorte-imagem-atual.logo { width: 64px; object-fit: contain; background: #fff; }
  </style>
  <script>
    // Campo de imagem (partial admin.partials.campo-de-imagem): recorte da imagem nova ou da atual
    (function () {
      function iniciar(campo) {
        const arquivo = campo.querySelector('.js-arquivo');
        const area = campo.querySelector('.js-area-imagem');
        const recorte = campo.querySelector('.js-recorte');
        const imagem = campo.querySelector('.js-imagem-recorte');
        const aviso = campo.querySelector('.js-aviso-pequena');
        const blocoAtual = campo.querySelector('.js-imagem-atual');
        const editarAtual = campo.querySelector('.js-editar-atual');
        const cancelar = campo.querySelector('.js-cancelar-edicao');
        const minimo = parseInt(campo.dataset.minimo, 10);
        const ehLogo = campo.dataset.tipo === 'logo';
        let cropper = null, url = null, espera = null, editandoAtual = false;
        let atual = blocoAtual ? blocoAtual.querySelector('img').getAttribute('src') : '';

        campo.fonteDaPrevia = atual;

        function avisar(pixels, texto) {
          aviso.hidden = pixels >= minimo;
          aviso.textContent = texto;
        }

        // A tela (bloco "Como vai ficar") lê em campo.fonteDaPrevia
        function publicar(fonte) {
          campo.fonteDaPrevia = fonte;
          campo.dispatchEvent(new CustomEvent('imagem:mudou', { bubbles: true }));
        }

        function limpar() {
          clearTimeout(espera);
          if (cropper) { cropper.destroy(); cropper = null; }
          if (url) { URL.revokeObjectURL(url); url = null; }
          imagem.onload = null;
          recorte.value = '';
          aviso.hidden = true;
          editandoAtual = false;
          area.hidden = true;
        }

        function voltarParaAtual() {
          arquivo.value = '';
          limpar();
          publicar(atual);
        }

        function atualizarRecorte() {
          const d = cropper.getData(true);
          const inteira = d.width >= cropper.getImageData().naturalWidth - 1 && d.height >= cropper.getImageData().naturalHeight - 1;

          // Abriu a imagem atual e não mexeu no quadro: nada a gravar
          recorte.value = editandoAtual && inteira ? '' : [Math.max(0, d.x), Math.max(0, d.y), d.width, d.height].join(',');

          if (ehLogo) {
            const maior = Math.max(d.width, d.height);
            avisar(maior, 'Logo pequena: o lado maior tem ' + maior + ' px. Pode ficar sem nitidez no site; o ideal é o lado maior com pelo menos ' + minimo + ' px.');
          } else {
            avisar(d.width, 'Imagem pequena (' + d.width + ' × ' + d.height + ' px no recorte): pode ficar sem nitidez no site. O ideal é pelo menos ' + minimo + ' px de largura.');
          }

          clearTimeout(espera);
          espera = setTimeout(() => {
            const tela = cropper.getCroppedCanvas({ maxWidth: 1280, maxHeight: 1280 });
            // Logo em PNG, para a prévia manter a transparência
            publicar(ehLogo ? tela.toDataURL('image/png') : tela.toDataURL('image/jpeg', 0.82));
          }, 120);
        }

        function abrirNoRecorte(fonte) {
          area.hidden = false;
          imagem.onload = () => {
            imagem.onload = null;
            // checkOrientation: false, porque o navegador já mostra a foto de celular virada certa (o servidor faz o mesmo)
            cropper = new Cropper(imagem, {
              aspectRatio: ehLogo ? NaN : parseFloat(campo.dataset.proporcao), viewMode: 1, autoCropArea: 1, checkOrientation: false,
              dragMode: 'none', movable: false, zoomable: false, rotatable: false, scalable: false,
              ready: atualizarRecorte, crop: () => cropper && atualizarRecorte(),
            });
          };
          imagem.src = fonte;
        }

        const erroTamanho = campo.querySelector('.js-erro-tamanho');
        const maxKb = parseInt(campo.dataset.maxKb, 10);

        arquivo.addEventListener('change', function () {
          limpar();
          erroTamanho.hidden = true;
          const escolhido = arquivo.files && arquivo.files[0];

          // Acima do limite: avisa já e descarta o arquivo (sem esperar o envio do formulário)
          if (escolhido && escolhido.size > maxKb * 1024) {
            const mb = (escolhido.size / 1048576).toFixed(1).replace('.', ',');
            erroTamanho.textContent = 'Esta imagem tem ' + mb + ' MB. Use um arquivo de até ' + Math.round(maxKb / 1024) + ' MB.';
            erroTamanho.hidden = false;
            arquivo.value = '';
            publicar(atual);
            return;
          }

          if (!escolhido || !escolhido.type.startsWith('image/')) {
            publicar(atual);
            return;
          }

          url = URL.createObjectURL(escolhido);
          publicar(url);

          // Sem o Cropper (o CDN não abriu): vai o arquivo inteiro e o servidor trata
          if (typeof Cropper !== 'undefined') {
            abrirNoRecorte(url);
          }
        });

        // Só o caminho do endereço: a imagem fica no mesmo site, e o navegador deixa ler o recorte para a prévia
        if (editarAtual) {
          editarAtual.addEventListener('click', function () {
            if (!atual || typeof Cropper === 'undefined') {
              return;
            }

            arquivo.value = '';
            limpar();
            editandoAtual = true;
            abrirNoRecorte(new URL(atual, location.href).pathname);
          });
        }

        if (cancelar) {
          cancelar.addEventListener('click', voltarParaAtual);
        }

        // No modal de edição, a tela informa a imagem atual do campeonato aberto
        campo.addEventListener('imagem:atual', event => {
          atual = event.detail.url || '';
          voltarParaAtual();
        });

        // Modal fechado: limpa o recorte (o arquivo é escolhido de novo ao reabrir)
        const modal = campo.closest('.modal');
        if (modal) {
          modal.addEventListener('hidden.bs.modal', () => { erroTamanho.hidden = true; voltarParaAtual(); });
        }
      }

      document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.js-campo-imagem').forEach(iniciar));
    })();
  </script>
  @endpush
@endonce
