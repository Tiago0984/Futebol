@extends('layout.admin')

@section('title', 'Configurações')

@section('content')
    <div class="container-fluid">

        {{-- Cabeçalho da página --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="m-0">Configurações</h1>
                <small class="text-secondary">Preferências do painel administrativo</small>
            </div>
        </div>

        <div class="row g-3">

            {{-- Card: Aparência --}}
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="card-title m-0">
                            <i class="bi bi-palette me-1"></i> Aparência
                        </h3>
                    </div>
                    <div class="card-body">

                        <div
                            class="d-flex align-items-center justify-content-between gap-3 p-3 rounded border border-secondary-subtle">
                            <div>
                                <div class="fw-semibold">Modo escuro</div>
                                <small class="text-secondary d-block">
                                    Alterna o tema do painel entre claro e escuro.
                                </small>
                            </div>

                            {{-- Switch estilo iPhone (mesma classe do AdminLTE) --}}
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="darkModeSwitch"
                                    style="width: 3rem; height: 1.5rem; cursor: pointer;" aria-label="Alternar modo escuro">
                                <label class="form-check-label visually-hidden" for="darkModeSwitch">Modo escuro</label>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Sua preferência é salva no navegador e mantida entre sessões.
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const STORAGE_KEY = 'adminlte-theme';

            // O AdminLTE 4 / Bootstrap 5.3+ controla o tema pelo atributo data-bs-theme
            // no <html>. O AdminLTE também salva em localStorage com a chave 'adminlte-theme'.
            const getTheme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const setTheme = (theme) => {
                document.documentElement.setAttribute('data-bs-theme', theme);
                try {
                    localStorage.setItem(STORAGE_KEY, theme);
                } catch (e) {}
            };

            const sw = document.getElementById('darkModeSwitch');
            if (!sw) return;

            // Sincroniza o switch com o tema atual SEMPRE que a página carregar
            const sync = () => {
                sw.checked = getTheme();
            };
            sync();

            // Quando o usuário clica no switch
            sw.addEventListener('change', function() {
                setTheme(sw.checked ? 'dark' : 'light');
            });
        })();
    </script>
@endpush
