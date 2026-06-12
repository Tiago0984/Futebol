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
    function renderizarPainelAtleta($elementoAtivo) {
        if (!$elementoAtivo.length) return;

        // CORREÇÃO DE ESCOPO: Procura os painéis de atualização apenas dentro da aba onde o botão está inserido
        var $contextoAba = $elementoAtivo.closest(".sport-split-pane");

        var nome = $elementoAtivo.data("nome");
        var foto = $elementoAtivo.data("foto");
        var posicao = $elementoAtivo.data("posicao");
        var camisa = $elementoAtivo.data("camisa");
        var jogos = $elementoAtivo.data("jogos");
        var gols = $elementoAtivo.data("gols");
        var defesas = $elementoAtivo.data("defesas");
        var convocacoes = $elementoAtivo.data("convocacoes");
        var idade = $elementoAtivo.data("idade");
        var peso = $elementoAtivo.data("peso");
        var altura = $elementoAtivo.data("altura");
        var sexo = $elementoAtivo.data("sexo");
        var descricao = $elementoAtivo.data("descricao");

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
        $contextoAba.find(".js-player-idade").text(idade);
        $contextoAba.find(".js-player-peso").text(peso);
        $contextoAba.find(".js-player-altura").text(altura);
        $contextoAba.find(".js-player-sexo").text(sexo);
        $contextoAba.find(".js-player-descricao").text(descricao);

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

    // Quando o modal abrir, força o clique no primeiro titular e no primeiro reserva para carregar as telas
    $(".sport-premium-modal").on("shown.bs.modal", function () {
        var $modal = $(this);

        // Seleciona e clica no primeiro atleta da aba Principal se nenhum estiver ativo
        var $primeiroPrincipal = $modal
            .find(
                "#elencoPrincipal" +
                    $modal.attr("id").replace("modalTime", "") +
                    " .js-atleta-btn",
            )
            .first();
        if ($primeiroPrincipal.length) {
            $primeiroPrincipal.trigger("click");
        }

        // Seleciona e clica no primeiro atleta da aba Reserva
        var $primeiroReserva = $modal
            .find(
                "#elencoReserva" +
                    $modal.attr("id").replace("modalTime", "") +
                    " .js-atleta-btn",
            )
            .first();
        if ($primeiroReserva.length) {
            $primeiroReserva.trigger("click");
        }
    });
});
