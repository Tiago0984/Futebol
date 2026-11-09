<?php

namespace Tests\Feature;

use App\Models\Noticia;
use App\Services\TratadorDeImagem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Capa da notícia (09/11/2026): recorte em 16:9 no formulário, gravada pelo TratadorDeImagem em 1920×1080 JPG com
 * nome gerado (antes, 2 MB gravada como veio, com o nome original), "Editar imagem" da capa salva, a antiga apagada
 * só se é gerada e ninguém mais usa, e o bloco "Como vai ficar" nos modais.
 */
class NoticiaCapaTest extends TestCase
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

    public function test_capa_nova_vai_para_16_9_com_nome_gerado(): void
    {
        $this->comoAdmin()->post(route('admin.noticias.store'), $this->dados() + [
            'foto_noticia' => $this->upload(4000, 2000),
        ])->assertRedirect(route('admin.noticias.index'))->assertSessionHasNoErrors();

        $noticia = Noticia::sole();
        $this->assertMatchesRegularExpression('/^capa-noticia-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$/', $noticia->foto_noticia);
        $this->assertSame([1920, 1080, IMAGETYPE_JPEG], $this->medidas($noticia->foto_noticia));
    }

    public function test_capa_usa_o_recorte_do_formulario(): void
    {
        // Metade esquerda vermelha, direita azul: o recorte da esquerda fica vermelho
        $this->comoAdmin()->post(route('admin.noticias.store'), $this->dados() + [
            'foto_noticia' => $this->upload(4000, 1000, true), 'recorte_foto_noticia' => '0,0,1777,1000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('vermelho', $this->corNoCentro(Noticia::sole()->foto_noticia));
    }

    public function test_noticia_sem_capa_e_capa_pequena_avisa(): void
    {
        $this->comoAdmin()->post(route('admin.noticias.store'), $this->dados(['titulo_noticia' => 'Sem capa']))->assertSessionHasNoErrors();
        $this->assertNull(Noticia::where('titulo_noticia', 'Sem capa')->sole()->foto_noticia);

        $this->post(route('admin.noticias.store'), $this->dados(['titulo_noticia' => 'Pequena']) + [
            'foto_noticia' => $this->upload(800, 450),
        ])->assertSessionHas('avisos_imagem', fn ($avisos) => str_contains($avisos[0] ?? '', 'Imagem pequena (800 × 450 px)'));
        $this->assertSame([800, 450, IMAGETYPE_JPEG], $this->medidas(Noticia::where('titulo_noticia', 'Pequena')->sole()->foto_noticia));
    }

    public function test_editar_imagem_recorta_a_capa_atual_e_apaga_a_antiga(): void
    {
        $this->comoAdmin()->post(route('admin.noticias.store'), $this->dados() + [
            'foto_noticia' => $this->upload(1920, 1080, true),
        ]);
        $noticia = Noticia::sole();
        $antiga = $noticia->foto_noticia;

        // Sem arquivo e sem recorte: a capa fica
        $this->put(route('admin.noticias.update', $noticia->id_noticia), $this->dados(['recorte_foto_noticia' => '']))->assertSessionHasNoErrors();
        $this->assertSame($antiga, $noticia->fresh()->foto_noticia);

        // Recorte da capa atual (o quarto da direita, azul): grava outra com nome novo e apaga a antiga
        $this->put(route('admin.noticias.update', $noticia->id_noticia), $this->dados(['recorte_foto_noticia' => '960,270,960,540']))
            ->assertSessionHasNoErrors();
        $noticia->refresh();
        $this->assertNotSame($antiga, $noticia->foto_noticia);
        Storage::disk('imagens')->assertMissing("news/{$antiga}");
        $this->assertSame([960, 540, IMAGETYPE_JPEG], $this->medidas($noticia->foto_noticia));
        $this->assertSame('azul', $this->corNoCentro($noticia->foto_noticia));
    }

    public function test_capa_antiga_com_nome_original_nao_e_apagada(): void
    {
        // Capas enviadas antes do tratamento ficam com o nome original: nunca são apagadas
        Storage::disk('imagens')->put('news/1700000000_foto.jpg', 'antiga');
        $noticia = Noticia::create($this->dados() + ['foto_noticia' => '1700000000_foto.jpg']);

        $this->comoAdmin()->put(route('admin.noticias.update', $noticia->id_noticia), $this->dados() + [
            'foto_noticia' => $this->upload(1920, 1080),
        ])->assertSessionHasNoErrors();

        $this->assertStringStartsWith('capa-noticia-', $noticia->fresh()->foto_noticia);
        Storage::disk('imagens')->assertExists('news/1700000000_foto.jpg');
    }

    public function test_modais_tem_o_recorte_em_16_9_e_o_como_vai_ficar(): void
    {
        $noticia = Noticia::create($this->dados() + ['foto_noticia' => 'capa-noticia-20261109-120000-abc123.jpg']);

        $this->comoAdmin()->get(route('admin.noticias.index'))->assertOk()
            ->assertSee('data-proporcao="' . (TratadorDeImagem::NOTICIA[0] / TratadorDeImagem::NOTICIA[1]) . '"', false)
            ->assertSee('name="recorte_foto_noticia"', false)
            ->assertSee('js-previa-noticia', false)
            ->assertSee('Site: topo da página da notícia (computador)')
            ->assertSee('Home: Últimas notícias')
            ->assertSee('id="edit_capa_atual"', false)
            ->assertSee('data-foto="' . e($noticia->foto_url) . '"', false)
            ->assertSee('previa-das-imagens.js', false);
    }

    // ---------- ajudantes ----------

    private function dados(array $extra = []): array
    {
        return $extra + [
            'titulo_noticia' => 'Vitória no clássico', 'conteudo_noticia' => 'Texto da notícia.',
            'categoria_noticia' => 'Jogos', 'autor_noticia' => 'Equipe AACJ', 'data_publicacao_noticia' => '2026-11-09 14:30:00',
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
        [$l, $a, $tipo] = getimagesize(Storage::disk('imagens')->path("news/{$arquivo}"));

        return [$l, $a, $tipo];
    }

    private function corNoCentro(string $arquivo): string
    {
        [$l, $a] = $this->medidas($arquivo);
        $cor = imagecolorat(imagecreatefromstring(Storage::disk('imagens')->get("news/{$arquivo}")), intdiv($l, 2), intdiv($a, 2));

        return (($cor >> 16) & 0xFF) > ($cor & 0xFF) ? 'vermelho' : 'azul';
    }
}
