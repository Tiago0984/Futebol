@extends('layout.admin')

@section('content')
  <main class="app-main">

    <div class="app-content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-6">
            <h3 class="mb-0">Editar Atleta</h3>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
              <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
              <li class="breadcrumb-item"><a href="{{ route('admin.atletas.index') }}">Atletas</a></li>
              <li class="breadcrumb-item active">Editar</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="app-content">
      <div class="container-fluid">

        @if(session('sucesso'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('sucesso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @if($errors->any())
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Corrija os erros abaixo:</strong>
            <ul class="mb-0 mt-1">
              @foreach($errors->all() as $erro)
                <li>{{ $erro }}</li>
              @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        <form action="{{ route('admin.atletas.update', $atleta->id_atleta) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <div class="card shadow-sm border-0">
            <div class="card-body px-4 py-4">

              {{-- DADOS DO ATLETA --}}
              <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
                <i class="bi bi-person-circle me-1"></i> Dados do Atleta
              </p>

              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Nome Completo</label>
                  <input type="text" name="nome_atleta" class="form-control @error('nome_atleta') is-invalid @enderror"
                    value="{{ old('nome_atleta', $atleta->nome_atleta) }}" required>
                  @error('nome_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Data de Nascimento</label>
                  <input type="date" name="data_nasc_atleta"
                    class="form-control @error('data_nasc_atleta') is-invalid @enderror"
                    value="{{ old('data_nasc_atleta', $atleta->data_nasc_atleta) }}" required>
                  @error('data_nasc_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">CPF</label>
                  <input type="text" name="cpf_atleta" class="form-control @error('cpf_atleta') is-invalid @enderror"
                    value="{{ old('cpf_atleta', $atleta->cpf_atleta) }}" maxlength="14" required>
                  @error('cpf_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">RG</label>
                  <input type="text" name="rg_atleta" class="form-control @error('rg_atleta') is-invalid @enderror"
                    value="{{ old('rg_atleta', $atleta->rg_atleta) }}" maxlength="12" required>
                  @error('rg_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Categoria</label>
                  <select name="id_categoria" class="form-select">
                    <option value="">— Selecionar —</option>
                    @foreach($categorias as $cat)
                      <option value="{{ $cat->id_categoria }}" {{ old('id_categoria', $atleta->id_categoria_atual) == $cat->id_categoria ? 'selected' : '' }}>
                        {{ $cat->nome_categoria }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Posição</label>
                  <select name="posicao" class="form-select">
                    <option value="">— Selecionar —</option>
                    @foreach(['Goleiro', 'Zagueiro', 'Lateral', 'Volante', 'Meia', 'Atacante'] as $pos)
                      <option value="{{ $pos }}" {{ old('posicao', $atleta->posicao_atleta_time ?? '') == $pos ? 'selected' : '' }}>
                        {{ $pos }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Nº Camisa / Matrícula</label>
                  <input type="text" name="numero_atleta" class="form-control"
                    value="{{ old('numero_atleta', $atleta->numero_atleta) }}" maxlength="10">
                </div>

                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Escola</label>
                  <input type="text" name="escola_atleta"
                    class="form-control @error('escola_atleta') is-invalid @enderror"
                    value="{{ old('escola_atleta', $atleta->escola_atleta) }}" required>
                  @error('escola_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Status</label>
                  <select name="status_atleta" class="form-select" required>
                    <option value="Ativo" {{ old('status_atleta', $atleta->status_atleta) === 'Ativo' ? 'selected' : '' }}>
                      Ativo</option>
                    <option value="Inativo" {{ old('status_atleta', $atleta->status_atleta) === 'Inativo' ? 'selected' : '' }}>Inativo</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Foto</label>
                  <input type="file" name="foto_atleta" class="form-control" accept="image/*">
                  @if($atleta->foto_atleta)
                    <div class="form-text">
                      <img src="{{ asset('futebol/images/atleta/' . $atleta->foto_atleta) }}" class="rounded-circle mt-1" width="32"
                        height="32" style="object-fit:cover;">
                      Foto atual
                    </div>
                  @endif
                </div>
              </div>

              <hr class="my-1">

              {{-- RESPONSÁVEL --}}
              <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
                <i class="bi bi-person-check me-1"></i> Responsável
              </p>

              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Nome do Responsável</label>
                  <input type="text" name="nome_responsavel"
                    class="form-control @error('nome_responsavel') is-invalid @enderror"
                    value="{{ old('nome_responsavel', $atleta->nome_responsavel) }}" required>
                  @error('nome_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Grau de Parentesco</label>
                  <select name="grau_parentesco_responsavel"
                    class="form-select @error('grau_parentesco_responsavel') is-invalid @enderror" required>
                    <option value="">— Selecionar —</option>
                    @foreach(['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                      <option value="{{ $grau }}" {{ old('grau_parentesco_responsavel', $atleta->grau_parentesco_responsavel) == $grau ? 'selected' : '' }}>
                        {{ $grau }}
                      </option>
                    @endforeach
                  </select>
                  @error('grau_parentesco_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">WhatsApp</label>
                  <input type="text" name="whatsapp_responsavel"
                    class="form-control @error('whatsapp_responsavel') is-invalid @enderror"
                    value="{{ old('whatsapp_responsavel', $atleta->whatsapp_responsavel) }}" required>
                  @error('whatsapp_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-uppercase fw-semibold text-muted">CPF do Responsável</label>
                  <input type="text" name="cpf_responsavel"
                    class="form-control @error('cpf_responsavel') is-invalid @enderror"
                    value="{{ old('cpf_responsavel', $atleta->cpf_responsavel) }}" maxlength="14" required>
                  @error('cpf_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>

              <hr class="my-1">

              {{-- ENDEREÇO --}}
              <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
                <i class="bi bi-geo-alt me-1"></i> Endereço
              </p>

              <div class="row g-3 mb-2">
                <div class="col-md-3">
                  <label class="form-label small text-uppercase fw-semibold text-muted">CEP</label>
                  <input type="text" name="cep_endereco" class="form-control @error('cep_endereco') is-invalid @enderror"
                    value="{{ old('cep_endereco', $atleta->cep_endereco) }}" maxlength="9" required>
                  @error('cep_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Rua</label>
                  <input type="text" name="rua_endereco" class="form-control @error('rua_endereco') is-invalid @enderror"
                    value="{{ old('rua_endereco', $atleta->rua_endereco) }}" required>
                  @error('rua_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Número</label>
                  <input type="text" name="numero_endereco"
                    class="form-control @error('numero_endereco') is-invalid @enderror"
                    value="{{ old('numero_endereco', $atleta->numero_endereco) }}" required>
                  @error('numero_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                  <label class="form-label small text-uppercase fw-semibold text-muted">UF</label>
                  <input type="text" name="estado_endereco"
                    class="form-control @error('estado_endereco') is-invalid @enderror"
                    value="{{ old('estado_endereco', $atleta->estado_endereco) }}" maxlength="2" required>
                  @error('estado_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Bairro</label>
                  <input type="text" name="bairro_endereco"
                    class="form-control @error('bairro_endereco') is-invalid @enderror"
                    value="{{ old('bairro_endereco', $atleta->bairro_endereco) }}" required>
                  @error('bairro_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Cidade</label>
                  <input type="text" name="cidade_endereco"
                    class="form-control @error('cidade_endereco') is-invalid @enderror"
                    value="{{ old('cidade_endereco', $atleta->cidade_endereco) }}" required>
                  @error('cidade_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-uppercase fw-semibold text-muted">Complemento</label>
                  <input type="text" name="complemento_endereco" class="form-control"
                    value="{{ old('complemento_endereco', $atleta->complemento_endereco) }}"
                    placeholder="Apto, bloco... (opcional)">
                </div>
              </div>

            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center px-4">
              <a href="{{ route('admin.atletas.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Salvar alterações
              </button>
            </div>
          </div>

        </form>

      </div>
    </div>
  </main>
@endsection