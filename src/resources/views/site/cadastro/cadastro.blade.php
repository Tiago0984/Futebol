@extends('layout.site')

@section('content')

@include('site.cadastro.banner')
@include('site.cadastro.intro')

<section id="form-matricula" style="padding: 40px 0 60px; background: #f5f5f5;">
    <div class="container">

        @if(session('sucesso'))
        <div class="alert-ok">
            <strong><span class="fa fa-check-circle"></span> {{ session('sucesso') }}</strong>
        </div>
        @endif

        @if(session('link_assinatura'))
        <div class="alert-err">
            <strong><span class="fa fa-exclamation-triangle"></span> Não foi possível enviar o e-mail ao responsável.</strong>
            <p style="margin:8px 0 0; font-size:13px; word-break:break-all;">
                Envie este link de assinatura manualmente: <a href="{{ session('link_assinatura') }}">{{ session('link_assinatura') }}</a>
            </p>
        </div>
        @endif

        @if($errors->any())
        <div class="alert-err">
            <strong><span class="fa fa-exclamation-triangle"></span> Verifique os campos:</strong>
            <ul>
                @foreach($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('cadastro.store') }}" method="POST" enctype="multipart/form-data" id="form-cadastro">
            @csrf

            {{-- DADOS DO ATLETA --}}
            <div class="mat-section">
                <h3><span class="fa fa-user"></span> Dados do Atleta</h3>

                <div class="row">
                    <div class="col-sm-8">
                        <div class="mat-group">
                            <label class="mat-label">Nome Completo *</label>
                            <input type="text" name="nome_atleta" value="{{ old('nome_atleta') }}" required class="mat-input" maxlength="100" autocomplete="off" autocapitalize="words">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Data de Nascimento *</label>
                            @php $limitesNasc = \App\Models\Atleta::limitesNascimento(); @endphp
                            <input type="date" name="data_nasc_atleta" value="{{ old('data_nasc_atleta') }}" required class="mat-input" id="data-nasc-atleta"
                                min="{{ $limitesNasc['min'] }}"
                                max="{{ $limitesNasc['max'] }}">
                            <p class="mat-hint">{{ $limitesNasc['mensagem'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">CPF *</label>
                            <input type="text" name="cpf_atleta" value="{{ old('cpf_atleta') }}" required class="mat-input" placeholder="000.000.000-00" id="cpf-atleta" maxlength="14" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">RG *</label>
                            <input type="text" name="rg_atleta" value="{{ old('rg_atleta') }}" required class="mat-input" id="rg-atleta" maxlength="15" autocomplete="off" placeholder="00.000.000-0">
                            <p class="mat-hint">Digite só os números: a pontuação entra sozinha. Se for o RG novo (que usa o número do CPF), é aceito: é só repetir o número do CPF.</p>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Sexo *</label>
                            <select name="sexo_atleta" required class="mat-input">
                                <option value="">Selecione</option>
                                <option value="M" {{ old('sexo_atleta')=='M'?'selected':'' }}>Masculino</option>
                                <option value="F" {{ old('sexo_atleta')=='F'?'selected':'' }}>Feminino</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Peso em kg e altura em metros com vírgula (55,5 e 1,80: digitando 180 vira 1,80), como no painel.
                     No celular, os dois ficam lado a lado (col-xs-6) e a foto embaixo --}}
                <div class="row">
                    <div class="col-xs-6 col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label" for="peso-atleta">Peso (kg)</label>
                            <input type="text" name="peso_atleta" value="{{ old('peso_atleta') ? str_replace('.', ',', old('peso_atleta')) : '' }}" class="mat-input" id="peso-atleta"
                                placeholder="Ex: 55,5" inputmode="decimal" maxlength="6" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-xs-6 col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label" for="altura-atleta">Altura (m)</label>
                            <input type="text" name="altura_atleta" value="{{ old('altura_atleta') ? str_replace('.', ',', old('altura_atleta')) : '' }}" class="mat-input" id="altura-atleta"
                                placeholder="Ex: 1,70" inputmode="decimal" maxlength="4" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="mat-group">
                            <label class="mat-label" for="foto-atleta">Foto do Atleta</label>
                            <div class="mat-foto">
                                <div class="mat-foto-previa">
                                    <img id="foto-atleta-previa" alt="Prévia da foto" hidden>
                                    <span class="fa fa-user" id="foto-atleta-vazia" aria-hidden="true"></span>
                                </div>
                                <div class="mat-foto-campo">
                                    <input type="file" name="foto_atleta" class="mat-input mat-input-arquivo" id="foto-atleta" accept="image/jpeg,image/png,image/webp">
                                    <p class="mat-hint">Opcional. JPG, PNG ou WebP, até 10 MB.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Escola *</label>
                            <input type="text" name="escola_atleta" value="{{ old('escola_atleta') }}" required class="mat-input" maxlength="100">
                        </div>
                    </div>
                    <div class="col-xs-6 col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Série *</label>
                            <input type="text" name="serie_atleta" value="{{ old('serie_atleta') }}" required class="mat-input" placeholder="Ex: 7º Ano" maxlength="20">
                        </div>
                    </div>
                    {{-- O treino é no turno oposto ao da escola; quem estuda à noite escolhe o turno do treino --}}
                    <div class="col-xs-6 col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Período *</label>
                            <select name="periodo_escolar_atleta" id="periodoEscolar" required class="mat-input">
                                <option value="">Selecione</option>
                                @foreach(\App\Models\Atleta::PERIODOS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('periodo_escolar_atleta') === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- mat-oculto (10/11/2026): o d-none de antes não existe no Bootstrap 3 do site, e o campo ficava sempre à mostra --}}
                    <div class="col-xs-6 col-sm-2 {{ old('periodo_escolar_atleta') === 'NOITE' ? '' : 'mat-oculto' }}" id="turnoTreinoCol">
                        <div class="mat-group">
                            <label class="mat-label">Treina de *</label>
                            <select name="turno_treino_atleta" id="turnoTreino" class="mat-input" @required(old('periodo_escolar_atleta') === 'NOITE')>
                                <option value="">Selecione</option>
                                @foreach(\App\Models\Atleta::TURNOS_TREINO as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('turno_treino_atleta') === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <script>
                    document.getElementById('periodoEscolar').addEventListener('change', function () {
                        const noite = this.value === 'NOITE';
                        const turno = document.getElementById('turnoTreino');
                        document.getElementById('turnoTreinoCol').classList.toggle('mat-oculto', !noite);
                        turno.required = noite;
                        if (!noite) turno.value = '';
                    });
                    </script>
                    <div class="col-xs-6 col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Sala</label>
                            <input type="text" name="sala_atleta" value="{{ old('sala_atleta') }}" class="mat-input" placeholder="Ex: A1" maxlength="50">
                        </div>
                    </div>
                </div>

                <div class="mat-group">
                    <label class="mat-label">Descrição / Observações</label>
                    <textarea name="descricao_atleta" rows="3" class="mat-input" maxlength="1000">{{ old('descricao_atleta') }}</textarea>
                </div>

                <div class="mat-group">
                    <label class="mat-label">E-mail *</label>
                    <input type="email" name="email_atleta" id="email-atleta" value="{{ old('email_atleta') }}" required class="mat-input" maxlength="255" inputmode="email" autocomplete="off" autocapitalize="off">
                </div>
            </div>

            {{-- ENDEREÇO DO ATLETA --}}
            <div class="mat-section">
                <h3><span class="fa fa-map-marker"></span> Endereço do Atleta</h3>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_endereco" value="{{ old('cep_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-atleta" maxlength="9" inputmode="numeric" autocomplete="postal-code">
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div class="mat-group">
                            <label class="mat-label">Rua *</label>
                            <input type="text" name="rua_endereco" value="{{ old('rua_endereco') }}" required class="mat-input" id="rua-atleta" maxlength="100" autocomplete="address-line1">
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Número *</label>
                            <input type="text" name="numero_endereco" value="{{ old('numero_endereco') }}" required class="mat-input" maxlength="6" placeholder="Ex: 123" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Bairro *</label>
                            <input type="text" name="bairro_endereco" value="{{ old('bairro_endereco') }}" required class="mat-input" id="bairro-atleta" maxlength="50">
                        </div>
                    </div>
                    <div class="col-xs-8 col-sm-5">
                        <div class="mat-group">
                            <label class="mat-label">Cidade *</label>
                            <input type="text" name="cidade_endereco" value="{{ old('cidade_endereco') }}" required class="mat-input" id="cidade-atleta" maxlength="50" autocomplete="address-level2">
                        </div>
                    </div>
                    <div class="col-xs-4 col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Estado *</label>
                            <input type="text" name="estado_endereco" value="{{ old('estado_endereco') }}" required class="mat-input mat-uf" maxlength="2" placeholder="SP" id="estado-atleta" autocomplete="address-level1" autocapitalize="characters">
                        </div>
                    </div>
                </div>

                <div class="mat-group">
                    <label class="mat-label">Complemento</label>
                    <input type="text" name="complemento_endereco" value="{{ old('complemento_endereco') }}" class="mat-input" placeholder="Apto, Bloco, Casa, etc." maxlength="100">
                </div>
            </div>

            {{-- DADOS DO RESPONSÁVEL --}}
            <div class="mat-section">
                <h3><span class="fa fa-users"></span> Dados do Responsável</h3>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="mat-group">
                            <label class="mat-label">Nome Completo *</label>
                            <input type="text" name="nome_responsavel" value="{{ old('nome_responsavel') }}" required class="mat-input" maxlength="100" autocomplete="name" autocapitalize="words">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CPF *</label>
                            <input type="text" name="cpf_responsavel" value="{{ old('cpf_responsavel') }}" required class="mat-input" placeholder="000.000.000-00" id="cpf-resp" maxlength="14" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">RG *</label>
                            <input type="text" name="rg_responsavel" value="{{ old('rg_responsavel') }}" required class="mat-input" id="rg-resp" maxlength="15" autocomplete="off" placeholder="00.000.000-0">
                            <p class="mat-hint">Se for o RG novo (que usa o número do CPF), é só repetir o número do CPF.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Grau de Parentesco *</label>
                            <select name="grau_parentesco" required class="mat-input">
                                <option value="">Selecione</option>
                                @foreach(\App\Models\Responsavel::GRAUS_PARENTESCO as $grau)
                                <option value="{{ $grau }}" @selected(old('grau_parentesco') === $grau)>{{ $grau }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">E-mail *</label>
                            <input type="email" name="email_responsavel" id="email-resp" value="{{ old('email_responsavel') }}" required class="mat-input" maxlength="150" inputmode="email" autocomplete="email" autocapitalize="off">
                            <p class="mat-hint">Diferente do e-mail do atleta: cada um recebe o próprio acesso ao app.</p>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Telefone</label>
                            <input type="text" name="telefone_responsavel" value="{{ old('telefone_responsavel') }}" class="mat-input" placeholder="(00) 0000-0000" id="telefone-resp" maxlength="15" inputmode="tel" autocomplete="tel-national">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">WhatsApp *</label>
                            <input type="text" name="whatsapp_responsavel" value="{{ old('whatsapp_responsavel') }}" required class="mat-input" placeholder="(00) 00000-0000" id="whatsapp-resp" maxlength="15" inputmode="tel" autocomplete="tel-national">
                        </div>
                    </div>
                </div>

                <hr class="mat-divider">
                <p class="mat-subtitle"><span class="fa fa-map-marker"></span> Endereço do Responsável</p>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_resp_endereco" value="{{ old('cep_resp_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-resp" maxlength="9" inputmode="numeric" autocomplete="postal-code">
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div class="mat-group">
                            <label class="mat-label">Rua *</label>
                            <input type="text" name="rua_resp_endereco" value="{{ old('rua_resp_endereco') }}" required class="mat-input" id="rua-resp" maxlength="100" autocomplete="address-line1">
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Número *</label>
                            <input type="text" name="numero_resp_endereco" value="{{ old('numero_resp_endereco') }}" required class="mat-input" maxlength="6" placeholder="Ex: 123" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Bairro *</label>
                            <input type="text" name="bairro_resp_endereco" value="{{ old('bairro_resp_endereco') }}" required class="mat-input" id="bairro-resp" maxlength="50">
                        </div>
                    </div>
                    <div class="col-xs-8 col-sm-5">
                        <div class="mat-group">
                            <label class="mat-label">Cidade *</label>
                            <input type="text" name="cidade_resp_endereco" value="{{ old('cidade_resp_endereco') }}" required class="mat-input" id="cidade-resp" maxlength="50" autocomplete="address-level2">
                        </div>
                    </div>
                    <div class="col-xs-4 col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Estado *</label>
                            <input type="text" name="estado_resp_endereco" value="{{ old('estado_resp_endereco') }}" required class="mat-input mat-uf" maxlength="2" placeholder="SP" id="estado-resp" autocomplete="address-level1" autocapitalize="characters">
                        </div>
                    </div>
                </div>
            </div>

            {{-- SUBMIT --}}
            <div style="text-align:center; padding: 10px 0 20px;">
                <button type="submit" class="mat-btn" id="btn-enviar-cadastro">
                    <span class="fa fa-paper-plane"></span> &nbsp;ENVIAR CADASTRO
                </button>
                <p style="margin-top:14px; color:#aaa; font-size:12px; letter-spacing:.3px;">
                    Após o envio, o responsável receberá um link no e-mail cadastrado para assinar a autorização.
                </p>
            </div>

        </form>
    </div>
</section>

<script>
function buscaCep(inputId, ruaId, bairroId, cidadeId, estadoId) {
    const cep = document.getElementById(inputId).value.replace(/\D/g,'');
    if (cep.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(d => {
            if (!d.erro) {
                document.getElementById(ruaId).value    = d.logradouro || '';
                document.getElementById(bairroId).value = d.bairro     || '';
                document.getElementById(cidadeId).value = d.localidade  || '';
                document.getElementById(estadoId).value = d.uf          || '';
            }
        });
}
document.getElementById('cep-atleta').addEventListener('blur', () =>
    buscaCep('cep-atleta','rua-atleta','bairro-atleta','cidade-atleta','estado-atleta'));
document.getElementById('cep-resp').addEventListener('blur', () =>
    buscaCep('cep-resp','rua-resp','bairro-resp','cidade-resp','estado-resp'));

/* ===== Máscaras dos campos CPF, RG e CEP ===== */
function aplicaMascaraCPF(el) {
    el.addEventListener('input', () => {
        let v = el.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        el.value = v;
    });
}
function aplicaMascaraCEP(el) {
    el.addEventListener('input', () => {
        let v = el.value.replace(/\D/g, '').slice(0, 8);
        v = v.replace(/(\d{5})(\d)/, '$1-$2');
        el.value = v;
    });
}
// RG com a pontuação que se adapta (10/11/2026; a mesma regra de Atleta::formatarRg no servidor): até 9 caracteres,
// o RG antigo 00.000.000-0 (o último pode ser X); 10 ou 11 dígitos, o RG novo com o número do CPF 000.000.000-00
function formataRg(valor) {
    let v = valor.toUpperCase().replace(/[^0-9X]/g, '');
    v = v.replace(/X(?=.)/g, '').slice(0, 11);   // o X só pode ser o último
    const partes = v.length <= 9
        ? [v.slice(0, 2), v.slice(2, 5), v.slice(5, 8), v.slice(8, 9)]
        : [v.slice(0, 3), v.slice(3, 6), v.slice(6, 9), v.slice(9, 11)];
    return partes[0] + (partes[1] ? '.' + partes[1] : '') + (partes[2] ? '.' + partes[2] : '') + (partes[3] ? '-' + partes[3] : '');
}
function aplicaMascaraRG(el) {
    el.addEventListener('input', () => { el.value = formataRg(el.value); });
    if (el.value) el.value = formataRg(el.value);   // o que voltou depois de um erro
}
function aplicaMascaraTelefone(el) {
    el.addEventListener('input', () => {
        let v = el.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/(\d{2})(\d{5})(\d{1,4})$/, '($1) $2-$3');
        } else if (v.length > 5) {
            v = v.replace(/(\d{2})(\d{4})(\d{1,4})$/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/(\d{2})(\d{1,5})$/, '($1) $2');
        } else if (v.length > 0) {
            v = v.replace(/(\d{1,2})/, '($1');
        }
        el.value = v;
    });
}

['cpf-atleta', 'cpf-resp'].forEach(id => aplicaMascaraCPF(document.getElementById(id)));
['cep-atleta', 'cep-resp'].forEach(id => aplicaMascaraCEP(document.getElementById(id)));
['rg-atleta', 'rg-resp'].forEach(id => aplicaMascaraRG(document.getElementById(id)));
['telefone-resp', 'whatsapp-resp'].forEach(id => aplicaMascaraTelefone(document.getElementById(id)));

/* ===== Peso, altura e UF (10/11/2026; as mesmas máscaras do painel) ===== */
// Peso em kg: até 3 dígitos inteiros e 2 decimais, com vírgula (55,5)
document.getElementById('peso-atleta').addEventListener('input', function () {
    const [inteiro, decimal] = this.value.replace('.', ',').replace(/[^\d,]/g, '').split(',');
    this.value = inteiro.slice(0, 3) + (decimal !== undefined ? ',' + decimal.slice(0, 2) : '');
});
// Altura em metros: digitando só números, a vírgula entra depois do primeiro (180 vira 1,80)
document.getElementById('altura-atleta').addEventListener('input', function () {
    const digitos = this.value.replace(/\D/g, '').slice(0, 3);
    this.value = digitos.length > 1 ? digitos[0] + ',' + digitos.slice(1) : digitos;
});
// UF: só letras, em maiúsculas
['estado-atleta', 'estado-resp'].forEach(id => document.getElementById(id).addEventListener('input', function () {
    this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 2);
}));

/* ===== Foto do atleta: prévia no círculo e aviso já ao escolher acima de 10 MB ===== */
(function () {
    const MAX_MB = 10;
    const arquivo = document.getElementById('foto-atleta');
    const previa = document.getElementById('foto-atleta-previa');
    const vazia = document.getElementById('foto-atleta-vazia');
    let url = null;

    function mostrar(fonte) {
        previa.hidden = !fonte;
        vazia.hidden = Boolean(fonte);
        if (fonte) previa.src = fonte; else previa.removeAttribute('src');
    }

    arquivo.addEventListener('change', function () {
        if (url) { URL.revokeObjectURL(url); url = null; }
        limpaErro(arquivo);
        const foto = arquivo.files && arquivo.files[0];

        if (foto && foto.size > MAX_MB * 1048576) {
            marcaErro(arquivo, 'Esta foto tem ' + (foto.size / 1048576).toFixed(1).replace('.', ',') + ' MB. Use uma foto de até ' + MAX_MB + ' MB.');
            arquivo.value = '';
            mostrar(null);
            return;
        }
        if (foto && !/^image\/(jpeg|png|webp)$/.test(foto.type)) {
            marcaErro(arquivo, 'A foto precisa ser uma imagem JPG, PNG ou WebP.');
            arquivo.value = '';
            mostrar(null);
            return;
        }
        url = foto ? URL.createObjectURL(foto) : null;
        mostrar(url);
    });
})();

/* ===== Validação de idade do atleta (9 a 17 anos) ===== */
function idadeValida(dataNasc) {
    if (!dataNasc) return false;
    const nascimento = new Date(dataNasc + 'T00:00:00');
    if (isNaN(nascimento.getTime())) return false;

    // Idade pelo ano, como as categorias: ano atual − ano de nascimento (o dia do aniversário não conta)
    const idade = new Date().getFullYear() - nascimento.getFullYear();

    return idade >= 9 && idade <= 17;
}

/* ===== Validação de CPF (dígitos verificadores) ===== */
function validaCPF(cpf) {
    cpf = String(cpf || '').replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

    let soma = 0;
    for (let i = 0; i < 9; i++) soma += parseInt(cpf.charAt(i), 10) * (10 - i);
    let resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9), 10)) return false;

    soma = 0;
    for (let i = 0; i < 10; i++) soma += parseInt(cpf.charAt(i), 10) * (11 - i);
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(10), 10)) return false;

    return true;
}

/* ===== Exibição de erros inline ===== */
function marcaErro(el, mensagem) {
    el.classList.add('mat-input-erro');
    let msg = el.parentElement.querySelector('.mat-error-msg');
    if (!msg) {
        msg = document.createElement('small');
        msg.className = 'mat-error-msg';
        el.parentElement.appendChild(msg);
    }
    msg.textContent = mensagem;
}
function limpaErro(el) {
    el.classList.remove('mat-input-erro');
    const msg = el.parentElement.querySelector('.mat-error-msg');
    if (msg) msg.remove();
}

/* ===== Validação no envio + bloqueio de duplo clique ===== */
const formCadastro = document.getElementById('form-cadastro');
const btnEnviarCadastro = document.getElementById('btn-enviar-cadastro');
let cadastroEnviando = false;

formCadastro.addEventListener('submit', function (e) {
    if (cadastroEnviando) {
        e.preventDefault();
        return;
    }

    let valido = true;

    const dataNascEl = document.getElementById('data-nasc-atleta');
    if (!idadeValida(dataNascEl.value)) {
        valido = false;
        marcaErro(dataNascEl, @js(\App\Models\Atleta::limitesNascimento()['mensagem']));
    } else {
        limpaErro(dataNascEl);
    }

    const whatsappEl = document.getElementById('whatsapp-resp');
    if (whatsappEl.value.replace(/\D/g, '').length < 10) {
        valido = false;
        marcaErro(whatsappEl, 'Informe um WhatsApp válido com DDD.');
    } else {
        limpaErro(whatsappEl);
    }

    // Cada um com o próprio e-mail (a mesma regra do servidor: sem diferenciar maiúsculas e espaços)
    const emailRespEl = document.getElementById('email-resp');
    const emailAtleta = document.getElementById('email-atleta').value.trim().toLowerCase();
    if (emailAtleta !== '' && emailAtleta === emailRespEl.value.trim().toLowerCase()) {
        valido = false;
        marcaErro(emailRespEl, 'O e-mail do responsável precisa ser diferente do e-mail do atleta.');
    } else {
        limpaErro(emailRespEl);
    }

    const telefoneEl = document.getElementById('telefone-resp');
    const telefoneDigitos = telefoneEl.value.replace(/\D/g, '');
    if (telefoneDigitos.length > 0 && telefoneDigitos.length < 10) {
        valido = false;
        marcaErro(telefoneEl, 'Informe um telefone válido com DDD.');
    } else {
        limpaErro(telefoneEl);
    }

    [document.getElementById('cpf-atleta'), document.getElementById('cpf-resp')].forEach(el => {
        if (!validaCPF(el.value)) {
            valido = false;
            marcaErro(el, 'CPF inválido.');
        } else {
            limpaErro(el);
        }
    });

    [document.getElementById('rg-atleta'), document.getElementById('rg-resp')].forEach(el => {
        const digitos = el.value.replace(/\D/g, '');
        if (digitos.length < 5) {
            valido = false;
            marcaErro(el, 'RG inválido.');
        } else {
            limpaErro(el);
        }
    });

    [document.getElementById('cep-atleta'), document.getElementById('cep-resp')].forEach(el => {
        if (el.value.replace(/\D/g, '').length !== 8) {
            valido = false;
            marcaErro(el, 'CEP inválido.');
        } else {
            limpaErro(el);
        }
    });

    // Peso e altura são opcionais; preenchidos, nas mesmas faixas do servidor (Atleta::PESO_* e ALTURA_*)
    [
        [document.getElementById('peso-atleta'), {{ \App\Models\Atleta::PESO_MIN }}, {{ \App\Models\Atleta::PESO_MAX }}, 'O peso precisa ficar entre {{ \App\Models\Atleta::PESO_MIN }} e {{ \App\Models\Atleta::PESO_MAX }} kg.'],
        [document.getElementById('altura-atleta'), {{ \App\Models\Atleta::ALTURA_MIN }}, {{ \App\Models\Atleta::ALTURA_MAX }}, 'Informe a altura em metros (ex.: 1,80), entre 0,80 e 2,30.'],
    ].forEach(([el, min, max, mensagem]) => {
        const valor = parseFloat(el.value.replace(',', '.'));
        if (el.value.trim() !== '' && (isNaN(valor) || valor < min || valor > max)) {
            valido = false;
            marcaErro(el, mensagem);
        } else {
            limpaErro(el);
        }
    });

    [document.getElementById('estado-atleta'), document.getElementById('estado-resp')].forEach(el => {
        if (!/^[A-Z]{2}$/.test(el.value.trim().toUpperCase())) {
            valido = false;
            marcaErro(el, 'Informe a UF com 2 letras (ex.: SP).');
        } else {
            limpaErro(el);
        }
    });

    if (!valido) {
        e.preventDefault();
        const primeiroErro = formCadastro.querySelector('.mat-input-erro');
        if (primeiroErro) primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    cadastroEnviando = true;
    btnEnviarCadastro.disabled = true;
    btnEnviarCadastro.innerHTML = '<span class="fa fa-spinner fa-spin"></span> &nbsp;ENVIANDO...';
});
</script>

@endsection
