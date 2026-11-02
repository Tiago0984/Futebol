<?php

namespace Tests\Feature;

use App\Jobs\EnviaEmailFaleConosco;
use App\Mail\CodigoRecuperacaoSenhaMail;
use App\Models\FaleConosco;
use App\Services\RecebeFaleConosco;
use App\Services\RecuperaSenhaPorCodigo;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

// Fale conosco pela fila (até 3 tentativas) e código de recuperação depois da resposta (defer).
// Nos outros testes a fila é "sync": o job roda na hora, e os testes de envio continuam valendo.
class EmailsForaDaTelaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['contato.email' => 'secretaria@aacj.test']);
    }

    public function test_fale_conosco_vai_para_a_fila_e_fica_pendente(): void
    {
        Queue::fake();

        $mensagem = app(RecebeFaleConosco::class)->receber([
            'nome' => 'Visitante', 'email' => 'visitante@example.test', 'assunto' => 'Matrícula', 'mensagem' => 'Olá',
        ]);

        $this->assertSame('PENDENTE', $mensagem->status_email_fale_conosco);
        Queue::assertPushed(EnviaEmailFaleConosco::class, fn ($job) => $job->idFaleConosco === $mensagem->getKey());
        Mail::assertNothingSent();
    }

    public function test_fale_conosco_tenta_tres_vezes_e_marca_falhou_no_fim(): void
    {
        Log::spy();
        $mensagem = FaleConosco::create([
            'origem_fale_conosco' => 'SITE', 'perfil_fale_conosco' => 'VISITANTE', 'nome_fale_conosco' => 'Visitante',
            'email_fale_conosco' => 'visitante@example.test', 'assunto_fale_conosco' => 'Matrícula',
            'mensagem_fale_conosco' => 'Olá', 'data_fale_conosco' => now(),
        ]);
        $job = new EnviaEmailFaleConosco($mensagem->getKey());

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);

        $job->failed(new RuntimeException('SMTP fora do ar'));

        $this->assertSame('FALHOU', $mensagem->refresh()->status_email_fale_conosco);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_codigo_de_recuperacao_sai_depois_da_resposta_sem_passar_pela_fila(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)
            ->update(['email_atleta' => 'pessoa@example.test', 'password' => Hash::make('senha-antiga-123')]);

        app(RecuperaSenhaPorCodigo::class)->solicitar('pessoa@example.test', 'atleta');

        Mail::assertNothingSent();                               // a resposta não espera o SMTP
        app(DeferredCallbackCollection::class)->invoke();        // o que o Laravel roda depois da resposta
        Mail::assertSent(CodigoRecuperacaoSenhaMail::class, fn ($m) => $m->hasTo('pessoa@example.test'));
        $this->assertSame(0, DB::table('jobs')->count());        // o código em texto puro não vai para o banco
    }
}
