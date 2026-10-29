<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\AtletaHistorico;
use App\Models\Responsavel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Foto do atleta pelo app (POST e DELETE /api/v1/atleta/foto; 29/10/2026): a enviada é tratada (quadrado de até
 * 512×512, JPG) e espera a aprovação da secretaria fora de public/ (foto_url continua a atual, foto_pendente true);
 * remover vale na hora. Erros 422 em errors.foto, que o app mostra embaixo da foto.
 */
class AtletaFotoPeloAppTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('imagens');
        Storage::fake('public');

        $this->id = $this->criarAtleta((now()->year - 12) . '-05-14', 'F', 'ATIVO');
        $this->colocarNaCategoria($this->id, $this->idCategoria('Sub-13', 'F'));
        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['foto_atleta' => Atleta::FOTO_PADRAO]);
        Sanctum::actingAs(Atleta::find($this->id));
    }

    private function enviar(UploadedFile $arquivo)
    {
        return $this->post('/api/v1/atleta/foto', ['foto' => $arquivo], ['Accept' => 'application/json']);
    }

    public function test_jpg_e_png_ficam_pendentes_em_quadrado_jpg_e_a_resposta_e_o_mesmo_formato_do_get(): void
    {
        $resposta = $this->enviar(UploadedFile::fake()->image('joana.jpg', 1200, 900))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Foto enviada. Ela aparece no app depois que a secretaria aprovar.')
            ->assertJsonPath('data.foto_pendente', true)
            ->assertJsonPath('data.foto_url', null) // a atual (padrão) continua até aprovar
            ->assertJsonMissingPath('data.foto_pendente_atleta'); // o arquivo fora de public/ não aparece

        $this->assertSame(array_keys($this->getJson('/api/v1/atleta')->json('data')), array_keys($resposta->json('data')));

        $atleta = Atleta::find($this->id);
        $this->assertSame(Atleta::FOTO_PADRAO, $atleta->foto_atleta);
        $this->assertMatchesRegularExpression('/^foto-atleta-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$/', $atleta->foto_pendente_atleta);
        $this->assertNotNull($atleta->data_foto_pendente_atleta);

        $caminho = Atleta::PASTA_FOTO_PENDENTE . '/' . $atleta->foto_pendente_atleta;
        Storage::disk('local')->assertExists($caminho);
        $this->assertSame([512, 512, IMAGETYPE_JPEG], array_slice(getimagesize(Storage::disk('local')->path($caminho)), 0, 3));
        $this->assertSame([], Storage::disk('imagens')->allFiles()); // nada em public/ antes da aprovação

        // PNG pequeno: vira JPG, quadrado, sem ampliar; troca a pendente anterior (o arquivo antigo sai)
        $this->enviar(UploadedFile::fake()->image('joana.png', 300, 400))->assertOk()->assertJsonPath('data.foto_pendente', true);

        $nova = Atleta::find($this->id)->foto_pendente_atleta;
        $this->assertNotSame($atleta->foto_pendente_atleta, $nova);
        Storage::disk('local')->assertMissing($caminho);
        $this->assertSame([300, 300, IMAGETYPE_JPEG], array_slice(getimagesize(Storage::disk('local')->path(Atleta::PASTA_FOTO_PENDENTE . '/' . $nova)), 0, 3));
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_arquivo_que_nao_e_imagem_ou_acima_de_10_mb_da_422_em_errors_foto(): void
    {
        $this->enviar(UploadedFile::fake()->create('notas.txt', 5, 'text/plain'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A foto deve ser uma imagem JPG ou PNG.')
            ->assertJsonPath('errors.foto.0', 'A foto deve ser uma imagem JPG ou PNG.');

        // Diz ser JPG, mas o conteúdo não abre
        $this->enviar(UploadedFile::fake()->create('falsa.jpg', 5, 'image/jpeg'))
            ->assertStatus(422)->assertJsonPath('errors.foto.0', 'A foto deve ser uma imagem JPG ou PNG.');

        $this->enviar(UploadedFile::fake()->create('grande.jpg', 11 * 1024, 'image/jpeg'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'A foto deve ter no máximo 10 MB.')
            ->assertJsonPath('errors.foto.0', 'A foto deve ter no máximo 10 MB.');

        $this->post('/api/v1/atleta/foto', [], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.foto.0', 'Envie a foto no campo "foto".');

        $this->assertFalse(Atleta::find($this->id)->temFotoPendente());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_remover_volta_para_a_padrao_apaga_a_foto_enviada_e_registra_no_historico(): void
    {
        // Foto antiga do admin (atleta_<uniqid>.jpg) e uma pendente
        Storage::disk('imagens')->put('our-teams/atleta_0123456789abc.jpg', 'x');
        Storage::disk('local')->put(Atleta::PASTA_FOTO_PENDENTE . '/foto-atleta-20261029-101010-abcdef.jpg', 'x');
        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update([
            'foto_atleta' => 'atleta_0123456789abc.jpg', 'foto_pendente_atleta' => 'foto-atleta-20261029-101010-abcdef.jpg',
        ]);
        Sanctum::actingAs(Atleta::find($this->id)); // o atleta do token relido, com a foto gravada acima

        $this->assertStringEndsWith('/futebol/images/our-teams/atleta_0123456789abc.jpg', $this->getJson('/api/v1/atleta')->json('data.foto_url'));

        $this->deleteJson('/api/v1/atleta/foto')
            ->assertOk()
            ->assertJsonPath('message', 'Foto removida.')
            ->assertJsonPath('data.foto_url', null)
            ->assertJsonPath('data.foto_pendente', false);

        $this->assertSame(Atleta::FOTO_PADRAO, Atleta::find($this->id)->foto_atleta);
        Storage::disk('imagens')->assertMissing('our-teams/atleta_0123456789abc.jpg');
        $this->assertSame([], Storage::disk('local')->allFiles());

        $historico = AtletaHistorico::where('id_atleta', $this->id)->sole();
        $this->assertSame('Foto: removida', $historico->resumo);
        $this->assertSame('Pelo app', $historico->autor);

        // Já sem foto: remover de novo responde igual e não registra nada
        $this->deleteJson('/api/v1/atleta/foto')->assertOk();
        $this->assertSame(1, AtletaHistorico::where('id_atleta', $this->id)->count());
    }

    public function test_remover_nunca_apaga_a_padrao_as_fotos_do_git_nem_a_de_outro_atleta(): void
    {
        Storage::disk('imagens')->put('our-teams/' . Atleta::FOTO_PADRAO, 'x');
        Storage::disk('imagens')->put('our-teams/jogador1.png', 'x');
        Storage::disk('public')->put('atletas/' . str_repeat('a', 40) . '.jpg', 'x');

        $this->deleteJson('/api/v1/atleta/foto')->assertOk();
        Storage::disk('imagens')->assertExists('our-teams/' . Atleta::FOTO_PADRAO);

        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['foto_atleta' => 'jogador1.png']);
        $this->deleteJson('/api/v1/atleta/foto')->assertOk();
        Storage::disk('imagens')->assertExists('our-teams/jogador1.png');

        // Foto do site que outro atleta também usa: fica
        $foto = 'atletas/' . str_repeat('a', 40) . '.jpg';
        $outro = $this->criarAtleta((now()->year - 12) . '-01-01', 'F', 'ATIVO');
        DB::table('tbl_atletas')->whereIn('id_atleta', [$this->id, $outro])->update(['foto_atleta' => $foto]);
        $this->deleteJson('/api/v1/atleta/foto')->assertOk();
        Storage::disk('public')->assertExists($foto);
    }

    public function test_responsavel_e_atleta_inativo_nao_trocam_a_foto_e_sem_token_da_401(): void
    {
        $idResponsavel = $this->criarResponsavel($this->id);
        Sanctum::actingAs(Responsavel::find($idResponsavel));
        $this->enviar(UploadedFile::fake()->image('filho.jpg'))->assertStatus(403);
        $this->deleteJson('/api/v1/atleta/foto')->assertStatus(403);
        // A área do responsável não tem rota de foto
        $this->post("/api/v1/responsavel/atletas/{$this->id}/foto", [], ['Accept' => 'application/json'])->assertStatus(404);

        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['status_atleta' => 'INATIVO']);
        Sanctum::actingAs(Atleta::find($this->id));
        $this->enviar(UploadedFile::fake()->image('joana.jpg'))->assertStatus(403);

        $this->assertFalse(Atleta::find($this->id)->temFotoPendente());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->app['auth']->forgetGuards();
        $this->post('/api/v1/atleta/foto', [], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_limite_de_5_por_minuto_da_429(): void
    {
        foreach (range(1, 5) as $_) {
            $this->deleteJson('/api/v1/atleta/foto')->assertOk();
        }

        $this->enviar(UploadedFile::fake()->image('joana.jpg'))
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um pouco e tente de novo.');
    }

    public function test_exclusao_definitiva_apaga_a_foto_pendente(): void
    {
        $this->enviar(UploadedFile::fake()->image('joana.jpg'))->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles());

        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['status_atleta' => 'REJEITADO']);
        $this->assertTrue(Atleta::find($this->id)->excluirComDependencias());

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
