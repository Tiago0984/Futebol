{{--
    Campos do jogo, usados nos modais de criar e editar.
    $p = prefixo dos ids ('criar_' ou 'edit_'); $comOld = preenche com old() (no criar e na tela do jogo; o editar
    da lista é preenchido pelo JS); $valores (opcional) = valores atuais do jogo, quando old() está vazio.
    Os nomes dos campos do evento são os mesmos do Calendário, para o alerta de conflito reenviar o formulário.
    Campeonato: data-categorias = categorias dele e data-participantes = [id_categoria => [id_time]] (o JS de
    admin.jogos._script_form mostra só essas categorias e, nos times, só os participantes da categoria escolhida).
--}}
@php $valor = fn ($campo) => $comOld ? old($campo, $valores[$campo] ?? null) : null; @endphp
<div class="row g-3 form-jogo" data-prefixo="{{ $p }}">
    <div class="col-md-7">
        <label class="form-label">Campeonato <span class="text-danger">*</span></label>
        <select name="id_campeonato" id="{{ $p }}id_campeonato" class="form-select js-campeonato" required>
            <option value="">— Selecionar —</option>
            <option value="{{ \App\Http\Controllers\Admin\JogosController::AMISTOSO }}" @selected($valor('id_campeonato') === \App\Http\Controllers\Admin\JogosController::AMISTOSO)>
                Amistoso (sem campeonato)
            </option>
            @foreach($campeonatos as $camp)
            <option value="{{ $camp->id_campeonato }}"
                data-categorias="{{ $camp->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->toJson() }}"
                data-participantes="{{ json_encode((object) ($camp->participantes_por_categoria ?? [])) }}"
                @selected((string) $valor('id_campeonato') === (string) $camp->id_campeonato)>
                {{ $camp->nome_campeonato }}
            </option>
            @endforeach
        </select>
        <small class="text-muted js-categoria-campeonato"></small>
    </div>
    <div class="col-md-5 js-bloco-categoria d-none">
        <label class="form-label" for="{{ $p }}id_categoria">
            <span class="js-rotulo-categoria">Categoria do jogo</span> <span class="text-danger js-obrigatoria">*</span>
        </label>
        <select name="id_categoria" id="{{ $p }}id_categoria" class="form-select js-categoria">
            <option value="" class="js-sem-categoria">Sem categoria</option>
            @foreach($categorias as $cat)
            <option value="{{ $cat->id_categoria }}" @selected((string) $valor('id_categoria') === (string) $cat->id_categoria)>{{ $cat->rotulo }}</option>
            @endforeach
        </select>
        <small class="text-muted js-ajuda-categoria"></small>
    </div>

    <div class="col-md-5 js-bloco-fase d-none">
        <label class="form-label" for="{{ $p }}fase">Fase</label>
        <select name="fase_jogo" id="{{ $p }}fase" class="form-select js-fase">
            @foreach(\App\Models\Jogo::FASES as $fase => $rotuloFase)
            <option value="{{ $fase }}" @selected(($valor('fase_jogo') ?? \App\Models\Jogo::FASE_PADRAO) === $fase)>{{ $rotuloFase }}</option>
            @endforeach
        </select>
        <small class="text-muted">A classificação conta só a fase de grupos.</small>
    </div>

    <div class="col-12 js-aviso-participantes d-none">
        <small class="text-muted"><i class="bi bi-info-circle"></i> Os times mostram só os participantes da categoria do jogo.</small>
    </div>
    <div class="col-md-5">
        <label class="form-label">Time Mandante <span class="text-danger">*</span></label>
        <select name="id_time_casa" id="{{ $p }}id_time_casa" class="form-select js-time-casa" required>
            <option value="">— Selecionar —</option>
            @foreach($times as $time)
            <option value="{{ $time->id_time }}" data-categoria="{{ $time->id_categoria }}"
                @selected((string) $valor('id_time_casa') === (string) $time->id_time)>{{ $time->nome_time }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end justify-content-center pb-1">
        <span class="fw-bold text-muted">VS</span>
    </div>
    <div class="col-md-5">
        <label class="form-label">Time Visitante <span class="text-danger">*</span></label>
        <select name="id_time_visitante" id="{{ $p }}id_time_visitante" class="form-select js-time-visitante" required>
            <option value="">— Selecionar —</option>
            @foreach($times as $time)
            <option value="{{ $time->id_time }}" @selected((string) $valor('id_time_visitante') === (string) $time->id_time)>{{ $time->nome_time }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-calendar-event"></i> Quando e onde (ficam no evento do jogo)</p></div>

    <div class="col-md-4">
        <label class="form-label">Data <span class="text-danger">*</span></label>
        <input type="date" name="data_evento_calendario" id="{{ $p }}data" class="form-control" required value="{{ $valor('data_evento_calendario') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Início</label>
        <input type="time" name="horario_inicio_evento_calendario" id="{{ $p }}inicio" class="form-control" value="{{ $valor('horario_inicio_evento_calendario') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Fim</label>
        <input type="time" name="horario_fim_evento_calendario" id="{{ $p }}fim" class="form-control" value="{{ $valor('horario_fim_evento_calendario') }}">
    </div>
    <div class="col-12">
        <label class="form-label">Local</label>
        <input type="text" name="local_evento_calendario" id="{{ $p }}local" class="form-control" maxlength="255"
            placeholder="Vazio = local do campeonato" list="locaisUsados" autocomplete="off" value="{{ $valor('local_evento_calendario') }}">
    </div>

    <div class="col-12"><hr class="my-1"><p class="modal-section-label mb-0"><i class="bi bi-123"></i> Placar (vazio = jogo ainda não jogado)</p></div>

    <div class="col-md-3">
        <label class="form-label">Gols Mandante</label>
        <input type="number" name="placar_time_casa_jogos" id="{{ $p }}placar_casa" class="form-control text-center"
            min="0" placeholder="—" value="{{ $valor('placar_time_casa_jogos') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Gols Visitante</label>
        <input type="number" name="placar_time_visitante_jogos" id="{{ $p }}placar_visitante" class="form-control text-center"
            min="0" placeholder="—" value="{{ $valor('placar_time_visitante_jogos') }}">
    </div>
</div>
