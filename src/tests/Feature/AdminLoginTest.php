<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshBancoDeTestes;

    private const GUARD = 'admin';

    // A UserFactory grava a senha "password"
    private const SENHA = 'password';

    public function test_login_com_credenciais_corretas_entra_no_painel(): void
    {
        $usuario = User::factory()->admin()->create();

        $this->post(route('admin.login.post'), [
            'login'    => $usuario->email_usuario,
            'password' => self::SENHA,
        ])->assertRedirect(route('admin.dash'));

        $this->assertAuthenticatedAs($usuario, self::GUARD);
        $this->assertSame($usuario->id_usuario, Auth::guard(self::GUARD)->id());
    }

    public function test_login_com_senha_errada_volta_com_erro(): void
    {
        $usuario = User::factory()->admin()->create();

        $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'login'    => $usuario->email_usuario,
                'password' => 'senha-errada',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest(self::GUARD);
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get(route('admin.calendario.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest(self::GUARD);
    }

    public function test_usuario_logado_que_abre_o_login_vai_para_o_painel(): void
    {
        $this->actingAs(User::factory()->admin()->create(), self::GUARD)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dash'));
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->admin()->create(), self::GUARD)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest(self::GUARD);

        $this->get(route('admin.dash'))->assertRedirect(route('admin.login'));
    }

    public function test_lembrar_me_grava_o_token_e_o_cookie(): void
    {
        // Sem token, como o usuário real de hoje: o Laravel só gera o token se ainda não existir
        $usuario = User::factory()->admin()->create(['remember_token_usuario' => null]);

        $resposta = $this->post(route('admin.login.post'), [
            'login'    => $usuario->email_usuario,
            'password' => self::SENHA,
            'remember' => '1',
        ]);

        $resposta->assertRedirect(route('admin.dash'))
            ->assertCookie(Auth::guard(self::GUARD)->getRecallerName());

        $token = $usuario->fresh()->remember_token_usuario;
        $this->assertNotNull($token);
        $this->assertSame(60, strlen($token));
    }

    public function test_painel_mostra_o_nome_do_usuario_logado(): void
    {
        $usuario = User::factory()->admin()->create(['nome_usuario' => 'Fulano Teste da Silva']);

        $this->actingAs($usuario, self::GUARD)
            ->get(route('admin.dash'))
            ->assertOk()
            ->assertSee('Fulano Teste da Silva');
    }

    public function test_schema_de_tbl_usuarios_apos_a_fase_2(): void
    {
        $colunas = collect(Schema::getColumns('tbl_usuarios'))->keyBy('name');

        $this->assertFalse($colunas->has('id'), 'A coluna id deveria ter virado id_usuario.');
        $this->assertTrue($colunas['id_usuario']['auto_increment']);
        $this->assertSame(['id_usuario'], collect(Schema::getIndexes('tbl_usuarios'))->firstWhere('primary', true)['columns']);
        $this->assertTrue(collect(Schema::getIndexes('tbl_usuarios'))->contains(
            fn ($indice) => $indice['name'] === 'email_usuario_unique' && $indice['unique']
        ));

        $this->assertSame('varchar(30)', $colunas['cargo_usuario']['type']);
        $this->assertTrue($colunas['cargo_usuario']['nullable']);
        $this->assertSame("enum('ADMIN','EDITOR','LEITURA')", $colunas['nivel_usuario']['type']);
        $this->assertFalse($colunas['nivel_usuario']['nullable']);

        // Usuário criado sem nível nasce com o menor nível
        $id = DB::table('tbl_usuarios')->insertGetId([
            'nome_usuario'  => 'Sem Nivel',
            'email_usuario' => 'sem.nivel@example.com',
            'senha_usuario' => 'x',
        ]);
        $this->assertSame('LEITURA', DB::table('tbl_usuarios')->where('id_usuario', $id)->value('nivel_usuario'));
    }
}
