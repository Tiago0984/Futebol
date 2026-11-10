@extends('layout.admin')

@section('title', 'Configurações')

{{--
    Configurações do painel: aparência (modo escuro, guardado no navegador) e minha conta (atalho para o perfil).
    Sem informações técnicas do sistema (decisão do dono do projeto, 10/11/2026).
--}}
@section('content')
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho titulo="Configurações">
      <x-slot:subtitulo>Preferências do painel e a sua conta.</x-slot:subtitulo>
    </x-admin.cabecalho>

    <div class="row g-4">
      <div class="col-lg-6">
        <x-admin.secao titulo="Aparência" icone="palette">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold">Modo escuro</div>
              <small class="text-body-secondary d-block">Alterna o tema do painel entre claro e escuro. Fica guardado neste navegador.</small>
            </div>
            <div class="form-check form-switch m-0">
              <input class="form-check-input config-chave" type="checkbox" role="switch" id="darkModeSwitch" aria-label="Alternar modo escuro">
              <label class="form-check-label visually-hidden" for="darkModeSwitch">Modo escuro</label>
            </div>
          </div>
        </x-admin.secao>
      </div>

      <div class="col-lg-6">
        <x-admin.secao titulo="Minha conta" icone="person-circle">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
              <img src="{{ Auth::guard('admin')->user()->urlFoto() }}" alt="" class="perfil-foto perfil-foto--pequena">
              <div class="min-w-0">
                <div class="fw-semibold">{{ Auth::guard('admin')->user()->nome_usuario }}</div>
                <small class="text-body-secondary">{{ Auth::guard('admin')->user()->email_usuario }}</small>
              </div>
            </div>
            <a href="{{ route('admin.perfil') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Meu perfil</a>
          </div>
        </x-admin.secao>
      </div>
    </div>

  </div>
</main>
@endsection

@push('scripts')
<script>
(function () {
    const STORAGE_KEY = 'adminlte-theme';
    // O AdminLTE 4 / Bootstrap 5.3+ controla o tema pelo atributo data-bs-theme no <html> e guarda em 'adminlte-theme'
    const sw = document.getElementById('darkModeSwitch');
    if (!sw) return;
    sw.checked = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    sw.addEventListener('change', function () {
        const tema = sw.checked ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', tema);
        try { localStorage.setItem(STORAGE_KEY, tema); } catch (e) {}
    });
})();
</script>
@endpush
