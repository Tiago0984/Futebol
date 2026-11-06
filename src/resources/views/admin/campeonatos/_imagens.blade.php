{{--
  Logo e banner do campeonato (26/10/2026): campo com recorte (admin.partials.campo-de-imagem) e, desde 06/11/2026,
  "Editar imagem" na atual e o bloco "Como vai ficar"; o servidor trata (logo 512×512 PNG; banner 1920×960 JPG).
  Parâmetros opcionais: editando (bool), logoAtual, bannerAtual (endereço da imagem atual, em miniatura) ou
  idLogoAtual, idBannerAtual (preenchidos pelo JavaScript do modal de edição).
--}}
@php
  $tratador = \App\Services\TratadorDeImagem::class;
  $manter = !empty($editando) ? ' Deixe em branco para manter a atual.' : '';
@endphp
<div class="col-md-6">
  @include('admin.partials.campo-de-imagem', [
    'name' => 'logo_evento', 'rotulo' => 'Logo / Escudo', 'tipo' => 'logo', 'minimo' => $tratador::LADO_MINIMO_LOGO,
    'miniatura' => $logoAtual ?? null, 'idAtual' => $idLogoAtual ?? null, 'editavel' => !empty($editando),
    'ajuda' => 'Qualquer imagem até 10 MB, de preferência com o lado maior de 256 px ou mais. Vai num quadrado de até 512 px, com o fundo transparente.' . $manter,
  ])
</div>
<div class="col-md-6">
  @include('admin.partials.campo-de-imagem', [
    'name' => 'banner_evento', 'rotulo' => 'Banner (imagem de destaque)', 'tipo' => 'capa',
    'proporcao' => $tratador::BANNER_CAMPEONATO[0] / $tratador::BANNER_CAMPEONATO[1],
    'minimo' => $tratador::LARGURA_MINIMA_CAPA,
    'miniatura' => $bannerAtual ?? null, 'idAtual' => $idBannerAtual ?? null, 'editavel' => !empty($editando),
    'ajuda' => 'Qualquer foto até 10 MB: recortada em 1920 × 960 (2:1).' . $manter,
  ])
</div>
@include('admin.campeonatos._previa_das_imagens')
