<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpezas diárias, de madrugada (horário de Brasília, APP_TIMEZONE). Rodam pelo cron do Plesk,
// que chama "php artisan schedule:run" a cada minuto (docs/agendador.md).
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:00');   // tokens do app vencidos há mais de 1 dia
Schedule::command('auth:clear-resets users')->dailyAt('03:05');            // links de senha vencidos, por broker
Schedule::command('auth:clear-resets atletas')->dailyAt('03:05');
Schedule::command('auth:clear-resets responsaveis')->dailyAt('03:05');
Schedule::command('model:prune')->dailyAt('03:10');                        // recuperações por código (RecuperacaoSenha::prunable)
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:15');     // jobs que falharam há mais de 30 dias

// Fila de e-mails (Fale conosco e assinatura do cadastro pelo site): a cada minuto, processa o que
// houver e termina quando a fila esvazia (sem worker ligado o tempo todo; o Plesk não mantém um).
// max-time abaixo de 60 s e withoutOverlapping: duas execuções nunca disputam a mesma fila.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5);
