{{--
    Sugestões dos formulários do admin (Fase 10), incluídas uma vez por página:
    - Local: os locais já usados em eventos, grade e campeonatos (input com list="locaisUsados");
      $locaisUsados vem do composer registrado no AppServiceProvider;
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
