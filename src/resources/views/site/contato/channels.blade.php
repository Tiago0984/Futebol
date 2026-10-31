<section class="contact-channels-section">
    <div class="container-contact">
        <div class="section-title-block">
            <span class="section-subtitle-tag">Atendimento Rápido</span>
            <h2>Fale com a Secretaria</h2>
            <p>Escolha o canal mais adequado para falar com o nosso time de suporte.</p>
        </div>

        <div class="contact-channels-grid">
            @php
                $telefone = trim((string) config('contato.telefone'));
                $whatsapp = trim((string) config('contato.whatsapp'));
                $telefoneDigitos = preg_replace('/\D/', '', $telefone);
                $whatsappDigitos = preg_replace('/\D/', '', $whatsapp);
            @endphp
            @if($telefoneDigitos !== '')
            <div class="channel-card">
                <div class="card-icon-box">📞</div>
                <h3>Telefone Geral</h3>
                <p class="channel-detail"><a href="tel:+{{ $telefoneDigitos }}">{{ $telefone }}</a></p>
                <p class="channel-sub text-muted">Dúvidas rápidas e administrativa</p>
            </div>
            @endif

            @if($whatsappDigitos !== '')
            <div class="channel-card highlight-whatsapp">
                <div class="card-icon-box">💬</div>
                <h3>WhatsApp Oficial</h3>
                <p class="channel-detail"><a href="https://wa.me/{{ $whatsappDigitos }}">{{ $whatsapp }}</a></p>
                <p class="channel-sub">Inscrições, matrículas e agendamentos</p>
            </div>
            @endif

            @if(config('contato.email'))
            <div class="channel-card">
                <div class="card-icon-box">✉️</div>
                <h3>E-mail</h3>
                <p class="channel-detail"><a href="mailto:{{ config('contato.email') }}">{{ config('contato.email') }}</a></p>
                <p class="channel-sub text-muted">Parcerias, corporativo e imprensa</p>
            </div>
            @endif
        </div>
    </div>
</section>
