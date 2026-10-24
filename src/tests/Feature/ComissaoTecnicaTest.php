<?php

namespace Tests\Feature;

use App\Models\ComissaoTecnica;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Cadastro da comissão técnica no admin (Etapa C1, 25/10/2026): criar, editar, ativar e desativar (sem exclusão),
 * cargo da lista fixa, categoria principal sempre entre as atendidas, foto com autorização e contato institucional.
 */
class ComissaoTecnicaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imagens');
    }

    public function test_cria_com_todos_os_campos_e_a_principal_entra_nas_categorias(): void
    {
        $sub13 = $this->idCategoria('Sub-13', 'M');
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $sub17 = $this->idCategoria('Sub-17', 'M');

        $this->comoAdmin()->post(route('admin.comissao.store'), $this->dados([
            'id_categoria' => $sub15, 'categorias' => [$sub13, $sub17],
            'foto_comissao_tecnica' => UploadedFile::fake()->image('carlos.png'), 'foto_autorizada_comissao_tecnica' => '1', // PNG: o GD do container não tem JPEG
        ]))->assertRedirect(route('admin.comissao.index'))->assertSessionHasNoErrors();

        $p = ComissaoTecnica::with('categorias')->firstOrFail();
        $this->assertSame(['Carlos Mendes', 'TREINADOR_TECNICO', 'Treinador técnico', 'ATIVO', '2019-03-01', true],
            [$p->nome_comissao_tecnica, $p->cargo_comissao_tecnica, $p->cargo_label, $p->status_comissao_tecnica,
             $p->data_inicio_comissao_tecnica->toDateString(), $p->foto_autorizada_comissao_tecnica]);
        $this->assertEqualsCanonicalizing([$sub13, $sub15, $sub17], $p->categorias->pluck('id_categoria')->all());

        // Foto com nome gerado (não o original), na pasta da comissão
        $this->assertNotSame('carlos.png', $p->foto_comissao_tecnica);
        Storage::disk('imagens')->assertExists('comissao/' . $p->foto_comissao_tecnica);
        $this->assertStringEndsWith('/futebol/images/comissao/' . $p->foto_comissao_tecnica, $p->fotoParaOApp());

        $this->comoAdmin()->get(route('admin.comissao.index'))->assertOk()
            ->assertSee('Carlos Mendes')->assertSee('Treinador técnico')->assertSee('(11) 4000-0000')->assertSee('Sub-15 Masculino');
    }

    public function test_validacoes_e_nada_e_gravado(): void
    {
        $this->comoAdmin()->post(route('admin.comissao.store'), $this->dados([
            'nome_comissao_tecnica' => '', 'cargo_comissao_tecnica' => 'TECNICO', 'data_inicio_comissao_tecnica' => now()->addDay()->toDateString(),
            'telefone_institucional_comissao_tecnica' => '11 9999-abc', 'email_institucional_comissao_tecnica' => 'carlos@',
            'foto_comissao_tecnica' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors([
            'nome_comissao_tecnica'                   => 'Informe o nome.',
            'cargo_comissao_tecnica'                  => 'Escolha um cargo da lista.',
            'data_inicio_comissao_tecnica'            => 'A data de início na AACJ não pode ser no futuro.',
            'telefone_institucional_comissao_tecnica' => 'O telefone só pode ter números, espaços, parênteses, + e -.',
            'email_institucional_comissao_tecnica'    => 'Informe um e-mail válido.',
            'foto_comissao_tecnica'                   => 'A foto precisa ser uma imagem (JPG, PNG ou WEBP).',
        ]);

        $this->assertSame(0, ComissaoTecnica::count());
        $this->assertSame([], Storage::disk('imagens')->allFiles());
    }

    public function test_editar_troca_a_foto_tira_a_autorizacao_e_as_categorias(): void
    {
        $sub13 = $this->idCategoria('Sub-13', 'M');
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $this->comoAdmin()->post(route('admin.comissao.store'), $this->dados([
            'id_categoria' => $sub13, 'categorias' => [$sub15],
            'foto_comissao_tecnica' => UploadedFile::fake()->image('a.png'), 'foto_autorizada_comissao_tecnica' => '1',
        ]));
        $p = ComissaoTecnica::firstOrFail();
        $fotoAntiga = $p->foto_comissao_tecnica;

        // Caixa da autorização desmarcada (não vem no envio), outra foto, só o Sub-15 e sem principal
        $this->comoAdmin()->put(route('admin.comissao.update', $p->id_comissao_tecnica), $this->dados([
            'cargo_comissao_tecnica' => 'PREPARADOR_FISICO', 'id_categoria' => '', 'categorias' => [$sub15],
            'foto_comissao_tecnica' => UploadedFile::fake()->image('b.png'),
        ]))->assertSessionHasNoErrors();

        $p->refresh()->load('categorias');
        $this->assertSame(['PREPARADOR_FISICO', false, null], [$p->cargo_comissao_tecnica, $p->foto_autorizada_comissao_tecnica, $p->id_categoria]);
        $this->assertSame([$sub15], $p->categorias->pluck('id_categoria')->all());
        Storage::disk('imagens')->assertMissing('comissao/' . $fotoAntiga);
        Storage::disk('imagens')->assertExists('comissao/' . $p->foto_comissao_tecnica);
        $this->assertNull($p->fotoParaOApp());     // sem autorização: não vai para o app
        $this->assertNotNull($p->urlFoto());       // no painel continua

        // Remover a foto
        $this->comoAdmin()->put(route('admin.comissao.update', $p->id_comissao_tecnica), $this->dados(['remover_foto' => '1']));
        Storage::disk('imagens')->assertMissing('comissao/' . $p->foto_comissao_tecnica);
        $this->assertNull($p->fresh()->foto_comissao_tecnica);

        $this->comoAdmin()->get(route('admin.comissao.edit', $p->id_comissao_tecnica))->assertOk()
            ->assertSee('Autorizou mostrar a foto no app')->assertSee('Não informe o celular nem o e-mail pessoal');
    }

    public function test_desativar_e_reativar_sem_exclusao(): void
    {
        $this->comoAdmin()->post(route('admin.comissao.store'), $this->dados());
        $p = ComissaoTecnica::firstOrFail();

        $this->comoAdmin()->patch(route('admin.comissao.toggleStatus', $p->id_comissao_tecnica))
            ->assertSessionHas('sucesso', 'Carlos Mendes foi desativado(a): não aparece mais para novas escolhas nem no app.');
        $this->assertSame('INATIVO', $p->fresh()->status_comissao_tecnica);
        $this->assertSame(0, ComissaoTecnica::ativos()->count());

        $this->comoAdmin()->patch(route('admin.comissao.toggleStatus', $p->id_comissao_tecnica));
        $this->assertTrue($p->fresh()->estaAtivo());

        $this->comoAdmin()->delete("/admin/comissao-tecnica/{$p->id_comissao_tecnica}")->assertStatus(405);
        $this->assertSame(1, ComissaoTecnica::count());
    }

    public function test_categoria_atendida_pela_comissao_nao_e_excluida(): void
    {
        $sub9 = $this->idCategoria('Sub-9', 'F');
        $this->comoAdmin()->post(route('admin.comissao.store'), $this->dados(['categorias' => [$sub9]]));

        $this->comoAdmin()->delete(route('admin.categorias.destroy', $sub9))
            ->assertSessionHas('erro', fn ($m) => str_contains($m, '1 profissional(is) da comissão técnica'));
        $this->assertDatabaseHas('tbl_categoria', ['id_categoria' => $sub9]);
    }

    public function test_visitante_nao_entra(): void
    {
        $this->get(route('admin.comissao.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.comissao.store'), $this->dados())->assertRedirect(route('admin.login'));
        $this->assertSame(0, ComissaoTecnica::count());
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome_comissao_tecnica'                   => 'Carlos Mendes',
            'cargo_comissao_tecnica'                  => 'TREINADOR_TECNICO',
            'data_inicio_comissao_tecnica'            => '2019-03-01',
            'licenca_comissao_tecnica'                => 'CBF Academy',
            'especialidade_comissao_tecnica'          => 'Treinamento técnico',
            'telefone_institucional_comissao_tecnica' => '(11) 4000-0000',
            'email_institucional_comissao_tecnica'    => 'carlos@aacj.org.br',
        ], $extra);
    }
}
