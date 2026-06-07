@extends('layout.admin')

@section('content')
  <main class="app-main">

    <div class="app-content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-6">
            <h3 class="mb-0">Atletas</h3>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
              <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
              <li class="breadcrumb-item active">Atletas</li>
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

        @if(session('erro'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('erro') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        <div class="card shadow-sm border-0">
          <div class="card-body p-0">

            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
              <span class="text-muted small fw-semibold">Gerenciamento de atletas</span>
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#modalNovoAtleta">
                <i class="bi bi-person-plus me-1"></i> Novo Atleta
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-4 text-uppercase small text-muted fw-semibold" style="width:70px;">Foto</th>
                    <th class="text-uppercase small text-muted fw-semibold">Nome</th>
                    <th class="text-uppercase small text-muted fw-semibold">Responsável</th>
                    <th class="text-uppercase small text-muted fw-semibold">Categoria</th>
                    <th class="text-uppercase small text-muted fw-semibold">Posição</th>
                    <th class="text-uppercase small text-muted fw-semibold">Status</th>
                    <th class="text-uppercase small text-muted fw-semibold pe-4">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($atletas as $atleta)
                    @php
                      $partes = explode(' ', trim($atleta->nome_atleta));
                      $iniciais = strtoupper(substr($partes[0], 0, 1) . (count($partes) > 1 ? substr(end($partes), 0, 1) : ''));
                      $paleta = ['#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0', '#2ec4b6', '#e76f51', '#457b9d'];
                      $corAvatar = $paleta[abs(crc32($atleta->nome_atleta)) % count($paleta)];
                      $avatarStyle = "width:42px;height:42px;background:{$corAvatar};font-size:0.8rem;flex-shrink:0;";
                      $ativo = strtolower($atleta->status_atleta ?? '') === 'ativo';

                      $categoria = $atleta->categorias->first();
                      $nomeCategoria = $categoria->nome_categoria ?? null;
                      $responsavel = $atleta->responsaveis->first();
                      $nomeResponsavel = $responsavel->nome_responsavel ?? null;
                      $grauParentesco = $responsavel?->pivot->grau_parentesco_responsavel ?? null;
                      $time = $atleta->times->first();
                      $posicao = $time?->pivot->posicao_atleta_time ?? null;

                      $corCategoria = match (true) {
                        str_contains($nomeCategoria ?? '', '11') => 'secondary',
                        str_contains($nomeCategoria ?? '', '13') => 'success',
                        str_contains($nomeCategoria ?? '', '15') => 'primary',
                        str_contains($nomeCategoria ?? '', '17') => 'warning',
                        str_contains($nomeCategoria ?? '', '19') => 'info',
                        default => 'secondary',
                      };
                    @endphp
                    <tr>
                      {{-- Foto --}}
                      <td class="ps-4">
                        @if($atleta->foto_atleta)
                          <img src="{{ asset('futebol/images/atleta/' . $atleta->foto_atleta) }}"
                            alt="{{ $atleta->nome_atleta }}" class="rounded-circle object-fit-cover"
                            style="width:42px;height:42px;">
                        @else
                          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                            style="{{ $avatarStyle }}">
                            {{ $iniciais }}
                          </div>
                        @endif
                      </td>

                      {{-- Nome --}}
                      <td>
                        <div class="fw-semibold" style="line-height:1.3;">{{ $atleta->nome_atleta }}</div>
                        @if($atleta->numero_atleta)
                          <div class="text-muted" style="font-size:0.78rem;">Nº {{ $atleta->numero_atleta }}</div>
                        @endif
                      </td>

                      {{-- Responsável --}}
                      <td>
                        @if($nomeResponsavel)
                          <div class="small fw-semibold" style="line-height:1.3;">{{ $nomeResponsavel }}</div>
                          @if($grauParentesco)
                            <div class="text-muted" style="font-size:0.75rem;">{{ $grauParentesco }}</div>
                          @endif
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>

                      {{-- Categoria --}}
                      <td>
                        @if($nomeCategoria)
                          <span class="badge rounded-pill bg-{{ $corCategoria }}">{{ $nomeCategoria }}</span>
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>

                      {{-- Posição --}}
                      <td>
                        <span class="text-muted small">{{ $posicao ?? '—' }}</span>
                      </td>

                      {{-- Status --}}
                      <td>
                        @if($ativo)
                          <span class="badge bg-success rounded-pill px-3">Ativo</span>
                        @else
                          <span class="badge bg-danger rounded-pill px-3">Inativo</span>
                        @endif
                      </td>

                      {{-- Ações --}}
                      <td class="pe-4">
                        <div class="d-flex gap-2 align-items-center">
                          <a href="{{ route('admin.atletas.edit', $atleta->id_atleta) }}" class="btn btn-sm btn-warning"
                            title="Editar atleta">
                            <i class="bi bi-pencil-fill"></i>
                          </a>
                          <form action="{{ route('admin.atletas.toggleStatus', $atleta->id_atleta) }}" method="POST"
                            class="m-0">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                              class="btn btn-sm {{ $ativo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                              title="{{ $ativo ? 'Desativar' : 'Ativar' }}">
                              <i class="bi {{ $ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                        Nenhum atleta cadastrado ainda.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>

          </div>
        </div>

      </div>
    </div>
  </main>

  {{-- MODAL NOVO ATLETA --}}
  <div class="modal fade {{ $errors->any() ? 'show' : '' }}" id="modalNovoAtleta" tabindex="-1"
    aria-labelledby="modalNovoAtletaLabel" @if($errors->any()) style="display:block;" aria-modal="true" role="dialog"
    @endif>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header bg-body-tertiary">
          <h5 class="modal-title fw-semibold" id="modalNovoAtletaLabel">
            <i class="bi bi-person-plus me-2"></i>Cadastrar novo atleta
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <form action="{{ route('admin.atletas.store') }}" method="POST" enctype="multipart/form-data" novalidate>
          @csrf
          <div class="modal-body px-4" style="max-height:65vh;overflow-y:auto;">

            <p class="text-uppercase text-primary fw-semibold small mb-3" style="letter-spacing:.06em;">
              <i class="bi bi-person-circle me-1"></i> Dados do Atleta
            </p>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Nome Completo</label>
                <input type="text" name="nome_atleta" class="form-control @error('nome_atleta') is-invalid @enderror"
                  placeholder="Ex: Gabriel Silva" required value="{{ old('nome_atleta') }}">
                @error('nome_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Data de Nascimento</label>
                <input type="date" name="data_nasc_atleta"
                  class="form-control @error('data_nasc_atleta') is-invalid @enderror" required
                  value="{{ old('data_nasc_atleta') }}">
                @error('data_nasc_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">CPF</label>
                <input type="text" name="cpf_atleta" class="form-control @error('cpf_atleta') is-invalid @enderror"
                  placeholder="000.000.000-00" maxlength="14" required value="{{ old('cpf_atleta') }}">
                @error('cpf_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">RG</label>
                <input type="text" name="rg_atleta" class="form-control @error('rg_atleta') is-invalid @enderror"
                  placeholder="00.000.000-0" maxlength="12" required value="{{ old('rg_atleta') }}">
                @error('rg_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Categoria</label>
                <select name="id_categoria" class="form-select">
                  <option value="">— Selecionar —</option>
                  @foreach($categorias as $cat)
                    <option value="{{ $cat->id_categoria }}" {{ old('id_categoria') == $cat->id_categoria ? 'selected' : '' }}>
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
                    <option value="{{ $pos }}" {{ old('posicao') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Nº Camisa / Matrícula</label>
                <input type="text" name="numero_atleta" class="form-control" placeholder="Ex: 10" maxlength="10"
                  value="{{ old('numero_atleta') }}">
              </div>

              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Sexo</label>
                <select name="sexo_atleta" class="form-select" required>
                  <option value="">— Selecionar —</option>
                  <option value="M" {{ old('sexo_atleta') == 'M' ? 'selected' : '' }}>Masculino</option>
                  <option value="F" {{ old('sexo_atleta') == 'F' ? 'selected' : '' }}>Feminino</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Peso (kg)</label>
                <input type="number" name="peso_atleta" class="form-control" placeholder="Ex: 55.00"
                  step="0.01" min="0" value="{{ old('peso_atleta') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Altura (m)</label>
                <input type="number" name="altura_atleta" class="form-control" placeholder="Ex: 1.70"
                  step="0.01" min="0" value="{{ old('altura_atleta') }}">
              </div>

              <div class="col-md-5">
                <label class="form-label small text-uppercase fw-semibold text-muted">Escola</label>
                <input type="text" name="escola_atleta" class="form-control @error('escola_atleta') is-invalid @enderror"
                  placeholder="Nome da escola" required value="{{ old('escola_atleta') }}">
                @error('escola_atleta')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">Série</label>
                <input type="text" name="serie_atleta" class="form-control" placeholder="Ex: 7º Ano"
                  value="{{ old('serie_atleta') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small text-uppercase fw-semibold text-muted">Período</label>
                <select name="periodo_escolar_atleta" class="form-select">
                  <option value="">— Selecionar —</option>
                  @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $per)
                    <option value="{{ $per }}" {{ old('periodo_escolar_atleta') == $per ? 'selected' : '' }}>{{ $per }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Foto</label>
                <input type="file" name="foto_atleta" class="form-control" accept="image/*">
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Descrição / Observações</label>
                <input type="text" name="descricao_atleta" class="form-control" placeholder="Observações opcionais"
                  value="{{ old('descricao_atleta') }}">
              </div>
            </div>

            <hr class="my-1">

            <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
              <i class="bi bi-person-check me-1"></i> Responsável
            </p>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Nome do Responsável</label>
                <input type="text" name="nome_responsavel"
                  class="form-control @error('nome_responsavel') is-invalid @enderror" placeholder="Ex: João Silva"
                  required value="{{ old('nome_responsavel') }}">
                @error('nome_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Grau de Parentesco</label>
                <select name="grau_parentesco_responsavel"
                  class="form-select @error('grau_parentesco_responsavel') is-invalid @enderror" required>
                  <option value="">— Selecionar —</option>
                  @foreach(['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'] as $grau)
                    <option value="{{ $grau }}" {{ old('grau_parentesco_responsavel') == $grau ? 'selected' : '' }}>
                      {{ $grau }}</option>
                  @endforeach
                </select>
                @error('grau_parentesco_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">WhatsApp</label>
                <input type="text" name="whatsapp_responsavel"
                  class="form-control @error('whatsapp_responsavel') is-invalid @enderror" placeholder="(11) 99999-9999"
                  required value="{{ old('whatsapp_responsavel') }}">
                @error('whatsapp_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">CPF do Responsável</label>
                <input type="text" name="cpf_responsavel"
                  class="form-control @error('cpf_responsavel') is-invalid @enderror" placeholder="000.000.000-00"
                  maxlength="14" required value="{{ old('cpf_responsavel') }}">
                @error('cpf_responsavel')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <hr class="my-1">

            <p class="text-uppercase text-primary fw-semibold small mb-3 mt-4" style="letter-spacing:.06em;">
              <i class="bi bi-geo-alt me-1"></i> Endereço
            </p>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label small text-uppercase fw-semibold text-muted">CEP</label>
                <input type="text" name="cep_endereco" class="form-control @error('cep_endereco') is-invalid @enderror"
                  placeholder="00000-000" maxlength="9" required value="{{ old('cep_endereco') }}">
                @error('cep_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-8">
                <label class="form-label small text-uppercase fw-semibold text-muted">Cidade</label>
                <input type="text" name="cidade_endereco"
                  class="form-control @error('cidade_endereco') is-invalid @enderror" placeholder="São Paulo" required
                  value="{{ old('cidade_endereco') }}">
                @error('cidade_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Rua</label>
                <input type="text" name="rua_endereco" class="form-control @error('rua_endereco') is-invalid @enderror"
                  placeholder="Nome da rua" required value="{{ old('rua_endereco') }}">
                @error('rua_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-3">
                <label class="form-label small text-uppercase fw-semibold text-muted">Número</label>
                <input type="text" name="numero_endereco"
                  class="form-control @error('numero_endereco') is-invalid @enderror" placeholder="123" required
                  value="{{ old('numero_endereco') }}">
                @error('numero_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-3">
                <label class="form-label small text-uppercase fw-semibold text-muted">Estado (UF)</label>
                <input type="text" name="estado_endereco"
                  class="form-control @error('estado_endereco') is-invalid @enderror" placeholder="SP" maxlength="2"
                  required value="{{ old('estado_endereco') }}">
                @error('estado_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Bairro</label>
                <input type="text" name="bairro_endereco"
                  class="form-control @error('bairro_endereco') is-invalid @enderror" placeholder="Nome do bairro"
                  required value="{{ old('bairro_endereco') }}">
                @error('bairro_endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label small text-uppercase fw-semibold text-muted">Complemento</label>
                <input type="text" name="complemento_endereco" class="form-control"
                  placeholder="Apto, bloco... (opcional)" value="{{ old('complemento_endereco') }}">
              </div>
            </div>

          </div>

          <div class="modal-footer bg-body-tertiary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-person-check me-1"></i> Salvar atleta
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
  @if($errors->any())
    <div class="modal-backdrop fade show"></div>
  @endif

@endsection