<section id="fazer-parceria" class="partnership-form-section">
    <div class="form-container">

        <div class="form-header">
            <h3>Proposta de Parceria</h3>
            <p>Preencha os campos abaixo de forma intuitiva e rápida.</p>
        </div>

        <form action="#" method="POST" onsubmit="event.preventDefault();">
            @csrf

            <div class="form-group-full">
                <label>Seu Nome *</label>
                <input type="text" name="nome" required class="form-input">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Empresa / Organização *</label>
                    <input type="text" name="empresa" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Tipo de Parceria Desejada</label>
                    <select name="tipo_parceria" class="form-input form-select">
                        <option value="patrocinador">Patrocinador Oficial</option>
                        <option value="apoiador">Apoiador</option>
                        <option value="comercial">Parceiro Comercial</option>
                        <option value="social">Parceiro Social</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>E-mail Corporativo *</label>
                    <input type="email" name="email" required class="form-input">
                </div>
                <div class="form-group">
                    <label>WhatsApp / Telefone *</label>
                    <input type="text" name="telefone" required class="form-input">
                </div>
            </div>

            <div class="form-group-full">
                <label>Como sua empresa deseja ajudar? (Mensagem)</label>
                <textarea name="mensagem" rows="4" class="form-input form-textarea"></textarea>
            </div>

            <div class="form-submit-block">
                <button type="submit" class="btn-form-submit">Enviar Proposta de Time</button>
            </div>
        </form>
    </div>
</section>