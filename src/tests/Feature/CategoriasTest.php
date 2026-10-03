<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class CategoriasTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_migrations_criam_as_10_categorias_oficiais(): void
    {
        $esperadas = [
            'Sub-9' => [9, 9], 'Sub-11' => [10, 11], 'Sub-13' => [12, 13], 'Sub-15' => [14, 15], 'Sub-17' => [16, 17],
        ];

        $this->assertSame(10, DB::table('tbl_categoria')->count());

        foreach ($esperadas as $nome => [$min, $max]) {
            foreach (['M', 'F'] as $sexo) {
                $this->assertDatabaseHas('tbl_categoria', [
                    'nome_categoria'      => $nome,
                    'sexo_categoria'      => $sexo,
                    'idade_min_categoria' => $min,
                    'idade_max_categoria' => $max,
                    'status_categoria'    => 'ATIVO',
                ]);
            }
        }
    }

    public function test_rotulo_mostra_nome_e_sexo(): void
    {
        $categoria = Categoria::where('nome_categoria', 'Sub-13')->where('sexo_categoria', 'F')->first();

        $this->assertSame('Sub-13 Feminino', $categoria->rotulo);
    }

    public function test_admin_nao_cria_categoria_misto(): void
    {
        $this->comoAdmin()
            ->post(route('admin.categorias.store'), $this->dados(['sexo_categoria' => 'Misto']))
            ->assertSessionHasErrors('sexo_categoria');
    }

    public function test_admin_nao_cria_categoria_repetida_para_o_mesmo_sexo(): void
    {
        $this->comoAdmin()
            ->post(route('admin.categorias.store'), $this->dados(['nome_categoria' => 'Sub-13', 'sexo_categoria' => 'M']))
            ->assertSessionHasErrors('nome_categoria');

        $this->assertSame(1, DB::table('tbl_categoria')->where('nome_categoria', 'Sub-13')->where('sexo_categoria', 'M')->count());
    }

    public function test_admin_cria_categoria_nova(): void
    {
        $this->comoAdmin()
            ->post(route('admin.categorias.store'), $this->dados())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_categoria', ['nome_categoria' => 'Sub-19', 'sexo_categoria' => 'M']);
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'admin');
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome_categoria'      => 'Sub-19',
            'idade_min_categoria' => 18,
            'idade_max_categoria' => 19,
            'sexo_categoria'      => 'M',
        ], $extra);
    }
}
