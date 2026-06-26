<section id="enviar-mensagem" class="contact-form-map-section">
    <div class="container-contact form-map-flex">

        <div class="form-side-container">
            <div class="form-header-left">
                <h3>Envie sua mensagem</h3>
                <p>Preencha os campos abaixo e entraremos em contato em até 24 horas úteis.</p>
            </div>

            <form action="#" method="POST" onsubmit="event.preventDefault();">
                @csrf

                <div class="form-group-full">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" required class="form-input">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail Corporativo/Pessoal *</label>
                        <input type="email" name="email" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp / Telefone *</label>
                        <input type="text" name="telefone" required class="form-input">
                    </div>
                </div>

                <div class="form-group-full">
                    <label>Assunto Principal</label>
                    <select name="assunto" class="form-input form-select">
                        <option value="matricula">Matrículas e Categorias</option>
                        <option value="horarios">Horários e Unidades</option>
                        <option value="financeiro">Financeiro / Mensalidades</option>
                        <option value="sugestao">Sugestões ou Elogios</option>
                    </select>
                </div>

                <div class="form-group-full">
                    <label>Sua Mensagem *</label>
                    <textarea name="mensagem" rows="4" required class="form-input form-textarea"></textarea>
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