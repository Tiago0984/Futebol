{{-- resources/views/site/parcerias/call-to-action.blade.php --}}

<section class="parcerias-cta section-padding">
    {{-- Bloco cinza placeholder de fundo — substituir por imagem/vídeo real futuramente --}}
    <div class="parcerias-cta__bg-placeholder"></div>
    <div class="parcerias-cta__overlay"></div>

    <div class="container parcerias-cta__content">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">

                <span class="section-label parcerias-cta__label">Faça parte desta história</span>

                <h2 class="parcerias-cta__title">
                    Sua empresa pode fazer parte da<br>
                    <strong>formação de futuros atletas</strong> e cidadãos.
                </h2>

                <p class="parcerias-cta__text">
                    {{-- TEXTO BASE — substituir pelo texto real --}}
                    Cada parceria é uma semente plantada no futuro. Juntos, construímos
                    não só campeões dentro do campo, mas pessoas melhores para o mundo.
                </p>

                <div class="parcerias-cta__actions">
                    <a href="#parceria-form" class="btn btn-primary btn-lg parcerias-cta__btn">
                        <i class="fas fa-handshake me-2"></i>
                        Quero ser parceiro
                    </a>
                    {{-- Contato direto --}}
                    <a href="https://wa.me/5500000000000" target="_blank" class="btn btn-outline-light btn-lg parcerias-cta__btn">
                        {{-- SUBSTITUIR: trocar número do WhatsApp pelo real --}}
                        <i class="fab fa-whatsapp me-2"></i>
                        Falar no WhatsApp
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-cta {
    position: relative;
    overflow: hidden;
    text-align: center;
}

/* Placeholder fundo cinza — remover quando houver imagem real */
.parcerias-cta__bg-placeholder {
    position: absolute;
    inset: 0;
    background: #1a1a1a;
    /* Futuramente: background-image: url(asset('assets/img/parcerias/cta-bg.jpg'));
       background-size: cover;
       background-position: center;
       background-attachment: fixed; */
}

.parcerias-cta__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(230, 51, 18, 0.75) 0%,
        rgba(0, 0, 0, 0.82) 100%
    );
    z-index: 1;
}

.parcerias-cta__content {
    position: relative;
    z-index: 2;
}

.parcerias-cta__label {
    color: rgba(255,255,255,0.75) !important;
    border-color: rgba(255,255,255,0.50) !important;
}

.parcerias-cta__title {
    font-size: clamp(1.8rem, 3.5vw, 3rem);
    font-weight: 900;
    color: #fff;
    text-transform: uppercase;
    line-height: 1.2;
    margin: 20px 0 24px;
    letter-spacing: -0.01em;
}

.parcerias-cta__title strong {
    color: #fff;
    text-decoration: underline;
    text-decoration-color: rgba(255,255,255,0.35);
    text-underline-offset: 6px;
}

.parcerias-cta__text {
    color: rgba(255,255,255,0.75);
    font-size: 1.05rem;
    line-height: 1.75;
    max-width: 580px;
    margin: 0 auto 40px;
}

.parcerias-cta__actions {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.parcerias-cta__btn {
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 14px 36px;
}
</style>