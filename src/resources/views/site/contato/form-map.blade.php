<section id="enviar-mensagem" class="contact-form-map-section">
    @php
        $valorAnterior = fn (string $campo, string $padrao = '') => is_string(old($campo)) ? old($campo) : $padrao;
    @endphp
    <div class="container-contact form-map-flex">

        <div class="form-side-container">
            <div class="form-header-left">
                <h3>Envie sua mensagem</h3>
                <p>Preencha os campos abaixo para falar com a nossa equipe.</p>
            </div>

            @if(session('sucesso'))
                <div class="alert alert-success" role="status">{{ session('sucesso') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul>@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
                </div>
            @endif
            <form action="{{ route('contato.enviar') }}" method="POST">
                @csrf

                <div class="form-group-full">
                    <label for="contato-nome">Nome Completo *</label>
                    <input id="contato-nome" type="text" name="nome" value="{{ $valorAnterior('nome') }}" required maxlength="150" autocomplete="name" class="form-input">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contato-email">E-mail Corporativo/Pessoal *</label>
                        <input id="contato-email" type="email" name="email" value="{{ $valorAnterior('email') }}" required maxlength="255" autocomplete="email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="contato-telefone">WhatsApp / Telefone *</label>
                        <input id="contato-telefone" type="tel" name="telefone" value="{{ $valorAnterior('telefone') }}" required maxlength="20" autocomplete="tel" class="form-input">
                    </div>
                </div>

                <div class="form-group-full">
                    <label for="contato-assunto">Assunto Principal *</label>
                    <select id="contato-assunto" name="assunto" required class="form-input form-select">
                        @foreach(\App\Http\Requests\ContatoSiteRequest::ASSUNTOS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($valorAnterior('assunto', 'matricula') === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group-full">
                    <label for="contato-mensagem">Sua Mensagem *</label>
                    <textarea id="contato-mensagem" name="mensagem" rows="4" required maxlength="2000" class="form-input form-textarea">{{ $valorAnterior('mensagem') }}</textarea>
                </div>

                <div class="form-submit-block-left">
                    <button type="submit" class="btn-form-submit">Enviar Mensagem</button>
                </div>
            </form>
        </div>

        <div class="map-side-container">
            <div class="location-briefing">
                <h3>Nossa Sede</h3>
                <p>📍 Rua do Esporte, 123 - Bairro dos Atletas, São Paulo - SP</p>
                <p>⏰ Horário: Seg a Sex das 08h às 18h | Sáb das 08h às 12h</p>
            </div>
            <div class="map-wrapper-box">
                <div class="placeholder-map-box">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3659.027584782976!2d-46.4318581!3d-23.495515899999997!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce63dda7be6fb9%3A0xa74e7d5a53104311!2sSenac%20S%C3%A3o%20Miguel%20Paulista!5e0!3m2!1sen!2sbr!4v1779885737127!5m2!1sen!2sbr" style="width:100%; height:400px; border-radius:6px; border:0; display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>

    </div>
</section>
