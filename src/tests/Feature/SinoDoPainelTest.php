<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\FaleConosco;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Sino do topo e números do menu lateral (09/11/2026; InicioDoPainel::contagens e avisosDoSino): o que chega de
 * fora (Fale conosco pelo site e pelo app, matrículas, fotos do app, fila de e-mails) aparece em qualquer tela do
 * painel, com as mesmas contagens do dashboard. A mensagem sai do sino quando alguém marca como lida.
 */
class SinoDoPainelTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['contato.email' => 'secretaria@aacj.test']);
    }

    public function test_sem_avisos_o_sino_fica_sem_numero_e_leva_ao_dashboard(): void
    {
        $resposta = $this->comoAdmin()->get(route('admin.times.index'))->assertOk();

        $sino = $this->sino($resposta);
        $this->assertStringContainsString('Nenhum aviso novo', $sino);
        $this->assertStringNotContainsString('navbar-badge', $sino);
        $this->assertStringContainsString('href="' . route('admin.dashboard') . '"', $sino);
        $this->assertStringNotContainsString('menu-contador', $this->barra($resposta));
    }

    public function test_fale_conosco_pelo_site_e_pelo_app_chega_no_sino_e_no_menu(): void
    {
        // Pelo site (visitante)
        $this->post(route('contato.enviar'), [
            'nome' => 'Visitante do Site', 'email' => 'visitante@teste.com', 'telefone' => '(11) 98765-4321',
            'assunto' => 'horarios', 'mensagem' => 'Quais são os horários?',
        ])->assertSessionHas('sucesso');

        // Pelo app (atleta ativo)
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['nome_atleta' => 'Atleta do App', 'email_atleta' => 'app@teste.com']);
        $token = Atleta::findOrFail($idAtleta)->createToken('teste', ['*'], now()->addDay())->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/fale-conosco', ['assunto' => 'Uniforme', 'mensagem' => 'Quando chega?'])
            ->assertCreated();
        $this->app['auth']->forgetGuards();

        $resposta = $this->comoAdmin()->get(route('admin.times.index'))->assertOk();
        $sino = $this->sino($resposta);
        $this->assertStringContainsString('<span class="navbar-badge badge text-bg-warning">2</span>', $sino);
        $this->assertStringContainsString('2 mensagem(ns) do Fale conosco não lida(s)', $sino);
        $this->assertStringContainsString('href="' . e(route('admin.fale-conosco.index', ['leitura' => 'nao_lidas'])) . '"', $sino);
        // As duas mensagens, a mais recente primeiro, cada uma abrindo a própria mensagem
        [$doApp, $doSite] = [FaleConosco::where('origem_fale_conosco', 'APP')->sole(), FaleConosco::where('origem_fale_conosco', 'SITE')->sole()];
        $this->assertStringContainsString('href="' . route('admin.fale-conosco.show', $doApp->getKey()) . '"', $sino);
        $this->assertStringContainsString('href="' . route('admin.fale-conosco.show', $doSite->getKey()) . '"', $sino);
        $this->assertStringContainsString('Atleta do App', $sino);
        $this->assertStringContainsString('Visitante do Site', $sino);
        $this->assertStringContainsString('title="Mensagens não lidas">2</span>', $this->barra($resposta));

        // Marcada como lida, sai do sino e do número do menu
        $this->patch(route('admin.fale-conosco.leitura', $doSite->getKey()), ['lida' => true])->assertRedirect();
        $resposta = $this->get(route('admin.times.index'))->assertOk();
        $sino = $this->sino($resposta);
        $this->assertStringContainsString('<span class="navbar-badge badge text-bg-warning">1</span>', $sino);
        $this->assertStringNotContainsString('Visitante do Site', $sino);
        $this->assertStringContainsString('Atleta do App', $sino);
        $this->assertStringContainsString('title="Mensagens não lidas">1</span>', $this->barra($resposta));
    }

    public function test_matriculas_fotos_e_fila_somam_no_sino(): void
    {
        $this->criarAtleta($this->nascidoComIdade(12), 'M', 'PENDENTE');
        $comFoto = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $comFoto)->update(['foto_pendente_atleta' => 'fotos-pendentes/x.jpg']);
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subHours(2)->timestamp, 'created_at' => now()->subHours(2)->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'sino-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x',
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'sino-2', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x',
        ]);

        $resposta = $this->comoAdmin()->get(route('admin.times.index'))->assertOk();
        $sino = $this->sino($resposta);
        // 1 matrícula + 1 foto + fila parada (1) + falhas (1, mesmo com 2 tarefas)
        $this->assertStringContainsString('<span class="navbar-badge badge text-bg-warning">4</span>', $sino);
        $this->assertStringContainsString('Fila de e-mails parada há 2 horas', $sino);
        $this->assertStringContainsString('2 tarefa(s) da fila falharam', $sino);
        $this->assertStringContainsString('1 matrícula(s) aguardando aprovação', $sino);
        $this->assertStringContainsString('1 foto(s) do app para aprovar', $sino);
        $this->assertStringContainsString('href="' . e(route('admin.atletas.index', ['status' => 'FOTO'])) . '"', $sino);

        $barra = $this->barra($resposta);
        $this->assertStringContainsString('title="Fotos enviadas pelo app para aprovar">1</span>', $barra);
        $this->assertStringContainsString('title="Matrículas aguardando aprovação">1</span>', $barra);
    }

    public function test_consultas_do_sino_nao_crescem_com_mais_mensagens(): void
    {
        $contar = function () {
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->comoAdmin()->get(route('admin.configuracoes.index'))->assertOk();

            return $n;
        };

        $this->mensagem('Primeira');
        $uma = $contar();
        foreach (range(1, 8) as $i) {
            $this->mensagem("Outra {$i}");
        }

        $this->assertSame($uma, $contar());
    }

    // ---------- ajudantes ----------

    private function mensagem(string $nome): void
    {
        DB::table('tbl_fale_conosco')->insert([
            'origem_fale_conosco' => 'SITE', 'perfil_fale_conosco' => 'VISITANTE',
            'nome_fale_conosco' => $nome, 'email_fale_conosco' => 'visitante@exemplo.com',
            'assunto_fale_conosco' => 'Dúvida', 'mensagem_fale_conosco' => 'Mensagem de teste.',
            'data_fale_conosco' => now(),
        ]);
    }

    private function sino(TestResponse $resposta): string
    {
        preg_match('#<nav class="app-header.*?</nav>#s', $resposta->getContent(), $m);

        return $m[0] ?? '';
    }

    private function barra(TestResponse $resposta): string
    {
        preg_match('#<aside class="app-sidebar.*?</aside>#s', $resposta->getContent(), $m);

        return $m[0] ?? '';
    }
}
