<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campos de documento e medidas do atleta no painel (10/11/2026): RG, WhatsApp do responsável, peso em kg e altura em
 * metros validados no cadastro e na edição (as mesmas regras do cadastro do site), peso e altura aceitos com vírgula,
 * e a prévia da foto nos modais.
 */
class AtletaDocumentosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_peso_e_altura_com_virgula_sao_gravados(): void
    {
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro([
            'peso_atleta' => '55,5', 'altura_atleta' => '1,80',
        ]))->assertSessionHasNoErrors();

        $atleta = DB::table('tbl_atletas')->where('nome_atleta', 'Atleta Novo')->first();
        $this->assertSame(['55.50', '1.80'], [$atleta->peso_atleta, $atleta->altura_atleta]);
    }

    public function test_cadastro_recusa_documentos_e_medidas_fora_do_formato(): void
    {
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro([
            'rg_atleta'            => 'ABC123',
            'whatsapp_responsavel' => '9999',
            'peso_atleta'          => '500',
            'altura_atleta'        => '180',
        ]))->assertSessionHasErrors([
            'rg_atleta'            => 'Informe um RG válido para o atleta (só números, X, pontos e hífen; de 5 a 15 caracteres).',
            'whatsapp_responsavel' => 'Informe um WhatsApp válido com DDD, como (11) 98765-4321.',
            'peso_atleta'          => 'O peso precisa ficar entre 15 e 150 kg.',
            'altura_atleta'        => 'Informe a altura em metros (ex.: 1,80), entre 0,80 e 2,30.',
        ]);

        $this->assertSame(0, DB::table('tbl_atletas')->count());
    }

    public function test_rg_mais_comprido_que_a_coluna_e_recusado(): void
    {
        // A coluna tem 15 caracteres; antes a validação aceitava 20 e o banco recusaria
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['rg_atleta' => '1234567890123456']))
            ->assertSessionHasErrors(['rg_atleta' => 'O RG pode ter no máximo 15 caracteres.']);
    }

    public function test_edicao_usa_as_mesmas_regras(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        $this->criarResponsavel($id);

        $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, ['altura_atleta' => '1.80.5']))
            ->assertSessionHasErrorsIn('edicao', ['altura_atleta']);

        $this->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, ['peso_atleta' => '48,25', 'altura_atleta' => '1,52']))
            ->assertSessionHasNoErrors();
        $atleta = DB::table('tbl_atletas')->where('id_atleta', $id)->first();
        $this->assertSame(['48.25', '1.52'], [$atleta->peso_atleta, $atleta->altura_atleta]);
    }

    public function test_modais_tem_as_mascaras_e_a_previa_da_foto(): void
    {
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('class="form-control text-center js-peso', false)
            ->assertSee('class="form-control text-center js-altura', false)
            ->assertSee('placeholder="1,80" inputmode="decimal" maxlength="4"', false)
            ->assertSee('js-campo-foto-atleta', false)
            ->assertSee('js-foto-atleta-previa', false)
            ->assertSee('id="edit_foto_atleta"', false);
    }
}
