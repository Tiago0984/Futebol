{{-- resources/views/site/parcerias/about.blade.php --}}

<section class="parcerias-about section-padding">
    <div class="container">
        <div class="row align-items-center g-5">

            {{-- Lado esquerdo: texto institucional --}}
            <div class="col-lg-6">
                <span class="section-label">Sobre as Parcerias</span>
                <h2 class="parcerias-about__title">
                    Mais do que uma parceria, <br>
                    <span class="text-primary">um compromisso com o futuro.</span>
                </h2>
                <p class="parcerias-about__text">
                    {{-- TEXTO BASE — substituir pelo texto real da escolinha --}}
                    Nossos parceiros fazem parte direta do crescimento da escolinha e contribuem com o
                    desenvolvimento esportivo, social e educacional dos nossos atletas. Cada apoio recebido
                    é revertido em estrutura, oportunidade e sonhos realizados dentro e fora do campo.
                </p>
                <p class="parcerias-about__text">
                    {{-- TEXTO BASE — substituir pelo texto real da escolinha --}}
                    Acreditamos que o esporte transforma vidas, e nossos parceiros acreditam nisso também.
                    Juntos, construímos uma comunidade mais forte, unida e cheia de propósito.
                </p>
                <div class="parcerias-about__stats">
                    <div class="parcerias-about__stat">
                        {{-- NÚMERO BASE — substituir pelo número real --}}
                        <span class="parcerias-about__stat-number">00+</span>
                        <span class="parcerias-about__stat-label">Parceiros ativos</span>
                    </div>
                    <div class="parcerias-about__stat">
                        {{-- NÚMERO BASE — substituir pelo número real --}}
                        <span class="parcerias-about__stat-number">000+</span>
                        <span class="parcerias-about__stat-label">Atletas beneficiados</span>
                    </div>
                    <div class="parcerias-about__stat">
                        {{-- NÚMERO BASE — substituir pelo número real --}}
                        <span class="parcerias-about__stat-number">00+</span>
                        <span class="parcerias-about__stat-label">Anos de parceria</span>
                    </div>
                </div>
            </div>

            {{-- Lado direito: placeholder de imagem --}}
            <div class="col-lg-6">
                <div class="parcerias-about__img-wrap">
                    {{-- Bloco cinza placeholder — substituir por <img> real futuramente --}}
                    <div class="parcerias-about__img-placeholder">
                        <span>Imagem ilustrativa<br><small>Substituir por imagem real</small></span>
                    </div>
                    {{-- Destaque flutuante --}}
                    <div class="parcerias-about__badge">
                        <i class="fas fa-trophy"></i>
                        <span>Parceiros que <strong>acreditam</strong> no esporte</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-about {
    background: #ffffff;
}

.parcerias-about__title {
    font-size: clamp(1.6rem, 3vw, 2.4rem);
    font-weight: 900;
    color: #111;
    text-transform: uppercase;
    line-height: 1.2;
    margin-bottom: 24px;
    letter-spacing: -0.01em;
}

.parcerias-about__text {
    color: #555;
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 16px;
}

.parcerias-about__stats {
    display: flex;
    gap: 32px;
    margin-top: 36px;
    padding-top: 28px;
    border-top: 1px solid #eee;
    flex-wrap: wrap;
}

.parcerias-about__stat {
    display: flex;
    flex-direction: column;
}

.parcerias-about__stat-number {
    font-size: 2rem;
    font-weight: 900;
    color: var(--color-primary, #e63312);
    line-height: 1;
}

.parcerias-about__stat-label {
    font-size: 0.78rem;
    color: #777;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-top: 4px;
}

/* Placeholder de imagem */
.parcerias-about__img-wrap {
    position: relative;
}

.parcerias-about__img-placeholder {
    width: 100%;
    aspect-ratio: 4 / 3;
    background: #d0d0d0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #888;
    font-size: 0.9rem;
    /* Futuramente: remover este bloco e usar <img src="..." class="img-fluid rounded"> */
}

.parcerias-about__badge {
    position: absolute;
    bottom: -20px;
    left: -20px;
    background: var(--color-primary, #e63312);
    color: #fff;
    padding: 16px 22px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    max-width: 240px;
}

.parcerias-about__badge i {
    font-size: 1.4rem;
    flex-shrink: 0;
}
</style>