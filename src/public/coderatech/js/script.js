/* ==========================================================================
   SMART HEADER — dois estados: completo (topo ou rolando para cima)
   e compacto (rolando para baixo). Barra preta reaparece ao subir.
   ========================================================================== */
(function () {
    var navbar = document.querySelector('.navbar-main');
    if (!navbar) return;

    var lastScrollY = window.scrollY || window.pageYOffset;

    window.addEventListener('scroll', function () {
        var currentY = window.scrollY || window.pageYOffset;

        if (currentY <= 0 || currentY < lastScrollY) {
            navbar.classList.remove('top-hidden');
        } else if (currentY > lastScrollY) {
            navbar.classList.add('top-hidden');
        }

        lastScrollY = currentY;
    }, { passive: true });
})();

/* ==========================================================================
   CONTAGEM REGRESSIVA — próximo evento do calendário
   ========================================================================== */
(function () {
    var el = document.getElementById('cal-days-left');
    if (!el) return;

    var dateStr = el.dataset.date;
    if (!dateStr) return;
    var target  = new Date(dateStr + 'T00:00:00');
    var today   = new Date();
    today.setHours(0, 0, 0, 0);
    var diff    = Math.ceil((target - today) / (1000 * 60 * 60 * 24));
    el.textContent = diff > 0 ? diff : '0';
})();

/* ==========================================================================
   FILTRO DO CALENDÁRIO — página /calendario
   ========================================================================== */
(function () {
    var filterBtns = document.querySelectorAll('.btn-cal-filter');
    if (!filterBtns.length) return;

    var cards     = document.querySelectorAll('.cal-event-card');
    var noResults = document.getElementById('cal-no-results');

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');

            var filter  = this.dataset.filter;
            var visible = 0;

            cards.forEach(function (card) {
                if (filter === 'all' || card.classList.contains('event-' + filter)) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (noResults) {
                noResults.style.display = visible === 0 ? 'flex' : 'none';
            }
        });
    });
})();

/* ==========================================================================
   SELETOR DE CAMPEONATO — seção Jogos & Classificação (home)
   ========================================================================== */
(function () {
    var campCards = document.querySelectorAll('.home-camp-card');
    if (!campCards.length) return;

    campCards.forEach(function (card) {
        card.addEventListener('click', function () {
            var id = this.dataset.campId;
            document.querySelectorAll('.home-camp-card').forEach(function (c) { c.classList.remove('is-active'); });
            document.querySelectorAll('.home-camp-panel').forEach(function (p) { p.classList.remove('is-active'); });
            this.classList.add('is-active');
            var panel = document.querySelector('.home-camp-panel[data-camp-id="' + id + '"]');
            if (panel) panel.classList.add('is-active');
        });
    });

    document.querySelectorAll('.home-camp-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.dataset.target;
            var panel  = this.closest('.home-camp-panel');
            panel.querySelectorAll('.home-camp-tab-btn').forEach(function (b) { b.classList.remove('is-active'); });
            panel.querySelectorAll('.home-camp-tab-panel').forEach(function (p) { p.classList.remove('is-active'); });
            this.classList.add('is-active');
            var tabPanel = document.getElementById(target);
            if (tabPanel) tabPanel.classList.add('is-active');
        });
    });
})();

/* ==========================================================================
   TIMELINE INTERATIVA — seção Nossa História (sobre)
   Caminho base das imagens lido do atributo data-img-base da section,
   evitando misturar Blade/PHP dentro do script.js.
   ========================================================================== */
(function () {
    var section = document.querySelector('.about-history-section');
    if (!section) return;

    var BASE = section.dataset.imgBase || '/futebol/images/sobre/';
    if (BASE.slice(-1) !== '/') BASE += '/';

    var timelineData = {
        '2010': {
            title: 'Fundação — 2010',
            img:   BASE + 'historia-2010.jpg',
            alt:   'Primeiros treinos da AACJ Futebol em campo emprestado — 2010',
            text:  'A <strong>AACJ Futebol</strong> deu seus primeiros passos em 2010, reunindo um grupo de pais, educadores e apaixonados pelo esporte com um sonho simples: dar às crianças da região um espaço seguro para jogar futebol e crescer. Com poucos recursos, mas muita determinação, os primeiros treinos aconteceram em um campo emprestado, e a semente de algo grande foi plantada.'
        },
        '2012': {
            title: 'Primeiros Campeonatos — 2012',
            img:   BASE + 'historia-2012.jpg',
            alt:   'Equipe Sub-13 AACJ na final do torneio municipal — 2012',
            text:  'Dois anos após a fundação, a <strong>AACJ Futebol</strong> disputou sua primeira competição oficial regional. A equipe Sub-13 surpreendeu ao chegar à final do torneio municipal, conquistando o respeito de clubes da região e atraindo novos atletas e parceiros para o projeto.'
        },
        '2015': {
            title: 'Expansão das Categorias — 2015',
            img:   BASE + 'historia-2015.jpg',
            alt:   'Escolinha AACJ com múltiplas categorias treinando juntas — 2015',
            text:  'Com a demanda crescendo, a associação expandiu suas categorias para Sub-9, Sub-11 e Sub-15, estruturando uma escolinha com metodologia própria. Mais de 80 atletas passaram a treinar regularmente, e o projeto social começou a ganhar visibilidade fora da comunidade.'
        },
        '2018': {
            title: 'Nova Estrutura — 2018',
            img:   BASE + 'historia-2018.jpg',
            alt:   'Inauguração do campo próprio da AACJ Futebol — 2018',
            text:  'Em 2018, com o apoio de patrocinadores e da prefeitura local, a <strong>AACJ Futebol</strong> inaugurou seu próprio campo com grama natural, vestiários e arquibancada para a torcida. Um marco que consolidou a associação como referência no desenvolvimento do futebol jovem na região.'
        },
        '2021': {
            title: 'Liga Premiere — 2021',
            img:   BASE + 'historia-2021.jpg',
            alt:   'AACJ Futebol estreando na Liga Premiere — 2021',
            text:  'O ano de 2021 marcou a entrada da <strong>AACJ Futebol</strong> na Liga Premiere, competição de maior prestígio do circuito regional. A participação elevou o nível técnico do clube, inspirou os jovens atletas a treinarem com mais dedicação e abriu portas para parcerias com clubes profissionais da região.'
        },
        '2024': {
            title: 'Crescimento e Futuro — 2024',
            img:   BASE + 'historia-2024.jpg',
            alt:   'Mais de 120 atletas ativos na AACJ Futebol — 2024',
            text:  'Em 2024, a <strong>AACJ Futebol</strong> atingiu a marca de mais de 120 atletas ativos, com programas de acompanhamento nutricional, reforço escolar e apoio psicológico. O clube segue firme no propósito de transformar o esporte em ponte para o futuro — dentro e fora dos campos.'
        }
    };

    var nodes       = document.querySelectorAll('.timeline-node');
    var textContent = document.getElementById('timeline-text-content');
    var timelineImg = document.getElementById('timeline-img');
    var card        = document.querySelector('.timeline-content-card');

    nodes.forEach(function (node) {
        node.addEventListener('click', function () {
            if (this.classList.contains('is-active')) return;

            nodes.forEach(function (n) { n.classList.remove('is-active'); });
            this.classList.add('is-active');

            var year = this.dataset.year;
            var data = timelineData[year];
            if (!data) return;

            card.classList.add('is-fading');
            setTimeout(function () {
                if (textContent) {
                    textContent.querySelector('h3').textContent = data.title;
                    textContent.querySelector('p').innerHTML    = data.text;
                }
                if (timelineImg) {
                    timelineImg.src = data.img;
                    timelineImg.alt = data.alt;
                }
                card.classList.remove('is-fading');
            }, 220);
        });
    });
})();

/* ==========================================================================
   FILTRO DA GALERIA — página /galeria
   ========================================================================== */
(function () {
    var filterBtns = document.querySelectorAll('.btn-gal-filter');
    if (!filterBtns.length) return;

    var items     = document.querySelectorAll('.gal-item');
    var noResults = document.getElementById('gal-no-results');

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');

            var filter  = this.dataset.filter;
            var visible = 0;

            items.forEach(function (item) {
                if (filter === 'all' || item.dataset.categoria === filter) {
                    item.style.display = '';
                    visible++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (noResults) {
                noResults.style.display = visible === 0 ? 'flex' : 'none';
            }
        });
    });
})();

/* ==========================================================================
   SISTEMA DE ACCORDION FLUIDO (FAQ) - CODERATECH
   Calcula a altura real do conteúdo em pixels para uma transição suave.
   ========================================================================== */
document.addEventListener("DOMContentLoaded", () => {
    // Seleciona todos os gatilhos de clique do FAQ
    const accordionTriggers = document.querySelectorAll(
        ".js-accordion .faq-accordion-trigger",
    );

    accordionTriggers.forEach((trigger) => {
        trigger.addEventListener("click", () => {
            const item = trigger.parentElement;
            const content = item.querySelector(".faq-accordion-content");

            // Verifica se o item clicado já está aberto
            const isActive = item.classList.contains("is-active");

            // --- EFEITO SANFONA ÚNICO ---
            // Fecha todos os outros blocos abertos antes de abrir o novo
            document.querySelectorAll(".js-accordion").forEach((otherItem) => {
                otherItem.classList.remove("is-active");
                const otherContent = otherItem.querySelector(
                    ".faq-accordion-content",
                );
                if (otherContent) {
                    otherContent.style.height = "0px";
                }
            });

            // --- CONTROLE DE ABERTURA FLUIDA ---
            // Se não estava ativo, abre calculando o scrollHeight (altura real interna)
            if (!isActive) {
                item.classList.add("is-active");
                content.style.height = content.scrollHeight + "px";
            } else {
                // Se já estava ativo, remove a classe e zera a altura
                item.classList.remove("is-active");
                content.style.height = "0px";
            }
        });
    });
});

$(document).ready(function () {
    // Armazena o ID do atleta que deve ser pré-selecionado quando o modal de time abrir.
    // Usado pelo handler shown.bs.modal para dar prioridade ao atleta vindo do badge
    // em vez de sempre auto-selecionar o primeiro da lista.
    var _atletaPendenteNoModalTime = null;

    function renderizarPainelAtleta($elementoAtivo) {
        if (!$elementoAtivo.length) return;

        // CORREÇÃO DE ESCOPO: Procura os painéis de atualização apenas dentro da aba onde o botão está inserido
        var $contextoAba = $elementoAtivo.closest(".sport-split-pane");

        // Captura o ID do atleta para atualizar o botão "ver informações completas"
        var atletaId = $elementoAtivo.data("atleta-id");
        var nome = $elementoAtivo.data("nome");
        var foto = $elementoAtivo.data("foto");
        var posicao = $elementoAtivo.data("posicao");
        var camisa = $elementoAtivo.data("camisa");
        var jogos = $elementoAtivo.data("jogos");
        var gols = $elementoAtivo.data("gols");
        var defesas = $elementoAtivo.data("defesas");
        var convocacoes = $elementoAtivo.data("convocacoes");
        // Cartões referentes apenas ao vínculo deste time (filtrados no Blade via tbl_cartoes + tbl_jogos)
        var amarelos = $elementoAtivo.data("amarelos") || 0;
        var vermelhos = $elementoAtivo.data("vermelhos") || 0;
        // Idade, peso, altura, sexo e descrição foram removidos do modal de time —
        // essas informações agora são exclusivas do card detalhado na página /jogadores.

        // Alimenta o painel correto do contexto
        $contextoAba.find(".js-player-nome").text(nome);
        $contextoAba
            .find(".js-player-foto")
            .attr("src", foto)
            .attr("alt", nome);
        $contextoAba.find(".js-player-posicao").text(posicao);
        $contextoAba.find(".js-player-camisa").text("#" + camisa);
        $contextoAba.find(".js-player-jogos").text(jogos);
        $contextoAba.find(".js-player-convocacoes").text(convocacoes);
        $contextoAba.find(".js-player-gols").text(gols);
        $contextoAba.find(".js-player-defesas").text(defesas);
        $contextoAba.find(".js-player-amarelos").text(amarelos);
        $contextoAba.find(".js-player-vermelhos").text(vermelhos);

        // Atualiza o data-atleta-id do botão "ver informações completas" com o atleta recém-selecionado,
        // permitindo que o JS saiba qual modal de atleta abrir ao clicar no botão.
        $contextoAba.find(".js-ver-atleta-completo").attr("data-atleta-id", atletaId);

        // Ocultar/Exibir gols ou defesas de forma inteligente
        var termoPosicao = String(posicao).toLowerCase();
        if (
            termoPosicao.includes("gol") ||
            termoPosicao.includes("gk") ||
            termoPosicao.includes("goleiro")
        ) {
            // Goleiros mostram defesas E gols
            $contextoAba.find(".js-box-defesas").show();
            $contextoAba.find(".js-box-gols").show();
        } else {
            $contextoAba.find(".js-box-defesas").hide();
            $contextoAba.find(".js-box-gols").show();
        }
    }

    // Evento ao clicar no jogador
    $(document).on("click", ".js-atleta-btn", function () {
        var $linhaSelecionada = $(this);

        // Remove a classe active apenas dos botões que estão na mesma lista/aba
        $linhaSelecionada
            .closest(".sport-sidebar-scroll")
            .find(".js-atleta-btn")
            .removeClass("active");
        $linhaSelecionada.addClass("active");

        renderizarPainelAtleta($linhaSelecionada);
    });

    // Quando o modal de time abrir, pré-seleciona o primeiro atleta de cada aba.
    // Se _atletaPendenteNoModalTime estiver preenchido (badge do jogador foi clicado),
    // usa o ID desse atleta em vez do primeiro da lista e limpa a flag.
    // Usa delegação de evento para funcionar tanto na home quanto na página /jogadores.
    $(document).on("shown.bs.modal", ".sport-premium-modal", function () {
        var $modal = $(this);
        var timeId = $modal.attr("id").replace("modalTime", "");

        if (_atletaPendenteNoModalTime) {
            // Atleta específico vindo do badge na página /jogadores — pré-seleciona ele
            var atletaIdPendente = _atletaPendenteNoModalTime;
            _atletaPendenteNoModalTime = null; // limpa imediatamente para não vazar para próximos modais

            var $btnAlvo = $modal
                .find(".js-atleta-btn[data-atleta-id='" + atletaIdPendente + "']")
                .first();
            if ($btnAlvo.length) {
                $btnAlvo.trigger("click");
            }

            // Ainda popula a aba Reserva com o primeiro atleta para não deixar o painel vazio
            var $primeiroReserva = $modal
                .find("#elencoReserva" + timeId + " .js-atleta-btn")
                .first();
            if ($primeiroReserva.length) $primeiroReserva.trigger("click");

            return;
        }

        // Comportamento padrão (home ou badge sem atleta pendente): primeiro de cada aba
        var $primeiroPrincipal = $modal
            .find("#elencoPrincipal" + timeId + " .js-atleta-btn")
            .first();
        if ($primeiroPrincipal.length) $primeiroPrincipal.trigger("click");

        var $primeiroReserva = $modal
            .find("#elencoReserva" + timeId + " .js-atleta-btn")
            .first();
        if ($primeiroReserva.length) $primeiroReserva.trigger("click");
    });

    // Badge de time clicável no card do jogador (página /jogadores):
    // Define o atleta pendente, fecha o modal do atleta e abre o modal de elenco do time.
    // O handler shown.bs.modal acima lê _atletaPendenteNoModalTime e pré-seleciona o atleta,
    // resolvendo o conflito de ordem de execução entre handlers no mesmo evento.
    // e.stopPropagation() evita que o clique propague para o article-pai.
    $(document).on("click", ".js-open-team-from-player", function (e) {
        e.stopPropagation();
        var timeId = $(this).data("time-id");
        var atletaId = $(this).data("atleta-id");
        var $playerModal = $(this).closest(".modal");

        // Espera o modal do atleta fechar ANTES de abrir o modal do time,
        // evitando conflito de modais simultâneos no Bootstrap 3.
        $playerModal.one("hidden.bs.modal", function () {
            var $teamModal = $("#modalTime" + timeId);

            // Define o atleta pendente ANTES de abrir o modal,
            // para o handler shown.bs.modal saber qual selecionar.
            _atletaPendenteNoModalTime = atletaId;
            $teamModal.modal("show");
        });

        $playerModal.modal("hide");
    });

    // ---------------------------------------------------------------
    // FILTRO CLIENT-SIDE — página /jogadores
    // Só inicializa se os elementos de filtro existirem no DOM
    // (evita rodar em outras páginas onde o script também é carregado).
    // Os data-* dos cards são escritos pelo grid-atletas.blade.php.
    // ---------------------------------------------------------------
    if ($('#filter-nome').length) {

        function filtrarAtletas() {
            var nome    = $('#filter-nome').val().toLowerCase().trim();
            var posicao = $('#filter-posicao').val().toLowerCase().trim();
            var timeId  = String($('#filter-time').val()).trim();
            var count   = 0;

            $('.player-sticker-card').each(function () {
                var $card       = $(this);
                var cardNome    = String($card.data('nome')    || '');
                var cardPosicao = String($card.data('posicao') || '');
                // data-times é uma string "1,2,3" com os IDs dos times do atleta
                var cardTimes   = String($card.data('times')   || '');

                var matchNome    = !nome    || cardNome.indexOf(nome) !== -1;
                var matchPosicao = !posicao || cardPosicao === posicao;
                // Verifica se o ID do time selecionado está na lista de times do card
                var matchTime    = !timeId  || cardTimes.split(',').indexOf(timeId) !== -1;

                if (matchNome && matchPosicao && matchTime) {
                    $card.show();
                    count++;
                } else {
                    $card.hide();
                }
            });

            // Exibe mensagem quando nenhum card passa nos filtros
            if (count === 0) {
                $('.js-no-results-filter').show();
            } else {
                $('.js-no-results-filter').hide();
            }
        }

        // Dispara filtro a cada caractere digitado no campo nome
        $('#filter-nome').on('input', filtrarAtletas);

        // Dispara filtro ao mudar posição ou clube (sem reload)
        $('#filter-posicao').on('change', filtrarAtletas);
        $('#filter-time').on('change', filtrarAtletas);

        // Limpar Filtros: zera os campos e re-filtra sem reload nem scroll para o topo
        $('#btn-limpar-filtros').on('click', function () {
            $('#filter-nome').val('');
            $('#filter-posicao').val('');
            $('#filter-time').val('');
            filtrarAtletas();
        });
    }

    // Auto-abre o modal do atleta quando a página /jogadores é acessada via ?open=ID
    // (vindo do botão "ver informações completas" no modal de time da home).
    // A variável global _autoOpenAtletaId é definida no index.blade.php com JS puro
    // antes do jQuery carregar, e aqui a consumimos quando o Bootstrap já está disponível.
    if (window._autoOpenAtletaId) {
        var $autoModal = $("#modalAtleta" + window._autoOpenAtletaId);
        if ($autoModal.length) {
            $autoModal.modal("show");
        }
    }

    // Botão "ver informações completas sobre esse atleta..." dentro do modal de time:
    // Na página /jogadores: fecha o modal de time e abre o modal detalhado do atleta.
    // Na home (onde os modais de atleta não existem): navega para a página /jogadores.
    $(document).on("click", ".js-ver-atleta-completo", function (e) {
        e.preventDefault();
        var atletaId = $(this).attr("data-atleta-id");
        var $playerDetailModal = $("#modalAtleta" + atletaId);

        if ($playerDetailModal.length) {
            // Página /jogadores: modal detalhado do atleta existe no DOM
            var $teamModal = $(this).closest(".sport-premium-modal");
            $teamModal.one("hidden.bs.modal", function () {
                $playerDetailModal.modal("show");
            });
            $teamModal.modal("hide");
        } else {
            // Home: sem modal de atleta — navega para a vitrine de jogadores
            // Passa o ID do atleta via querystring (?open=ID) para que a página
            // de jogadores abra automaticamente o modal do atleta correto.
            window.location.href = $(this).attr("href") + "?open=" + atletaId;
        }
    });
});
