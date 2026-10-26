{{-- Foto do carrossel da home (26/10/2026): recorte em 16:9 e prévia; o servidor grava 1920×1080 JPG (TratadorDeImagem) --}}
@php($tratador = \App\Services\TratadorDeImagem::class)
@include('admin.partials.campo-de-imagem', [
  'name' => 'foto_banner', 'rotulo' => $rotulo, 'tipo' => 'capa', 'obrigatorio' => $obrigatorio ?? false,
  'proporcao' => $tratador::BANNER_SITE[0] / $tratador::BANNER_SITE[1], 'minimo' => $tratador::LARGURA_MINIMA_CAPA,
  'previas' => [['Carrossel da home', '1.7778']],
  'ajuda' => 'Qualquer foto até 10 MB: recortada em 1920 × 1080 (16:9). O título e o subtítulo aparecem por cima da foto.' . $ajuda,
])
