<?php

namespace Tests\Feature;

use App\Models\Time;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Tela de Times com as categorias marcadas (27/10/2026): um cadastro só por time (a AACJ e cada adversário), com
 * uma caixa por categoria; nome sem repetir; a categoria em que o time está num campeonato não é desmarcada.
 */
class TimesCategoriasTest extends TestCase
{
    use CriaDadosDeAtleta, RefreshBancoDeTestes;

    private int $sub13;
    private int $sub15;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sub13 = $this->idCategoria('Sub-13');
        $this->sub15 = $this->idCategoria('Sub-15');
    }

    public function test_cria_o_time_com_as_categorias_marcadas(): void
    {
        $this->comoAdmin()->post(route('admin.times.store'), [
            'nome_time' => 'AACJ', 'tipo_time' => 'INTERNO', 'categorias' => [$this->sub15, $this->sub13],
        ])->assertSessionHas('sucesso');

        $time = Time::where('nome_time', 'AACJ')->firstOrFail();
        $this->assertSame([$this->sub13, $this->sub15], $this->idsDas($time));
        // A coluna antiga ficou sem uso (28/10/2026): o time novo não a preenche
        $this->assertNull($time->id_categoria);
    }

    public function test_exige_categoria_e_nao_repete_o_nome(): void
    {
        $this->comoAdmin()->post(route('admin.times.store'), ['nome_time' => 'Botafogo', 'tipo_time' => 'EXTERNO'])
            ->assertSessionHasErrors(['categorias' => 'Marque ao menos uma categoria de que o time participa.']);

        $this->timeCom('Botafogo', 'EXTERNO', [$this->sub13]);
        $this->comoAdmin()->post(route('admin.times.store'), [
            'nome_time' => 'botafogo', 'tipo_time' => 'EXTERNO', 'categorias' => [$this->sub15],
        ])->assertSessionHasErrors(['nome_time' => 'Já existe um time com este nome. Marque as categorias nele, em vez de cadastrar outro.']);

        $this->assertSame(1, Time::count());
    }

    public function test_masculino_e_feminino_em_cadastros_separados(): void
    {
        // 28/10/2026, dono do projeto: "AACJ" e "AACJ Feminino", para todos os times; outra AACJ do mesmo sexo pode
        $sub13F = (int) DB::table('tbl_categoria')->where('nome_categoria', 'Sub-13')->where('sexo_categoria', 'F')->value('id_categoria');

        $this->comoAdmin()->post(route('admin.times.store'), [
            'nome_time' => 'AACJ Masculino', 'tipo_time' => 'INTERNO', 'categorias' => [$this->sub13, $sub13F],
        ])->assertSessionHasErrors(['categorias' =>
            'Um time não mistura categorias masculinas e femininas. Para o feminino, cadastre outro time (ex.: "AACJ Feminino").']);
        $this->assertSame(0, Time::count());

        foreach ([['AACJ Masculino', [$this->sub13, $this->sub15]], ['AACJ Feminino', [$sub13F]], ['AACJ B', [$this->sub13]]] as [$nome, $categorias]) {
            $this->comoAdmin()->post(route('admin.times.store'), ['nome_time' => $nome, 'tipo_time' => 'INTERNO', 'categorias' => $categorias])
                ->assertSessionHas('sucesso');
        }
        $this->assertSame(['AACJ B', 'AACJ Feminino', 'AACJ Masculino'], Time::orderBy('nome_time')->pluck('nome_time')->all());

        // Na edição, a mesma regra
        $aacj = Time::where('nome_time', 'AACJ Masculino')->firstOrFail();
        $this->comoAdmin()->put(route('admin.times.update', $aacj->id_time), [
            'nome_time' => 'AACJ Masculino', 'tipo_time' => 'INTERNO', 'categorias' => [$this->sub13, $sub13F],
        ])->assertSessionHasErrors('categorias');
        $this->assertSame([$this->sub13, $this->sub15], $this->idsDas($aacj));
    }

    public function test_edicao_marca_e_desmarca_mas_nao_tira_a_categoria_de_um_campeonato(): void
    {
        $botafogo = $this->timeCom('Botafogo', 'EXTERNO', [$this->sub13, $this->sub15]);
        $copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->sub13, 'nome_campeonato' => 'Copa da Cidade', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => '2026-11-01', 'data_fim_campeonato' => '2026-11-30',
        ]);
        $this->participantes($copa, [$botafogo->id_time], $this->sub13);

        // Tirar o Sub-13, em que ele joga a Copa: recusado, nada muda
        $this->comoAdmin()->put(route('admin.times.update', $botafogo->id_time), [
            'nome_time' => 'Botafogo', 'tipo_time' => 'EXTERNO', 'categorias' => [$this->sub15], '_editar_id' => $botafogo->id_time,
        ])->assertSessionHasErrors(['categorias' =>
            'O Botafogo joga no Sub-13 Masculino do campeonato Copa da Cidade. Tire-o do campeonato nessa categoria antes de desmarcá-la.']);
        $this->assertSame([$this->sub13, $this->sub15], $this->idsDas($botafogo->fresh()));

        // Tirar o Sub-15, livre: aceito
        $this->comoAdmin()->put(route('admin.times.update', $botafogo->id_time), [
            'nome_time' => 'Botafogo', 'tipo_time' => 'EXTERNO', 'categorias' => [$this->sub13],
        ])->assertSessionHas('sucesso');
        $this->assertSame([$this->sub13], $this->idsDas($botafogo->fresh()));
    }

    public function test_categoria_inativa_so_continua_no_time_que_ja_a_tem(): void
    {
        $sub17 = $this->idCategoria('Sub-17');
        $aacj = $this->timeCom('AACJ', 'INTERNO', [$this->sub13, $sub17]);
        DB::table('tbl_categoria')->where('id_categoria', $sub17)->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()->put(route('admin.times.update', $aacj->id_time), [
            'nome_time' => 'AACJ', 'tipo_time' => 'INTERNO', 'categorias' => [$this->sub13, $sub17],
        ])->assertSessionHas('sucesso');

        $this->comoAdmin()->post(route('admin.times.store'), [
            'nome_time' => 'Palmeiras', 'tipo_time' => 'EXTERNO', 'categorias' => [$sub17],
        ])->assertSessionHasErrors(['categorias' => 'Categoria inativa não pode ser marcada: Sub-17 Masculino.']);
    }

    public function test_logo_com_nome_gerado_nao_troca_a_de_outro_time(): void
    {
        // 28/10/2026: antes a logo ia com o nome original; enviar "time-preto.jpg" no Palmeiras trocaria a do Botafogo
        Storage::fake('imagens');
        $botafogo = $this->timeCom('Botafogo', 'EXTERNO', [$this->sub13]);
        $botafogo->update(['logo_time' => 'time-preto.jpg']);
        $palmeiras = $this->timeCom('Palmeiras', 'EXTERNO', [$this->sub13]);
        $palmeiras->update(['logo_time' => 'time-verde.png']);

        $this->comoAdmin()->put(route('admin.times.update', $palmeiras->id_time), [
            'nome_time' => 'Palmeiras', 'tipo_time' => 'EXTERNO', 'categorias' => [$this->sub13],
            'logo_time' => UploadedFile::fake()->image('time-preto.png', 300, 200),
        ])->assertSessionHas('sucesso');

        $logo = $palmeiras->fresh()->logo_time;
        $this->assertMatchesRegularExpression('/^logo-time-\d{8}-\d{6}-[a-z0-9]{6}\.png$/', $logo);
        // 300 × 200 entra inteira num quadrado de 300 (o lado maior; não amplia)
        [$largura, $altura] = getimagesizefromstring(Storage::disk('imagens')->get("team/{$logo}"));
        $this->assertSame([300, 300], [$largura, $altura]);
        $this->assertSame('time-preto.jpg', $botafogo->fresh()->logo_time);

        // Trocar de novo apaga a gerada anterior (ninguém mais a usa); a do Git nunca é apagada
        $this->comoAdmin()->put(route('admin.times.update', $palmeiras->id_time), [
            'nome_time' => 'Palmeiras', 'tipo_time' => 'EXTERNO', 'categorias' => [$this->sub13],
            'logo_time' => UploadedFile::fake()->image('outra.png', 600, 600),
        ])->assertSessionHas('sucesso');
        Storage::disk('imagens')->assertMissing("team/{$logo}");
        Storage::disk('imagens')->assertExists('team/' . $palmeiras->fresh()->logo_time);
    }

    public function test_lista_um_time_com_os_selos_das_categorias_e_exclui_com_elas(): void
    {
        $palmeiras = $this->timeCom('Palmeiras', 'EXTERNO', [$this->sub13, $this->sub15]);

        $this->comoAdmin()->get(route('admin.times.index'))->assertOk()
            ->assertSee('Categorias de que o time participa')
            ->assertSee('Sub-13 M')
            ->assertSee('Sub-15 M')
            ->assertSee('data-categorias=",' . $this->sub13 . ',' . $this->sub15 . ',"', false)
            ->assertDontSee('name="id_categoria"', false);

        DB::table('tbl_time')->where('id_time', $palmeiras->id_time)->update(['status_time' => 'INATIVO']);
        $this->comoAdmin()->delete(route('admin.times.destroy', $palmeiras->id_time))->assertSessionHas('sucesso');
        $this->assertDatabaseMissing('tbl_time_categoria', ['id_time' => $palmeiras->id_time]);
        $this->assertDatabaseMissing('tbl_time', ['id_time' => $palmeiras->id_time]);
    }

    private function timeCom(string $nome, string $tipo, array $categorias): Time
    {
        $time = Time::create(['nome_time' => $nome, 'tipo_time' => $tipo, 'logo_time' => 'time-azul.png', 'status_time' => 'ATIVO']);
        $time->definirCategorias($categorias);

        return $time;
    }

    private function idsDas(Time $time): array
    {
        return $time->categorias()->pluck('tbl_categoria.id_categoria')->map(fn ($id) => (int) $id)->all();
    }

    private function idCategoria(string $nome): int
    {
        return (int) DB::table('tbl_categoria')->where('nome_categoria', $nome)->where('sexo_categoria', 'M')->value('id_categoria');
    }
}
