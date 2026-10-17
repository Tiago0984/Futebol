<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Noticia;
use App\Models\Time;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Padrão da API (17/10/2026, comparação com a API Futebol): todo erro com { success: false, message } em
 * português (e "errors" no 422), limite geral de requisições e o endereço completo das imagens ao lado do nome
 * do arquivo (logo_url, banner_url, foto_url).
 */
class ApiPadraoTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_401_sem_token_em_portugues(): void
    {
        $this->getJson('/api/v1/agenda')->assertUnauthorized()->assertExactJson([
            'success' => false, 'message' => 'Faça login para continuar: o token não foi enviado, é inválido ou venceu.',
        ]);

        // Sem o cabeçalho Accept, também em JSON
        $this->get('/api/v1/agenda')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_422_com_success_message_e_errors_em_portugues(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'O campo e-mail deve ser um e-mail válido.')
            ->assertJsonPath('errors.email.0', 'O campo e-mail deve ser um e-mail válido.')
            ->assertJsonPath('errors.senha.0', 'O campo senha é obrigatório.');
    }

    public function test_rota_inexistente_e_metodo_errado(): void
    {
        $this->getJson('/api/v1/nao-existe')->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Endereço não encontrado.']);

        $this->deleteJson('/api/v1/campeonatos')->assertStatus(405)
            ->assertExactJson(['success' => false, 'message' => 'Método não aceito nesta rota.']);
    }

    public function test_429_no_limite_geral_por_ip(): void
    {
        $this->getJson('/api/v1/status')->assertOk()->assertHeader('X-RateLimit-Limit', 60);

        for ($i = 0; $i < 59; $i++) {
            $this->getJson('/api/v1/status');
        }

        $this->getJson('/api/v1/status')->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson(['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.']);

        RateLimiter::clear('api-ip:127.0.0.1');
    }

    public function test_o_admin_continua_fora_do_padrao_da_api(): void
    {
        // Página do site que não existe: a página de erro do site, não o JSON da API
        $this->get('/pagina-que-nao-existe')->assertNotFound()->assertDontSee('"success"', false);
    }

    public function test_imagens_com_o_endereco_completo(): void
    {
        $this->assertSame(asset('futebol/images/team/aacj.png'), (new Time(['logo_time' => 'aacj.png']))->logo_url);
        $this->assertNull((new Time(['logo_time' => null]))->logo_url);
        $this->assertSame('https://exemplo.com/x.png', (new Time(['logo_time' => 'https://exemplo.com/x.png']))->logo_url);

        DB::table('tbl_banner')->insert([
            'titulo_banner' => 'Bem-vindo', 'foto_banner' => 'banner1.jpg', 'ordem_banner' => 1, 'status_banner' => 'ATIVO',
        ]);
        $this->getJson('/api/v1/banners')->assertOk()
            ->assertJsonPath('data.0.foto_banner', 'banner1.jpg')
            ->assertJsonPath('data.0.foto_url', asset('futebol/images/banner/banner1.jpg'));

        $this->assertSame(asset('futebol/images/news/n.jpg'), (new Noticia(['foto_noticia' => 'n.jpg']))->foto_url);
        $this->assertSame(asset('futebol/images/banner/b.jpg'), (new Banner(['foto_banner' => 'b.jpg']))->foto_url);
    }
}
