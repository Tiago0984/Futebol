{{-- resources/views/site/parcerias/benefits.blade.php --}}

<section class="parcerias-benefits section-padding">
    <div class="container">

        <div class="section-header text-center">
            <span class="section-label">Por que ser parceiro?</span>
            <h2 class="section-title">Os <span class="text-primary">benefícios</span> de estar conosco</h2>
            <p class="section-subtitle">
                {{-- TEXTO BASE — substituir pelo texto real --}}
                Ser parceiro da escolinha vai muito além de uma exposição de marca.
                É pertencer a uma causa que move pessoas, famílias e comunidades.
            </p>
        </div>

        <div class="parcerias-benefits__grid">

            {{-- Benefício 1 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Visibilidade de Marca</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Sua marca presente em uniformes, eventos, redes sociais e materiais
                    de comunicação da escolinha, alcançando toda a comunidade esportiva.
                </p>
            </div>

            {{-- Benefício 2 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Impacto Social Real</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Cada parceria financia oportunidades reais para crianças e jovens
                    que encontram no futebol um caminho de crescimento e cidadania.
                </p>
            </div>

            {{-- Benefício 3 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Presença em Eventos</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Participação ativa em campeonatos, torneios e festividades esportivas
                    com grande mobilização de público e mídia local.
                </p>
            </div>

            {{-- Benefício 4 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Fortalecimento Comunitário</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Associe sua marca a valores como disciplina, trabalho em equipe,
                    inclusão e desenvolvimento humano que o esporte promove.
                </p>
            </div>

            {{-- Benefício 5 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-star"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Reconhecimento e Autoridade</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Seja reconhecido como uma empresa que investe nas pessoas,
                    no esporte e no futuro da comunidade local.
                </p>
            </div>

            {{-- Benefício 6 --}}
            <div class="parcerias-benefits__card">
                <div class="parcerias-benefits__icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h4 class="parcerias-benefits__card-title">Relacionamento Duradouro</h4>
                <p class="parcerias-benefits__card-text">
                    {{-- TEXTO BASE --}}
                    Mais que uma parceria pontual, construímos relações sólidas,
                    transparentes e baseadas em propósito compartilhado.
                </p>
            </div>

        </div>

    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-benefits {
    background: #111;
}

.parcerias-benefits .section-label {
    color: var(--color-primary, #e63312);
    opacity: 1;
}

.parcerias-benefits .section-title {
    color: #ffffff;
}

.parcerias-benefits .section-subtitle {
    color: rgba(255,255,255,0.60);
}

.parcerias-benefits__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-top: 48px;
}

.parcerias-benefits__card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 32px 28px;
    transition: background 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
}

.parcerias-benefits__card:hover {
    background: rgba(255,255,255,0.07);
    border-color: var(--color-primary, #e63312);
    transform: translateY(-4px);
}

.parcerias-benefits__icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--color-primary, #e63312);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.parcerias-benefits__icon i {
    font-size: 1.3rem;
    color: #fff;
}

.parcerias-benefits__card-title {
    font-size: 1rem;
    font-weight: 800;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 12px;
}

.parcerias-benefits__card-text {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.60);
    line-height: 1.75;
    margin-bottom: 0;
}
</style>