<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Meu perfil (10/11/2026): o "Perfil" do menu do usuário abre a página, que muda nome, e-mail, cargo e foto (tratada,
 * com nome gerado) e troca a senha com a atual. O nível de acesso não muda por aqui.
 */
class PerfilTest extends TestCase
{
    use RefreshBancoDeTestes;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('imagens');
        $this->usuario = User::factory()->leitura()->create([
            'nome_usuario' => 'Ana Secretaria', 'email_usuario' => 'ana@aacj.test', 'senha_usuario' => 'senha-antiga-123',
        ]);
    }

    public function test_menu_do_usuario_leva_ao_perfil(): void
    {
        $this->actingAs($this->usuario, 'admin')->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="' . route('admin.perfil') . '"', false)
            ->assertSee('Somente leitura');

        $this->get(route('admin.perfil'))->assertOk()
            ->assertSee('value="Ana Secretaria"', false)
            ->assertSee('Definido por um administrador.')
            // Sem foto: a silhueta neutra (10/11/2026), não mais o avatar5.png
            ->assertSee(asset(User::AVATAR_PADRAO), false)
            ->assertDontSee('avatar5.png');
        $this->assertFileExists(public_path(User::AVATAR_PADRAO));
    }

    // O cargo e o nível são do administrador (dono do projeto, 10/11/2026): enviados pelo perfil, são ignorados
    public function test_atualiza_os_dados_sem_mudar_o_nivel_nem_o_cargo(): void
    {
        $this->actingAs($this->usuario, 'admin')->get(route('admin.perfil'))->assertOk()
            ->assertDontSee('name="cargo_usuario"', false)
            ->assertDontSee('Cargo');

        $this->put(route('admin.perfil.update'), [
            'nome_usuario' => 'Ana Souza', 'email_usuario' => 'ana.souza@aacj.test', 'cargo_usuario' => 'COORDENADOR',
            'nivel_usuario' => 'ADMIN',
        ])->assertRedirect(route('admin.perfil'))->assertSessionHasNoErrors();

        $this->usuario->refresh();
        $this->assertSame(['Ana Souza', 'ana.souza@aacj.test', null, 'LEITURA'],
            [$this->usuario->nome_usuario, $this->usuario->email_usuario, $this->usuario->cargo_usuario, $this->usuario->nivel_usuario]);
    }

    public function test_email_de_outro_usuario_e_recusado(): void
    {
        User::factory()->create(['email_usuario' => 'outro@aacj.test']);

        $this->actingAs($this->usuario, 'admin')->put(route('admin.perfil.update'), [
            'nome_usuario' => 'Ana', 'email_usuario' => 'outro@aacj.test',
        ])->assertSessionHasErrors(['email_usuario' => 'Este e-mail já é de outro usuário do painel.']);
    }

    public function test_foto_tratada_com_nome_gerado_e_a_antiga_apagada(): void
    {
        $this->actingAs($this->usuario, 'admin')->put(route('admin.perfil.update'), [
            'nome_usuario' => 'Ana', 'email_usuario' => 'ana@aacj.test', 'foto_usuario' => UploadedFile::fake()->image('eu.png', 900, 600),
        ])->assertSessionHasNoErrors();

        $primeira = $this->usuario->fresh()->foto_usuario;
        $this->assertMatchesRegularExpression('/^foto-usuario-\d{8}-\d{6}-[a-z0-9]{6}\.jpg$/', $primeira);
        Storage::disk('imagens')->assertExists("usuarios/{$primeira}");
        [$largura, $altura] = getimagesize(Storage::disk('imagens')->path("usuarios/{$primeira}"));
        $this->assertSame([512, 512], [$largura, $altura]);
        $this->get(route('admin.perfil'))->assertSee(asset('futebol/images/usuarios/' . $primeira), false);

        // Remover: volta ao avatar padrão e a foto gerada sai do disco
        $this->put(route('admin.perfil.update'), ['nome_usuario' => 'Ana', 'email_usuario' => 'ana@aacj.test', 'remover_foto' => '1']);
        $this->assertNull($this->usuario->fresh()->foto_usuario);
        Storage::disk('imagens')->assertMissing("usuarios/{$primeira}");
    }

    public function test_troca_a_senha_so_com_a_atual_certa(): void
    {
        $this->actingAs($this->usuario, 'admin')->put(route('admin.perfil.senha'), [
            'senha_atual' => 'errada', 'nova_senha' => 'senha-nova-456', 'nova_senha_confirmation' => 'senha-nova-456',
        ])->assertSessionHasErrorsIn('senha', ['senha_atual' => 'A senha atual não confere.']);

        $this->put(route('admin.perfil.senha'), [
            'senha_atual' => 'senha-antiga-123', 'nova_senha' => 'curta', 'nova_senha_confirmation' => 'outra',
        ])->assertSessionHasErrorsIn('senha', ['nova_senha']);

        $this->put(route('admin.perfil.senha'), [
            'senha_atual' => 'senha-antiga-123', 'nova_senha' => 'senha-nova-456', 'nova_senha_confirmation' => 'senha-nova-456',
        ])->assertRedirect(route('admin.perfil'))->assertSessionHas('sucesso', 'Sua senha foi trocada.');

        $this->assertTrue(Hash::check('senha-nova-456', $this->usuario->fresh()->senha_usuario));
    }
}
