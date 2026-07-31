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
                            <input type="text" name="nome_atleta" value="{{ old('nome_atleta') }}" required class="mat-input">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Data de Nascimento *</label>
                            <input type="date" name="data_nasc_atleta" value="{{ old('data_nasc_atleta') }}" required class="mat-input" id="data-nasc-atleta"
                                min="{{ now()->subYears(18)->addDay()->format('Y-m-d') }}"
                                max="{{ now()->subYears(9)->format('Y-m-d') }}">
                            <p class="mat-hint">Idade permitida: 9 a 17 anos</p>
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
                            <input type="text" name="rg_atleta" value="{{ old('rg_atleta') }}" required class="mat-input" id="rg-atleta" maxlength="15" autocomplete="off">
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

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Peso (kg)</label>
                            <input type="number" step="0.01" name="peso_atleta" value="{{ old('peso_atleta') }}" class="mat-input" placeholder="Ex: 55.00">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Altura (m)</label>
                            <input type="number" step="0.01" name="altura_atleta" value="{{ old('altura_atleta') }}" class="mat-input" placeholder="Ex: 1.70">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="mat-group">
                            <label class="mat-label">Foto do Atleta</label>
                            <input type="file" name="foto_atleta" class="mat-input" accept="image/*" style="padding:7px 14px;">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Escola *</label>
                            <input type="text" name="escola_atleta" value="{{ old('escola_atleta') }}" required class="mat-input">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Série *</label>
                            <input type="text" name="serie_atleta" value="{{ old('serie_atleta') }}" required class="mat-input" placeholder="Ex: 7º Ano">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Período *</label>
                            <select name="periodo_escolar_atleta" required class="mat-input">
                                <option value="">Selecione</option>
                                <option value="Manhã" {{ old('periodo_escolar_atleta')=='Manhã'?'selected':'' }}>Manhã</option>
                                <option value="Tarde" {{ old('periodo_escolar_atleta')=='Tarde'?'selected':'' }}>Tarde</option>
                                <option value="Noite" {{ old('periodo_escolar_atleta')=='Noite'?'selected':'' }}>Noite</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Sala</label>
                            <input type="text" name="sala_atleta" value="{{ old('sala_atleta') }}" class="mat-input" placeholder="Ex: A1">
                        </div>
                    </div>
                </div>

                <div class="mat-group">
                    <label class="mat-label">Descrição / Observações</label>
                    <textarea name="descricao_atleta" rows="3" class="mat-input">{{ old('descricao_atleta') }}</textarea>
                </div>

                <div class="mat-group">
                    <label class="mat-label">E-mail *</label>
                    <input type="email" name="email_atleta" value="{{ old('email_atleta') }}" required class="mat-input">
                </div>
            </div>

            {{-- ENDEREÇO DO ATLETA --}}
            <div class="mat-section">
                <h3><span class="fa fa-map-marker"></span> Endereço do Atleta</h3>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_endereco" value="{{ old('cep_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-atleta" maxlength="9" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div class="mat-group">
                            <label class="mat-label">Rua *</label>
                            <input type="text" name="rua_endereco" value="{{ old('rua_endereco') }}" required class="mat-input" id="rua-atleta">
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Número *</label>
                            <input type="text" name="numero_endereco" value="{{ old('numero_endereco') }}" required class="mat-input">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Bairro *</label>
                            <input type="text" name="bairro_endereco" value="{{ old('bairro_endereco') }}" required class="mat-input" id="bairro-atleta">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="mat-group">
                            <label class="mat-label">Cidade *</label>
                            <input type="text" name="cidade_endereco" value="{{ old('cidade_endereco') }}" required class="mat-input" id="cidade-atleta">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Estado *</label>
                            <input type="text" name="estado_endereco" value="{{ old('estado_endereco') }}" required class="mat-input" maxlength="2" placeholder="SP" id="estado-atleta">
                        </div>
                    </div>
                </div>

                <div class="mat-group">
                    <label class="mat-label">Complemento</label>
                    <input type="text" name="complemento_endereco" value="{{ old('complemento_endereco') }}" class="mat-input" placeholder="Apto, Bloco, Casa, etc.">
                </div>
            </div>

            {{-- DADOS DO RESPONSÁVEL --}}
            <div class="mat-section">
                <h3><span class="fa fa-users"></span> Dados do Responsável</h3>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="mat-group">
                            <label class="mat-label">Nome Completo *</label>
                            <input type="text" name="nome_responsavel" value="{{ old('nome_responsavel') }}" required class="mat-input">
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
                            <input type="text" name="rg_responsavel" value="{{ old('rg_responsavel') }}" required class="mat-input" id="rg-resp" maxlength="15" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Grau de Parentesco *</label>
                            <select name="grau_parentesco" required class="mat-input">
                                <option value="">Selecione</option>
                                <option value="Pai"        {{ old('grau_parentesco')=='Pai'?'selected':'' }}>Pai</option>
                                <option value="Mãe"        {{ old('grau_parentesco')=='Mãe'?'selected':'' }}>Mãe</option>
                                <option value="Avô"        {{ old('grau_parentesco')=='Avô'?'selected':'' }}>Avô</option>
                                <option value="Avó"        {{ old('grau_parentesco')=='Avó'?'selected':'' }}>Avó</option>
                                <option value="Tutor Legal"{{ old('grau_parentesco')=='Tutor Legal'?'selected':'' }}>Tutor Legal</option>
                                <option value="Outro"      {{ old('grau_parentesco')=='Outro'?'selected':'' }}>Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">E-mail *</label>
                            <input type="email" name="email_responsavel" value="{{ old('email_responsavel') }}" required class="mat-input">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Telefone</label>
                            <input type="text" name="telefone_responsavel" value="{{ old('telefone_responsavel') }}" class="mat-input" placeholder="(00) 0000-0000" id="telefone-resp" maxlength="15" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">WhatsApp *</label>
                            <input type="text" name="whatsapp_responsavel" value="{{ old('whatsapp_responsavel') }}" required class="mat-input" placeholder="(00) 00000-0000" id="whatsapp-resp" maxlength="15" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                </div>

                <hr class="mat-divider">
                <p class="mat-subtitle"><span class="fa fa-map-marker"></span> Endereço do Responsável</p>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_resp_endereco" value="{{ old('cep_resp_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-resp" maxlength="9" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-sm-7">
                        <div class="mat-group">
                            <label class="mat-label">Rua *</label>
                            <input type="text" name="rua_resp_endereco" value="{{ old('rua_resp_endereco') }}" required class="mat-input" id="rua-resp">
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="mat-group">
                            <label class="mat-label">Número *</label>
                            <input type="text" name="numero_resp_endereco" value="{{ old('numero_resp_endereco') }}" required class="mat-input">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Bairro *</label>
                            <input type="text" name="bairro_resp_endereco" value="{{ old('bairro_resp_endereco') }}" required class="mat-input" id="bairro-resp">
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="mat-group">
                            <label class="mat-label">Cidade *</label>
                            <input type="text" name="cidade_resp_endereco" value="{{ old('cidade_resp_endereco') }}" required class="mat-input" id="cidade-resp">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Estado *</label>
                            <input type="text" name="estado_resp_endereco" value="{{ old('estado_resp_endereco') }}" required class="mat-input" maxlength="2" placeholder="SP" id="estado-resp">
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
function aplicaMascaraRG(el) {
    el.addEventListener('input', () => {
        el.value = el.value.replace(/[^0-9Xx.\-\s]/g, '').slice(0, 15);
    });
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

/* ===== Validação de idade do atleta (9 a 17 anos) ===== */
function idadeValida(dataNasc) {
    if (!dataNasc) return false;
    const nascimento = new Date(dataNasc + 'T00:00:00');
    if (isNaN(nascimento.getTime())) return false;

    const hoje = new Date();
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    const aindaNaoFezAniversario =
        (hoje.getMonth() < nascimento.getMonth()) ||
        (hoje.getMonth() === nascimento.getMonth() && hoje.getDate() < nascimento.getDate());
    if (aindaNaoFezAniversario) idade--;

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
        marcaErro(dataNascEl, 'A idade do atleta deve estar entre 9 e 17 anos.');
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
