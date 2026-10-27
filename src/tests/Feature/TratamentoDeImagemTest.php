<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Campeonato;
use App\Services\TratadorDeImagem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Imagens enviadas pelo painel (26/10/2026, TratadorDeImagem): banner do campeonato 1920×960 e Banners da home
 * 1920×1080 em JPG, pelo recorte do formulário ou pelo centro, sem ampliar; logo 512×512 PNG inteira com fundo
 * transparente; aviso para imagem pequena; nome gerado e o antigo apagado só se é gerado e ninguém mais usa; o
 * comando imagens:reprocessar.
 */
class TratamentoDeImagemTest extends TestCase
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

    public function test_capa_pelo_centro_reduz_para_o_formato_em_jpg(): void
    {
        $r = TratadorDeImagem::capa($this->imagem(3000, 1000), 'campeonatos', 'banner-campeonato', 1920, 960);

        $this->assertMatchesRegularExpression('/^banner-campeonato-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$/', $r['arquivo']);
        $this->assertSame([1920, 960, IMAGETYPE_JPEG], $this->medidas('campeonatos', $r['arquivo']));
        $this->assertNull($r['aviso']);
    }

    public function test_capa_usa_o_recorte_do_formulario_e_ignora_o_invalido(): void
    {
        // Metade esquerda vermelha, direita azul
        $origem = $this->imagem(4000, 1000, true);

        $esquerda = TratadorDeImagem::capa($origem, 'campeonatos', 'banner-campeonato', 1920, 960, '0,0,2000,1000');
        $this->assertSame('vermelho', $this->corNoCentro('campeonatos', $esquerda['arquivo']));

        $direita = TratadorDeImagem::capa($origem, 'campeonatos', 'banner-campeonato', 1920, 960, '2000,0,2000,1000');
        $this->assertSame('azul', $this->corNoCentro('campeonatos', $direita['arquivo']));

        // Outra proporção ou fora da imagem: o centro (2000 × 1000 no meio, metade de cada cor)
        foreach (['0,0,1000,1000', '9000,0,2000,1000', 'qualquer coisa'] as $invalido) {
            $centro = TratadorDeImagem::capa($origem, 'campeonatos', 'banner-campeonato', 1920, 960, $invalido);
            $this->assertSame([1920, 960, IMAGETYPE_JPEG], $this->medidas('campeonatos', $centro['arquivo']), $invalido);
            $this->assertSame('vermelho', $this->corEm('campeonatos', $centro['arquivo'], 400, 480), $invalido);
            $this->assertSame('azul', $this->corEm('campeonatos', $centro['arquivo'], 1500, 480), $invalido);
        }
    }

    public function test_capa_pequena_nao_e_ampliada_e_avisa(): void
    {
        $r = TratadorDeImagem::capa($this->imagem(800, 600), 'banner', 'banner-site', 1920, 1080);

        $this->assertSame([800, 450, IMAGETYPE_JPEG], $this->medidas('banner', $r['arquivo']));
        $this->assertStringContainsString('Imagem pequena (800 × 450 px)', $r['aviso']);
    }

    public function test_logo_inteira_num_quadrado_transparente_sem_ampliar(): void
    {
        $grande = TratadorDeImagem::logo($this->imagem(1000, 500), 'campeonatos', 'logo-campeonato');
        $this->assertMatchesRegularExpression('/\.png$/', $grande['arquivo']);
        $this->assertSame([512, 512, IMAGETYPE_PNG], $this->medidas('campeonatos', $grande['arquivo']));
        $this->assertNull($grande['aviso']);

        $png = imagecreatefromstring(Storage::disk('imagens')->get("campeonatos/{$grande['arquivo']}"));
        $this->assertSame(127, (imagecolorat($png, 5, 5) >> 24) & 0x7F, 'canto transparente (a imagem não foi cortada: sobra em cima e embaixo)');
        $this->assertSame(0, (imagecolorat($png, 256, 256) >> 24) & 0x7F, 'centro opaco');

        $pequena = TratadorDeImagem::logo($this->imagem(200, 100), 'campeonatos', 'logo-campeonato');
        $this->assertSame([200, 200, IMAGETYPE_PNG], $this->medidas('campeonatos', $pequena['arquivo']));
        $this->assertStringContainsString('Logo pequena: o lado maior tem 200 px.', $pequena['aviso']);

        // Vale o lado maior (a logo é mostrada inteira, num quadrado): 189 × 280 não avisa
        $this->assertNull(TratadorDeImagem::logo($this->imagem(189, 280), 'campeonatos', 'logo-campeonato')['aviso']);
    }

    public function test_campeonato_trata_logo_e_banner_e_apaga_o_antigo_so_se_e_gerado_e_ninguem_usa(): void
    {
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $dados = ['nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA', 'data_inicio_campeonato' => now()->toDateString(),
            'data_fim_campeonato' => now()->addMonth()->toDateString(), 'categorias' => [$sub15]];

        $this->comoAdmin()->post(route('admin.campeonatos.store'), $dados + [
            'logo_evento' => $this->upload(1000, 1000), 'banner_evento' => $this->upload(900, 600), 'recorte_banner_evento' => '0,0,900,450',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('avisos_imagem', ['Imagem pequena (900 × 450 px): pode ficar sem nitidez no site. O ideal é uma foto com pelo menos 1200 px de largura.']);

        $copa = Campeonato::where('nome_campeonato', 'Copa')->sole();
        $this->assertSame([512, 512, IMAGETYPE_PNG], $this->medidas('campeonatos', $copa->logo_evento));
        $this->assertSame([900, 450, IMAGETYPE_JPEG], $this->medidas('campeonatos', $copa->banner_evento));
        $this->assertStringEndsWith("/futebol/images/campeonatos/{$copa->banner_evento}", $copa->banner_url);

        // Trocar a logo apaga a gerada antiga; o banner (sem arquivo novo) fica
        $logoAntiga = $copa->logo_evento;
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), $dados + ['logo_evento' => $this->upload(600, 600)])
            ->assertSessionHasNoErrors()->assertSessionHas('avisos_imagem', []);
        $copa->refresh();
        $this->assertNotSame($logoAntiga, $copa->logo_evento);
        Storage::disk('imagens')->assertMissing("campeonatos/{$logoAntiga}");
        Storage::disk('imagens')->assertExists("campeonatos/{$copa->banner_evento}");

        // Arquivo que veio com o projeto (nome não gerado) ou usado por outro campeonato: fica
        Storage::disk('imagens')->put('campeonatos/logo.png', 'x');
        $copa->update(['logo_evento' => 'logo.png']);
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), $dados + ['logo_evento' => $this->upload(600, 600)]);
        Storage::disk('imagens')->assertExists('campeonatos/logo.png');

        $compartilhada = $copa->refresh()->logo_evento;
        DB::table('tbl_campeonato')->insert(['id_categoria' => $sub15, 'nome_campeonato' => 'Outra', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now(), 'data_fim_campeonato' => now(), 'logo_evento' => $compartilhada, 'status_campeonato' => 'ATIVO']);
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), $dados + ['logo_evento' => $this->upload(600, 600)]);
        Storage::disk('imagens')->assertExists("campeonatos/{$compartilhada}");
    }

    public function test_banner_da_home_em_16_por_9_e_a_troca_apaga_o_antigo(): void
    {
        $dados = ['titulo_banner' => 'Bem-vindo', 'ordem_banner' => 1, 'status_banner' => 'ativo'];

        $this->comoAdmin()->post(route('admin.banners.store'), $dados + ['foto_banner' => $this->upload(3000, 3000)])
            ->assertSessionHasNoErrors()->assertSessionHas('avisos_imagem', []);
        $banner = Banner::sole();
        $this->assertSame([1920, 1080, IMAGETYPE_JPEG], $this->medidas('banner', $banner->foto_banner));

        $antiga = $banner->foto_banner;
        $this->comoAdmin()->put(route('admin.banners.update', $banner->id_banner), $dados + ['foto_banner' => $this->upload(1920, 1080)])
            ->assertSessionHasNoErrors();
        Storage::disk('imagens')->assertMissing("banner/{$antiga}");
        Storage::disk('imagens')->assertExists('banner/' . $banner->refresh()->foto_banner);
    }

    public function test_edicao_do_campeonato_mostra_a_imagem_atual_em_miniatura(): void
    {
        $id = DB::table('tbl_campeonato')->insertGetId(['id_categoria' => $this->idCategoria('Sub-15', 'M'), 'nome_campeonato' => 'Copa',
            'tipo_campeonato' => 'COPA', 'data_inicio_campeonato' => now(), 'data_fim_campeonato' => now(), 'status_campeonato' => 'ATIVO',
            'logo_evento' => 'logo-campeonato-20261026-150000-aaaaaa.png', 'banner_evento' => 'banner-campeonato-20261026-150000-bbbbbb.jpg']);
        $copa = Campeonato::find($id);

        // Página de edição: a miniatura, não o nome do arquivo
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $id))->assertOk()
            ->assertSee('src="' . $copa->logo_url . '"', false)->assertSee('src="' . $copa->banner_url . '"', false)
            ->assertSee('Imagem atual')->assertDontSee('Atual: logo-campeonato');

        // Modal "Editar" da lista: o endereço vai no botão, para o JavaScript montar a miniatura
        $this->comoAdmin()->get(route('admin.campeonatos.index'))->assertOk()
            ->assertSee('data-logo="' . $copa->logo_url . '"', false)->assertSee('data-banner="' . $copa->banner_url . '"', false)
            ->assertSee('id="edit_logo_atual"', false);
    }

    public function test_carrossel_da_home_ocupa_a_largura_da_tela(): void
    {
        Banner::create(['titulo_banner' => 'Bem-vindo', 'foto_banner' => 'banner-site-20261026-210942-9ddj4g.jpg', 'ordem_banner' => 1, 'status_banner' => 'ativo']);

        // A imagem invisível do slide define a altura: na largura toda, 16:9 com qualquer arquivo (estilo.css, seção 76)
        $this->get('/')->assertOk()->assertSee('futebol/images/banner/banner-site-20261026-210942-9ddj4g.jpg');
        $this->assertStringContainsString('.banner .carousel-inner > .item > img { width: 100%; }',
            file_get_contents(public_path('coderatech/css/estilo.css')));
    }

    public function test_banner_sem_subtitulo_nao_mostra_o_bloco_vazio(): void
    {
        Banner::create(['titulo_banner' => 'Só título', 'subtitulo_banner' => null, 'foto_banner' => 'a.jpg', 'ordem_banner' => 1, 'status_banner' => 'ativo']);
        $this->get('/')->assertOk()->assertSee('Só título')->assertDontSee('class="caption-desc"', false);

        Banner::create(['titulo_banner' => 'Com subtítulo', 'subtitulo_banner' => 'Matrículas abertas', 'foto_banner' => 'b.jpg', 'ordem_banner' => 2, 'status_banner' => 'ativo']);
        $this->get('/')->assertOk()->assertSee('<div class="caption-desc">Matrículas abertas</div>', false);
    }

    public function test_envio_acima_de_10_mb_e_recusado(): void
    {
        $this->comoAdmin()->post(route('admin.banners.store'), ['titulo_banner' => 'B', 'ordem_banner' => 1, 'status_banner' => 'ativo',
            'foto_banner' => UploadedFile::fake()->image('grande.jpg', 100, 100)->size(TratadorDeImagem::ENVIO_MAX_KB + 1)])
            ->assertSessionHasErrors(['foto_banner' => 'Não foi possível enviar a imagem. Use um arquivo de até 10 MB.']);
        $this->assertSame(0, Banner::count());
    }

    public function test_falha_de_envio_tem_mensagem_em_portugues_em_todos_os_campos_de_imagem(): void
    {
        // Arquivo que o PHP recusou (acima do upload_max_filesize): UPLOAD_ERR_INI_SIZE
        $recusado = fn (string $nome) => new UploadedFile($this->imagem(10, 10), $nome, 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->comoAdmin()->post(route('admin.banners.store'), ['titulo_banner' => 'B', 'ordem_banner' => 1, 'status_banner' => 'ativo',
            'foto_banner' => $recusado('foto.jpg')])
            ->assertSessionHasErrors(['foto_banner' => 'Não foi possível enviar a imagem. Use um arquivo de até 10 MB.']);

        $this->comoAdmin()->post(route('admin.campeonatos.store'), ['nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->toDateString(),
            'categorias' => [$this->idCategoria('Sub-15', 'M')], 'logo_evento' => $recusado('logo.jpg'), 'banner_evento' => $recusado('banner.jpg')])
            ->assertSessionHasErrors([
                'logo_evento'   => 'Não foi possível enviar a imagem. Use um arquivo de até 10 MB.',
                'banner_evento' => 'Não foi possível enviar a imagem. Use um arquivo de até 10 MB.',
            ]);

        $this->comoAdmin()->post(route('admin.noticias.store'), ['foto_noticia' => $recusado('n.jpg')])
            ->assertSessionHasErrors(['foto_noticia' => 'Não foi possível enviar a imagem. Use um arquivo de até 2 MB.']);
        $this->comoAdmin()->post(route('admin.times.store'), ['logo_time' => $recusado('t.jpg')])
            ->assertSessionHasErrors(['logo_time' => 'Não foi possível enviar a imagem. Use um arquivo de até 1 MB.']);
        $this->comoAdmin()->post(route('admin.galeria.store'), ['foto_galeria' => $recusado('g.jpg')])
            ->assertSessionHasErrors(['foto_galeria' => 'Não foi possível enviar a imagem. Use um arquivo de até 4 MB.']);
    }

    public function test_comando_simula_trata_copia_os_originais_e_pula_o_que_corta_demais(): void
    {
        $disco = Storage::disk('imagens');
        $disco->put('campeonatos/logo.png', file_get_contents($this->imagem(1254, 1254)));
        $disco->put('campeonatos/em-pe.jpg', file_get_contents($this->imagem(736, 1041)));
        $disco->put('banner/banner1.jpg', file_get_contents($this->imagem(1384, 768)));
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $base = ['id_categoria' => $sub15, 'tipo_campeonato' => 'COPA', 'data_inicio_campeonato' => now(), 'data_fim_campeonato' => now(), 'status_campeonato' => 'ATIVO'];
        DB::table('tbl_campeonato')->insert([$base + ['nome_campeonato' => 'A', 'logo_evento' => 'logo.png', 'banner_evento' => 'em-pe.jpg'],
            $base + ['nome_campeonato' => 'B', 'logo_evento' => 'logo.png', 'banner_evento' => null]]);
        Banner::create(['titulo_banner' => 'B', 'foto_banner' => 'banner1.jpg', 'ordem_banner' => 1, 'status_banner' => 'ativo']);

        $this->artisan('imagens:reprocessar', ['--simular' => true])->expectsOutputToContain('Simulação: nada foi gravado.')->assertSuccessful();
        $this->assertSame(['logo.png'], DB::table('tbl_campeonato')->distinct()->pluck('logo_evento')->all());

        $this->travelTo('2026-10-26 15:00:00');
        $this->artisan('imagens:reprocessar')->expectsOutputToContain('2 arquivo(s) tratado(s).')->assertSuccessful();

        $logos = DB::table('tbl_campeonato')->distinct()->pluck('logo_evento');
        $this->assertCount(1, $logos, 'o arquivo usado pelos dois campeonatos é tratado uma vez');
        $this->assertSame([512, 512, IMAGETYPE_PNG], $this->medidas('campeonatos', $logos[0]));
        $this->assertSame('em-pe.jpg', DB::table('tbl_campeonato')->where('nome_campeonato', 'A')->value('banner_evento'), 'corta 65%: pulado');
        $this->assertSame([1365, 768, IMAGETYPE_JPEG], $this->medidas('banner', Banner::sole()->foto_banner));
        $disco->assertExists('campeonatos/logo.png');   // o original fica

        $copia = storage_path('app/imagens-originais/20261026-150000');
        $this->assertFileExists("{$copia}/campeonatos/logo.png");
        $this->assertFileExists("{$copia}/banner/banner1.jpg");
        $this->apagarPasta($copia);

        // De novo: tudo já tratado (o em pé continua pulado)
        $this->artisan('imagens:reprocessar')->expectsOutputToContain('0 arquivo(s) tratado(s).')->assertSuccessful();

        // --forcar trata o que corta demais
        $this->artisan('imagens:reprocessar', ['--campeonatos' => true, '--forcar' => true])->expectsOutputToContain('1 arquivo(s) tratado(s).');
        $this->assertSame([736, 368, IMAGETYPE_JPEG], $this->medidas('campeonatos', DB::table('tbl_campeonato')->where('nome_campeonato', 'A')->value('banner_evento')));
        $this->apagarPasta($copia);   // só a cópia deste teste (a pasta pode ter cópias de verdade)
    }

    // Imagem JPG de teste num arquivo temporário: cinza, ou metade vermelha e metade azul
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

    private function upload(int $largura, int $altura): UploadedFile
    {
        return new UploadedFile($this->imagem($largura, $altura), 'foto.jpg', 'image/jpeg', null, true);
    }

    private function medidas(string $pasta, string $arquivo): array
    {
        [$l, $a, $tipo] = getimagesize(Storage::disk('imagens')->path("{$pasta}/{$arquivo}"));

        return [$l, $a, $tipo];
    }

    private function corNoCentro(string $pasta, string $arquivo): string
    {
        [$l, $a] = $this->medidas($pasta, $arquivo);

        return $this->corEm($pasta, $arquivo, intdiv($l, 2), intdiv($a, 2));
    }

    private function corEm(string $pasta, string $arquivo, int $x, int $y): string
    {
        $cor = imagecolorat(imagecreatefromstring(Storage::disk('imagens')->get("{$pasta}/{$arquivo}")), $x, $y);

        return (($cor >> 16) & 0xFF) > (($cor) & 0xFF) ? 'vermelho' : 'azul';
    }

    private function apagarPasta(string $pasta): void
    {
        if (! is_dir($pasta)) {
            return;
        }
        $itens = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($itens as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($pasta);
    }
}
