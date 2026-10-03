<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class AtletaCategoriaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- sugeridaPara (regra do ano) ----------

    public function test_nascido_em_dezembro_de_2017_e_sub9_em_2026(): void
    {
        $this->assertSame('Sub-9 Masculino', Categoria::sugeridaPara('2017-12-31', 'M', 2026)?->rotulo);
        $this->assertSame('Sub-9 Feminino', Categoria::sugeridaPara('2017-12-31', 'F', 2026)?->rotulo);
    }

    public function test_sugerida_segue_o_ano_e_nao_a_data(): void
    {
        // Em 2026: 2016 = 10 anos (Sub-11), 2014 = 12 (Sub-13), 2009 = 17 (Sub-17), mesmo nascendo em 1º/jan ou 31/dez
        $this->assertSame('Sub-11 Masculino', Categoria::sugeridaPara('2016-01-01', 'M', 2026)?->rotulo);
        $this->assertSame('Sub-11 Masculino', Categoria::sugeridaPara('2016-12-31', 'M', 2026)?->rotulo);
        $this->assertSame('Sub-13 Feminino', Categoria::sugeridaPara('2014-06-15', 'F', 2026)?->rotulo);
        $this->assertSame('Sub-17 Masculino', Categoria::sugeridaPara('2009-01-01', 'M', 2026)?->rotulo);
    }

    public function test_sem_sugerida_fora_de_9_a_17(): void
    {
        $this->assertNull(Categoria::sugeridaPara('2018-01-01', 'M', 2026)); // 8 anos
        $this->assertNull(Categoria::sugeridaPara('2008-12-31', 'M', 2026)); // 18 anos
    }

    // ---------- aprovação da matrícula ----------

    public function test_aprovacao_grava_a_categoria_sugerida(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        $idSub13M = $this->idCategoria('Sub-13', 'M');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), ['id_categoria' => $idSub13M])
            ->assertRedirect(route('admin.matriculas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'ATIVO']);
        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta' => $idAtleta, 'id_categoria' => $idSub13M, 'status_categoria_atleta' => 'ATIVO',
        ]);
    }

    public function test_aprovacao_exige_categoria(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), [])
            ->assertSessionHasErrors('id_categoria');

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'PENDENTE']);
    }

    public function test_aprovacao_acima_da_idade_exige_motivo_e_grava_a_observacao(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        $idSub15M = $this->idCategoria('Sub-15', 'M');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), ['id_categoria' => $idSub15M])
            ->assertSessionHasErrors('id_categoria');
        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'status_atleta' => 'PENDENTE']);

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), [
                'id_categoria'     => $idSub15M,
                'motivo_categoria' => 'Atleta mais robusto, decisão do técnico.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta'                   => $idAtleta,
            'id_categoria'                => $idSub15M,
            'status_categoria_atleta'     => 'ATIVO',
            'observacao_categoria_atleta' => 'Atleta mais robusto, decisão do técnico.',
        ]);
    }

    public function test_aprovacao_abaixo_da_idade_e_bloqueada_mesmo_com_motivo(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'PENDENTE');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), [
                'id_categoria'     => $this->idCategoria('Sub-13', 'M'),
                'motivo_categoria' => 'Tentativa',
            ])
            ->assertSessionHasErrors('id_categoria');

        $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $idAtleta]);
    }

    public function test_aprovacao_em_categoria_de_outro_sexo_e_bloqueada(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'F', 'PENDENTE');

        $this->comoAdmin()
            ->patch(route('admin.matriculas.aprovar', $idAtleta), ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHasErrors('id_categoria');
    }

    // ---------- troca de categoria na edição do atleta ----------

    public function test_troca_de_categoria_fecha_a_linha_antiga_e_abre_nova(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->colocarNaCategoria($idAtleta, $idSub13M);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'id_categoria'     => $idSub15M,
                'motivo_categoria' => 'Subiu por desempenho.',
            ]))
            ->assertSessionHasNoErrors();

        $linhas = DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->orderBy('id_categoria_atleta')->get();

        $this->assertCount(2, $linhas);
        $this->assertSame($idSub13M, (int) $linhas[0]->id_categoria);
        $this->assertSame('ENCERRADO', $linhas[0]->status_categoria_atleta);
        $this->assertNotNull($linhas[0]->data_fim_categoria_atleta);
        $this->assertSame($idSub15M, (int) $linhas[1]->id_categoria);
        $this->assertSame('ATIVO', $linhas[1]->status_categoria_atleta);
        $this->assertSame('Subiu por desempenho.', $linhas[1]->observacao_categoria_atleta);
    }

    public function test_editar_sem_mudar_a_categoria_nao_cria_linha(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->colocarNaCategoria($idAtleta, $idSub13M);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'id_categoria' => $idSub13M,
                'nome_atleta'  => 'Nome Alterado',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->count());
        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'nome_atleta' => 'Nome Alterado']);
    }

    public function test_troca_para_categoria_abaixo_e_bloqueada_na_edicao(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->colocarNaCategoria($idAtleta, $idSub13M);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'id_categoria' => $this->idCategoria('Sub-11', 'M'),
            ]))
            ->assertSessionHasErrorsIn('edicao', 'id_categoria');

        $this->assertSame(1, DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->count());
        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta' => $idAtleta, 'id_categoria' => $idSub13M, 'status_categoria_atleta' => 'ATIVO',
        ]);
    }

    public function test_editar_so_o_peso_de_atleta_acima_da_idade_nao_pede_motivo(): void
    {
        // 12 anos na Sub-15, com motivo gravado: está acima da idade, mas a categoria não muda
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->colocarNaCategoria($idAtleta, $idSub15M, 'Atleta mais robusto.');

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                'id_categoria' => $idSub15M,
                'peso_atleta'  => 48.5,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'peso_atleta' => 48.5]);
        $this->assertSame(1, DB::table('tbl_categoria_atleta')->where('id_atleta', $idAtleta)->count());
        $this->assertDatabaseHas('tbl_categoria_atleta', [
            'id_atleta' => $idAtleta, 'status_categoria_atleta' => 'ATIVO', 'observacao_categoria_atleta' => 'Atleta mais robusto.',
        ]);
    }

    // ---------- idade de 9 a 17 anos no ano, também no admin ----------

    public function test_admin_cadastro_recusa_8_e_18_anos_no_ano(): void
    {
        $ano = now()->year;

        foreach ([($ano - 8) . '-01-01', ($ano - 18) . '-12-31'] as $nascimento) {
            $this->comoAdmin()
                ->post(route('admin.atletas.store'), $this->dadosCadastro([
                    'data_nasc_atleta' => $nascimento,
                    'id_categoria'     => $this->idCategoria('Sub-9', 'M'),
                ]))
                ->assertSessionHasErrors('data_nasc_atleta');
        }

        $this->assertDatabaseMissing('tbl_atletas', ['nome_atleta' => 'Atleta Novo']);
    }

    public function test_admin_cadastro_aceita_9_e_17_anos_no_ano(): void
    {
        $ano = now()->year;

        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro([
                'data_nasc_atleta' => ($ano - 9) . '-12-31',
                'id_categoria'     => $this->idCategoria('Sub-9', 'M'),
            ]))
            ->assertSessionHasNoErrors();

        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro([
                'data_nasc_atleta' => ($ano - 17) . '-01-01',
                'cpf_atleta'       => '333.333.333-33',
                'id_categoria'     => $this->idCategoria('Sub-17', 'M'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('tbl_atletas')->where('nome_atleta', 'Atleta Novo')->count());
    }

    public function test_admin_edicao_recusa_8_e_18_anos_no_ano(): void
    {
        $ano      = now()->year;
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        foreach ([($ano - 8) . '-01-01', ($ano - 18) . '-12-31'] as $nascimento) {
            $this->comoAdmin()
                ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
                    'data_nasc_atleta' => $nascimento,
                ]))
                ->assertSessionHasErrorsIn('edicao', 'data_nasc_atleta');
        }

        $this->assertDatabaseHas('tbl_atletas', ['id_atleta' => $idAtleta, 'data_nasc_atleta' => $this->nascidoComIdade(12)]);
    }

    // ---------- telas ----------

    public function test_lista_de_atletas_mostra_so_a_categoria_ativa(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-11', 'M'));
        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, [
            'id_categoria' => $this->idCategoria('Sub-13', 'M'),
        ]));

        $this->comoAdmin()
            ->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('SUB-13 MASCULINO')
            ->assertDontSee('SUB-11 MASCULINO</span>', false);
    }

    public function test_matricula_mostra_a_categoria_sugerida(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'F', 'PENDENTE');

        $this->comoAdmin()
            ->get(route('admin.matriculas.show', $idAtleta))
            ->assertOk()
            ->assertSee('Sub-13 Feminino (sugerida)')
            ->assertDontSee('Sub-11 Feminino'); // abaixo da idade: nem aparece no select

        // Aprovação rápida da lista envia a sugerida
        $this->comoAdmin()
            ->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertSee('name="id_categoria" value="' . $this->idCategoria('Sub-13', 'F') . '"', false);
    }

    // ---------- cadastro público pelo ano de nascimento ----------

    public function test_cadastro_publico_aceita_quem_faz_9_anos_ate_dezembro(): void
    {
        $ano = now()->year;

        // Faz 9 anos só em 31/dez: pela data exata seria recusado; pela regra do ano, é aceito
        $this->post(route('cadastro.store'), ['data_nasc_atleta' => ($ano - 9) . '-12-31'])
            ->assertSessionDoesntHaveErrors('data_nasc_atleta');

        $this->post(route('cadastro.store'), ['data_nasc_atleta' => ($ano - 17) . '-01-01'])
            ->assertSessionDoesntHaveErrors('data_nasc_atleta');
    }

    public function test_cadastro_publico_recusa_fora_de_9_a_17_no_ano(): void
    {
        $ano = now()->year;

        $this->post(route('cadastro.store'), ['data_nasc_atleta' => ($ano - 8) . '-01-01'])
            ->assertSessionHasErrors('data_nasc_atleta');

        $this->post(route('cadastro.store'), ['data_nasc_atleta' => ($ano - 18) . '-12-31'])
            ->assertSessionHasErrors('data_nasc_atleta');
    }
}
