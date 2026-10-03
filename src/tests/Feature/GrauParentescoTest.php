<?php

namespace Tests\Feature;

use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Grau de parentesco: uma lista só (Responsavel::GRAUS_PARENTESCO) no site e no admin, e valores
 * antigos em outro formato ("PAI", "MAE") chegam ao modal de edição já casando com uma opção.
 */
class GrauParentescoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public static function grausGravados(): array
    {
        return [
            'PAI (maiúsculo)'          => ['PAI', 'Pai'],
            'MAE (maiúsculo, sem til)' => ['MAE', 'Mãe'],
            'mãe (minúsculo)'          => ['mãe', 'Mãe'],
            'Mãe (já certo)'           => ['Mãe', 'Mãe'],
            'responsável legal'        => ['responsável legal', 'Responsável Legal'],
        ];
    }

    #[DataProvider('grausGravados')]
    public function test_normaliza_grau_gravado_para_a_opcao_da_lista(string $gravado, string $esperado): void
    {
        $this->assertSame($esperado, Responsavel::normalizarGrau($gravado));
    }

    public function test_grau_vazio_ou_desconhecido(): void
    {
        $this->assertNull(Responsavel::normalizarGrau(null));
        $this->assertNull(Responsavel::normalizarGrau('  '));
        $this->assertSame('Padrinho', Responsavel::normalizarGrau('Padrinho')); // fora da lista: volta como está
    }

    #[DataProvider('grausGravados')]
    public function test_lista_expoe_o_grau_como_uma_opcao_do_modal(string $gravado, string $esperado): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->vincularResponsavel($idAtleta, $gravado);

        $this->comoAdmin()
            ->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('data-grau-responsavel="' . e($esperado) . '"', false)
            ->assertSee('<option value="' . e($esperado) . '">', false);
    }

    public function test_admin_recusa_grau_fora_da_lista(): void
    {
        $this->comoAdmin()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['grau_parentesco_responsavel' => 'Padrinho']))
            ->assertSessionHasErrors('grau_parentesco_responsavel');

        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['grau_parentesco_responsavel' => 'PAI']))
            ->assertSessionHasErrorsIn('edicao', 'grau_parentesco_responsavel');
    }

    public function test_site_aceita_so_graus_da_lista(): void
    {
        $this->post(route('cadastro.store'), ['grau_parentesco' => 'Tutor Legal'])
            ->assertSessionHasErrors('grau_parentesco');

        $this->post(route('cadastro.store'), ['grau_parentesco' => 'Responsável Legal'])
            ->assertSessionDoesntHaveErrors('grau_parentesco');
    }

    public function test_migration_normaliza_so_os_formatos_antigos(): void
    {
        $graus = ['PAI', 'MAE', 'Mãe', 'Pai', 'Outro'];
        $ids   = [];

        foreach ($graus as $grau) {
            $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
            $this->vincularResponsavel($idAtleta, $grau);
            $ids[$grau] = $idAtleta;
        }

        $migration = require database_path('migrations/2026_10_03_000005_normaliza_grau_parentesco.php');
        $migration->up();

        $gravados = DB::table('tbl_atleta_responsavel')->pluck('grau_parentesco_responsavel', 'id_atleta');
        $this->assertSame('Pai', $gravados[$ids['PAI']]);
        $this->assertSame('Mãe', $gravados[$ids['MAE']]);
        $this->assertSame('Mãe', $gravados[$ids['Mãe']]);
        $this->assertSame('Pai', $gravados[$ids['Pai']]);
        $this->assertSame('Outro', $gravados[$ids['Outro']]);
    }

    // Responsável com o grau gravado exatamente como veio (sem passar pela validação)
    private function vincularResponsavel(int $idAtleta, string $grau): void
    {
        $idEndereco = DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->value('id_endereco');

        $idResponsavel = DB::table('tbl_responsavel')->insertGetId([
            'id_endereco'          => $idEndereco,
            'nome_responsavel'     => 'Responsável de Teste',
            'cpf_responsavel'      => '111.111.111-11',
            'rg_responsavel'       => '1111111',
            'whatsapp_responsavel' => '(11) 99999-9999',
        ]);

        DB::table('tbl_atleta_responsavel')->insert([
            'id_atleta'                   => $idAtleta,
            'id_responsavel'              => $idResponsavel,
            'grau_parentesco_responsavel' => $grau,
        ]);
    }
}
