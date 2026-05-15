{{-- resources/views/site/parcerias/partners.blade.php --}}

<section class="parcerias-partners section-padding" id="nossos-parceiros">
    <div class="container">

        <div class="section-header text-center">
            <span class="section-label">Quem já está com a gente</span>
            <h2 class="section-title">Nossos <span class="text-primary">Parceiros</span></h2>
            <p class="section-subtitle">
                {{-- TEXTO BASE — substituir pelo texto real --}}
                Empresas e organizações que acreditam no poder transformador do esporte
                e apoiam ativamente a nossa missão.
            </p>
        </div>

        {{-- Filtro de categorias --}}
        <div class="parcerias-partners__filter">
            <button class="parcerias-partners__filter-btn active" data-filter="all">Todos</button>
            <button class="parcerias-partners__filter-btn" data-filter="patrocinador">Patrocinadores</button>
            <button class="parcerias-partners__filter-btn" data-filter="apoiador">Apoiadores</button>
            <button class="parcerias-partners__filter-btn" data-filter="comercial">Parceiros Comerciais</button>
            <button class="parcerias-partners__filter-btn" data-filter="social">Parceiros Sociais</button>
        </div>

        {{-- ──────────────────────────────────────────────────────────────────
             ESTRUTURA PREPARADA PARA PAINEL ADMIN FUTURAMENTE:
             @foreach($parceiros as $parceiro)
                 <div class="parcerias-partners__card" data-type="{{ $parceiro->tipo_parceiro }}">
                     <div class="parcerias-partners__card-logo">
                         <img src="{{ asset('storage/' . $parceiro->logo_parceiro) }}" alt="{{ $parceiro->nome_parceiro }}">
                     </div>
                     <span class="parcerias-partners__card-tag">{{ ucfirst($parceiro->tipo_parceiro) }}</span>
                     <h4 class="parcerias-partners__card-name">{{ $parceiro->nome_parceiro }}</h4>
                     <p class="parcerias-partners__card-desc">{{ $parceiro->descricao_parceiro }}</p>
                 </div>
             @endforeach
        ─────────────────────────────────────────────────────────────────── --}}

        {{-- CARDS PLACEHOLDER — remover quando parceiros reais forem cadastrados --}}
        <div class="parcerias-partners__grid" id="partners-grid">

            {{-- Card placeholder 1 --}}
            <div class="parcerias-partners__card" data-type="patrocinador">
                <div class="parcerias-partners__card-logo">
                    {{-- Bloco cinza logo placeholder --}}
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--patrocinador">Patrocinador</span>
                <h4 class="parcerias-partners__card-name">Nome do Parceiro</h4>
                <p class="parcerias-partners__card-desc">
                    {{-- TEXTO BASE --}}
                    Apoiando jovens talentos e contribuindo para o desenvolvimento esportivo da comunidade.
                </p>
            </div>

            {{-- Card placeholder 2 --}}
            <div class="parcerias-partners__card" data-type="patrocinador">
                <div class="parcerias-partners__card-logo">
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--patrocinador">Patrocinador</span>
                <h4 class="parcerias-partners__card-name">Nome do Parceiro</h4>
                <p class="parcerias-partners__card-desc">
                    Parceiro estratégico na formação de atletas e no fortalecimento da escolinha.
                </p>
            </div>

            {{-- Card placeholder 3 --}}
            <div class="parcerias-partners__card" data-type="apoiador">
                <div class="parcerias-partners__card-logo">
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--apoiador">Apoiador</span>
                <h4 class="parcerias-partners__card-name">Nome do Apoiador</h4>
                <p class="parcerias-partners__card-desc">
                    Apoiando iniciativas sociais e esportivas, acreditando no futebol como ferramenta de transformação.
                </p>
            </div>

            {{-- Card placeholder 4 --}}
            <div class="parcerias-partners__card" data-type="apoiador">
                <div class="parcerias-partners__card-logo">
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--apoiador">Apoiador</span>
                <h4 class="parcerias-partners__card-name">Nome do Apoiador</h4>
                <p class="parcerias-partners__card-desc">
                    Presente em cada conquista, acreditando no potencial de cada atleta da escolinha.
                </p>
            </div>

            {{-- Card placeholder 5 --}}
            <div class="parcerias-partners__card" data-type="comercial">
                <div class="parcerias-partners__card-logo">
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--comercial">Parceiro Comercial</span>
                <h4 class="parcerias-partners__card-name">Nome do Parceiro</h4>
                <p class="parcerias-partners__card-desc">
                    Parceiro comercial que viabiliza recursos, estrutura e condições para o crescimento da escolinha.
                </p>
            </div>

            {{-- Card placeholder 6 --}}
            <div class="parcerias-partners__card" data-type="social">
                <div class="parcerias-partners__card-logo">
                    <div class="parcerias-partners__logo-placeholder"></div>
                </div>
                <span class="parcerias-partners__card-tag parcerias-partners__card-tag--social">Parceiro Social</span>
                <h4 class="parcerias-partners__card-name">Nome do Parceiro</h4>
                <p class="parcerias-partners__card-desc">
                    Parceiro com propósito social, unindo forças para impactar positivamente a vida dos atletas.
                </p>
            </div>

        </div>
        {{-- fim #partners-grid --}}

    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-partners {
    background: #f7f7f7;
}

/* Filtro */
.parcerias-partners__filter {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 36px 0 48px;
}

.parcerias-partners__filter-btn {
    padding: 8px 22px;
    border: 2px solid #ddd;
    background: transparent;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #555;
    cursor: pointer;
    transition: all 0.25s ease;
}

.parcerias-partners__filter-btn:hover,
.parcerias-partners__filter-btn.active {
    background: var(--color-primary, #e63312);
    border-color: var(--color-primary, #e63312);
    color: #fff;
}

/* Grid */
.parcerias-partners__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 28px;
}

/* Card */
.parcerias-partners__card {
    background: #fff;
    border-radius: 10px;
    padding: 28px 24px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
}

.parcerias-partners__card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.10);
}

.parcerias-partners__card-logo {
    margin-bottom: 16px;
}

/* Placeholder logo cinza */
.parcerias-partners__logo-placeholder {
    width: 120px;
    height: 60px;
    background: #d8d8d8;
    border-radius: 4px;
    /* Futuramente: remover e usar <img> real */
}

/* Tags de tipo */
.parcerias-partners__card-tag {
    display: inline-block;
    font-size: 0.70rem;
    font-weight: 700;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    border-radius: 50px;
    padding: 3px 12px;
    margin-bottom: 12px;
    width: fit-content;
}

.parcerias-partners__card-tag--patrocinador {
    background: rgba(230, 51, 18, 0.10);
    color: var(--color-primary, #e63312);
}

.parcerias-partners__card-tag--apoiador {
    background: rgba(30, 100, 200, 0.10);
    color: #1e64c8;
}

.parcerias-partners__card-tag--comercial {
    background: rgba(40, 167, 69, 0.10);
    color: #28a745;
}

.parcerias-partners__card-tag--social {
    background: rgba(255, 140, 0, 0.10);
    color: #ff8c00;
}

.parcerias-partners__card-name {
    font-size: 1rem;
    font-weight: 800;
    color: #111;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.parcerias-partners__card-desc {
    font-size: 0.88rem;
    color: #666;
    line-height: 1.7;
    margin-bottom: 0;
    flex: 1;
}
</style>

{{-- ─── SCRIPT (Filtro simples) ────────────────────────────────────────────── --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterBtns = document.querySelectorAll('.parcerias-partners__filter-btn');
    const cards      = document.querySelectorAll('.parcerias-partners__card');

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.getAttribute('data-filter');

            cards.forEach(function (card) {
                if (filter === 'all' || card.getAttribute('data-type') === filter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>