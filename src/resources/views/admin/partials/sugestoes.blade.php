{{--
    Sugestões dos formulários do admin (Fase 10), incluídas uma vez por página:
    - Local: os locais já usados em eventos, grade e campeonatos (input com list="locaisUsados");
      $locaisUsados vem do composer registrado no AppServiceProvider;
    - Responsável pela atividade (select da comissão técnica, partial admin.partials.select-responsavel): nos modais
      da lista preenchidos pelo JS, prepararSelectResponsavel() acrescenta o profissional inativo ou o texto antigo
      do registro aberto, como opção só para manter;
    - Subtipo: "Exame médico" e "Avaliação física" quando o tipo do evento é Avaliação. O input do subtipo
      (.js-subtipo) só ganha a lista enquanto o select do tipo (.js-tipo-evento) do mesmo formulário está em
      AVALIACAO. Os campos continuam livres.
--}}
<datalist id="locaisUsados">
    @foreach ($locaisUsados as $local)
    <option value="{{ $local }}"></option>
    @endforeach
</datalist>

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

document.addEventListener('DOMContentLoaded', function () {
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
