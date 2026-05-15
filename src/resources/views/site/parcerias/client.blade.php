{{-- resources/views/site/parcerias/client.blade.php --}}

<section class="parcerias-client section-padding-sm">
    <div class="container">

        <p class="parcerias-client__label">Marcas que confiam na escolinha</p>

        {{-- Carrossel infinito de logos --}}
        <div class="parcerias-client__track-wrap">
            <div class="parcerias-client__track">

                {{-- LOGOS PLACEHOLDER — substituir pelos logos reais futuramente --}}
                {{-- Futuramente: @foreach($parceiros as $parceiro)
                        <div class="parcerias-client__item">
                            <img src="{{ asset('storage/' . $parceiro->logo_parceiro) }}" alt="{{ $parceiro->nome_parceiro }}">
                        </div>
                     @endforeach --}}

                {{-- Grupo 1 (original) --}}
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>

                {{-- Grupo 2 (clone para loop infinito) --}}
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>
                <div class="parcerias-client__item" aria-hidden="true">
                    <div class="parcerias-client__logo-placeholder">Logo Parceiro</div>
                </div>

            </div>
        </div>

    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-client {
    background: #f7f7f7;
    border-top: 1px solid #ebebeb;
    border-bottom: 1px solid #ebebeb;
}

.section-padding-sm { padding: 48px 0; }

.parcerias-client__label {
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #aaa;
    margin-bottom: 32px;
}

/* Carrossel infinito via CSS animation */
.parcerias-client__track-wrap {
    overflow: hidden;
    position: relative;
}

.parcerias-client__track-wrap::before,
.parcerias-client__track-wrap::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 80px;
    z-index: 2;
    pointer-events: none;
}

.parcerias-client__track-wrap::before {
    left: 0;
    background: linear-gradient(to right, #f7f7f7, transparent);
}

.parcerias-client__track-wrap::after {
    right: 0;
    background: linear-gradient(to left, #f7f7f7, transparent);
}

.parcerias-client__track {
    display: flex;
    gap: 40px;
    animation: client-scroll 22s linear infinite;
    width: max-content;
}

.parcerias-client__track:hover {
    animation-play-state: paused;
}

@keyframes client-scroll {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.parcerias-client__item {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.55;
    transition: opacity 0.3s ease;
}

.parcerias-client__item:hover {
    opacity: 1;
}

/* Placeholder de logo */
.parcerias-client__logo-placeholder {
    width: 140px;
    height: 56px;
    background: #d0d0d0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    color: #999;
    font-weight: 600;
    /* Futuramente: remover e usar <img> real */
}
</style>