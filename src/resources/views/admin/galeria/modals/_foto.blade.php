{{-- Foto da galeria (09/11/2026): recorte livre (só tira as sobras); o servidor grava em JPG, até 1920 px no lado maior
     (TratadorDeImagem::fotoLivre). No Editar (idAtual), o "Editar imagem" da foto salva. Abaixo, o "Como vai ficar" --}}
@include('admin.partials.campo-de-imagem', [
  'name' => 'foto_galeria', 'rotulo' => $rotulo, 'tipo' => 'foto', 'obrigatorio' => $obrigatorio ?? false,
  'minimo' => \App\Services\TratadorDeImagem::LARGURA_MINIMA_CAPA,
  'idAtual' => $idAtual ?? null, 'editavel' => !empty($idAtual),
  'ajuda' => 'Qualquer foto até 10 MB. A grade do site mostra um pedaço do centro; ao clicar, a foto abre inteira.' . ($ajuda ?? ''),
])
@include('admin.galeria.modals._previa')
