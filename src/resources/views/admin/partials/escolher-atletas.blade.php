{{--
    Caixa para escolher atletas: lista para ticar, agrupada pela categoria atual, com busca por nome, "Marcar
    todos" de cada categoria (só os visíveis), contador e "pelo menos um" no envio do formulário em volta.
    Usada no evento individual (Calendário), no aviso livre (Notificações) e na convocação (Campeonatos).
    $prefixo: começo dos ids (ind, aviso, conv); $grupos: [rótulo da categoria => atletas]; $escolhidos: ids marcados
    (old()); $ajuda: texto embaixo da caixa. Os atletas vão em atletas[].
    Opcionais: $bloqueados [id_atleta => motivo] aparecem desabilitados com "· motivo"; $comCamisa = true põe o
    número da camisa ao lado de cada atleta (camisas[id], obrigatório só para os marcados; $camisas: old()).
--}}
@php
  $bloqueados = $bloqueados ?? [];
  $comCamisa  = $comCamisa ?? false;
  $camisas    = $camisas ?? [];
@endphp
<div class="js-escolher-atletas">
    <div class="d-flex flex-wrap align-items-end gap-2 mb-1">
        <label class="form-label mb-0 me-auto" for="{{ $prefixo }}_busca">Atletas <span class="text-danger">*</span>
            <span class="badge bg-secondary ms-1 js-contador-atletas" id="{{ $prefixo }}_contador">0 escolhido(s)</span>
        </label>
        <input type="search" id="{{ $prefixo }}_busca" class="form-control form-control-sm js-busca-atletas" style="max-width:240px"
            placeholder="Buscar atleta..." autocomplete="off">
    </div>
    <div class="border rounded p-2 js-lista-atletas" id="{{ $prefixo }}_atletas" style="max-height:260px;overflow-y:auto;">
        @forelse($grupos as $rotuloCategoria => $grupo)
        <div class="js-grupo-atletas mb-2">
            <div class="d-flex align-items-center gap-2 border-bottom pb-1 mb-1">
                <strong class="small">{{ $rotuloCategoria }}</strong>
                <button type="button" class="btn btn-link btn-sm p-0 ms-auto js-marcar-grupo">Marcar todos</button>
            </div>
            <div class="row g-1">
                @foreach($grupo as $atleta)
                @php $motivoBloqueio = $bloqueados[$atleta->id_atleta] ?? null; @endphp
                <div class="col-md-6 js-item-atleta" data-nome="{{ mb_strtolower($atleta->nome_atleta) }}">
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check mb-0 flex-grow-1">
                            <input class="form-check-input js-check-atleta" type="checkbox" name="atletas[]"
                                value="{{ $atleta->id_atleta }}" id="{{ $prefixo }}_atleta_{{ $atleta->id_atleta }}"
                                @checked(! $motivoBloqueio && in_array((string) $atleta->id_atleta, $escolhidos, true)) @disabled($motivoBloqueio)>
                            <label class="form-check-label" for="{{ $prefixo }}_atleta_{{ $atleta->id_atleta }}">{{ $atleta->nome_atleta }}@if($motivoBloqueio) <span class="text-muted small">· {{ $motivoBloqueio }}</span>@endif</label>
                        </div>
                        @if($comCamisa && ! $motivoBloqueio)
                        <input type="number" min="1" max="99" name="camisas[{{ $atleta->id_atleta }}]" value="{{ $camisas[$atleta->id_atleta] ?? '' }}"
                            class="form-control form-control-sm js-camisa-atleta" style="width:72px" placeholder="Nº"
                            aria-label="Camisa de {{ $atleta->nome_atleta }}" title="Número da camisa">
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @empty
        <p class="text-muted small mb-0">Nenhum atleta ativo.</p>
        @endforelse
    </div>
    <small class="text-muted">{{ $ajuda }}</small>
    <div class="text-danger small d-none js-aviso-atletas" id="{{ $prefixo }}_aviso_atletas">Escolha pelo menos um atleta.</div>
</div>

@once
<script>
// Caixa de atletas: contador, busca por nome, "marcar todos" da categoria (só os visíveis) e pelo menos um
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-escolher-atletas').forEach(function (caixa) {
        const form = caixa.closest('form');
        const checks = () => [...caixa.querySelectorAll('.js-check-atleta')];
        const contador = caixa.querySelector('.js-contador-atletas');
        const aviso = caixa.querySelector('.js-aviso-atletas');

        // Camisa (convocação): só o atleta marcado manda o número, e ele é obrigatório
        function sincronizarCamisas() {
            caixa.querySelectorAll('.js-item-atleta').forEach(item => {
                const check = item.querySelector('.js-check-atleta');
                const camisa = item.querySelector('.js-camisa-atleta');
                if (camisa) { camisa.disabled = !check.checked; camisa.required = check.checked; }
            });
        }

        function contar() {
            sincronizarCamisas();
            const n = checks().filter(c => c.checked).length;
            contador.textContent = `${n} escolhido(s)`;
            if (n) aviso.classList.add('d-none');
        }

        caixa.addEventListener('change', e => { if (e.target.classList.contains('js-check-atleta')) contar(); });

        caixa.querySelector('.js-busca-atletas').addEventListener('input', function () {
            const busca = this.value.toLowerCase().trim();
            caixa.querySelectorAll('.js-grupo-atletas').forEach(grupo => {
                let algum = false;
                grupo.querySelectorAll('.js-item-atleta').forEach(item => {
                    const ok = !busca || item.dataset.nome.includes(busca);
                    item.classList.toggle('d-none', !ok);
                    algum = algum || ok;
                });
                grupo.classList.toggle('d-none', !algum);
            });
        });

        caixa.querySelectorAll('.js-marcar-grupo').forEach(botao => botao.addEventListener('click', function () {
            const visiveis = [...this.closest('.js-grupo-atletas').querySelectorAll('.js-item-atleta:not(.d-none) .js-check-atleta:not(:disabled)')];
            const marcar = visiveis.some(c => !c.checked);
            visiveis.forEach(c => c.checked = marcar);
            this.textContent = marcar ? 'Desmarcar todos' : 'Marcar todos';
            contar();
        }));

        form?.addEventListener('submit', e => {
            if (!checks().some(c => c.checked)) {
                e.preventDefault();
                aviso.classList.remove('d-none');
                caixa.querySelector('.js-lista-atletas').scrollIntoView({ block: 'nearest' });
            }
        });

        contar();
    });
});
</script>
@endonce
