/* ==========================================================================
   SISTEMA DE ACCORDION FLUIDO (FAQ) - CODERATECH
   Calcula a altura real do conteúdo em pixels para uma transição suave.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
    
    // Seleciona todos os gatilhos de clique do FAQ
    const accordionTriggers = document.querySelectorAll('.js-accordion .faq-accordion-trigger');

    accordionTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            const item = trigger.parentElement;
            const content = item.querySelector('.faq-accordion-content');
            
            // Verifica se o item clicado já está aberto
            const isActive = item.classList.contains('is-active');
            
            // --- EFEITO SANFONA ÚNICO ---
            // Fecha todos os outros blocos abertos antes de abrir o novo
            document.querySelectorAll('.js-accordion').forEach(otherItem => {
                otherItem.classList.remove('is-active');
                const otherContent = otherItem.querySelector('.faq-accordion-content');
                if (otherContent) {
                    otherContent.style.height = '0px';
                }
            });
            
            // --- CONTROLE DE ABERTURA FLUIDA ---
            // Se não estava ativo, abre calculando o scrollHeight (altura real interna)
            if (!isActive) {
                item.classList.add('is-active');
                content.style.height = content.scrollHeight + 'px';
            } else {
                // Se já estava ativo, remove a classe e zera a altura
                item.classList.remove('is-active');
                content.style.height = '0px';
            }
        });
    });

});