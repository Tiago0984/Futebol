<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\AtletaHistorico;
use App\Models\Notificacao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Aprovação da foto enviada pelo app (F2, 29/10/2026): na tela de Atletas, a secretaria vê a atual e a nova, aprova
 * (a nova vai para public/our-teams e aparece no app) ou recusa (a nova é apagada). Nos dois casos, um AVISO ao atleta.
 */
class AtletaFotoAprovacaoTest extends TestCase
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
        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['nome_atleta' => 'Joana Exemplo', 'foto_atleta' => 'atleta_0123456789abc.jpg']);
        Storage::disk('imagens')->put('our-teams/atleta_0123456789abc.jpg', 'antiga');

        // A foto nova chega pelo app, como na F1
        Sanctum::actingAs(Atleta::find($this->id));
        $this->post('/api/v1/atleta/foto', ['foto' => UploadedFile::fake()->image('joana.jpg', 800, 800)], ['Accept' => 'application/json'])->assertOk();
    }

    private function pendente(): ?string
    {
        return Atleta::find($this->id)->foto_pendente_atleta;
    }

    public function test_lista_mostra_o_selo_o_aviso_e_a_foto_nova_so_para_o_admin(): void
    {
        $this->comoAdmin()->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('Foto nova para aprovar')
            ->assertSee('1 foto(s) nova(s)', false)
            ->assertSee('Foto para aprovar')
            ->assertSee(route('admin.atletas.fotoPendente', $this->id));

        $this->get(route('admin.atletas.fotoPendente', $this->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Visitante não vê (vai para o login) e não há endereço público para ela
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.atletas.fotoPendente', $this->id))->assertRedirect();
        $this->assertSame([], Storage::disk('imagens')->files('our-teams/' . $this->pendente()));
    }

    public function test_aprovar_troca_a_foto_apaga_a_antiga_registra_e_avisa_o_atleta(): void
    {
        $nova = $this->pendente();

        $this->comoAdmin()->from(route('admin.atletas.index'))
            ->patch(route('admin.atletas.fotoPendente.aprovar', $this->id))
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHas('sucesso', 'Foto de Joana Exemplo aprovada. O atleta foi avisado no app.');

        $atleta = Atleta::find($this->id);
        $this->assertSame($nova, $atleta->foto_atleta);
        $this->assertFalse($atleta->temFotoPendente());
        Storage::disk('imagens')->assertExists('our-teams/' . $nova);
        Storage::disk('imagens')->assertMissing('our-teams/atleta_0123456789abc.jpg');
        $this->assertSame([], Storage::disk('local')->allFiles());

        $historico = AtletaHistorico::where('id_atleta', $this->id)->sole();
        $this->assertSame('Foto: trocada', $historico->resumo);
        $this->assertNotNull($historico->id_usuario);

        $aviso = Notificacao::where('id_atleta', $this->id)->sole();
        $this->assertSame('AVISO', $aviso->tipo_notificacao);
        $this->assertSame('Foto aprovada', $aviso->titulo_notificacao);
        $this->assertNull($aviso->id_evento_calendario);

        // O app já recebe a foto nova
        Sanctum::actingAs(Atleta::find($this->id));
        $this->getJson('/api/v1/atleta')
            ->assertJsonPath('data.foto_pendente', false)
            ->assertJsonPath('data.foto_url', asset('futebol/images/our-teams/' . $nova));
        $this->getJson('/api/v1/notificacoes')
            ->assertJsonPath('data.notificacoes.0.tipo', 'AVISO')
            ->assertJsonPath('data.notificacoes.0.titulo', 'Foto aprovada');
    }

    public function test_recusar_apaga_a_nova_mantem_a_atual_e_avisa_com_o_motivo(): void
    {
        $this->comoAdmin()->from(route('admin.atletas.index'))
            ->delete(route('admin.atletas.fotoPendente.recusar', $this->id), ['motivo' => '  A foto precisa mostrar o rosto.  '])
            ->assertRedirect(route('admin.atletas.index'))
            ->assertSessionHas('sucesso', 'Foto nova de Joana Exemplo recusada; a atual continua. O atleta foi avisado no app.');

        $atleta = Atleta::find($this->id);
        $this->assertSame('atleta_0123456789abc.jpg', $atleta->foto_atleta);
        $this->assertFalse($atleta->temFotoPendente());
        Storage::disk('imagens')->assertExists('our-teams/atleta_0123456789abc.jpg');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, AtletaHistorico::where('id_atleta', $this->id)->count());

        $aviso = Notificacao::where('id_atleta', $this->id)->sole();
        $this->assertSame('AVISO', $aviso->tipo_notificacao);
        $this->assertSame('Foto não aprovada', $aviso->titulo_notificacao);
        $this->assertSame('A secretaria não aprovou a sua foto nova: A foto precisa mostrar o rosto. Você pode enviar outra pelo app.', $aviso->mensagem_notificacao);

        // Sem motivo, a frase fecha sem ele
        Sanctum::actingAs(Atleta::find($this->id));
        $this->post('/api/v1/atleta/foto', ['foto' => UploadedFile::fake()->image('outra.png', 400, 400)], ['Accept' => 'application/json'])->assertOk();
        $this->comoAdmin()->delete(route('admin.atletas.fotoPendente.recusar', $this->id))->assertSessionHas('sucesso');
        $this->assertSame(
            'A secretaria não aprovou a sua foto nova. Você pode enviar outra pelo app.',
            Notificacao::where('id_atleta', $this->id)->orderByDesc('id_notificacao')->value('mensagem_notificacao')
        );
    }

    public function test_motivo_longo_ou_sem_foto_pendente_da_erro_sem_mudar_nada(): void
    {
        $this->comoAdmin()->from(route('admin.atletas.index'))
            ->delete(route('admin.atletas.fotoPendente.recusar', $this->id), ['motivo' => str_repeat('a', Notificacao::MOTIVO_FOTO_MAX + 1)])
            ->assertSessionHas('erro', 'O motivo da recusa pode ter até 200 caracteres.');
        $this->assertNotNull($this->pendente());

        $this->comoAdmin()->patch(route('admin.atletas.fotoPendente.aprovar', $this->id))->assertSessionHas('sucesso');

        // Já aprovada: aprovar ou recusar de novo só avisa
        $this->comoAdmin()->patch(route('admin.atletas.fotoPendente.aprovar', $this->id))
            ->assertSessionHas('erro', 'Joana Exemplo não tem foto nova esperando aprovação.');
        $this->comoAdmin()->delete(route('admin.atletas.fotoPendente.recusar', $this->id))
            ->assertSessionHas('erro', 'Joana Exemplo não tem foto nova esperando aprovação.');
        $this->get(route('admin.atletas.fotoPendente', $this->id))->assertNotFound();
        $this->assertSame(1, Notificacao::where('id_atleta', $this->id)->count());
    }

    public function test_atleta_inativado_antes_da_revisao_nao_recebe_o_aviso(): void
    {
        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['status_atleta' => 'INATIVO']);

        $this->comoAdmin()->patch(route('admin.atletas.fotoPendente.aprovar', $this->id))
            ->assertSessionHas('sucesso', 'Foto de Joana Exemplo aprovada. O atleta não está ativo e não recebeu o aviso.');
        $this->assertSame(0, Notificacao::where('id_atleta', $this->id)->count());
    }
}
