{{-- resources/views/site/parcerias/form.blade.php --}}

<section class="parcerias-form section-padding" id="parceria-form">
    <div class="container">
        <div class="row align-items-center g-5">

            {{-- Lado esquerdo: chamada textual --}}
            <div class="col-lg-5">
                <span class="section-label">Seja um parceiro</span>
                <h2 class="parcerias-form__title">
                    Dê o <strong>primeiro passo.</strong><br>
                    A gente cuida do resto.
                </h2>
                <p class="parcerias-form__text">
                    {{-- TEXTO BASE — substituir pelo texto real --}}
                    Preencha o formulário ao lado e nossa equipe entrará em contato
                    para apresentar as modalidades de parceria, benefícios e possibilidades
                    de colaboração. É rápido, simples e pode mudar muitas vidas.
                </p>

                <div class="parcerias-form__info">
                    <div class="parcerias-form__info-item">
                        <i class="fas fa-envelope"></i>
                        {{-- SUBSTITUIR: trocar pelo e-mail real --}}
                        <span>parcerias@escolinha.com.br</span>
                    </div>
                    <div class="parcerias-form__info-item">
                        <i class="fab fa-whatsapp"></i>
                        {{-- SUBSTITUIR: trocar pelo WhatsApp real --}}
                        <span>(00) 00000-0000</span>
                    </div>
                    <div class="parcerias-form__info-item">
                        <i class="fas fa-clock"></i>
                        {{-- SUBSTITUIR: trocar pelo horário real --}}
                        <span>Respondemos em até 24 horas úteis</span>
                    </div>
                </div>
            </div>

            {{-- Lado direito: formulário --}}
            <div class="col-lg-7">
                <div class="parcerias-form__box">

                    <div class="parcerias-form__box-header">
                        <i class="fas fa-handshake"></i>
                        <span>Quero ser parceiro</span>
                    </div>

                    {{-- Mensagem de sucesso (oculta por padrão) --}}
                    @if(session('success_parceria'))
                        <div class="alert alert-success">
                            {{ session('success_parceria') }}
                        </div>
                    @endif

                    <form action="{{ route('parcerias.form') }}" method="POST" class="parcerias-form__form">
                        @csrf

                        <div class="row g-3">

                            {{-- Nome --}}
                            <div class="col-sm-6">
                                <div class="parcerias-form__field">
                                    <label for="nome">Nome completo <span>*</span></label>
                                    <div class="parcerias-form__input-wrap">
                                        <i class="fas fa-user"></i>
                                        <input
                                            type="text"
                                            id="nome"
                                            name="nome"
                                            placeholder="Seu nome"
                                            required
                                            value="{{ old('nome') }}"
                                        >
                                    </div>
                                    @error('nome')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Empresa --}}
                            <div class="col-sm-6">
                                <div class="parcerias-form__field">
                                    <label for="empresa">Empresa / Organização <span>*</span></label>
                                    <div class="parcerias-form__input-wrap">
                                        <i class="fas fa-building"></i>
                                        <input
                                            type="text"
                                            id="empresa"
                                            name="empresa"
                                            placeholder="Nome da empresa"
                                            required
                                            value="{{ old('empresa') }}"
                                        >
                                    </div>
                                    @error('empresa')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- E-mail --}}
                            <div class="col-sm-6">
                                <div class="parcerias-form__field">
                                    <label for="email">E-mail <span>*</span></label>
                                    <div class="parcerias-form__input-wrap">
                                        <i class="fas fa-envelope"></i>
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            placeholder="seu@email.com"
                                            required
                                            value="{{ old('email') }}"
                                        >
                                    </div>
                                    @error('email')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Telefone --}}
                            <div class="col-sm-6">
                                <div class="parcerias-form__field">
                                    <label for="telefone">Telefone / WhatsApp <span>*</span></label>
                                    <div class="parcerias-form__input-wrap">
                                        <i class="fas fa-phone"></i>
                                        <input
                                            type="tel"
                                            id="telefone"
                                            name="telefone"
                                            placeholder="(00) 00000-0000"
                                            required
                                            value="{{ old('telefone') }}"
                                        >
                                    </div>
                                    @error('telefone')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Tipo de parceria --}}
                            <div class="col-12">
                                <div class="parcerias-form__field">
                                    <label>Tipo de parceria desejada <span>*</span></label>
                                    <div class="parcerias-form__tipo-grid">
                                        <label class="parcerias-form__tipo-option">
                                            <input type="radio" name="tipo_parceria" value="patrocinador" {{ old('tipo_parceria') == 'patrocinador' ? 'checked' : '' }} required>
                                            <span>
                                                <i class="fas fa-trophy"></i>
                                                Patrocinador
                                            </span>
                                        </label>
                                        <label class="parcerias-form__tipo-option">
                                            <input type="radio" name="tipo_parceria" value="apoiador" {{ old('tipo_parceria') == 'apoiador' ? 'checked' : '' }}>
                                            <span>
                                                <i class="fas fa-heart"></i>
                                                Apoiador
                                            </span>
                                        </label>
                                        <label class="parcerias-form__tipo-option">
                                            <input type="radio" name="tipo_parceria" value="comercial" {{ old('tipo_parceria') == 'comercial' ? 'checked' : '' }}>
                                            <span>
                                                <i class="fas fa-briefcase"></i>
                                                Parceiro Comercial
                                            </span>
                                        </label>
                                        <label class="parcerias-form__tipo-option">
                                            <input type="radio" name="tipo_parceria" value="social" {{ old('tipo_parceria') == 'social' ? 'checked' : '' }}>
                                            <span>
                                                <i class="fas fa-users"></i>
                                                Parceiro Social
                                            </span>
                                        </label>
                                    </div>
                                    @error('tipo_parceria')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Mensagem --}}
                            <div class="col-12">
                                <div class="parcerias-form__field">
                                    <label for="mensagem">Mensagem</label>
                                    <div class="parcerias-form__input-wrap parcerias-form__input-wrap--textarea">
                                        <i class="fas fa-comment-dots"></i>
                                        <textarea
                                            id="mensagem"
                                            name="mensagem"
                                            rows="4"
                                            placeholder="Conte um pouco sobre sua empresa e como imagina a parceria..."
                                        >{{ old('mensagem') }}</textarea>
                                    </div>
                                    @error('mensagem')
                                        <span class="parcerias-form__error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Submit --}}
                            <div class="col-12">
                                <button type="submit" class="parcerias-form__submit">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Enviar proposta de parceria
                                </button>
                            </div>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</section>

{{-- ─── STYLE ─────────────────────────────────────────────────────────────── --}}
<style>
.parcerias-form {
    background: #f7f7f7;
}

.parcerias-form__title {
    font-size: clamp(1.6rem, 3vw, 2.3rem);
    font-weight: 900;
    color: #111;
    text-transform: uppercase;
    line-height: 1.2;
    margin-bottom: 20px;
    letter-spacing: -0.01em;
}

.parcerias-form__title strong {
    color: var(--color-primary, #e63312);
}

.parcerias-form__text {
    color: #666;
    font-size: 0.95rem;
    line-height: 1.8;
    margin-bottom: 32px;
}

/* Info de contato */
.parcerias-form__info {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.parcerias-form__info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.90rem;
    color: #555;
}

.parcerias-form__info-item i {
    width: 36px;
    height: 36px;
    background: var(--color-primary, #e63312);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

/* Box do formulário */
.parcerias-form__box {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 40px rgba(0,0,0,0.08);
    overflow: hidden;
}

.parcerias-form__box-header {
    background: var(--color-primary, #e63312);
    color: #fff;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.parcerias-form__box-header i {
    font-size: 1.3rem;
}

.parcerias-form__form {
    padding: 28px;
}

/* Campos */
.parcerias-form__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.parcerias-form__field label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #444;
}

.parcerias-form__field label span {
    color: var(--color-primary, #e63312);
}

.parcerias-form__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.parcerias-form__input-wrap i {
    position: absolute;
    left: 14px;
    color: #aaa;
    font-size: 0.85rem;
    pointer-events: none;
}

.parcerias-form__input-wrap input,
.parcerias-form__input-wrap textarea {
    width: 100%;
    border: 2px solid #e8e8e8;
    border-radius: 8px;
    padding: 11px 14px 11px 38px;
    font-size: 0.92rem;
    color: #333;
    background: #fafafa;
    transition: border-color 0.2s ease, background 0.2s ease;
    outline: none;
    resize: none;
}

.parcerias-form__input-wrap input:focus,
.parcerias-form__input-wrap textarea:focus {
    border-color: var(--color-primary, #e63312);
    background: #fff;
}

.parcerias-form__input-wrap--textarea {
    align-items: flex-start;
}

.parcerias-form__input-wrap--textarea i {
    top: 12px;
}

/* Grid de tipo de parceria */
.parcerias-form__tipo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.parcerias-form__tipo-option {
    cursor: pointer;
}

.parcerias-form__tipo-option input[type="radio"] {
    display: none;
}

.parcerias-form__tipo-option span {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 2px solid #e8e8e8;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #555;
    background: #fafafa;
    transition: all 0.2s ease;
}

.parcerias-form__tipo-option input[type="radio"]:checked + span {
    background: var(--color-primary, #e63312);
    border-color: var(--color-primary, #e63312);
    color: #fff;
}

.parcerias-form__tipo-option span:hover {
    border-color: var(--color-primary, #e63312);
    color: var(--color-primary, #e63312);
}

/* Erro */
.parcerias-form__error {
    font-size: 0.78rem;
    color: var(--color-primary, #e63312);
}

/* Botão submit */
.parcerias-form__submit {
    width: 100%;
    background: var(--color-primary, #e63312);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 14px 28px;
    font-size: 0.95rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition: background 0.25s ease, transform 0.2s ease;
    margin-top: 8px;
}

.parcerias-form__submit:hover {
    background: #c42a0e;
    transform: translateY(-2px);
}

@media (max-width: 480px) {
    .parcerias-form__tipo-grid {
        grid-template-columns: 1fr;
    }
}
</style>