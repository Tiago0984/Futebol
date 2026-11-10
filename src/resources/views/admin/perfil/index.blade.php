@extends('layout.admin')

@section('title', 'Meu perfil')

{{--
    Meu perfil (10/11/2026; Admin\PerfilController): os dados de quem está logado (foto com prévia, nome, e-mail) e a troca
    de senha com a atual. O nível de acesso só aparece: é definido por um administrador. Sem o cargo (dono do projeto,
    10/11/2026: o cargo é da comissão técnica, e só o administrador o define).
--}}
@section('content')
@php $senhaErros = $errors->getBag('senha'); @endphp
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho titulo="Meu perfil" :caminho="[['Configurações', route('admin.configuracoes.index')], ['Meu perfil', null]]"
                       :voltar="route('admin.configuracoes.index')">
      <x-slot:subtitulo>Seus dados no painel e a sua senha.</x-slot:subtitulo>
    </x-admin.cabecalho>

    @if (session('sucesso'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
      <i class="bi bi-check-circle"></i> {{ session('sucesso') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    @endif

    <div class="row g-4">
      <div class="col-lg-7">
        <x-admin.secao titulo="Meus dados" icone="person-vcard" id="meusDados">
          @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
          </div>
          @endif

          <form method="POST" action="{{ route('admin.perfil.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="d-flex align-items-center gap-3 mb-3 js-campo-foto-perfil" data-max-kb="{{ \App\Services\TratadorDeImagem::ENVIO_MAX_KB }}">
              <img src="{{ $usuario->urlFoto() }}" alt="Sua foto" class="perfil-foto js-foto-perfil-previa" data-atual="{{ $usuario->urlFoto() }}">
              <div class="flex-grow-1 min-w-0">
                <label class="form-label" for="pf_foto">Foto</label>
                <input type="file" id="pf_foto" name="foto_usuario" class="form-control js-foto-perfil-arquivo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">Aparece no topo do painel. Recortada em quadrado, até 10 MB.</div>
                <div class="alert alert-danger py-1 px-2 small mt-1 mb-0 js-foto-perfil-erro" hidden></div>
                @if ($usuario->foto_usuario)
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" name="remover_foto" value="1" id="pf_remover">
                  <label class="form-check-label small" for="pf_remover">Remover a foto (volta ao avatar padrão)</label>
                </div>
                @endif
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="pf_nome">Nome <span class="text-danger">*</span></label>
                <input type="text" id="pf_nome" name="nome_usuario" class="form-control" maxlength="255" required
                       value="{{ old('nome_usuario', $usuario->nome_usuario) }}">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="pf_email">E-mail (login) <span class="text-danger">*</span></label>
                <input type="email" id="pf_email" name="email_usuario" class="form-control" maxlength="255" required
                       value="{{ old('email_usuario', $usuario->email_usuario) }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">Nível de acesso</label>
                <input type="text" class="form-control" readonly value="{{ \App\Models\User::NIVEIS[$usuario->nivel_usuario] ?? $usuario->nivel_usuario }}">
                <div class="form-text">Definido por um administrador.</div>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar dados</button>
            </div>
          </form>
        </x-admin.secao>
      </div>

      <div class="col-lg-5">
        <x-admin.secao titulo="Trocar senha" icone="key" id="trocarSenha"
                       ajuda="Pelo menos 8 caracteres. Depois de trocar, use a senha nova no próximo login.">
          @if ($senhaErros->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($senhaErros->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
          </div>
          @endif

          <form method="POST" action="{{ route('admin.perfil.senha') }}">
            @csrf
            @method('PUT')
            {{-- O olho mostra e esconde o que foi digitado (10/11/2026), como na tela de login --}}
            @foreach ([
              ['pf_senha_atual', 'senha_atual', 'Senha atual', 'current-password', false],
              ['pf_nova_senha', 'nova_senha', 'Nova senha', 'new-password', true],
              ['pf_confirmacao', 'nova_senha_confirmation', 'Repita a nova senha', 'new-password', true],
            ] as [$id, $nome, $rotulo, $autocomplete, $minimo])
            <div class="mb-3">
              <label class="form-label" for="{{ $id }}">{{ $rotulo }}</label>
              <div class="input-group">
                <input type="password" id="{{ $id }}" name="{{ $nome }}" class="form-control" required
                       @if ($minimo) minlength="8" @endif autocomplete="{{ $autocomplete }}">
                <button type="button" class="btn btn-outline-secondary js-ver-senha" data-campo="{{ $id }}"
                        title="Mostrar a senha" aria-label="Mostrar a senha" aria-pressed="false">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            @endforeach
            <div class="d-flex justify-content-end">
              <button type="submit" class="btn-modal-submit"><i class="bi bi-shield-lock"></i> Trocar senha</button>
            </div>
          </form>
        </x-admin.secao>
      </div>
    </div>

  </div>
</main>
@endsection

@push('scripts')
<script>
// Olho dos campos de senha: alterna entre mostrar e esconder o que foi digitado
document.querySelectorAll('.js-ver-senha').forEach(botao => botao.addEventListener('click', () => {
    const campo = document.getElementById(botao.dataset.campo);
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    botao.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
    botao.title = mostrar ? 'Esconder a senha' : 'Mostrar a senha';
    botao.setAttribute('aria-label', botao.title);
    botao.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
}));

// Prévia da foto escolhida (a atual se nada foi escolhido); acima do limite, avisa e descarta
document.addEventListener('DOMContentLoaded', () => {
    const campo = document.querySelector('.js-campo-foto-perfil');
    if (!campo) return;
    const arquivo = campo.querySelector('.js-foto-perfil-arquivo');
    const previa = campo.querySelector('.js-foto-perfil-previa');
    const erro = campo.querySelector('.js-foto-perfil-erro');
    const remover = document.getElementById('pf_remover');
    const maxKb = parseInt(campo.dataset.maxKb, 10);
    let url = null;

    arquivo.addEventListener('change', () => {
        erro.hidden = true;
        if (url) { URL.revokeObjectURL(url); url = null; }
        const escolhido = arquivo.files && arquivo.files[0];

        if (escolhido && escolhido.size > maxKb * 1024) {
            erro.textContent = 'Esta foto tem ' + (escolhido.size / 1048576).toFixed(1).replace('.', ',') + ' MB. Use uma foto de até ' + Math.round(maxKb / 1024) + ' MB.';
            erro.hidden = false;
            arquivo.value = '';
        } else if (escolhido && escolhido.type.startsWith('image/')) {
            url = URL.createObjectURL(escolhido);
            previa.src = url;
            if (remover) remover.checked = false;
            return;
        }
        previa.src = previa.dataset.atual;
    });
});
</script>
@endpush
