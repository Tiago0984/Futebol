<?php

namespace App\Jobs;

use App\Mail\FaleConoscoMail;
use App\Models\FaleConosco;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

// Fila "database", processada pelo agendador a cada minuto (routes/console.php, docs/agendador.md).
// Recebe só o id: o job relê a mensagem, e nada da conversa fica copiado na tabela jobs.
class EnviaEmailFaleConosco implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];   // 1 min e 5 min entre as tentativas (SMTP fora do ar por pouco tempo)

    public function __construct(public int $idFaleConosco)
    {
    }

    public function handle(): void
    {
        $mensagem = FaleConosco::find($this->idFaleConosco);
        if (! $mensagem) {
            return;
        }

        Mail::to(config('contato.email'))->send(new FaleConoscoMail($mensagem));
        $mensagem->status_email_fale_conosco = 'ENVIADO';
        $mensagem->save();
    }

    // Depois da última tentativa (ou na hora, com a fila "sync"): a mensagem continua no painel.
    public function failed(?Throwable $erro): void
    {
        FaleConosco::whereKey($this->idFaleConosco)->update(['status_email_fale_conosco' => 'FALHOU']);
        Log::warning('Falha ao enviar e-mail do fale conosco.', [
            'id_fale_conosco' => $this->idFaleConosco,
            'tipo_erro' => $erro ? get_class($erro) : null,
        ]);
    }
}
