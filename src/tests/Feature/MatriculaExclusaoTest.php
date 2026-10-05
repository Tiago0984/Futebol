<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class MatriculaExclusaoTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_admin_exclui_atleta_rejeitado_com_vinculos(): void
    {
        $idAtleta    = $this->criarAtleta('REJEITADO');
        $idCategoria = $this->criarCategoria();

        DB::table('tbl_categoria_atleta')->insert([
            'id_categoria'                 => $idCategoria,
            'id_atleta'                    => $idAtleta,
            'data_inicio_categoria_atleta' => now(),
            'status_categoria_atleta'      => 'ATIVO',
        ]);

        $this->comoAdmin()
            ->delete(route('admin.matriculas.deletar', $idAtleta))
            ->assertRedirect(route('admin.matriculas.rejeitadas'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('tbl_atletas', ['id_atleta' => $idAtleta]);
        $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $idAtleta]);
    }

    public function test_exclusao_remove_as_inscricoes_em_eventos(): void
    {
        $idAtleta = $this->criarAtleta('REJEITADO');
        $idEvento = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Evento', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'status_evento_calendario' => 'ATIVO',
        ]);
        DB::table('tbl_evento_atleta')->insert([
            'id_evento_calendario' => $idEvento, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL',
        ]);

        $this->comoAdmin()
            ->delete(route('admin.matriculas.deletar', $idAtleta))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('tbl_atletas', ['id_atleta' => $idAtleta]);
        $this->assertDatabaseMissing('tbl_evento_atleta', ['id_atleta' => $idAtleta]);
        $this->assertDatabaseHas('tbl_evento_calendario', ['id_evento_calendario' => $idEvento]);
    }

    public function test_exclusao_remove_as_notificacoes(): void
    {
        $idAtleta = $this->criarAtleta('REJEITADO');
        $idOutro  = $this->criarAtleta('ATIVO');
        foreach ([$idAtleta, $idAtleta, $idOutro] as $id) {
            DB::table('tbl_notificacao')->insert([
                'id_atleta' => $id, 'tipo_notificacao' => 'AGENDA',
                'titulo_notificacao' => 'Agenda de dezembro disponível', 'mensagem_notificacao' => 'Seus treinos já estão na agenda.',
            ]);
        }

        $this->comoAdmin()
            ->delete(route('admin.matriculas.deletar', $idAtleta))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('tbl_atletas', ['id_atleta' => $idAtleta]);
        $this->assertDatabaseMissing('tbl_notificacao', ['id_atleta' => $idAtleta]);
        $this->assertSame(1, DB::table('tbl_notificacao')->where('id_atleta', $idOutro)->count()); // as dos outros ficam
    }

    public function test_atleta_com_cartao_nao_e_excluido(): void
    {
        $idAtleta = $this->criarAtleta('REJEITADO');

        DB::table('tbl_cartoes')->insert([
            'id_atleta'   => $idAtleta,
            'tipo_cartao' => 'AMARELO',
        ]);

        $this->comoAdmin()
            ->delete(route('admin.matriculas.deletar', $idAtleta))
            ->assertRedirect(route('admin.matriculas.rejeitadas'))
            ->assertSessionHas('erro');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta]);
    }

    public function test_tbl_inscricao_foi_descontinuada(): void
    {
        $this->assertFalse(Schema::hasTable('tbl_inscricao'));
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'admin');
    }

    private function criarAtleta(string $status): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco'    => 'Rua de Teste',
            'numero_endereco' => '100',
            'bairro_endereco' => 'Centro',
            'cep_endereco'    => '01000-000',
            'cidade_endereco' => 'São Paulo',
            'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_atletas')->insertGetId([
            'id_endereco'      => $idEndereco,
            'nome_atleta'      => 'Atleta de Teste',
            'data_nasc_atleta' => '2013-05-10',
            'cpf_atleta'       => '000.000.000-00',
            'rg_atleta'        => '00.000.000-0',
            'sexo_atleta'      => 'M',
            'escola_atleta'    => 'Escola de Teste',
            'status_atleta'    => $status,
        ]);
    }

    private function criarCategoria(): int
    {
        return DB::table('tbl_categoria')->insertGetId([
            'nome_categoria'      => 'Categoria de Teste',
            'idade_min_categoria' => 12,
            'idade_max_categoria' => 13,
            'sexo_categoria'      => 'M',
        ]);
    }
}
