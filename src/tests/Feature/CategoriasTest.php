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

    public function test_limpeza_da_sub12_move_vinculos_e_recalcula_atletas(): void
    {
        $idSub12  = DB::table('tbl_categoria')->insertGetId([
            'nome_categoria' => 'Sub-12', 'idade_min_categoria' => 10, 'idade_max_categoria' => 12, 'sexo_categoria' => 'M',
        ]);
        $idSub11M = DB::table('tbl_categoria')->where('nome_categoria', 'Sub-11')->where('sexo_categoria', 'M')->value('id_categoria');
        $idSub13M = DB::table('tbl_categoria')->where('nome_categoria', 'Sub-13')->where('sexo_categoria', 'M')->value('id_categoria');

        $idTime = DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idSub12, 'logo_time' => 'x.png', 'nome_time' => 'Time Teste', 'tipo_time' => 'INTERNO',
        ]);
        $idCampeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idSub12, 'logo_evento' => 'x.png', 'banner_evento' => 'x.png', 'nome_campeonato' => 'Copa Teste',
            'organizador_campeonato' => 'AACJ', 'tipo_campeonato' => 'TORNEIO', 'data_inicio_campeonato' => now(),
            'data_fim_campeonato' => now(), 'local_evento' => 'Campo',
        ]);

        // Idade 12 no ano atual: vai para a Sub-13 M. Nascido em 1984: fora de 9–17, fica sem categoria.
        $anoSub13 = now()->year - 12;
        $idNaFaixa = $this->criarAtletaNaCategoria("{$anoSub13}-12-31", $idSub12);
        $idForaDaFaixa = $this->criarAtletaNaCategoria('1984-09-10', $idSub12);

        $migration = require database_path('migrations/2026_10_03_000003_remove_categoria_sub12_de_teste.php');
        $migration->up();

        $this->assertDatabaseMissing('tbl_categoria', ['id_categoria' => $idSub12]);
        $this->assertDatabaseHas('tbl_time', ['id_time' => $idTime, 'id_categoria' => $idSub11M]);
        $this->assertDatabaseHas('tbl_campeonato', ['id_campeonato' => $idCampeonato, 'id_categoria' => $idSub11M]);

        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta' => $idNaFaixa, 'id_categoria' => $idSub13M, 'status_categoria_atleta' => 'ATIVO',
        ]);
        $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $idForaDaFaixa]);

        // Rodar de novo não faz nada (a Sub-12 já não existe)
        $migration->up();
        $this->assertSame(1, DB::table('tbl_categoria_atleta')->where('id_atleta', $idNaFaixa)->count());
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

    private function criarAtletaNaCategoria(string $nascimento, int $idCategoria): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua', 'numero_endereco' => '1', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        $idAtleta = DB::table('tbl_atletas')->insertGetId([
            'id_endereco' => $idEndereco, 'nome_atleta' => 'Atleta', 'data_nasc_atleta' => $nascimento,
            'cpf_atleta' => '000.000.000-00', 'rg_atleta' => '0000000', 'sexo_atleta' => 'M',
            'escola_atleta' => 'Escola', 'status_atleta' => 'ATIVO',
        ]);

        DB::table('tbl_categoria_atleta')->insert([
            'id_categoria' => $idCategoria, 'id_atleta' => $idAtleta,
            'data_inicio_categoria_atleta' => now(), 'status_categoria_atleta' => 'ATIVO',
        ]);

        return $idAtleta;
    }
}
