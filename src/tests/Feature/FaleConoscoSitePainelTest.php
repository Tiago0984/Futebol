<?php

namespace Tests\Feature;

use App\Mail\FaleConoscoMail;
use App\Models\FaleConosco;
use App\Services\RecebeFaleConosco;
use Illuminate\Support\Facades\Mail;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class FaleConoscoSitePainelTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const CORPO = [
        'nome' => 'Visitante de Teste', 'email' => 'visitante@teste.com', 'telefone' => '(11) 98765-4321',
        'assunto' => 'horarios', 'mensagem' => 'Gostaria de saber os horários.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['contato.email' => 'secretaria@aacj.test', 'contato.telefone' => null, 'contato.whatsapp' => null]);
    }

    private function mensagem(): FaleConosco
    {
        return app(RecebeFaleConosco::class)->receber(array_replace(self::CORPO, [
            'assunto' => 'Mensagem para a secretaria', 'mensagem' => '<script>alert(1)</script>',
        ]));
    }

    public function test_formulario_esta_ligado_e_contatos_nao_configurados_ficam_ocultos(): void
    {
        $this->get('/contato')->assertOk()
            ->assertSee('action="' . route('contato.enviar') . '"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('maxlength="2000"', false)
            ->assertDontSee('event.preventDefault()', false)
            ->assertDontSee('tel:', false)->assertDontSee('wa.me', false)
            ->assertDontSee('3333-3333')->assertDontSee('99999-9999')->assertDontSee('contato@escolinha.com.br')
            ->assertSee('mailto:secretaria@aacj.test', false);
    }

    public function test_contatos_configurados_geram_links(): void
    {
        config(['contato.telefone' => '+55 (11) 2345-6789', 'contato.whatsapp' => '+55 (11) 98765-4321']);
        $this->get('/contato')->assertOk()->assertSee('tel:+551123456789', false)
            ->assertSee('https://wa.me/5511987654321', false);
    }

    public function test_visitante_envia_sem_login_e_recebe_confirmacao(): void
    {
        $this->post('/contato', self::CORPO + ['id_atleta' => 999, 'perfil' => 'ATLETA'])
            ->assertRedirect(route('contato', ['#enviar-mensagem']))->assertSessionHas('sucesso');
        $mensagem = FaleConosco::sole();
        $this->assertSame('SITE', $mensagem->origem_fale_conosco);
        $this->assertSame('VISITANTE', $mensagem->perfil_fale_conosco);
        $this->assertNull($mensagem->id_atleta);
        $this->assertNull($mensagem->id_responsavel);
        $this->assertSame(self::CORPO['nome'], $mensagem->nome_fale_conosco);
        $this->assertSame(self::CORPO['telefone'], $mensagem->telefone_fale_conosco);
        $this->assertSame('Horários e Unidades', $mensagem->assunto_fale_conosco);
        Mail::assertSent(FaleConoscoMail::class, fn ($mail) => $mail->hasTo('secretaria@aacj.test')
            && $mail->hasReplyTo('visitante@teste.com'));
        $this->get('/contato')->assertSee('Mensagem recebida com sucesso. Obrigado pelo contato!');
    }

    public function test_validacao_publica_em_portugues_preserva_campos_sem_gravar(): void
    {
        $this->from('/contato')->post('/contato', array_replace(self::CORPO, [
            'email' => 'invalido', 'telefone' => '123', 'mensagem' => str_repeat('a', 2001), 'assunto' => 'nao-existe',
        ]))->assertRedirect('/contato')->assertSessionHasErrors([
            'email' => 'Informe um e-mail válido.',
            'telefone' => 'Informe um telefone válido com DDD.',
            'mensagem' => 'O campo mensagem não pode ter mais de 2000 caracteres.',
            'assunto' => 'Escolha um assunto da lista.',
        ])->assertSessionHasInput('nome', self::CORPO['nome']);
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
        Mail::assertNothingSent();
    }

    public function test_telefone_em_array_retorna_validacao_sem_erro_interno(): void
    {
        $this->post('/contato', array_replace(self::CORPO, ['telefone' => ['invalido']]))
            ->assertSessionHasErrors(['telefone' => 'O campo telefone deve ser um texto.']);
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
        $this->get('/contato')->assertOk();
    }

    public function test_site_limita_tres_por_ip_com_retry_after_e_preserva_campos(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/contato', self::CORPO)->assertRedirect();
        }
        $this->post('/contato', self::CORPO)->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertSee('Muitos envios. Aguarde um minuto e tente novamente.')
            ->assertSee(self::CORPO['mensagem'])
            ->assertSessionHasInput('nome', self::CORPO['nome']);
        $this->assertDatabaseCount('tbl_fale_conosco', 3);
        Mail::assertSentCount(3);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->post('/contato', self::CORPO)->assertRedirect();
        $this->assertDatabaseCount('tbl_fale_conosco', 4);
    }

    public function test_formulario_exige_csrf_fora_do_ambiente_de_testes(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->post('/contato', self::CORPO)->assertStatus(419);
            $this->assertDatabaseCount('tbl_fale_conosco', 0);
            $this->withSession(['_token' => 'token-de-teste'])
                ->post('/contato', self::CORPO + ['_token' => 'token-de-teste'])->assertRedirect();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_painel_exige_admin_e_nao_expoe_mensagens_publicamente(): void
    {
        $mensagem = $this->mensagem();
        $this->get(route('admin.fale-conosco.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.fale-conosco.show', $mensagem->getKey()))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.fale-conosco.leitura', $mensagem->getKey()), ['lida' => true])
            ->assertRedirect(route('admin.login'));
        $this->assertNull($mensagem->fresh()->data_leitura_fale_conosco);
    }

    public function test_painel_filtra_pagina_e_ordena_por_data_e_id(): void
    {
        $this->travelTo('2026-10-31 10:00:00');
        $maisAntiga = $this->mensagem();
        $this->travel(1)->minute();
        for ($i = 0; $i < 20; $i++) {
            $this->mensagem();
        }
        $ultima = $this->mensagem();
        $ultima->data_leitura_fale_conosco = now();
        $ultima->save();
        $this->comoAdmin()->get(route('admin.fale-conosco.index'))->assertOk()
            ->assertViewHas('mensagens', fn ($p) => $p->total() === 22 && $p->count() === 20 && $p->first()->is($ultima));
        $this->get(route('admin.fale-conosco.index', ['page' => 2]))->assertOk()
            ->assertViewHas('mensagens', fn ($p) => $p->count() === 2 && $p->last()->is($maisAntiga));
        $this->get(route('admin.fale-conosco.index', ['leitura' => 'lidas']))->assertOk()
            ->assertViewHas('mensagens', fn ($p) => $p->total() === 1 && $p->first()->is($ultima));
        $this->get(route('admin.fale-conosco.index', ['leitura' => 'nao_lidas']))->assertOk()
            ->assertViewHas('mensagens', fn ($p) => $p->total() === 21);
    }

    public function test_painel_escapa_conteudo_e_permite_marcar_e_desmarcar_leitura(): void
    {
        $mensagem = $this->mensagem();
        $url = route('admin.fale-conosco.leitura', $mensagem->getKey());
        $this->comoAdmin()->get(route('admin.fale-conosco.show', $mensagem->getKey()))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNull($mensagem->fresh()->data_leitura_fale_conosco);
        $this->patch($url, ['lida' => true])->assertRedirect();
        $primeiraLeitura = $mensagem->fresh()->data_leitura_fale_conosco;
        $this->assertNotNull($primeiraLeitura);
        $this->travel(1)->minute();
        $this->patch($url, ['lida' => true])->assertRedirect();
        $this->assertTrue($primeiraLeitura->equalTo($mensagem->fresh()->data_leitura_fale_conosco));
        $this->patch($url, ['lida' => false])->assertRedirect();
        $this->assertNull($mensagem->fresh()->data_leitura_fale_conosco);
        $this->patch($url, ['lida' => 'invalido'])->assertSessionHasErrors('lida');
        $this->get(route('admin.fale-conosco.show', 999999))->assertNotFound();
        $this->patch(route('admin.fale-conosco.leitura', 999999), ['lida' => true])->assertNotFound();
    }
}
