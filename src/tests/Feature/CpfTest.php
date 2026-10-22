<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * CPF (21/10/2026; débito "CPF único"): válido pelos dígitos verificadores no site e no admin, gravado sempre como
 * 000.000.000-00 e único no atleta (também na edição) e no responsável (índices do banco).
 */
class CpfTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_validacao_e_formato(): void
    {
        $this->assertTrue(Cpf::valido('529.982.247-25'));
        $this->assertTrue(Cpf::valido('52998224725'));
        $this->assertFalse(Cpf::valido('529.982.247-26'));   // dígito verificador errado
        $this->assertFalse(Cpf::valido('111.111.111-11'));   // sequência
        $this->assertFalse(Cpf::valido('000.000.000-00'));
        $this->assertFalse(Cpf::valido('5299822472'));       // 10 dígitos
        $this->assertFalse(Cpf::valido(null));

        $this->assertSame('529.982.247-25', Cpf::formatar(' 52998224725 '));
        $this->assertSame('529.982.247-25', Cpf::formatar('529 982 247 25'));
        $this->assertSame('123', Cpf::formatar('123'));
        $this->assertNull(Cpf::formatar(null));
    }

    public function test_indices_unicos_no_banco(): void
    {
        $unicos = collect(DB::select("SELECT TABLE_NAME t, INDEX_NAME i, COLUMN_NAME c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0 AND COLUMN_NAME IN ('cpf_atleta', 'cpf_responsavel')"))
            ->mapWithKeys(fn ($l) => [$l->i => "{$l->t}.{$l->c}"])->all();

        $this->assertSame(['cpf_atleta_unique' => 'tbl_atletas.cpf_atleta', 'cpf_responsavel_unique' => 'tbl_responsavel.cpf_responsavel'], collect($unicos)->sortKeys()->all());
    }

    public function test_admin_valida_formata_e_nao_repete(): void
    {
        $outro = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $cpfDoOutro = DB::table('tbl_atletas')->where('id_atleta', $outro)->value('cpf_atleta');

        // Inválido: mensagem, nada gravado
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['cpf_atleta' => '529.982.247-26', 'cpf_responsavel' => '111.111.111-11']))
            ->assertSessionHasErrors(['cpf_atleta' => 'Informe um CPF válido para o atleta.', 'cpf_responsavel' => 'Informe um CPF válido para o responsável.']);

        // O de outro atleta, digitado sem os pontos: recusado (compara já formatado)
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['cpf_atleta' => Cpf::digitos($cpfDoOutro)]))
            ->assertSessionHasErrors(['cpf_atleta' => 'Este CPF já é de outro atleta cadastrado.']);

        // Sem os pontos: gravado com a máscara (atleta e responsável)
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['cpf_atleta' => '52998224725', 'cpf_responsavel' => '11144477735']))
            ->assertSessionHasNoErrors();
        $novo = Atleta::where('cpf_atleta', '529.982.247-25')->sole();
        $this->assertSame('111.444.777-35', $novo->responsaveis()->value('cpf_responsavel'));

        // Edição: o CPF de outro atleta é recusado (antes, a edição não conferia)
        $this->colocarNaCategoria($outro, $this->idCategoria('Sub-13', 'M'));
        $this->comoAdmin()->put(route('admin.atletas.update', $outro), $this->dadosEdicao($outro, ['cpf_atleta' => '529.982.247-25']))
            ->assertSessionHasErrorsIn('edicao', ['cpf_atleta' => 'Este CPF já é de outro atleta cadastrado.']);
        $this->comoAdmin()->put(route('admin.atletas.update', $outro), $this->dadosEdicao($outro))->assertSessionHasNoErrors('edicao');
    }

    public function test_model_grava_com_a_mascara(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $atleta = Atleta::find($id);
        $atleta->update(['cpf_atleta' => '52998224725']);

        $this->assertSame('529.982.247-25', DB::table('tbl_atletas')->where('id_atleta', $id)->value('cpf_atleta'));
    }
}
