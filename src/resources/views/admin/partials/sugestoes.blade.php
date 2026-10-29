{{--
    Sugestões dos formulários do admin (Fase 10), incluídas uma vez por página:
    - Local (Etapa L2): o select do cadastro de locais (partial admin.partials.select-local). Aqui ficam o
      prepararSelectLocal() dos modais preenchidos pelo JS (o local inativo ou o texto antigo do registro aberto), o
      endereço do local escolhido embaixo do select e o "Novo local" (cadastro rápido em JSON, sem sair da tela);
    - Responsável pela atividade (select da comissão técnica, partial admin.partials.select-responsavel): nos modais
      da lista preenchidos pelo JS, prepararSelectResponsavel() acrescenta o profissional inativo ou o texto antigo
      do registro aberto, como opção só para manter;
    - Subtipo: "Exame médico" e "Avaliação física" quando o tipo do evento é Avaliação. O input do subtipo
      (.js-subtipo) só ganha a lista enquanto o select do tipo (.js-tipo-evento) do mesmo formulário está em
      AVALIACAO. Os campos continuam livres.
--}}
<datalist id="subtiposAvaliacao">
    @foreach (\App\Models\EventoCalendario::SUBTIPOS_AVALIACAO as $subtipo)
    <option value="{{ $subtipo }}"></option>
    @endforeach
</datalist>

<script>
// Select do responsável num modal preenchido pelo JS: id do profissional, nome dele (para o inativo, que não está na
// lista) e o texto antigo. textContent: o nome e o texto não são interpretados como HTML
window.prepararSelectResponsavel = function (select, id, nome, texto) {
    select.querySelectorAll('.js-opcao-extra').forEach(opt => opt.remove());
    const extra = (valor, rotulo) => {
        const opt = document.createElement('option');
        opt.value = valor;
        opt.textContent = rotulo;
        opt.className = 'js-opcao-extra';
        select.appendChild(opt);
    };
    if (id && ![...select.options].some(opt => opt.value === String(id))) extra(id, `${nome} (inativo)`);
    if (!id && texto) extra(@js(\App\Models\ComissaoTecnica::TEXTO_ANTIGO), `${texto} (texto antigo)`);
    select.value = id ? String(id) : (texto ? @js(\App\Models\ComissaoTecnica::TEXTO_ANTIGO) : '');
};

// Select do local num modal preenchido pelo JS: id do local, nome (o texto gravado é o nome do local) e o texto. Sem
// id e com texto = texto antigo; com id fora da lista = local inativo (só para manter)
window.prepararSelectLocal = function (select, id, texto) {
    select.querySelectorAll('.js-opcao-extra').forEach(opt => opt.remove());
    const extra = (valor, rotulo) => {
        const opt = document.createElement('option');
        opt.value = valor;
        opt.textContent = rotulo;
        opt.className = 'js-opcao-extra';
        select.appendChild(opt);
    };
    if (id && ![...select.options].some(opt => opt.value === String(id))) extra(id, `${texto} (inativo)`);
    if (!id && texto) extra(@js(\App\Models\Local::TEXTO_ANTIGO), `${texto} (texto antigo)`);
    select.value = id ? String(id) : (texto ? @js(\App\Models\Local::TEXTO_ANTIGO) : '');
    select.dispatchEvent(new Event('change'));
};

document.addEventListener('DOMContentLoaded', function () {
    // Endereço do local escolhido, embaixo do select
    const mostrarEndereco = select => {
        const ajuda = select.closest('.js-campo-local')?.querySelector('.js-endereco-local');
        if (ajuda) ajuda.textContent = select.selectedOptions[0]?.dataset.endereco || '';
    };
    document.querySelectorAll('.js-select-local').forEach(select => {
        select.addEventListener('change', () => mostrarEndereco(select));
        mostrarEndereco(select);
    });

    // "Novo local": abre o cadastro rápido dentro do formulário; salvo, o local entra em todos os selects da página
    // e já fica escolhido neste
    document.querySelectorAll('.js-campo-local').forEach(campo => {
        const painel = campo.querySelector('.js-painel-novo-local');
        const select = campo.querySelector('.js-select-local');
        const erros  = painel.querySelector('.js-erros-novo-local');
        const limpar = () => {
            painel.querySelectorAll('[data-campo]').forEach(i => i.value = '');
            erros.replaceChildren();
        };

        campo.querySelector('.js-novo-local').addEventListener('click', () => {
            painel.classList.toggle('d-none');
            if (!painel.classList.contains('d-none')) painel.querySelector('[data-campo="nome_local"]').focus();
        });
        painel.querySelector('.js-cancelar-novo-local').addEventListener('click', () => { limpar(); painel.classList.add('d-none'); });

        // CEP: máscara e o ViaCEP (o mesmo do cadastro de Locais)
        const cep = painel.querySelector('.js-cep-novo-local');
        cep.addEventListener('input', () => {
            const d = cep.value.replace(/\D/g, '').slice(0, 8);
            cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
            if (d.length !== 8) return;
            fetch('https://viacep.com.br/ws/' + d + '/json/').then(r => r.json()).then(e => {
                if (e.erro) return;
                [['rua_local', e.logradouro], ['bairro_local', e.bairro], ['cidade_local', e.localidade], ['estado_local', e.uf]]
                    .forEach(([nome, valor]) => { if (valor) painel.querySelector(`[data-campo="${nome}"]`).value = valor; });
            }).catch(() => {});
        });

        painel.querySelector('.js-salvar-novo-local').addEventListener('click', () => {
            const dados = new FormData();
            painel.querySelectorAll('[data-campo]').forEach(i => dados.append(i.dataset.campo, i.value));
            erros.replaceChildren();
            fetch(painel.dataset.url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': painel.dataset.token },
                body: dados,
            }).then(async r => {
                const corpo = await r.json().catch(() => ({}));
                if (r.status === 201) {
                    document.querySelectorAll('.js-select-local').forEach(sel => {
                        const opt = document.createElement('option');
                        opt.value = corpo.id_local;
                        opt.textContent = corpo.nome;
                        opt.dataset.endereco = corpo.endereco;
                        sel.insertBefore(opt, sel.querySelector('.js-opcao-extra'));
                    });
                    select.value = String(corpo.id_local);
                    select.dispatchEvent(new Event('change'));
                    limpar();
                    painel.classList.add('d-none');
                    return;
                }
                const mensagens = corpo.errors ? Object.values(corpo.errors).flat() : [corpo.message || 'Não foi possível cadastrar o local.'];
                erros.replaceChildren(...mensagens.map(m => Object.assign(document.createElement('li'), { textContent: m })));
            }).catch(() => {
                erros.replaceChildren(Object.assign(document.createElement('li'), { textContent: 'Sem conexão: tente de novo.' }));
            });
        });
    });

    function sugerirSubtipo(selectTipo) {
        const subtipo = selectTipo.form?.querySelector('.js-subtipo');
        if (!subtipo) return;
        if (selectTipo.value === 'AVALIACAO') subtipo.setAttribute('list', 'subtiposAvaliacao');
        else subtipo.removeAttribute('list');
    }

    document.querySelectorAll('.js-tipo-evento').forEach(sel => {
        sel.addEventListener('change', () => sugerirSubtipo(sel));
        sugerirSubtipo(sel);
    });
    // Modal de edição preenchido pelo JS da lista: confere de novo ao abrir
    document.addEventListener('shown.bs.modal', e => e.target.querySelectorAll('.js-tipo-evento').forEach(sugerirSubtipo));
});
</script>
