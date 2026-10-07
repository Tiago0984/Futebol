{{--
    JS do formulário do jogo (admin.jogos.modals._campos), usado na lista de Jogos e na tela do jogo.
    - Campeonato: mostra a categoria dele (só exibição); Amistoso: mostra o select de categoria, sugerindo a
      do time mandante quando ainda está vazio.
    - Times: no jogo de campeonato, só os participantes (data-times da opção); no amistoso, ou campeonato
      sem participantes cadastrados, todos. O time que o formulário abriu selecionado continua na lista
      (jogo antigo); o servidor confere de novo (JogosController::conferirParticipantes).
    window.atualizarFormJogo(form) refaz tudo (a lista chama depois de preencher o modal de edição).
--}}
<script>
(function () {
    const AMISTOSO = @js(\App\Http\Controllers\Admin\JogosController::AMISTOSO);

    function filtrarTimes(form, participantes) {
        form.querySelectorAll('.js-time-casa, .js-time-visitante').forEach(sel => {
            const inicial = sel.dataset.inicial ?? '';
            sel.querySelectorAll('option[value]:not([value=""])').forEach(opt => {
                const fora = participantes !== null && !participantes.includes(Number(opt.value)) && opt.value !== inicial;
                opt.hidden = fora;
                opt.disabled = fora;
            });
            if (sel.selectedOptions[0]?.disabled) sel.value = '';
        });
    }

    window.atualizarFormJogo = function (form) {
        const campeonato = form.querySelector('.js-campeonato');
        const amistoso   = campeonato.value === AMISTOSO;
        const bloco      = form.querySelector('.js-bloco-categoria');
        const categoria  = form.querySelector('.js-categoria');
        const dica       = form.querySelector('.js-categoria-campeonato');
        const opcao      = campeonato.selectedOptions[0];

        bloco.classList.toggle('d-none', !amistoso);
        const rotulo = opcao?.dataset.categoria;
        dica.textContent = !amistoso && rotulo ? `Categoria do campeonato: ${rotulo} (só exibição; quem joga é o elenco dos times).` : '';

        const times = !amistoso && opcao?.dataset.times ? JSON.parse(opcao.dataset.times) : [];
        const participantes = times.length ? times : null;
        filtrarTimes(form, participantes);
        form.querySelector('.js-aviso-participantes')?.classList.toggle('d-none', participantes === null);

        const casa = form.querySelector('.js-time-casa').selectedOptions[0];
        if (amistoso && !categoria.value && casa?.dataset.categoria) {
            categoria.value = casa.dataset.categoria;
        }
    };

    // Guarda os times com que o formulário abriu (o modal de edição da lista chama de novo ao preencher)
    window.guardarTimesIniciais = function (form) {
        form.querySelectorAll('.js-time-casa, .js-time-visitante').forEach(sel => sel.dataset.inicial = sel.value);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.form-jogo').forEach(form => {
            window.guardarTimesIniciais(form);
            form.querySelector('.js-campeonato').addEventListener('change', () => window.atualizarFormJogo(form));
            form.querySelector('.js-time-casa').addEventListener('change', () => window.atualizarFormJogo(form));
            window.atualizarFormJogo(form);
        });
    });
})();
</script>
