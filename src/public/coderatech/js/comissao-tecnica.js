document.addEventListener('DOMContentLoaded', function () {
    // Mesma máscara no cadastro e na edição; o servidor confere e grava só os dígitos.
    document.querySelectorAll('.js-telefone-comissao').forEach(campo => {
        campo.addEventListener('input', function () {
            let valor = campo.value.replace(/\D/g, '').slice(0, 11);
            if (valor.length > 10) valor = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            else if (valor.length > 6) valor = valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
            else if (valor.length > 2) valor = valor.replace(/(\d{2})(\d{1,4})/, '($1) $2');
            campo.value = valor;
        });
    });

    document.querySelectorAll('.js-foto-comissao').forEach(campo => {
        const arquivo = campo.querySelector('.js-arquivo-foto');
        const previa = campo.querySelector('.js-previa-foto');
        const imagem = campo.querySelector('.js-imagem-foto');
        const erro = campo.querySelector('.js-erro-foto');
        const remover = campo.querySelector('.js-remover-foto');
        let url = null;

        function limpar() {
            previa.hidden = true;
            imagem.onload = null;
            imagem.onerror = null;
            imagem.removeAttribute('src');
            if (url) URL.revokeObjectURL(url);
            url = null;
        }

        function avisar(mensagem = '') {
            erro.textContent = mensagem;
            erro.hidden = !mensagem;
        }

        function voltarParaAtual(mensagem = '') {
            limpar();
            arquivo.value = '';
            avisar(mensagem);
            if (!campo.dataset.atual || remover?.checked) return;
            imagem.onload = () => { previa.hidden = false; };
            imagem.onerror = limpar;
            imagem.src = campo.dataset.atual;
        }

        arquivo.addEventListener('change', function () {
            const foto = arquivo.files[0];
            if (!foto) {
                voltarParaAtual();
                return;
            }
            const maxKb = Number(campo.dataset.maxKb);
            if (foto.size > maxKb * 1024) {
                voltarParaAtual(`Use uma foto de até ${Math.round(maxKb / 1024)} MB.`);
                return;
            }
            if (foto.type && !['image/jpeg', 'image/png', 'image/webp'].includes(foto.type)) {
                voltarParaAtual('Selecione uma foto JPG, PNG ou WEBP.');
                return;
            }
            limpar();
            avisar();
            if (remover) remover.checked = false;
            imagem.onload = () => { previa.hidden = false; };
            imagem.onerror = () => voltarParaAtual('Não foi possível abrir esta foto. Escolha uma imagem JPG, PNG ou WEBP.');
            url = URL.createObjectURL(foto);
            imagem.src = url;
        });

        remover?.addEventListener('change', () => voltarParaAtual());
        campo.closest('.modal')?.addEventListener('hidden.bs.modal', () => {
            limpar();
            avisar();
            arquivo.value = '';
        });
        voltarParaAtual();
    });

    const modal = document.getElementById('modalCriarProfissional');
    if (modal?.dataset.abrir === '1') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
