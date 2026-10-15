{{--
    JS do formulário do jogo (admin.jogos.modals._campos), usado na lista de Jogos e na tela do jogo.
    - Campeonato: "Categoria do jogo" obrigatória, só com as categorias dele (data-categorias; com uma só, ela
      já vem escolhida): vale para a idade e a convocação (Fase H, Etapa H3). Amistoso: "Categoria do
      amistoso", opcional e só exibição, sugerindo a do time mandante quando ainda está vazia.
    - Times: no jogo de campeonato, só os participantes da categoria escolhida (data-participantes da opção;
      sem categoria escolhida, os de todas); no amistoso, ou categoria sem participantes cadastrados, todos. O
      time que o formulário abriu selecionado continua na lista (jogo antigo); o servidor confere de novo
      (JogosController::conferirParticipantes).
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

    // Categorias oferecidas: as do campeonato (a opção vazia vira "— Selecionar —", só de aviso); no amistoso,
    // todas e "Sem categoria"
    function filtrarCategorias(categoria, doCampeonato) {
        categoria.querySelectorAll('option').forEach(opt => {
            if (opt.value === '') {
                opt.textContent = doCampeonato !== null ? '— Selecionar —' : 'Sem categoria';
                opt.hidden = doCampeonato !== null;
                return;
            }
            const fora = doCampeonato !== null && !doCampeonato.includes(Number(opt.value));
            opt.hidden = fora;
            opt.disabled = fora;
        });
        if (doCampeonato !== null && !doCampeonato.includes(Number(categoria.value))) {
            categoria.value = doCampeonato.length === 1 ? String(doCampeonato[0]) : '';
        }
    }

    window.atualizarFormJogo = function (form) {
        const campeonato = form.querySelector('.js-campeonato');
        const amistoso   = campeonato.value === AMISTOSO;
        const bloco      = form.querySelector('.js-bloco-categoria');
        const categoria  = form.querySelector('.js-categoria');
        const dica       = form.querySelector('.js-categoria-campeonato');
        const opcao      = campeonato.selectedOptions[0];
        const doCampeonato = !amistoso && opcao?.dataset.categorias ? JSON.parse(opcao.dataset.categorias) : null;

        bloco.classList.toggle('d-none', campeonato.value === '');
        // Fase só no jogo de campeonato (Fase H, Etapa H4)
        form.querySelector('.js-bloco-fase')?.classList.toggle('d-none', amistoso || campeonato.value === '');
        form.querySelector('.js-rotulo-categoria').textContent = amistoso ? 'Categoria do amistoso' : 'Categoria do jogo';
        form.querySelector('.js-obrigatoria').classList.toggle('d-none', amistoso);
        form.querySelector('.js-ajuda-categoria').textContent = amistoso
            ? 'Só exibição: o amistoso não tem limite de idade.'
            : 'Uma das categorias do campeonato: vale para a idade e a convocação.';
        categoria.required = !amistoso && campeonato.value !== '';
        filtrarCategorias(categoria, doCampeonato);

        dica.textContent = amistoso
            ? "Amistoso: um time da AACJ contra um adversário externo. Os atletas são escolhidos na tela do jogo, depois de salvar."
            : (doCampeonato ? 'Quem joga são os convocados do campeonato na categoria do jogo.' : '');

        // Participantes da categoria escolhida (sem categoria, os de todas); categoria sem participantes: todos
        const porCategoria = doCampeonato && opcao.dataset.participantes ? JSON.parse(opcao.dataset.participantes) : {};
        const times = !doCampeonato ? [] : (categoria.value
            ? (porCategoria[categoria.value] ?? [])
            : [...new Set(Object.values(porCategoria).flat())]);
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
            form.querySelector('.js-categoria').addEventListener('change', () => window.atualizarFormJogo(form));
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
                document.getElementById('edit_fase').value             = this.dataset.fase;
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
