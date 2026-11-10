@extends('layout.admin')

@section('title', 'Configurações')

{{--
    Configurações do painel (10/11/2026; dados em Admin\ConfiguracoesController): aparência (modo escuro, guardado no
    navegador), minha conta (atalho para o perfil), a situação do sistema (só leitura: versão, ambiente, e-mail, fila,
    link das fotos, contatos e push, sem senhas nem chaves) e atalhos úteis.
--}}
@section('content')
@php
    $ok = fn (bool $bom, string $sim, string $nao) => $bom
        ? '<span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> ' . e($sim) . '</span>'
        : '<span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ' . e($nao) . '</span>';
@endphp
<main class="app-main">
  <div class="container-fluid py-4">

    <x-admin.cabecalho titulo="Configurações">
      <x-slot:subtitulo>Preferências do painel e a situação do sistema.</x-slot:subtitulo>
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

        @if (Route::has('admin.perfil'))
        <x-admin.secao titulo="Minha conta" icone="person-circle">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold">{{ Auth::guard('admin')->user()->nome_usuario }}</div>
              <small class="text-body-secondary">{{ Auth::guard('admin')->user()->email_usuario }}</small>
            </div>
            <a href="{{ route('admin.perfil') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Meu perfil</a>
          </div>
        </x-admin.secao>
        @endif

        <x-admin.secao titulo="Atalhos" icone="link-45deg">
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Ver site</a>
            <a href="{{ url('/api/documentacao') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-braces"></i> Documentação da API</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
          </div>
        </x-admin.secao>

      </div>

      <div class="col-lg-6">
        <x-admin.secao titulo="Sistema" icone="cpu" id="situacaoDoSistema"
                       ajuda="Só leitura: para conferir o servidor sem entrar no SSH. Nenhuma senha ou chave aparece aqui.">
          <dl class="config-lista">
            <dt>Versão do código</dt>
            <dd>
              @if ($sistema['versao'])
                <code>{{ $sistema['versao']['branch'] ?? 'sem branch' }}</code> · <code>{{ $sistema['versao']['commit'] ?? '?' }}</code>
              @else
                <span class="text-body-secondary">não identificada</span>
              @endif
            </dd>

            <dt>Ambiente</dt>
            <dd>
              {{ $sistema['ambiente'] === 'production' ? 'Produção' : ucfirst($sistema['ambiente']) }}
              @if ($sistema['debug'] && $sistema['ambiente'] === 'production')
                {!! $ok(false, '', 'Modo de depuração ligado em produção') !!}
              @endif
            </dd>

            <dt>PHP e Laravel</dt>
            <dd>PHP {{ $sistema['php'] }} · Laravel {{ $sistema['laravel'] }}</dd>

            <dt>Fuso horário</dt>
            <dd>{{ $sistema['fuso'] }} · agora {{ $sistema['agora']->format('d/m/Y H:i') }}</dd>

            <dt>E-mail</dt>
            <dd>
              {!! $ok($sistema['email']['entrega'], 'Enviando (' . $sistema['email']['mailer'] . ')', 'Não entrega: vai para o log (' . $sistema['email']['mailer'] . ')') !!}
              @if ($sistema['email']['remetente'])
                <div class="small text-body-secondary">Remetente: {{ $sistema['email']['remetente'] }}</div>
              @endif
              @unless ($sistema['email']['entrega'])
                <div class="small text-body-secondary">Normal no computador local. No servidor, confira o .env (seção 1 do CLAUDE.md).</div>
              @endunless
            </dd>

            <dt>Fila de e-mails</dt>
            <dd>
              @if ($sistema['fila']['parada_desde'])
                {!! $ok(false, '', 'Parada desde ' . $sistema['fila']['parada_desde']->format('d/m H:i')) !!}
                <div class="small text-body-secondary">Confira a tarefa "Agendador Laravel" no Plesk.</div>
              @else
                {!! $ok(true, 'Andando (' . $sistema['fila']['conexao'] . ')', '') !!}
              @endif
              @if ($sistema['fila']['falhas'])
                <div class="small text-danger-emphasis">{{ $sistema['fila']['falhas'] }} tarefa(s) com falha.</div>
              @endif
            </dd>

            <dt>Fotos enviadas pelo site</dt>
            <dd>{!! $ok($sistema['storage'], 'Link criado', 'Falta o "php artisan storage:link"') !!}</dd>

            <dt>Contatos do site</dt>
            <dd>
              @foreach ($sistema['contato'] as $rotulo => $valor)
                <div class="small">{{ $rotulo }}: {!! $valor ? e($valor) : '<span class="text-body-secondary">não configurado (fica escondido no site)</span>' !!}</div>
              @endforeach
            </dd>

            <dt>Notificações push</dt>
            <dd class="mb-0">{{ $sistema['push'] ? 'Com chave de acesso da Expo' : 'Sem chave de acesso (opcional)' }}</dd>
          </dl>
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
