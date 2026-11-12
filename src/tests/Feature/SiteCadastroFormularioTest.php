<?php

namespace Tests\Feature;

use App\Models\Atleta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Matricule-se do site no celular e no app (10/11/2026): peso e altura validados e aceitos com vírgula (antes, 180 na
 * altura dava erro 500), foto tratada (quadrado de 512 px, até 10 MB), endereço no tamanho das colunas (número 6,
 * bairro e cidade 50), UF com 2 letras, mensagens em português e o formulário com os ajustes de celular.
 */
class SiteCadastroFormularioTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
    }

    public function test_peso_e_altura_com_virgula_e_foto_tratada(): void
    {
        $this->post(route('cadastro.store'), $this->formulario([
            'peso_atleta' => '55,5', 'altura_atleta' => '1,80', 'estado_endereco' => 'sp',
            'foto_atleta' => UploadedFile::fake()->image('foto-do-celular.png', 1200, 1600),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('cadastro.index'));

        $atleta = Atleta::where('email_atleta', 'novo@atleta.com')->sole();
        $this->assertSame(['55.50', '1.80'], [(string) $atleta->peso_atleta, (string) $atleta->altura_atleta]);
        $this->assertMatchesRegularExpression('#^atletas/foto-atleta-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$#', $atleta->foto_atleta);
        Storage::disk('public')->assertExists($atleta->foto_atleta);
        [$largura, $altura] = getimagesize(Storage::disk('public')->path($atleta->foto_atleta));
        $this->assertSame([512, 512], [$largura, $altura]);
        $this->assertSame('SP', DB::table('tbl_endereco')->where('id_endereco', $atleta->id_endereco)->value('estado_endereco'));
    }

    public function test_valores_fora_do_formato_viram_mensagens_e_nao_erro_500(): void
    {
        $this->post(route('cadastro.store'), $this->formulario([
            'peso_atleta'     => '500',
            'altura_atleta'   => '180',
            'numero_endereco' => '1234567',
            'bairro_endereco' => str_repeat('B', 51),
            'estado_endereco' => 'S1',
            'foto_atleta'     => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors([
            'peso_atleta'     => 'O peso precisa ficar entre 15 e 150 kg.',
            'altura_atleta'   => 'Informe a altura em metros (ex.: 1,80), entre 0,80 e 2,30.',
            'numero_endereco' => 'O número pode ter no máximo 6 caracteres (ex.: 123, 1500A ou S/N).',
            'bairro_endereco' => 'O campo bairro deve ter no máximo 50 caracteres.',
            'estado_endereco' => 'Informe a UF com 2 letras (ex.: SP).',
            'foto_atleta'     => 'A foto precisa ser uma imagem (JPG, PNG ou WebP).',
        ]);

        $this->assertSame(0, DB::table('tbl_atletas')->count());
    }

    public function test_campos_vazios_com_mensagens_em_portugues(): void
    {
        $this->post(route('cadastro.store'), [])->assertSessionHasErrors([
            'nome_atleta'          => 'O campo nome é obrigatório.',
            'escola_atleta'        => 'O campo escola é obrigatório.',
            'grau_parentesco'      => 'O campo grau de parentesco é obrigatório.',
            'cidade_resp_endereco' => 'O campo cidade do responsável é obrigatório.',
        ]);
    }

    // RG com a pontuação que se adapta (10/11/2026): 9 caracteres (o antigo, com X) ou 11 dígitos (o novo, com o CPF)
    public function test_rg_ganha_a_pontuacao_do_antigo_e_do_novo(): void
    {
        $this->assertSame('12.345.678-9', Atleta::formatarRg('123456789'));
        $this->assertSame('12.345.678-X', Atleta::formatarRg('12345678x'));
        $this->assertSame('123.456.789-01', Atleta::formatarRg('12345678901'));
        $this->assertSame('123.456.789-01', Atleta::formatarRg('123.456.789-01'));
        $this->assertSame('MG-12.345', Atleta::formatarRg(' MG-12.345 '));   // outro tamanho: como foi digitado

        $this->post(route('cadastro.store'), $this->formulario(['rg_atleta' => '12345678901', 'rg_responsavel' => '987654321']))
            ->assertSessionHasNoErrors();
        $atleta = Atleta::where('email_atleta', 'novo@atleta.com')->sole();
        $this->assertSame('123.456.789-01', $atleta->rg_atleta);
        $this->assertSame('98.765.432-1', DB::table('tbl_responsavel')->where('email_responsavel', 'resp@site.com')->value('rg_responsavel'));

        // No painel, a mesma regra
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['rg_atleta' => '11122233344']))
            ->assertSessionHasNoErrors();
        $this->assertSame('111.222.333-44', DB::table('tbl_atletas')->where('nome_atleta', 'Atleta Novo')->value('rg_atleta'));
    }

    public function test_formulario_preparado_para_o_celular(): void
    {
        $this->get(route('cadastro.index'))->assertOk()
            ->assertSee('id="peso-atleta"', false)
            ->assertSee('placeholder="Ex: 1,70" inputmode="decimal" maxlength="4"', false)
            ->assertSee('id="foto-atleta-previa"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
            ->assertSee('class="col-xs-6 col-sm-2 mat-oculto" id="turnoTreinoCol"', false)
            ->assertSee('maxlength="6" placeholder="Ex: 123"', false)
            ->assertSee('autocomplete="postal-code"', false)
            ->assertSee('inputmode="tel" autocomplete="tel-national"', false)
            ->assertDontSee('d-none');
    }

    // Cada um com o próprio e-mail (12/11/2026): o mesmo, mesmo com outras maiúsculas e espaços, é recusado sem gravar nada
    public function test_email_do_responsavel_diferente_do_email_do_atleta(): void
    {
        $this->post(route('cadastro.store'), $this->formulario([
            'email_atleta' => 'familia@site.com', 'email_responsavel' => ' Familia@Site.com ',
        ]))->assertSessionHasErrors([
            'email_responsavel' => 'O e-mail do responsável precisa ser diferente do e-mail do atleta.',
        ])->assertSessionDoesntHaveErrors('email_atleta');

        $this->assertSame(0, DB::table('tbl_atletas')->count());
        $this->assertSame(0, DB::table('tbl_responsavel')->count());
        Mail::assertNothingQueued();

        // Com e-mails diferentes, o cadastro segue normal
        $this->post(route('cadastro.store'), $this->formulario(['email_atleta' => 'familia@site.com']))
            ->assertSessionHasNoErrors()->assertRedirect(route('cadastro.index'));
        $this->get(route('cadastro.index'))->assertSee('id="email-resp"', false)
            ->assertSee('Diferente do e-mail do atleta');
    }

    // ---------- ajudantes ----------

    private function formulario(array $extra = []): array
    {
        return array_merge([
            'nome_atleta' => 'Atleta do Site', 'data_nasc_atleta' => $this->nascidoComIdade(12), 'cpf_atleta' => $this->cpfDeTeste(500000001),
            'rg_atleta' => '12.345.678-9', 'sexo_atleta' => 'M', 'escola_atleta' => 'Escola', 'serie_atleta' => '6º ano',
            'periodo_escolar_atleta' => 'MANHA', 'email_atleta' => 'novo@atleta.com',
            'cep_endereco' => '01000-000', 'rua_endereco' => 'Rua do Atleta', 'numero_endereco' => '10', 'bairro_endereco' => 'Centro',
            'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
            'nome_responsavel' => 'Responsável do Site', 'cpf_responsavel' => $this->cpfDeTeste(500000002), 'rg_responsavel' => '98.765.432-1',
            'email_responsavel' => 'resp@site.com', 'whatsapp_responsavel' => '(11) 98888-7777', 'grau_parentesco' => 'Mãe',
            'cep_resp_endereco' => '01000-000', 'rua_resp_endereco' => 'Rua do Responsável', 'numero_resp_endereco' => '20',
            'bairro_resp_endereco' => 'Centro', 'cidade_resp_endereco' => 'São Paulo', 'estado_resp_endereco' => 'SP',
        ], $extra);
    }
}
