# Recuperação de senha por código

O fluxo segue o guia do professor: solicitar o código; na tela com os quatro dígitos e as duas senhas, validar o código e usar o `reset_token` na mesma ação. Somente depois do sucesso da redefinição o app volta ao login. A interface do app fica no outro repositório.

As três rotas são públicas, sob `/api/v1`, com JSON e `Accept: application/json`. `perfil` aceita `atleta` ou `responsavel`; omitido ou nulo, continua sendo `atleta`, compatível com o exemplo do guia. O mesmo e-mail pode existir nos dois perfis, sem misturar suas senhas, códigos ou sessões.

## 1. Solicitar código

`POST /api/v1/auth/esqueci-senha`

```json
{ "email": "pessoa@example.test", "perfil": "responsavel" }
```

Resposta `200`, também para conta inexistente, inativa, pedido suprimido pelo intervalo mínimo ou falha no e-mail:

```json
{ "success": true, "message": "Se o e-mail estiver cadastrado, enviaremos um código de verificação." }
```

Só envia para atleta ativo ou responsável com filho ativo. Código de quatro dígitos, gerado com aleatoriedade criptográfica e guardado com hash, válido por 15 minutos. No máximo um envio por minuto por conta. Um novo envio substitui a recuperação anterior daquela conta, inclusive um token já emitido pela validação. Não envia link pela API. O e-mail sai depois da resposta (`defer`, como o push), sem fila: a resposta não espera o SMTP e tem o mesmo tempo com ou sem conta, e o código em texto puro não é gravado na tabela `jobs`. Sem nova tentativa: se o SMTP falhar, fica só o log e a pessoa pede outro código depois de 60 segundos.

Mantidos os limites de 5 requisições/minuto por IP e 3 requisições/10 minutos por e-mail e perfil. Pedidos inválidos também contam nos limitadores de requisições.

## 2. Validar código

`POST /api/v1/auth/validar-codigo`

```json
{ "email": "pessoa@example.test", "perfil": "responsavel", "codigo": "0042" }
```

O código é uma string com exatamente quatro dígitos, preservando zeros iniciais.

Resposta `200`:

```json
{ "success": true, "data": { "reset_token": "token-aleatorio-retornado-pela-api" } }
```

O token é opaco, com 32 bytes aleatórios representados em hexadecimal, guardado somente como SHA-256, de uso único e válido por 10 minutos a partir da validação. Ele não é um token de login: não armazenar no AsyncStorage; usar na chamada seguinte da mesma ação da tela.

Código incorreto, malformado, expirado, consumido ou bloqueado, assim como conta sem recuperação ou sem acesso, retorna `422`:

```json
{
  "success": false,
  "message": "Código inválido ou expirado.",
  "errors": { "codigo": ["Código inválido ou expirado."] }
}
```

No máximo cinco erros por código, independentemente do IP. A quinta tentativa errada invalida o código. O acerto consome o código antes de emitir o token. Limite adicional de 5 requisições/minuto por IP.

## 3. Redefinir senha

`POST /api/v1/auth/redefinir-senha`

```json
{
  "reset_token": "token-retornado-na-validacao",
  "nova_senha": "minha-nova-senha",
  "nova_senha_confirmation": "minha-nova-senha"
}
```

Resposta `200`:

```json
{ "success": true, "message": "Senha redefinida com sucesso. Faça login novamente." }
```

A senha exige pelo menos oito caracteres. Falhas de senha retornam `422`, com mensagens em português em `errors.nova_senha`; não consomem o token. Token inválido, usado ou vencido retorna `422`, mensagem `Token de redefinição inválido ou expirado.` e `errors.reset_token`.

Sucesso grava a senha com hash, encerra todos os tokens Sanctum daquela conta e invalida códigos, tokens de redefinição e links de convite anteriores daquele perfil, em uma transação. Não faz login automaticamente. Se o e-mail mudar ou a conta perder acesso entre as etapas, a recuperação anterior não pode ser concluída. Limite de 5 requisições/minuto por IP.

## Limites e CORS

`429` retorna o padrão da API:

```json
{ "success": false, "message": "Muitas tentativas. Aguarde um pouco e tente de novo." }
```

O cabeçalho `Retry-After` informa segundos de espera nas três rotas. `config/cors.php` já o expõe por `Access-Control-Expose-Headers: Retry-After` para leitura pelo app web. Os limites gerais da API também continuam valendo.

## Convites e segurança

Os links de 24 horas do painel e da aprovação de matrícula continuam funcionando, usando os brokers e tabelas existentes. Solicitar código não apaga o convite: somente uma troca de senha concluída invalida os meios anteriores de recuperação. As páginas e os e-mails de convite orientam a usar código no app caso o link vença.

O fluxo por código usa a nova `tbl_recuperacao_senha` (migration `2026_10_31_000002_create_tbl_recuperacao_senha_table`). Transações bloqueiam primeiro a conta e depois a recuperação para impedir reenvios simultâneos no intervalo e o consumo duplo de códigos/tokens. Erros de código são confirmados no banco antes do `422`, para preservar o contador. Após a troca, os segredos são apagados, preservando a data do último envio para manter os 60 segundos mesmo após sucesso. Alterar a senha estando logado preserva o token atual, como antes, e também invalida recuperações antigas.

Falhas de e-mail são registradas com perfil, ID e classe do erro, sem copiar código/token para o log da aplicação. Localmente o mailer permanece `log`, que registra o conteúdo do e-mail para desenvolvimento; testes usam `Mail::fake()` e endereços fictícios, sem entrega externa.

## Testes e publicação

```sh
docker compose exec php php artisan test --filter=RecuperacaoSenha
```

Os testes usam apenas `db_futebol_test`, com trava de segurança. O teste de concorrência usa dois processos PHP reais disputando a mesma conta no MySQL e verifica envio, limite de erros, validação, redefinição e login concorrente com a troca de senha; os workers também conferem o banco e simulam e-mail. O login bloqueia a conta durante a conferência da senha e emissão de sessão, para impedir que uma senha antiga crie um token durante a redefinição e escape da revogação.

Não há chaves novas no `.env` nem dependências novas. A publicação requer autorização, backup no phpMyAdmin, atualização do código, revisão de `migrate --pretend --force`, aplicação de `migrate --force` e `optimize:clear`, usando `/opt/plesk/php/8.4/bin/php`. Não publicar sem o OK do dono do projeto; coordenar com o app, pois a recuperação passa a exigir as duas chamadas novas em vez do link.
