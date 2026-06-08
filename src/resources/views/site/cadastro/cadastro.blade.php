@extends('layout.site')

@section('content')

<section style="padding: 150px 0 60px; background: #f5f5f5;">
    <div class="container">

        @if(session('sucesso'))
        <div class="alert-ok">
            <strong><span class="fa fa-check-circle"></span> {{ session('sucesso') }}</strong>
        </div>
        @endif

        @if(session('link_whatsapp'))
        <div class="whatsapp-box">
            <p><span class="fa fa-whatsapp"></span> Envie o link de assinatura ao responsável:</p>
            <a href="{{ session('link_whatsapp') }}" target="_blank">
                <span class="fa fa-whatsapp"></span> Abrir WhatsApp
            </a>
            <small style="display:block; margin-top:8px; color:#555; font-size:11px;">
                Link direto: {{ session('link_assinatura') }}
            </small>
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

        <form action="{{ route('cadastro.store') }}" method="POST" enctype="multipart/form-data">
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
                            <input type="date" name="data_nasc_atleta" value="{{ old('data_nasc_atleta') }}" required class="mat-input">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">CPF *</label>
                            <input type="text" name="cpf_atleta" value="{{ old('cpf_atleta') }}" required class="mat-input" placeholder="000.000.000-00">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">RG *</label>
                            <input type="text" name="rg_atleta" value="{{ old('rg_atleta') }}" required class="mat-input">
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
                    <div class="col-sm-5">
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
                    <div class="col-sm-4">
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
                </div>

                <div class="mat-group">
                    <label class="mat-label">Descrição / Observações</label>
                    <textarea name="descricao_atleta" rows="3" class="mat-input">{{ old('descricao_atleta') }}</textarea>
                </div>

                <hr class="mat-divider">
                <p class="mat-subtitle"><span class="fa fa-lock"></span> Acesso à Área do Aluno</p>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="mat-group">
                            <label class="mat-label">E-mail *</label>
                            <input type="email" name="email_atleta" value="{{ old('email_atleta') }}" required class="mat-input">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Senha *</label>
                            <input type="password" name="password" required class="mat-input" minlength="8">
                            <p class="mat-hint">Mínimo 8 caracteres</p>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">Confirmar Senha *</label>
                            <input type="password" name="password_confirmation" required class="mat-input">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ENDEREÇO DO ATLETA --}}
            <div class="mat-section">
                <h3><span class="fa fa-map-marker"></span> Endereço do Atleta</h3>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_endereco" value="{{ old('cep_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-atleta">
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
                            <input type="text" name="cpf_responsavel" value="{{ old('cpf_responsavel') }}" required class="mat-input" placeholder="000.000.000-00">
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">RG *</label>
                            <input type="text" name="rg_responsavel" value="{{ old('rg_responsavel') }}" required class="mat-input">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-4">
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
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">Telefone</label>
                            <input type="text" name="telefone_responsavel" value="{{ old('telefone_responsavel') }}" class="mat-input" placeholder="(00) 0000-0000">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="mat-group">
                            <label class="mat-label">WhatsApp *</label>
                            <input type="text" name="whatsapp_responsavel" value="{{ old('whatsapp_responsavel') }}" required class="mat-input" placeholder="(00) 00000-0000">
                            <p class="mat-hint"><span class="fa fa-whatsapp"></span> O link de assinatura será enviado aqui</p>
                        </div>
                    </div>
                </div>

                <hr class="mat-divider">
                <p class="mat-subtitle"><span class="fa fa-map-marker"></span> Endereço do Responsável</p>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="mat-group">
                            <label class="mat-label">CEP *</label>
                            <input type="text" name="cep_resp_endereco" value="{{ old('cep_resp_endereco') }}" required class="mat-input" placeholder="00000-000" id="cep-resp">
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
                <button type="submit" class="mat-btn">
                    <span class="fa fa-paper-plane"></span> &nbsp;ENVIAR CADASTRO
                </button>
                <p style="margin-top:14px; color:#aaa; font-size:12px; letter-spacing:.3px;">
                    Após o envio, o responsável receberá um link no WhatsApp para assinar a autorização.
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
</script>

@endsection
