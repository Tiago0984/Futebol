{{-- resources/views/site/parcerias/banner.blade.php --}}

<section class="parcerias-banner">
    {{-- Bloco cinza simulando imagem de fundo (substituir por img/CSS background real futuramente) --}}
    <div class="parcerias-banner__bg-placeholder"></div>
    <div class="parcerias-banner__overlay"></div>

    <div class="container parcerias-banner__content">
        <span class="parcerias-banner__label">Parcerias & Patrocínios</span>
        <h1 class="parcerias-banner__title">
            Jogue junto com quem <br>
            <strong>transforma vidas</strong> através do esporte.
        </h1>
        <p class="parcerias-banner__subtitle">
            Sua marca ao lado de quem forma atletas, cidadãos e futuros líderes.
        </p>
        <div class="parcerias-banner__actions">
            <a href="#parceria-form" class="btn btn-primary btn-lg parcerias-banner__cta">
                Quero ser parceiro
            </a>
            <a href="#nossos-parceiros" class="btn btn-outline-light btn-lg parcerias-banner__cta-secondary">
                Conheça nossos parceiros
            </a>
        </div>
    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-banner {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    overflow: hidden;
}

/* Placeholder cinza de fundo — remover quando houver imagem real */
.parcerias-banner__bg-placeholder {
    position: absolute;
    inset: 0;
    background: #2a2a2a;
    /* Futuramente: background-image: url('{{ asset("assets/img/parcerias/banner.jpg") }}');
       background-size: cover;
       background-position: center; */
}

.parcerias-banner__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(0, 0, 0, 0.80) 0%,
        rgba(0, 0, 0, 0.50) 60%,
        rgba(0, 0, 0, 0.65) 100%
    );
    z-index: 1;
}

.parcerias-banner__content {
    position: relative;
    z-index: 2;
    padding-top: 120px;
    padding-bottom: 80px;
}

.parcerias-banner__label {
    display: inline-block;
    font-size: 0.80rem;
    font-weight: 700;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--color-primary, #e63312);
    border-left: 3px solid var(--color-primary, #e63312);
    padding-left: 12px;
    margin-bottom: 20px;
}

.parcerias-banner__title {
    font-size: clamp(2.2rem, 5vw, 4rem);
    font-weight: 900;
    color: #ffffff;
    line-height: 1.15;
    text-transform: uppercase;
    margin-bottom: 20px;
    letter-spacing: -0.01em;
}

.parcerias-banner__title strong {
    color: var(--color-primary, #e63312);
}

.parcerias-banner__subtitle {
    font-size: clamp(1rem, 2vw, 1.25rem);
    color: rgba(255, 255, 255, 0.80);
    max-width: 540px;
    margin-bottom: 40px;
    line-height: 1.6;
}

.parcerias-banner__actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.parcerias-banner__cta {
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 14px 36px;
}
</style>