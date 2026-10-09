{{--
    JS do formulário do jogo (admin.jogos.modals._campos), usado na lista de Jogos e na tela do jogo.
    - Campeonato: mostra a categoria dele (só exibição); Amistoso: mostra o select de categoria, sugerindo a
      do time mandante quando ainda está vazio.
    - Times: no jogo de campeonato, só os participantes (data-times da opção); no amistoso, ou campeonato
      sem participantes cadastrados, todos. O time que o formulário abriu selecionado continua na lista
      (jogo antigo); o servidor confere de novo (JogosController::conferirParticipantes).
    window.atualizarFormJogo(form) refaz tudo (chamado depois de preencher o modal de edição).
    - Botão Editar das ações do jogo (admin.jogos._acoes): preenche o modal #modalEditarJogo.
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

        // Botão Editar das ações do jogo (admin.jogos._acoes): preenche o modal de edição com os dados do botão
        document.querySelectorAll('.btn-editar-jogo').forEach(btn => {
            btn.addEventListener('click', function () {
                const f = document.getElementById('formEditarJogo');
                f.action = `{{ url('admin/jogos') }}/${this.dataset.id}`;
                document.getElementById('edit_id_campeonato').value    = this.dataset.campeonato;
                document.getElementById('edit_id_categoria').value     = this.dataset.categoria;
                document.getElementById('edit_id_time_casa').value     = this.dataset.casa;
                document.getElementById('edit_id_time_visitante').value = this.dataset.visitante;
                document.getElementById('edit_data').value             = this.dataset.data;
                document.getElementById('edit_inicio').value           = this.dataset.inicio;
                document.getElementById('edit_fim').value              = this.dataset.fim;
                document.getElementById('edit_local').value            = this.dataset.local;
                document.getElementById('edit_placar_casa').value      = this.dataset.placarCasa;
                document.getElementById('edit_placar_visitante').value = this.dataset.placarVisitante;
                // Times com que o modal abriu ficam na lista mesmo fora dos participantes (jogo antigo)
                window.guardarTimesIniciais(f.querySelector('.form-jogo'));
                window.atualizarFormJogo(f.querySelector('.form-jogo'));
            });
        });
    });
})();
</script>
