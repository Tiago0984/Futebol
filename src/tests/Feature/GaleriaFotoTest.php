<?php

namespace Tests\Feature;

use App\Models\Galeria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Foto da galeria (09/11/2026): recorte livre no formulário (só tira as sobras, porque o site abre a foto inteira ao
 * clicar), gravada pelo TratadorDeImagem::fotoLivre em JPG até 1920 px no lado maior, sem ampliar, com nome gerado
 * (antes, 4 MB gravada como veio, com o nome original), "Editar imagem" da foto salva, a antiga apagada só se é
 * gerada e ninguém mais usa, e o bloco "Como vai ficar" nos modais.
 */
class GaleriaFotoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private array $temporarios = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imagens');
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->temporarios, 'is_file'));
        parent::tearDown();
    }

    public function test_foto_grande_e_reduzida_no_lado_maior_mantendo_a_proporcao(): void
    {
        $this->comoAdmin()->post(route('admin.galeria.store'), $this->dados() + [
            'foto_galeria' => $this->upload(4000, 3000),
        ])->assertRedirect(route('admin.galeria.index'))->assertSessionHasNoErrors();

        $foto = Galeria::sole();
        $this->assertMatchesRegularExpression('/^foto-galeria-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$/', $foto->foto_galeria);
        $this->assertSame([1920, 1440, IMAGETYPE_JPEG], $this->medidas($foto->foto_galeria));
    }

    public function test_recorte_livre_em_qualquer_proporcao(): void
    {
        // Metade esquerda vermelha: uma faixa em pé da esquerda (1000 × 3000) fica vermelha e em pé
        $this->comoAdmin()->post(route('admin.galeria.store'), $this->dados() + [
            'foto_galeria' => $this->upload(4000, 3000, true), 'recorte_foto_galeria' => '0,0,1000,3000',
        ])->assertSessionHasNoErrors();

        $arquivo = Galeria::sole()->foto_galeria;
        $this->assertSame([640, 1920, IMAGETYPE_JPEG], $this->medidas($arquivo));
        $this->assertSame('vermelho', $this->corNoCentro($arquivo));
    }

    public function test_foto_pequena_nao_e_ampliada_e_avisa(): void
    {
        $this->comoAdmin()->post(route('admin.galeria.store'), $this->dados() + [
            'foto_galeria' => $this->upload(800, 600),
        ])->assertSessionHas('avisos_imagem', fn ($avisos) => str_contains($avisos[0] ?? '', 'Foto pequena (800 × 600 px)'));

        $this->assertSame([800, 600, IMAGETYPE_JPEG], $this->medidas(Galeria::sole()->foto_galeria));
    }

    public function test_editar_imagem_recorta_a_foto_atual_e_apaga_a_antiga(): void
    {
        $this->comoAdmin()->post(route('admin.galeria.store'), $this->dados() + [
            'foto_galeria' => $this->upload(1600, 1200, true),
        ]);
        $foto = Galeria::sole();
        $antiga = $foto->foto_galeria;

        // Sem arquivo e sem recorte: a foto fica
        $this->put(route('admin.galeria.update', $foto->id_galeria), $this->dados(['recorte_foto_galeria' => '']))->assertSessionHasNoErrors();
        $this->assertSame($antiga, $foto->fresh()->foto_galeria);

        // Recorte da foto atual (a metade da direita, azul): grava outra com nome novo e apaga a antiga
        $this->put(route('admin.galeria.update', $foto->id_galeria), $this->dados(['recorte_foto_galeria' => '800,0,800,1200']))
            ->assertSessionHasNoErrors();
        $foto->refresh();
        $this->assertNotSame($antiga, $foto->foto_galeria);
        Storage::disk('imagens')->assertMissing("galeria/{$antiga}");
        $this->assertSame([800, 1200, IMAGETYPE_JPEG], $this->medidas($foto->foto_galeria));
        $this->assertSame('azul', $this->corNoCentro($foto->foto_galeria));
    }

    public function test_foto_antiga_com_nome_original_nao_e_apagada(): void
    {
        // Fotos enviadas antes do tratamento ficam com o nome original: nunca são apagadas
        Storage::disk('imagens')->put('galeria/1700000000_treino.jpg', 'antiga');
        $foto = Galeria::create($this->dados() + ['foto_galeria' => '1700000000_treino.jpg']);

        $this->comoAdmin()->put(route('admin.galeria.update', $foto->id_galeria), $this->dados() + [
            'foto_galeria' => $this->upload(1600, 1200),
        ])->assertSessionHasNoErrors();

        $this->assertStringStartsWith('foto-galeria-', $foto->fresh()->foto_galeria);
        Storage::disk('imagens')->assertExists('galeria/1700000000_treino.jpg');
    }

    public function test_modais_tem_o_recorte_livre_e_o_como_vai_ficar(): void
    {
        $foto = Galeria::create($this->dados() + ['foto_galeria' => 'foto-galeria-20261109-120000-abc123.jpg']);

        $this->comoAdmin()->get(route('admin.galeria.index'))->assertOk()
            ->assertSee('data-tipo="foto"', false)
            ->assertSee('name="recorte_foto_galeria"', false)
            ->assertSee('js-previa-galeria', false)
            ->assertSee('Site: página Galeria (quadrado)')
            ->assertSee('Home: galeria (4:3)')
            ->assertSee('Site: ao clicar (foto inteira)')
            ->assertSee('id="edit_foto_galeria_atual"', false)
            ->assertSee('data-foto="' . e(asset('futebol/images/galeria/' . $foto->foto_galeria)) . '"', false);
    }

    // ---------- ajudantes ----------

    private function dados(array $extra = []): array
    {
        return $extra + [
            'titulo_galeria' => 'Treino de sábado', 'categoria_galeria' => 'GERAL', 'ordem_galeria' => 1, 'status_galeria' => 'ATIVO',
        ];
    }

    private function imagem(int $largura, int $altura, bool $duasCores = false): string
    {
        $gd = imagecreatetruecolor($largura, $altura);
        if ($duasCores) {
            imagefilledrectangle($gd, 0, 0, intdiv($largura, 2) - 1, $altura, imagecolorallocate($gd, 255, 0, 0));
            imagefilledrectangle($gd, intdiv($largura, 2), 0, $largura, $altura, imagecolorallocate($gd, 0, 0, 255));
        } else {
            imagefill($gd, 0, 0, imagecolorallocate($gd, 120, 120, 120));
        }

        $base = tempnam(sys_get_temp_dir(), 'img');
        $caminho = "{$base}.jpg";
        imagejpeg($gd, $caminho, 90);
        array_push($this->temporarios, $base, $caminho);

        return $caminho;
    }

    private function upload(int $largura, int $altura, bool $duasCores = false): UploadedFile
    {
        return new UploadedFile($this->imagem($largura, $altura, $duasCores), 'foto.jpg', 'image/jpeg', null, true);
    }

    private function medidas(string $arquivo): array
    {
        [$l, $a, $tipo] = getimagesize(Storage::disk('imagens')->path("galeria/{$arquivo}"));

        return [$l, $a, $tipo];
    }

    private function corNoCentro(string $arquivo): string
    {
        [$l, $a] = $this->medidas($arquivo);
        $cor = imagecolorat(imagecreatefromstring(Storage::disk('imagens')->get("galeria/{$arquivo}")), intdiv($l, 2), intdiv($a, 2));

        return (($cor >> 16) & 0xFF) > ($cor & 0xFF) ? 'vermelho' : 'azul';
    }
}
