<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase H, Etapa H1: campeonato com várias categorias (tbl_campeonato_categoria) e participantes por categoria
 * (tbl_campeonato_time.id_categoria). O mesmo time pode entrar em várias categorias. Na tela todas valem igual;
 * a de referência (tbl_campeonato.id_categoria, escolhida pelo sistema) está sempre na lista e continua valendo
 * para a idade, a convocação e os jogos até a Etapa H3.
 */
class CampeonatoCategoriasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const IMAGEM_DE_TESTE = 'teste_fase_h1.png';

    private User $admin;
    private int $idSub11M;
    private int $idSub15M;
    private int $idSub17M;
    private int $botafogo;
    private int $aacj15;
    private int $aacj17;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub11M = $this->idCategoria('Sub-11', 'M');
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->botafogo = $this->time('Botafogo', 'EXTERNO', $this->idSub15M);
        $this->aacj15   = $this->time('AACJ Sub-15', 'INTERNO', $this->idSub15M);
        $this->aacj17   = $this->time('AACJ Sub-17', 'INTERNO', $this->idSub17M);
    }

    // ---------- tabela ----------

    public function test_tabelas_com_os_tipos_os_unicos_e_a_fk_composta(): void
    {
        $tipos = collect(DB::select("SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('tbl_campeonato_categoria', 'tbl_campeonato_time')"))->keyBy('c');
        $this->assertSame('int unsigned', $tipos['id_campeonato_categoria']->t);
        $this->assertSame('int', $tipos['id_categoria']->t);
        $this->assertSame('NO', $tipos['id_categoria']->n);

        $unicos = collect(DB::select("SELECT INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
            AND TABLE_NAME IN ('tbl_campeonato_categoria', 'tbl_campeonato_time') GROUP BY INDEX_NAME"))->pluck('c', 'i');
        $this->assertSame('id_campeonato,id_categoria', $unicos['campeonato_categoria_unique']);
        $this->assertSame('id_campeonato,id_categoria,id_time', $unicos['campeonato_time_categoria_unique']);

        $fk = collect(DB::select("SELECT COLUMN_NAME c, REFERENCED_TABLE_NAME t, REFERENCED_COLUMN_NAME r FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_ct_campeonato_categoria' ORDER BY ORDINAL_POSITION"));
        $this->assertSame(['id_campeonato', 'id_categoria'], $fk->pluck('c')->all());
        $this->assertSame('tbl_campeonato_categoria', $fk->first()->t);
    }

    public function test_banco_recusa_participante_em_categoria_que_o_campeonato_nao_tem(): void
    {
        $copa = $this->campeonato('Copa', $this->idSub15M);
        $this->participantes($copa, [$this->botafogo]);

        $this->expectException(QueryException::class);
        DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $copa, 'id_categoria' => $this->idSub17M, 'id_time' => $this->botafogo]);
    }

    // ---------- model ----------

    public function test_grava_varias_categorias_e_o_mesmo_time_em_duas(): void
    {
        $copa = Campeonato::find($this->campeonato('Copa', $this->idSub15M));

        $copa->gravarCategoriasEParticipantes([$this->idSub15M, $this->idSub17M, $this->idSub11M], [
            $this->idSub15M => [$this->botafogo, $this->aacj15],
            $this->idSub17M => [$this->botafogo, $this->aacj17],
            $this->idSub17M + 1000 => [$this->aacj15], // categoria fora da lista: ignorada
        ]);

        $this->assertSame([$this->idSub11M, $this->idSub15M, $this->idSub17M], $copa->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('Sub-11 Masculino, Sub-15 Masculino, Sub-17 Masculino', $copa->rotulo_das_categorias);
        $this->assertEquals([
            $this->idSub15M => [$this->botafogo, $this->aacj15],
            $this->idSub17M => [$this->botafogo, $this->aacj17],
        ], $copa->participantesPorCategoria());

        // times(): cada time uma vez
        $this->assertSame(3, $copa->times()->get()->count());
        $this->assertSame(3, Campeonato::with('times')->find($copa->id_campeonato)->times->count());
        $this->assertTrue($copa->times()->where('tbl_time.id_time', $this->botafogo)->exists());

        // Regravar tira categoria e participantes que saíram
        $copa->gravarCategoriasEParticipantes([$this->idSub15M], [$this->idSub15M => [$this->aacj15]]);
        $this->assertSame([$this->idSub15M], $copa->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all());
        $this->assertEquals([$this->idSub15M => [$this->aacj15]], $copa->participantesPorCategoria());
    }

    public function test_categoria_de_um_campeonato_nao_e_excluida(): void
    {
        $copa = Campeonato::find($this->campeonato('Copa', $this->idSub15M));
        $copa->gravarCategoriasEParticipantes([$this->idSub15M, $this->idSub11M], []);

        $this->assertStringContainsString('1 campeonato(s)', Categoria::find($this->idSub11M)->motivoParaNaoExcluir());
    }

    // ---------- tela ----------

    public function test_criar_campeonato_com_categorias_e_participantes_por_categoria(): void
    {
        $this->comoAdmin()->post(route('admin.campeonatos.store'), [
            ...$this->dados('Copa São Carlos', true),
            'categorias'    => [$this->idSub15M, $this->idSub17M, $this->idSub11M],
            'participantes' => [
                $this->idSub15M => [$this->botafogo, $this->aacj15],
                $this->idSub17M => [$this->botafogo, $this->aacj17],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        // Referência (até a H3): a mais velha das marcadas
        $copa = Campeonato::where('nome_campeonato', 'Copa São Carlos')->sole();
        $this->assertSame($this->idSub17M, (int) $copa->id_categoria);
        $this->assertSame(3, $copa->categorias()->count());
        $this->assertEquals([
            $this->idSub15M => [$this->botafogo, $this->aacj15],
            $this->idSub17M => [$this->botafogo, $this->aacj17],
        ], $copa->participantesPorCategoria());

        // A lista mostra todas as categorias, sem nenhuma "principal"
        $this->comoAdmin()->get(route('admin.campeonatos.index'))->assertOk()
            ->assertSeeInOrder(['Sub-11 Masculino', 'Sub-15 Masculino', 'Sub-17 Masculino'])
            ->assertDontSee('>principal<', false)
            ->assertSee('data-participantes="' . e(json_encode([$this->idSub15M => [$this->botafogo, $this->aacj15], $this->idSub17M => [$this->botafogo, $this->aacj17]])) . '"', false);
    }

    public function test_ao_menos_uma_categoria_e_obrigatoria(): void
    {
        $this->comoAdmin()->post(route('admin.campeonatos.store'), [...$this->dados('Liga', true), 'categorias' => [$this->idSub15M]])
            ->assertSessionHasNoErrors();
        $liga = Campeonato::where('nome_campeonato', 'Liga')->sole();
        $this->assertSame($this->idSub15M, (int) $liga->id_categoria);
        $this->assertSame([$this->idSub15M], $liga->categorias->pluck('id_categoria')->map(fn ($id) => (int) $id)->all());

        $this->comoAdmin()->post(route('admin.campeonatos.store'), $this->dados('Sem categoria', true))
            ->assertSessionHasErrors(['categorias' => 'Marque ao menos uma categoria do campeonato.']);
        $this->comoAdmin()->post(route('admin.campeonatos.store'), [...$this->dados('Vazia', true), 'categorias' => []])
            ->assertSessionHasErrors(['categorias' => 'Marque ao menos uma categoria do campeonato.']);
    }

    public function test_referencia_fica_a_atual_enquanto_marcada_e_senao_a_mais_velha(): void
    {
        $idSub17F = $this->idCategoria('Sub-17', 'F');

        // Sem atual: a mais velha; no empate de idade, a masculina
        $this->assertSame($this->idSub17M, Campeonato::categoriaDeReferencia([$this->idSub11M, $this->idSub17M, $this->idSub15M]));
        $this->assertSame($this->idSub17M, Campeonato::categoriaDeReferencia([$idSub17F, $this->idSub17M]));
        $this->assertNull(Campeonato::categoriaDeReferencia([]));

        // Acrescentar uma categoria mais velha não troca a atual (os jogos não são regravados)
        $copa = $this->campeonato('Copa', $this->idSub15M);
        $this->participantes($copa, [$this->aacj15]);
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa), [
            ...$this->dados('Copa'), 'categorias' => [$this->idSub15M, $this->idSub17M],
            'participantes' => [$this->idSub15M => [$this->aacj15], $this->idSub17M => [$this->aacj17]],
        ])->assertSessionHas('sucesso', 'Campeonato atualizado com sucesso.');
        $this->assertSame($this->idSub15M, (int) Campeonato::find($copa)->id_categoria);

        // Tirar a atual: passa para a mais velha das que ficam
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa), [
            ...$this->dados('Copa'), 'categorias' => [$this->idSub11M, $this->idSub17M],
            'participantes' => [$this->idSub17M => [$this->aacj17]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->idSub17M, (int) Campeonato::find($copa)->id_categoria);
    }

    public function test_editar_trocando_categorias_regrava_os_participantes(): void
    {
        $copa = $this->campeonato('Copa', $this->idSub15M);
        $this->participantes($copa, [$this->botafogo, $this->aacj15]);

        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa), [
            ...$this->dados('Copa'),
            'categorias'    => [$this->idSub15M, $this->idSub17M],
            'participantes' => [
                $this->idSub15M => [$this->aacj15],
                $this->idSub17M => [$this->botafogo, $this->aacj17],
                $this->idSub11M => [$this->botafogo], // bloco de categoria desmarcada: ignorado
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([
            $this->idSub15M => [$this->aacj15],
            $this->idSub17M => [$this->botafogo, $this->aacj17],
        ], Campeonato::find($copa)->participantesPorCategoria());
        $this->assertDatabaseMissing('tbl_campeonato_categoria', ['id_campeonato' => $copa, 'id_categoria' => $this->idSub11M]);
    }

    public function test_time_com_convocados_pode_sair_de_uma_categoria_mas_nao_de_todas(): void
    {
        $copa = Campeonato::find($this->campeonato('Copa', $this->idSub15M));
        $copa->gravarCategoriasEParticipantes([$this->idSub15M, $this->idSub17M], [
            $this->idSub15M => [$this->aacj15], $this->idSub17M => [$this->aacj15],
        ]);
        $ana = $this->criarAtleta($this->nascidoComIdade(15), 'M', 'ATIVO');
        DB::table('tbl_convocacao')->insert(['id_campeonato' => $copa->id_campeonato, 'id_time' => $this->aacj15, 'id_atleta' => $ana, 'camisa_convocacao' => 10]);

        $dados = [...$this->dados('Copa'), 'categorias' => [$this->idSub15M, $this->idSub17M]];

        // Sai do Sub-17 e fica no Sub-15: pode
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), [...$dados, 'participantes' => [$this->idSub15M => [$this->aacj15]]])
            ->assertSessionHasNoErrors();

        // Sai de todas: não pode
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), [...$dados, 'participantes' => [$this->idSub17M => [$this->botafogo]]])
            ->assertSessionHasErrors(['participantes' => 'Não é possível tirar do campeonato time com atletas convocados: AACJ Sub-15 (1 convocado(s)). Remova a convocação do time antes.']);
        $this->assertDatabaseHas('tbl_campeonato_time', ['id_campeonato' => $copa->id_campeonato, 'id_time' => $this->aacj15]);
    }

    public function test_excluir_campeonato_tira_categorias_e_participantes(): void
    {
        $copa = Campeonato::find($this->campeonato('Copa', $this->idSub15M, 'INATIVO'));
        $copa->gravarCategoriasEParticipantes([$this->idSub15M, $this->idSub17M], [$this->idSub17M => [$this->botafogo]]);

        $this->comoAdmin()->delete(route('admin.campeonatos.destroy', $copa->id_campeonato))->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('tbl_campeonato', ['id_campeonato' => $copa->id_campeonato]);
        $this->assertDatabaseMissing('tbl_campeonato_categoria', ['id_campeonato' => $copa->id_campeonato]);
        $this->assertDatabaseMissing('tbl_campeonato_time', ['id_campeonato' => $copa->id_campeonato]);
    }

    public function test_paginas_de_criar_e_editar_mostram_as_categorias_e_os_participantes(): void
    {
        $copa = Campeonato::find($this->campeonato('Copa', $this->idSub15M));
        $copa->gravarCategoriasEParticipantes([$this->idSub15M, $this->idSub17M], [$this->idSub17M => [$this->botafogo]]);

        $this->comoAdmin()->get(route('admin.campeonatos.create'))->assertOk()
            ->assertSee('Categorias do campeonato')->assertSee('Times participantes por categoria')
            ->assertDontSee('Categoria principal');
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $copa->id_campeonato))->assertOk()
            ->assertSee('id="p_cat_' . $this->idSub17M . '" checked', false)
            ->assertSee('name="participantes[' . $this->idSub17M . '][]"', false)
            ->assertSee('id="p_p_' . $this->idSub17M . '_' . $this->botafogo . '"' . "\n" . '                 checked', false);
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    // Campos do formulário. A criação manda logo, banner, organizador e local (colunas NOT NULL no banco, opcionais no
    // formulário: débito anterior à Fase H); a imagem vai para a pasta pública e é apagada no tearDown
    private function dados(string $nome, bool $completo = false): array
    {
        return [
            'nome_campeonato' => $nome, 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            ...($completo ? [
                'organizador_campeonato' => 'Liga', 'local_evento' => 'Campo A',
                'logo_evento'   => UploadedFile::fake()->image(self::IMAGEM_DE_TESTE),
                'banner_evento' => UploadedFile::fake()->image(self::IMAGEM_DE_TESTE),
            ] : []),
        ];
    }

    protected function tearDown(): void
    {
        File::delete(public_path('futebol/images/campeonatos/' . self::IMAGEM_DE_TESTE));

        parent::tearDown();
    }

    private function campeonato(string $nome, int $idCategoria, string $status = 'ATIVO'): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => $nome, 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Campo A', 'status_campeonato' => $status,
        ]);
    }

    private function time(string $nome, string $tipo, int $idCategoria): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
            'status_time' => 'ATIVO',
        ]);
    }
}
