<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Support\Facades\DB;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * GET /api/v1/categorias/{id}/times. A rota dava 500 desde que os times passaram a ter várias categorias
 * (27/10/2026): id_time era ambíguo na consulta com a tbl_time_categoria, e nenhum teste a cobria.
 */
class CategoriaTimesApiTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_times_da_categoria_responde_pela_tabela_de_categorias_do_time(): void
    {
        $categoria = Categoria::where('nome_categoria', 'Sub-13')->where('sexo_categoria', 'M')->firstOrFail();
        $idTime = DB::table('tbl_time')->insertGetId(['nome_time' => 'AACJ Masculino', 'logo_time' => 'aacj.png', 'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO']);
        DB::table('tbl_time_categoria')->insert(['id_time' => $idTime, 'id_categoria' => $categoria->id_categoria]);

        $this->getJson("/api/v1/categorias/{$categoria->id_categoria}/times")
            ->assertOk()
            ->assertJsonPath('data.times.0.id_time', $idTime)
            ->assertJsonPath('data.times.0.da_aacj', true)
            ->assertJsonMissingPath('data.times.0.pivot');
    }
}
