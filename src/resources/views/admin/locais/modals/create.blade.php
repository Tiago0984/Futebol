{{-- "Novo local" em modal (10/11/2026; antes, uma tela própria). Abre sozinho com ?novo=1 (o endereço antigo
     admin.locais.create redireciona para cá) e quando o cadastro volta com erro. O "Cadastrar" de um texto já usado abre
     o modal com o nome preenchido e guarda o texto em texto_origem (o store leva ao Editar, para ligar os usos) --}}
@php
  $nomeInicial = request('nome') !== null ? mb_substr(trim((string) request('nome')), 0, 100) : '';
  $abrir = $errors->any() || request()->boolean('novo');
@endphp
<div class="modal fade" id="modalCriarLocal" tabindex="-1" aria-labelledby="modalCriarLocalLabel" aria-hidden="true"
     data-abrir="{{ $abrir ? '1' : '0' }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start modal-admin">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriarLocalLabel"><i class="bi bi-geo-alt"></i> Novo local</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="POST" action="{{ route('admin.locais.store') }}">
                @csrf
                <input type="hidden" name="texto_origem" id="lc_texto_origem" value="{{ old('texto_origem', $nomeInicial) }}">
                <div class="modal-body">
                    @if($errors->any())
                    <div class="alert alert-danger mb-3" role="alert">
                        <strong>Verifique os campos:</strong>
                        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                    @endif
                    <p class="small text-muted mb-3">O endereço e o link do mapa aparecem no "Abrir mapa" do app.</p>
                    @include('admin.locais._campos', ['local' => new \App\Models\Local(), 'nomeInicial' => $nomeInicial])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit"><i class="bi bi-check-lg"></i> Salvar local</button>
                </div>
            </form>
        </div>
    </div>
</div>
