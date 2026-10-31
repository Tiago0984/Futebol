<?php

namespace Tests\Feature;

use App\Mail\FaleConoscoMail;
use App\Models\Atleta;
use App\Models\FaleConosco;
use App\Models\Responsavel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class FaleConoscoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const CORPO = ['assunto' => 'Treino', 'mensagem' => 'Qual é o horário?'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['contato.email' => 'secretaria@aacj.test', 'contato.telefone' => null, 'contato.whatsapp' => null]);
    }

    private function atleta(): Atleta
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => "atleta{$id}@teste.com"]);

        return Atleta::findOrFail($id);
    }

    private function token(Atleta|Responsavel $conta): string
    {
        return $conta->createToken('teste', ['*'], now()->addDay())->plainTextToken;
    }

    private function enviar(string $token, array $dados = self::CORPO)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/fale-conosco', $dados);
    }

    public function test_atleta_recebe_201_com_protocolo_data_e_identidade_do_token(): void
    {
        $this->travelTo('2026-10-31 10:00:00');
        $atleta = $this->atleta();
        $resposta = $this->enviar($this->token($atleta), self::CORPO + [
            'perfil' => 'responsavel', 'id_atleta' => 999, 'nome' => 'Outra pessoa', 'email' => 'outra@teste.com',
            'data_leitura_fale_conosco' => now(), 'status_email_fale_conosco' => 'FALHOU',
        ])->assertCreated();
        $mensagem = FaleConosco::sole();
        $resposta->assertExactJson([
            'success' => true,
            'message' => 'Mensagem recebida com sucesso.',
            'data' => ['id_fale_conosco' => $mensagem->getKey(), 'data_envio' => '2026-10-31T10:00:00-03:00'],
        ]);
        $this->assertSame($atleta->getKey(), $mensagem->id_atleta);
        $this->assertNull($mensagem->id_responsavel);
        $this->assertSame('APP', $mensagem->origem_fale_conosco);
        $this->assertSame('ATLETA', $mensagem->perfil_fale_conosco);
        $this->assertSame($atleta->nome_atleta, $mensagem->nome_fale_conosco);
        $this->assertSame($atleta->email_atleta, $mensagem->email_fale_conosco);
        $this->assertNull($mensagem->data_leitura_fale_conosco);
        $this->assertSame('ENVIADO', $mensagem->status_email_fale_conosco);
        Mail::assertSent(FaleConoscoMail::class, fn ($mail) => $mail->hasTo('secretaria@aacj.test')
            && $mail->hasReplyTo($atleta->email_atleta));
        DB::table('tbl_atletas')->where('id_atleta', $atleta->getKey())->update(['nome_atleta' => 'Nome alterado']);
        $this->assertSame('Atleta de Teste', $mensagem->fresh()->nome_fale_conosco);
    }

    public function test_responsavel_envia_com_a_propria_identidade(): void
    {
        $atleta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $this->enviar($this->token($responsavel))->assertCreated();
        $mensagem = FaleConosco::sole();
        $this->assertSame('RESPONSAVEL', $mensagem->perfil_fale_conosco);
        $this->assertSame($responsavel->getKey(), $mensagem->id_responsavel);
        $this->assertNull($mensagem->id_atleta);
        $this->assertSame($responsavel->email_responsavel, $mensagem->email_fale_conosco);
        Mail::assertSent(FaleConoscoMail::class, fn ($mail) => $mail->hasReplyTo($responsavel->email_responsavel));
    }

    public function test_sem_token_token_invalido_e_token_vencido_nao_gravam(): void
    {
        $this->post('/api/v1/fale-conosco', self::CORPO)->assertUnauthorized()->assertJsonPath('success', false);
        $this->enviar('invalido')->assertUnauthorized();
        $atleta = $this->atleta();
        $vencido = $atleta->createToken('vencido', ['*'], now()->subMinute())->plainTextToken;
        $this->enviar($vencido)->assertUnauthorized();
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
        Mail::assertNothingSent();
    }

    public function test_outro_model_autenticado_nao_pode_enviar(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson('/api/v1/fale-conosco', self::CORPO)->assertForbidden();
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
    }

    public function test_atleta_inativado_e_responsavel_sem_filho_ativo_sao_barrados(): void
    {
        $atleta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $tokenAtleta = $this->token($atleta);
        $tokenResponsavel = $this->token($responsavel);
        DB::table('tbl_atletas')->where('id_atleta', $atleta->getKey())->update(['status_atleta' => 'INATIVO']);
        $this->enviar($tokenAtleta)->assertForbidden();
        $this->enviar($tokenResponsavel)->assertForbidden();
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertNothingSent();
    }

    public static function camposInvalidos(): iterable
    {
        yield 'obrigatórios' => [[], 'assunto', 'O campo assunto é obrigatório.'];
        yield 'só espaços' => [['assunto' => '  ', 'mensagem' => '  '], 'assunto', 'O campo assunto é obrigatório.'];
        yield 'assunto longo' => [['assunto' => str_repeat('á', 101), 'mensagem' => 'Teste'], 'assunto', 'O campo assunto não pode ter mais de 100 caracteres.'];
        yield 'mensagem longa' => [['assunto' => 'Teste', 'mensagem' => str_repeat('á', 2001)], 'mensagem', 'O campo mensagem não pode ter mais de 2000 caracteres.'];
        yield 'assunto array' => [['assunto' => ['Teste'], 'mensagem' => 'Teste'], 'assunto', 'O campo assunto deve ser um texto.'];
        yield 'mensagem numérica' => [['assunto' => 'Teste', 'mensagem' => 123], 'mensagem', 'O campo mensagem deve ser um texto.'];
    }

    #[DataProvider('camposInvalidos')]
    public function test_validacao_em_portugues_nao_grava_nem_envia(array $dados, string $campo, string $erro): void
    {
        $this->enviar($this->token($this->atleta()), $dados)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath("errors.{$campo}.0", $erro);
        $this->assertDatabaseCount('tbl_fale_conosco', 0);
        Mail::assertNothingSent();
    }

    public function test_limites_exatos_sao_aceitos_e_espacos_das_pontas_removidos(): void
    {
        $this->enviar($this->token($this->atleta()), [
            'assunto' => ' ' . str_repeat('á', 100) . ' ', 'mensagem' => ' ' . str_repeat('ç', 2000) . ' ',
        ])->assertCreated();
        $this->assertSame(str_repeat('á', 100), FaleConosco::sole()->assunto_fale_conosco);
        $this->assertSame(str_repeat('ç', 2000), FaleConosco::sole()->mensagem_fale_conosco);
    }

    public function test_limite_compartilhado_entre_tokens_mas_independente_por_conta_e_perfil(): void
    {
        $this->withHeader('Origin', 'https://app.example.test');
        $atleta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $token1 = $this->token($atleta);
        $token2 = $this->token($atleta);
        $this->enviar($token1)->assertCreated();
        $this->enviar($token2)->assertCreated();
        $this->enviar($token1)->assertCreated();
        $this->enviar($token2)->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson(['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.']);
        $this->enviar($this->token($responsavel))->assertCreated();
        $this->enviar($this->token($this->atleta()))->assertCreated();
        $this->assertDatabaseCount('tbl_fale_conosco', 5);
        Mail::assertSentCount(5);
        $this->travel(61)->seconds();
        $this->enviar($token1)->assertCreated();
    }

    public function test_falha_no_email_preserva_mensagem_e_retorna_201(): void
    {
        Mail::shouldReceive('to')->once()->with('secretaria@aacj.test')->andThrow(new RuntimeException('SMTP indisponível'));
        Log::spy();
        $this->enviar($this->token($this->atleta()))->assertCreated()->assertJsonPath('success', true);
        $this->assertSame('FALHOU', FaleConosco::sole()->status_email_fale_conosco);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_destinatario_invalido_nao_perde_mensagem(): void
    {
        config(['contato.email' => '']);
        $this->enviar($this->token($this->atleta()))->assertCreated();
        $this->assertSame('FALHOU', FaleConosco::sole()->status_email_fale_conosco);
        Mail::assertNothingSent();
    }

    public function test_email_simulado_preserva_remetente_configurado_reply_to_e_html_escapado(): void
    {
        config(['mail.from.address' => 'smtp@aacj.test', 'mail.from.name' => 'AACJ Futebol']);
        $this->enviar($this->token($this->atleta()), [
            'assunto' => 'Teste', 'mensagem' => '<script>alert(1)</script>',
        ])->assertCreated();
        $this->assertSame('ENVIADO', FaleConosco::sole()->status_email_fale_conosco);
        Mail::assertSent(FaleConoscoMail::class, function ($mail) {
            $this->assertTrue($mail->hasTo('secretaria@aacj.test'));
            $this->assertTrue($mail->hasReplyTo(FaleConosco::sole()->email_fale_conosco));
            $this->assertNull($mail->envelope()->from); // Herda o remetente SMTP; não usa o visitante como From.
            $html = $mail->render();
            $this->assertStringContainsString('&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);

            return true;
        });
    }
}
