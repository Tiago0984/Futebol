/* ==========================================================================
   "COMO VAI FICAR" — imagens no painel (06/11/2026; partials admin/campeonatos/_previa_das_imagens,
   admin/banners/modals/_previa e, desde 09/11/2026, admin/noticias/modals/_previa e admin/galeria/modals/_previa). Mostra a imagem (a atual, a nova ou o recorte em andamento) com os dados digitados.
   As imagens chegam pelo evento "imagem:mudou" do partial admin.partials.campo-de-imagem, em campo.fonteDaPrevia.
   Nada aqui é gravado: o recorte vai pelo campo escondido de cada imagem.
   ========================================================================== */
(function () {
  // As mesmas palavras de Campeonato::getIniciaisAttribute
  const FORA_DAS_INICIAIS = ['da', 'de', 'do', 'das', 'dos', 'e'];

  function iniciais(nome) {
    return nome.trim().split(/\s+/)
      .filter(palavra => palavra && !FORA_DAS_INICIAIS.includes(palavra.toLowerCase()))
      .slice(0, 2)
      .map(palavra => palavra.charAt(0).toUpperCase())
      .join('');
  }

  function dataNaTela(valor) {
    return valor ? valor.split('-').reverse().join('/') : 'dd/mm/aaaa';
  }

  function pintarBanner(bloco, fonte) {
    bloco.querySelectorAll('.js-previa-banner').forEach(elemento => {
      elemento.classList.toggle('sem-imagem', !fonte);
      elemento.style.backgroundImage = fonte ? 'url("' + fonte + '")' : '';
    });
  }

  // O site é desenhado na largura real e reduzido até caber: assim, a proporção entre imagem e texto é a de lá
  function escalar(bloco) {
    bloco.querySelectorAll('.js-previa-moldura').forEach(moldura => {
      const largura = moldura.clientWidth;
      const conteudo = moldura.firstElementChild;

      if (!largura) {
        return;
      }

      const real = parseInt(moldura.dataset.largura, 10);
      conteudo.style.width = real + 'px';
      conteudo.style.transform = 'scale(' + largura / real + ')';
      moldura.style.height = conteudo.offsetHeight * largura / real + 'px';
    });
  }

  // No modal de edição, a tela preenche os campos por JavaScript (sem o evento "input"); escondido, a largura é 0
  function acompanhar(bloco, atualizarTudo) {
    bloco.closest('.modal')?.addEventListener('shown.bs.modal', atualizarTudo);

    if (window.ResizeObserver) {
      const observador = new ResizeObserver(() => escalar(bloco));
      bloco.querySelectorAll('.js-previa-moldura').forEach(moldura => observador.observe(moldura));
    }

    atualizarTudo();
  }

  function iniciarCampeonato(bloco) {
    const form = bloco.closest('form');
    const valor = nome => form.querySelector('[name="' + nome + '"]')?.value || '';
    const campoDe = nome => form.querySelector('.js-campo-imagem input[name="' + nome + '"]')?.closest('.js-campo-imagem');
    const todos = seletor => bloco.querySelectorAll(seletor);

    function escrever(seletor, texto) {
      todos(seletor).forEach(elemento => { elemento.textContent = texto; });
    }

    function atualizarTextos() {
      const nome = valor('nome_campeonato');

      escrever('.js-previa-nome', nome || 'Nome do campeonato');
      escrever('.js-previa-tipo', valor('tipo_campeonato') || 'Tipo');
      escrever('.js-previa-iniciais', iniciais(nome || 'Nome do campeonato'));

      todos('.js-previa-datas').forEach(elemento => {
        elemento.textContent = dataNaTela(valor('data_inicio_campeonato')) + elemento.dataset.separador + dataNaTela(valor('data_fim_campeonato'));
      });

      // As categorias marcadas no formulário, com o rótulo de cada caixa
      const rotulos = [...form.querySelectorAll('input[name="categorias[]"]:checked')]
        .map(caixa => form.querySelector('label[for="' + caixa.id + '"]')?.textContent.trim())
        .filter(Boolean);

      todos('.js-previa-categorias').forEach(lista => {
        lista.replaceChildren(...rotulos.map(rotulo => {
          const selo = document.createElement('span');
          selo.className = lista.dataset.classe;
          selo.textContent = rotulo;
          return selo;
        }));
      });
    }

    function atualizarImagens() {
      const logo = campoDe('logo_evento')?.fonteDaPrevia || '';
      const banner = campoDe('banner_evento')?.fonteDaPrevia || '';

      pintarBanner(bloco, banner);

      todos('.js-previa-logo').forEach(imagem => {
        if (logo) {
          imagem.src = logo;
        } else {
          imagem.removeAttribute('src');
        }
      });
      todos('.js-previa-com-logo').forEach(elemento => { elemento.hidden = !logo; });
      todos('.js-previa-iniciais').forEach(elemento => { elemento.hidden = Boolean(logo); });
    }

    form.addEventListener('input', () => { atualizarTextos(); escalar(bloco); });
    form.addEventListener('change', () => { atualizarTextos(); escalar(bloco); });
    form.addEventListener('imagem:mudou', atualizarImagens);

    acompanhar(bloco, () => { atualizarTextos(); atualizarImagens(); escalar(bloco); });
  }

  function iniciarBannerDaHome(bloco) {
    const form = bloco.closest('form');
    const valor = nome => form.querySelector('[name="' + nome + '"]')?.value.trim() || '';

    function atualizarTextos() {
      const subtitulo = valor('subtitulo_banner');

      bloco.querySelectorAll('.js-previa-titulo').forEach(elemento => { elemento.textContent = valor('titulo_banner') || 'Título do banner'; });
      // Sem subtítulo, sem o bloco, como no site
      bloco.querySelectorAll('.js-previa-subtitulo').forEach(elemento => {
        elemento.textContent = subtitulo;
        elemento.hidden = !subtitulo;
      });
    }

    function atualizarImagem() {
      pintarBanner(bloco, form.querySelector('.js-campo-imagem')?.fonteDaPrevia || '');
    }

    form.addEventListener('input', () => { atualizarTextos(); escalar(bloco); });
    form.addEventListener('imagem:mudou', atualizarImagem);

    acompanhar(bloco, () => { atualizarTextos(); atualizarImagem(); escalar(bloco); });
  }

  // Mês abreviado como o translatedFormat('M') do site, em pt_BR
  const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

  // Str::limit do site: corta e põe "..." (o cartão da home corta em 70, os posts recentes em 55)
  function limitar(texto, limite) {
    return limite && texto.length > limite ? texto.slice(0, limite).trimEnd() + '...' : texto;
  }

  // Capa da notícia (09/11/2026; partial admin/noticias/modals/_previa): topo da notícia, lista, home e miniatura
  function iniciarNoticia(bloco) {
    const form = bloco.closest('form');
    const valor = nome => form.querySelector('[name="' + nome + '"]')?.value.trim() || '';
    const todos = seletor => bloco.querySelectorAll(seletor);

    function escrever(seletor, texto) {
      todos(seletor).forEach(elemento => { elemento.textContent = limitar(texto, parseInt(elemento.dataset.limite, 10)); });
    }

    function atualizarTextos() {
      const categoria = valor('categoria_noticia') || 'Categoria';
      // datetime-local: "2026-11-09T14:30"
      const [data] = valor('data_publicacao_noticia').split('T');
      const [ano, mes, dia] = data ? data.split('-') : [];

      escrever('.js-previa-titulo', valor('titulo_noticia') || 'Título da notícia');
      escrever('.js-previa-categoria', categoria);
      escrever('.js-previa-autor', valor('autor_noticia') || 'Autor');
      escrever('.js-previa-dia', dia || 'dd');
      escrever('.js-previa-mes', mes ? MESES[parseInt(mes, 10) - 1] : 'mês');
      escrever('.js-previa-data', data ? dia + '/' + mes + '/' + ano : 'dd/mm/aaaa');
      escrever('.js-previa-data-curta', data ? dia + ' ' + MESES[parseInt(mes, 10) - 1] + ', ' + ano : 'dd mês, aaaa');
      escrever('.js-previa-data-longa', data ? dia + ' de ' + MESES[parseInt(mes, 10) - 1] + ', ' + ano : 'dd de mês, aaaa');
    }

    function atualizarImagem() {
      const capa = form.querySelector('.js-campo-imagem')?.fonteDaPrevia || '';

      todos('.js-previa-capa').forEach(imagem => {
        imagem.hidden = !capa;
        if (capa) {
          imagem.src = capa;
        } else {
          imagem.removeAttribute('src');
        }
      });
      todos('.js-previa-fundo').forEach(elemento => { elemento.style.backgroundImage = capa ? 'url("' + capa + '")' : ''; });
      todos('.js-previa-sem-capa').forEach(elemento => { elemento.hidden = Boolean(capa); });
      todos('.js-previa-sem-capa-aviso').forEach(elemento => { elemento.hidden = Boolean(capa); });
    }

    form.addEventListener('input', () => { atualizarTextos(); escalar(bloco); });
    form.addEventListener('imagem:mudou', () => { atualizarImagem(); escalar(bloco); });

    acompanhar(bloco, () => { atualizarTextos(); atualizarImagem(); escalar(bloco); });
  }

  // Foto da galeria (09/11/2026; partial admin/galeria/modals/_previa): quadrado da página Galeria, 4:3 da home e a
  // foto inteira que abre ao clicar
  function iniciarGaleria(bloco) {
    const form = bloco.closest('form');

    function atualizarTexto() {
      const titulo = form.querySelector('[name="titulo_galeria"]')?.value.trim() || '';
      bloco.querySelectorAll('.js-previa-titulo').forEach(elemento => {
        elemento.textContent = titulo;
        elemento.hidden = !titulo;
      });
    }

    function atualizarImagem() {
      const foto = form.querySelector('.js-campo-imagem')?.fonteDaPrevia || '';

      bloco.querySelectorAll('.js-previa-foto').forEach(imagem => {
        imagem.hidden = !foto;
        if (foto) {
          imagem.src = foto;
        } else {
          imagem.removeAttribute('src');
        }
      });
      bloco.querySelectorAll('.js-previa-sem-foto').forEach(elemento => { elemento.hidden = Boolean(foto); });
    }

    form.addEventListener('input', () => { atualizarTexto(); escalar(bloco); });
    form.addEventListener('imagem:mudou', () => { atualizarImagem(); escalar(bloco); });

    acompanhar(bloco, () => { atualizarTexto(); atualizarImagem(); escalar(bloco); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-previa-campeonato').forEach(iniciarCampeonato);
    document.querySelectorAll('.js-previa-banner-home').forEach(iniciarBannerDaHome);
    document.querySelectorAll('.js-previa-noticia').forEach(iniciarNoticia);
    document.querySelectorAll('.js-previa-galeria').forEach(iniciarGaleria);
  });
})();
