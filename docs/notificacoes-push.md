# Notificações push pela Expo

Implementado em 01/11/2026. O app Expo fica em outro repositório; suas rotas e corpos foram preservados.

## Aparelhos e autenticação

As duas rotas usam `auth:sanctum`, `perfil:atleta,responsavel` e o throttle geral da API. Podem remover/desligar aparelhos mesmo após a conta perder acesso por inatividade. O envio contempla somente atletas ATIVO.

- `POST /api/v1/dispositivos`: `{ "token": "ExponentPushToken[xxxx]", "plataforma": "android", "notificacoes_ativas": true }`.
- `DELETE /api/v1/dispositivos`: `{ "token": "ExponentPushToken[xxxx]" }`, antes do logout Sanctum.
- Sucesso nas duas: HTTP 200 `{ "success": true }`. DELETE com token inexistente ou de outra conta devolve a mesma resposta, sem alterar o aparelho alheio.
- Sem autenticação: 401; perfil do painel: 403; campos inválidos: 422 `{success:false,message,errors}` em português; throttle: 429 com `Retry-After`, já exposto no CORS.
- Token obrigatório, texto até 255 caracteres, `ExponentPushToken[...]` ou `ExpoPushToken[...]` com conteúdo e fechamento; plataforma `android`/`ios`; preferência booleana obrigatória no POST. Não enviar IDs do dono no corpo.

O projeto autentica `Atleta` e `Responsavel` diretamente; `User`/`tbl_usuarios` pertence ao painel. Por decisão aprovada pelo dono, `tbl_dispositivo` usa `id_atleta` e `id_responsavel` INT com sinal, FKs com cascata e `CHECK dispositivo_um_dono` garantindo exatamente um preenchido. Não cria contas artificiais em `users`/`tbl_usuarios`.

O upsert atômico pelo token transfere o aparelho para a conta autenticada, sempre limpando o outro campo. Atualiza plataforma, `ativo_dispositivo` e `data_ultimo_uso`. Datas de criação/alteração: `data_criacao_dispositivo` e `data_atualizacao_dispositivo`. O token tem índice único e collation `utf8mb4_bin`, exceção necessária à regra `general_ci`: um identificador opaco distingue maiúsculas/minúsculas. O token fica oculto na serialização do model.

## Disparo e transporte

`Notificacao::gravarParaAtivos()` é o ponto único. Insere as linhas dentro de transação (também quando o aviso livre não veio de uma ação já transacionada), obtém os IDs exatos via `insertGetId`, registra `DB::afterCommit()` e só então agenda `defer()->always()`.

Não infere faixas de AUTO_INCREMENT, não envia antes do commit e não depende de `queue:work`, `jobs`, cron ou SDK. O callback deferido roda na terminação HTTP após o envio da resposta no PHP-FPM do Plesk. Transações externas e savepoints são respeitados; rollback externo descarta os disparos.

Depois da resposta, `EnviaPushDaNotificacao` relê os avisos, o status do atleta, os responsáveis vinculados e os aparelhos ativos. O vínculo basta para o responsável, pois não existe status próprio nesse cadastro. Não envia para outros responsáveis, atletas inativos ou aparelhos desligados. Responsável com mais de um filho distinto vinculado recebe o primeiro nome do atleta prefixado ao título. Vínculos repetidos no legado não duplicam o push nem contam como filhos diferentes. Cada aviso continua gerando sua própria mensagem por aparelho, inclusive quando dois irmãos recebem o mesmo texto.

`ClienteExpo` faz POST JSON para `https://exp.host/--/api/v2/push/send`, em lotes de no máximo 100, conexão de até 3 s e timeout de 10 s por lote. Sem repetição automática para evitar duplicação após uma resposta perdida. Payload:

```json
{
  "to": "ExponentPushToken[xxxx]",
  "title": "Joana · Nova atividade na sua agenda",
  "body": "Texto congelado do aviso",
  "sound": "default",
  "channelId": "default",
  "priority": "high",
  "data": {
    "id_notificacao": 310,
    "id_atleta": 17,
    "id_evento": 42,
    "tipo": "INSCRICAO"
  }
}
```

IDs inteiros; `id_evento` é omitido se não há evento. O push não muda a leitura do aviso. O app abre o compromisso ou a lista de avisos e seleciona antes o filho indicado, quando responsável.

Tickets são associados ao token pela posição no lote. `DeviceNotRegistered` apaga somente o token correspondente. Outros tickets, HTTP inválido, JSON inesperado e exceções geram `Log::warning`, sem expor tokens, credenciais, mensagens pessoais ou o corpo da resposta Expo. Falhas não desfazem a ação ou a notificação; lotes seguintes continuam após falha no transporte.

`EXPO_ACCESS_TOKEN` é opcional em `config/services.php`; se preenchido, envia `Authorization: Bearer`. A chave está comentada e vazia no `.env.example`. Nenhuma alteração no SMTP.

## Limites desta entrega

Envio sem worker é uma tentativa após a resposta, no mesmo processo PHP. Não há persistência de pendências, reenvio automático, coleta posterior de receipts nem garantia de entrega se o processo/servidor parar. Ticket `ok` significa aceite pela Expo, não confirmação no celular. `DeviceNotRegistered` descoberto apenas em um receipt posterior não é coletado nesta entrega. O aviso permanece disponível na API independentemente do push.

Novas inserções devem passar por `gravarParaAtivos`; inserções SQL diretas ou `Notificacao::create` não acionam esse fluxo. A preferência desliga push, não apaga o aviso dentro do app. O trabalho após a resposta ainda ocupa o processo PHP durante as requisições à Expo.

## Testes e referências

`DispositivoTest`, `NotificacaoPushTest` e `ClienteExpoTest`: autenticação, validação, transferência entre perfis, exclusão restrita, CHECK/cascata, tokens sensíveis a caixa, payload, responsáveis, commit/resposta, rollback externo, inatividade, troca de dono antes do envio, tickets inválidos, lotes e indisponibilidade. Sempre `Http::fake`/bloqueio de chamadas não simuladas; nenhum push real nos testes.

Resultado em 01/11/2026: suíte completa com **964 testes passando / 6.971 verificações**, 432 s. Após a proteção contra vínculos repetidos, checagem final dos três testes acima e `DocumentacaoApiTest`: **35 testes / 201 verificações**, todos passando. Migration aplicada somente no local (batch 17), após `backup/db_futebol_antes_push_20261101.sql`. HTTP local sem autenticação confirmou 401 e CORS expondo `Retry-After`; rotas e compilação Blade conferidas.

- [Expo: envio, lotes e tickets](https://docs.expo.dev/push-notifications/sending-notifications/).
- [Laravel 13: deferred functions](https://laravel.com/framework/docs/13.x/helpers#deferred-functions).
- [MariaDB: restrições CHECK em CREATE/ALTER TABLE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).
- Publicação e teste manual: [publicacao-notificacoes-push.md](publicacao-notificacoes-push.md).
