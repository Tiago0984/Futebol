<!doctype html>
<html lang="pt-BR">
<head>
    @include('admin.partials.head')
    {{-- Estilo do projeto (seção 68 — Login Admin) --}}
    <link rel="stylesheet" href="{{ asset('coderatech/css/estilo.css') }}">
</head>

<body class="lp-body">
<div class="lp-wrapper">

    {{-- ── PAINEL ESQUERDO: BRANDING ────────────────────────────────────────── --}}
    <div class="lp-brand">
        <div class="lp-brand-overlay"></div>
        <div class="lp-brand-inner">

            {{-- Logo --}}
            <div class="lp-logo-row">
                <img src="{{ asset('futebol/images/logo2.png') }}" alt="Logo AACJ Futebol">
                <div class="lp-logo-text">
                    <span class="lp-school-name">AACJ Futebol</span>
                    <span class="lp-school-sub">Escolinha de Futebol</span>
                </div>
            </div>

            {{-- Hero --}}
            <div class="lp-hero">
                <div class="lp-line"></div>
                <span class="lp-badge-brand">Painel Administrativo</span>
                <h2 class="lp-title">
                    Gerencie sua<br>
                    <em>escolinha</em> com<br>
                    excelência
                </h2>
                <p class="lp-desc">
                    Área exclusiva para gestores e coordenadores.
                    Acesso completo a atletas, treinos, eventos e
                    o calendário da escolinha.
                </p>
            </div>

            {{-- Estatísticas --}}
            <div class="lp-stats">
                <div>
                    <div class="lp-stat-value">100+</div>
                    <div class="lp-stat-label">Atletas</div>
                </div>
                <div>
                    <div class="lp-stat-value">5</div>
                    <div class="lp-stat-label">Categorias</div>
                </div>
                <div>
                    <div class="lp-stat-value">15+</div>
                    <div class="lp-stat-label">Anos</div>
                </div>
            </div>

        </div>
    </div>

    {{-- ── PAINEL DIREITO: FORMULÁRIO ───────────────────────────────────────── --}}
    <div class="lp-form-panel">

        <div class="lp-form-header">
            <div class="lp-restricted-badge">
                <span class="lp-pulse"></span>
                Área Restrita
            </div>
            <h1>Bem-vindo<br>de volta</h1>
            <p>Insira suas credenciais para acessar o painel de gestão.</p>
        </div>

        @if (session('error'))
            <div class="lp-alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="lp-alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Verifique os dados (e-mail / senha) e tente novamente.
            </div>
        @endif

        <form action="{{ route('admin.login.post') }}" method="post" class="lp-form">
            @csrf

            <div class="lp-field">
                <label for="lp-email">E-mail</label>
                <div class="lp-input-wrap">
                    <i class="bi bi-envelope lp-input-icon"></i>
                    <input
                        id="lp-email"
                        type="text"
                        name="login"
                        class="lp-input"
                        placeholder="seu@email.com"
                        value="{{ old('login') }}"
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="lp-field">
                <label for="lp-password">Senha</label>
                <div class="lp-input-wrap">
                    <i class="bi bi-lock-fill lp-input-icon"></i>
                    <input
                        id="lp-password"
                        type="password"
                        name="password"
                        class="lp-input"
                        placeholder="••••••••"
                        autocomplete="current-password"
                    >
                    <button type="button" class="lp-eye-btn" onclick="lpTogglePass()" aria-label="Mostrar ou ocultar senha">
                        <i class="bi bi-eye" id="lp-eye-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="lp-btn-submit">
                Entrar no Painel
                <i class="bi bi-arrow-right lp-btn-arrow"></i>
            </button>
        </form>

        <a href="{{ url('/') }}" class="lp-back">
            <i class="bi bi-arrow-left lp-back-arrow"></i>
            Voltar ao site
        </a>

    </div>
</div>

<script>
    function lpTogglePass() {
        var input = document.getElementById('lp-password');
        var icon  = document.getElementById('lp-eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
</script>

@include('admin.partials.script')
</body>
</html>
