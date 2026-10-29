{{--
    Select "Responsável pela atividade" (Etapa C2): só os profissionais ATIVOS da comissão técnica, sem texto livre.
    Parâmetros: $idCampo (id do select); opcionais $atual (EventoCalendario ou GradeTreino em edição, na tela do evento)
    e $rotuloDoCampo (não $rotulo, que as telas usam em laços e vazaria para cá). Extras do registro em edição (o servidor também confere, em ComissaoTecnica::daEscolha):
    - profissional atual que foi desativado: "Nome (inativo)", só para manter;
    - texto antigo (texto livre de 24/10/2026): "Prof. Carlos (texto antigo)", mantido até alguém escolher da lista.
    Nos modais da lista, o JS do partial admin.partials.sugestoes cria esses extras (prepararSelectResponsavel).
--}}
@php
    $atual       = $atual ?? null;
    $idAtual     = $atual?->id_comissao_tecnica;
    $textoAntigo = $atual?->responsavel_evento_calendario ?? $atual?->responsavel_grade_treino;
    $ativos      = \App\Models\ComissaoTecnica::paraEscolher();
    $inativo     = $idAtual && ! $ativos->contains('id_comissao_tecnica', $idAtual) ? $atual->comissaoTecnica : null;
    $selecionado = $errors->any()
        ? (string) old('id_comissao_tecnica', '')
        : (string) ($idAtual ?: (filled($textoAntigo) ? \App\Models\ComissaoTecnica::TEXTO_ANTIGO : ''));
@endphp
<label class="form-label" for="{{ $idCampo }}">{{ $rotuloDoCampo ?? 'Responsável pela atividade' }}</label>
<select id="{{ $idCampo }}" name="id_comissao_tecnica" class="form-select js-select-responsavel">
    <option value="">— Sem responsável —</option>
    @foreach ($ativos as $p)
    <option value="{{ $p->id_comissao_tecnica }}" @selected($selecionado === (string) $p->id_comissao_tecnica)>{{ $p->nome_comissao_tecnica }} · {{ $p->cargo_label }}</option>
    @endforeach
    @if ($inativo)
    <option value="{{ $inativo->id_comissao_tecnica }}" class="js-opcao-extra" @selected($selecionado === (string) $inativo->id_comissao_tecnica)>{{ $inativo->nome_comissao_tecnica }} (inativo)</option>
    @endif
    @if (! $idAtual && filled($textoAntigo))
    <option value="{{ \App\Models\ComissaoTecnica::TEXTO_ANTIGO }}" class="js-opcao-extra" @selected($selecionado === \App\Models\ComissaoTecnica::TEXTO_ANTIGO)>{{ $textoAntigo }} (texto antigo)</option>
    @endif
</select>
<small class="text-muted">
    @if ($ativos->isEmpty())
        Nenhum profissional ativo: cadastre em <a href="{{ route('admin.comissao.index') }}">Comissão técnica</a>.
    @else
        Da <a href="{{ route('admin.comissao.index') }}">comissão técnica</a>. Opcional; o app mostra o perfil.
    @endif
</small>
@error('id_comissao_tecnica')<div class="text-danger small">{{ $message }}</div>@enderror
