# Agendador (limpezas diárias)

O Laravel tem um agendador próprio (`routes/console.php`). Ele só roda se alguém chamar
`php artisan schedule:run` a cada minuto; quem faz isso no servidor é uma **Tarefa agendada do Plesk**.
Cada execução confere o horário e roda só o que está na hora (as outras terminam em segundos, sem fazer nada).

## O que roda (todo dia, horário de Brasília)

| Hora  | Comando                              | O que apaga                                                                                     |
|-------|--------------------------------------|-------------------------------------------------------------------------------------------------|
| 03:00 | `sanctum:prune-expired --hours=24`   | Tokens de login do app (atleta e responsável) vencidos há mais de 1 dia                          |
| 03:05 | `auth:clear-resets` (3 brokers)      | Links "Defina sua senha" vencidos (`users`, `atletas`, `responsaveis`)                          |
| 03:10 | `model:prune`                        | Recuperações por código com mais de 1 dia e código/token vencidos (`RecuperacaoSenha::prunable`) |

| 03:15 | `queue:prune-failed --hours=720`    | Registros de e-mails que falharam (`failed_jobs`) com mais de 30 dias                            |

Nada disso apaga dado de atleta, responsável, evento ou aviso: só segredos que já não valem.

## Fila de e-mails (a cada minuto)

`queue:work --stop-when-empty --max-time=50` processa a fila `database` e termina quando ela esvazia
(sem worker ligado o tempo todo). Vão pela fila, com até 3 tentativas (1 e 5 minutos entre elas):

- **Fale conosco** (`EnviaEmailFaleConosco`): `PENDENTE` até sair; `FALHOU` no painel depois da última tentativa.
- **Link de assinatura do cadastro pelo site** (`AutorizacaoAssinaturaMail`).

Ficam **fora** da fila: o convite e a assinatura enviados pelo painel (na hora, para o admin ver o link
a copiar se falhar) e o código de recuperação de senha (depois da resposta, com `defer`, para chegar na
hora e não gravar o código na tabela `jobs`).

**Sem a tarefa do Plesk, a fila não anda:** o Fale conosco fica `PENDENTE` e o link de assinatura do
site não sai. Com `QUEUE_CONNECTION=sync` no `.env`, tudo volta a sair na hora (sem tentativas).
Falhas: `php artisan queue:failed` lista; `php artisan queue:retry all` tenta de novo.

## Configurar no Plesk (uma vez)

1. No painel, **Sites e domínios › Tarefas agendadas › Adicionar tarefa**.
2. **Tipo:** "Executar um script PHP".
   - **Caminho do script:** o `artisan` do projeto (⚠️ troque pelo caminho completo; no Terminal SSH,
     `cd ~/coderatech.tipi5.smpsistema.com.br/futebol/src && pwd` mostra a pasta; o script é `<pasta>/artisan`).
   - **Argumentos:** `schedule:run`
   - **Versão do PHP:** 8.4 (o `php` padrão do servidor é o 7.2 e falha).
3. **Executar:** "Cron style" com `* * * * *` (a cada minuto).
4. **Notificar:** "Não notificar" (senão chega um e-mail por minuto).
5. Salvar e usar **Executar agora**: o resultado deve ser "No scheduled commands are ready to run." fora das 03:00–03:10.

Se o Plesk só oferecer "Executar um comando", usar
`/opt/plesk/php/8.4/bin/php <pasta>/artisan schedule:run` (⚠️ troque `<pasta>`).

## Conferir

- `php artisan schedule:list` mostra os horários e a próxima execução.
- Para testar uma limpeza na hora: `php artisan model:prune --pretend` (só conta, não apaga).
