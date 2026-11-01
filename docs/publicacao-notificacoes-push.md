# Publicação de notificações push no Plesk

Executar somente com OK do dono. Esta entrega não faz push para o GitHub nem altera produção. Rodar cada bloco separado, esperando terminar. Não colar dois comandos na mesma linha.

## 1. Backup antes de alterar o servidor

No Plesk: **Bancos de dados → db_futebol → phpMyAdmin**. Selecione o banco → **Exportar → Personalizado → todas as tabelas**, estrutura e dados, formato SQL (gzip opcional) → Exportar. Baixe e guarde o arquivo, confira que não está vazio. Isso é backup do banco do servidor, não o dump local.

## 2. Publicar o código, após autorização

No PC de desenvolvimento, na raiz do repositório:

```bash
git switch feature/agenda-eventos
```

```bash
git status --short --branch
```

Com as alterações aprovadas já commitadas e o diretório limpo, envie a feature e atualize main (sem `--force`):

```bash
git push origin feature/agenda-eventos
```

```bash
git push origin feature/agenda-eventos:main
```

Se houver divergência/rejeição, pare e confira as branches; não force o push.

No **Terminal SSH do Plesk**:

```bash
cd ~/coderatech.tipi5.smpsistema.com.br/futebol
```

```bash
git status --short --branch
```

Deve estar na `main`. Preserve imagens enviadas, `.env`, backups e alterações prévias do servidor; não usar `reset --hard`/`clean`.

```bash
git pull --ff-only origin main
```

```bash
git log -1 --oneline
```

```bash
cd src
```

```bash
/opt/plesk/php/8.4/bin/php --version
```

Não houve alteração de dependências: não precisa instalar SDK/composer para esta funcionalidade.

## 3. Ambiente e migration

O `.env` do servidor fica em **Arquivos → coderatech.tipi5.smpsistema.com.br → futebol → src → .env**.

Não precisa mudar banco, SMTP, `APP_KEY` nem `QUEUE_CONNECTION`. Opcional: adicionar `EXPO_ACCESS_TOKEN=` vazio; preencher somente se o projeto Expo exigir segurança de push, com o token fornecido por quem administra esse projeto. Não colocar o token no Git, print ou chat. Salvar. A linha comentada do `.env.example` serve apenas como referência.

Após editar o `.env`, limpar configuração antes de conferir o banco:

```bash
/opt/plesk/php/8.4/bin/php artisan optimize:clear
```

```bash
/opt/plesk/php/8.4/bin/php artisan migrate:status
```

```bash
/opt/plesk/php/8.4/bin/php artisan migrate --pretend --force
```

A migration nova esperada é:

`2026_11_01_000001_create_tbl_dispositivo_table`

Cria a tabela, FKs para atleta/responsável e CHECK que exige exatamente um dono. Se as entregas anteriores ainda estiverem pendentes, também aparecerão `2026_10_31_000001_create_tbl_fale_conosco_table` e `2026_10_31_000002_create_tbl_recuperacao_senha_table`. Confira a lista real antes de continuar; migrations inesperadas exigem revisão.

Com backup salvo e SQL conferido:

```bash
/opt/plesk/php/8.4/bin/php artisan migrate --force
```

```bash
/opt/plesk/php/8.4/bin/php artisan optimize:clear
```

```bash
/opt/plesk/php/8.4/bin/php artisan migrate:status
```

A migration nova deve mostrar `Ran`. Não executar `migrate:fresh`, `db:wipe` ou seeders no servidor. Não precisa iniciar worker/cron.

## 4. Teste com curl e conta fictícia

Use o terminal Bash/WSL do PC. ⚠️ Substitua os valores dos prompts por um **token Sanctum de conta de teste** e um **token Expo real obtido do celular dessa conta**. Um texto inventado não permite testar entrega. Não use uma conta de família real. Digitar pelos prompts evita salvar os valores literais no histórico do shell.

```bash
read -rsp 'Token Sanctum da conta de teste: ' AACJ_TESTE_AUTH; echo
```

```bash
read -rsp 'Token Expo do celular de teste: ' AACJ_TESTE_EXPO; echo
```

Cadastro/ativação, esperado HTTP 200 e `{"success":true}`:

```bash
printf '{"token":"%s","plataforma":"android","notificacoes_ativas":true}' "$AACJ_TESTE_EXPO" | curl -i -sS 'https://coderatech.tipi5.smpsistema.com.br/AACJ/api/v1/dispositivos' -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer $AACJ_TESTE_AUTH" --data-binary @-
```

⚠️ Use `ios` no corpo se esse for o aparelho. Deixe o app em segundo plano, com permissão de notificações e canal `default` configurados. Pelo painel **Notificações → Novo aviso**, selecione somente o atleta fictício dessa conta e envie “Teste de push”. Deve aparecer no celular e continuar na lista de avisos do app. Toque: abre a lista; para testar um compromisso, gere um aviso por uma ação normal num evento futuro de teste. Como responsável, confira a seleção do filho e, se tiver dois vinculados, o prefixo do primeiro nome.

Desligar, esperado o mesmo 200; novo aviso deve ficar apenas na lista do app:

```bash
printf '{"token":"%s","plataforma":"android","notificacoes_ativas":false}' "$AACJ_TESTE_EXPO" | curl -i -sS 'https://coderatech.tipi5.smpsistema.com.br/AACJ/api/v1/dispositivos' -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer $AACJ_TESTE_AUTH" --data-binary @-
```

Remover antes de sair da conta:

```bash
printf '{"token":"%s"}' "$AACJ_TESTE_EXPO" | curl -i -sS 'https://coderatech.tipi5.smpsistema.com.br/AACJ/api/v1/dispositivos' -X DELETE -H 'Accept: application/json' -H 'Content-Type: application/json' -H "Authorization: Bearer $AACJ_TESTE_AUTH" --data-binary @-
```

Repetir DELETE deve continuar retornando 200. Não envie tokens nas capturas do terminal. Limpe as variáveis ao terminar:

```bash
unset AACJ_TESTE_AUTH AACJ_TESTE_EXPO
```

Se não chegar: confira o registro em `tbl_dispositivo` sem copiar/exibir o token, vínculo com o atleta, `ativo_dispositivo`, permissão do celular e credenciais/configuração do projeto Expo. Consulte apenas avisos de falha relevantes em `storage/logs/laravel.log` (não compartilhe o log inteiro). Falha Expo não gera erro na ação do painel nem apaga o aviso. Testes automatizados de falha/timeout/rollback são locais e usam `Http::fake`; não rode a suíte contra o banco de produção.

Envio após a resposta não é uma fila persistente: uma interrupção do PHP pode perder o push, mas o aviso gravado segue disponível no app. O ticket inicial não confirma entrega ao dispositivo; não há consulta posterior a receipts nesta entrega.
