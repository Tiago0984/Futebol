{{-- Capa da notícia (09/11/2026): recorte em 16:9; o servidor grava 1920×1080 JPG (TratadorDeImagem::NOTICIA). No
     Editar (idAtual), o "Editar imagem" da capa salva. Abaixo, o bloco "Como vai ficar" --}}
@php($tratador = \App\Services\TratadorDeImagem::class)
@include('admin.partials.campo-de-imagem', [
  'name' => 'foto_noticia', 'rotulo' => $rotulo, 'tipo' => 'capa',
  'proporcao' => $tratador::NOTICIA[0] / $tratador::NOTICIA[1], 'minimo' => $tratador::LARGURA_MINIMA_CAPA,
  'idAtual' => $idAtual ?? null, 'editavel' => !empty($idAtual),
  'ajuda' => 'Qualquer foto até 10 MB: recortada em 1920 × 1080 (16:9). Cada lugar do site corta um pouco das bordas: deixe o principal no centro do quadro.' . ($ajuda ?? ''),
])
@include('admin.noticias.modals._previa')
