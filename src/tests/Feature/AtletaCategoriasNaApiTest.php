<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Responsavel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * "categorias" em GET /api/v1/atleta e GET /api/v1/responsavel/atletas/{id} (06/11/2026): o histórico inteiro, da
 * mais recente para a mais antiga, no mesmo formato de antes, com a observação da secretaria sempre null.
 */
class AtletaCategoriasNaApiTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const OBSERVACAO = 'Anotação interna da secretaria.';

    private int $id;
    private int $sub13;
    private int $sub15;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sub13 = $this->idCategoria('Sub-13', 'M');
        $this->sub15 = $this->idCategoria('Sub-15', 'M');
        $this->id = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');

        // Sub-13 → Sub-15 → Sub-13: a mesma categoria duas vezes são dois vínculos
        $atleta = Atleta::find($this->id);
        $this->em('2025-02-01 09:00:00', fn () => $atleta->trocarCategoria($this->sub13, self::OBSERVACAO));
        $this->em('2026-01-10 10:00:00', fn () => $atleta->trocarCategoria($this->sub15, self::OBSERVACAO));
        $this->em('2026-03-05 11:00:00', fn () => $atleta->trocarCategoria($this->sub13));
    }

    public function test_atleta_recebe_o_historico_da_mais_recente_para_a_mais_antiga_no_mesmo_formato(): void
    {
        $categorias = $this->doAtleta();

        $this->assertSame(['Sub-13', 'Sub-15', 'Sub-13'], array_column($categorias, 'nome_categoria'));
        $this->assertSame(
            ['2026-03-05T11:00:00-03:00', '2026-01-10T10:00:00-03:00', '2025-02-01T09:00:00-03:00'],
            array_column(array_column($categorias, 'pivot'), 'data_inicio_categoria_atleta')
        );
        $this->assertSame(
            [null, '2026-03-05T11:00:00-03:00', '2026-01-10T10:00:00-03:00'],
            array_column(array_column($categorias, 'pivot'), 'data_fim_categoria_atleta')
        );
        $this->assertSame(['ATIVO', 'ENCERRADO', 'ENCERRADO'], array_column(array_column($categorias, 'pivot'), 'status_categoria_atleta'));

        $this->assertSame(['id_categoria', 'nome_categoria', 'pivot'], array_keys($categorias[1]));
        $this->assertEqualsCanonicalizing([
            'id_atleta', 'id_categoria', 'data_inicio_categoria_atleta', 'data_fim_categoria_atleta',
            'data_atualizacao_categoria_atleta', 'status_categoria_atleta', 'observacao_categoria_atleta',
        ], array_keys($categorias[1]['pivot']));
    }

    public function test_so_uma_categoria_e_a_atual(): void
    {
        $atuais = array_filter($this->doAtleta(), fn ($categoria) => $categoria['pivot']['status_categoria_atleta'] === 'ATIVO'
            && $categoria['pivot']['data_fim_categoria_atleta'] === null);

        $this->assertCount(1, $atuais);
        $this->assertSame($this->sub13, reset($atuais)['id_categoria']);
    }

    public function test_responsavel_recebe_o_mesmo_historico(): void
    {
        $doAtleta = $this->doAtleta();

        Sanctum::actingAs(Responsavel::find($this->criarResponsavel($this->id)));
        $doResponsavel = $this->getJson("/api/v1/responsavel/atletas/{$this->id}")->assertOk()->json('data.categorias');

        $this->assertCount(3, $doResponsavel);
        $this->assertSame($doAtleta, $doResponsavel);
    }

    public function test_observacao_vem_null_nas_duas_rotas_e_continua_gravada(): void
    {
        $doAtleta = $this->doAtleta();

        Sanctum::actingAs(Responsavel::find($this->criarResponsavel($this->id)));
        $doResponsavel = $this->getJson("/api/v1/responsavel/atletas/{$this->id}")->assertOk();

        foreach ($doAtleta as $categoria) {
            $this->assertArrayHasKey('observacao_categoria_atleta', $categoria['pivot']);
            $this->assertNull($categoria['pivot']['observacao_categoria_atleta']);
        }
        $this->assertSame([null, null, null], array_column(array_column($doResponsavel->json('data.categorias'), 'pivot'), 'observacao_categoria_atleta'));
        $doResponsavel->assertDontSee(self::OBSERVACAO);

        $this->assertSame(2, DB::table('tbl_categoria_atleta')->where('observacao_categoria_atleta', self::OBSERVACAO)->count());
    }

    public function test_vinculos_no_mesmo_instante_vem_do_mais_novo_para_o_mais_antigo(): void
    {
        $outro = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        $atleta = Atleta::find($outro);
        $this->em('2026-06-01 08:00:00', function () use ($atleta) {
            $atleta->trocarCategoria($this->sub13);
            $atleta->trocarCategoria($this->sub15);
        });

        Sanctum::actingAs($atleta);

        $this->assertSame(['Sub-15', 'Sub-13'], array_column($this->getJson('/api/v1/atleta')->json('data.categorias'), 'nome_categoria'));
    }

    public function test_estatisticas_continuam_so_com_a_categoria_atual(): void
    {
        Sanctum::actingAs(Atleta::find($this->id));

        $this->getJson('/api/v1/atleta/estatisticas')
            ->assertOk()
            ->assertJsonPath('data.categoria_atual.id_categoria', $this->sub13)
            ->assertJsonMissingPath('data.categorias');
    }

    private function doAtleta(): array
    {
        Sanctum::actingAs(Atleta::find($this->id));

        return $this->getJson('/api/v1/atleta')->assertOk()->json('data.categorias');
    }

    private function em(string $momento, callable $acao): void
    {
        Carbon::setTestNow($momento);
        $acao();
        Carbon::setTestNow();
    }
}
