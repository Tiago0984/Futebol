# Publicação do Fale conosco

Este roteiro descreve ações futuras. Push e produção dependem do OK do dono do projeto. O código é preparado na `feature/agenda-eventos`; o servidor usa `main`.

## 1. Disponibilizar o código, somente após autorização

No computador de desenvolvimento, depois de conferir os commits da entrega e receber autorização:

```sh
git push origin feature/agenda-eventos:main
```

O `git pull` do servidor só receberá a entrega depois desse push. Nunca criar commits no servidor.

## 2. Backup pelo phpMyAdmin

1. No Plesk, abrir **Bancos de dados → db_futebol → phpMyAdmin**.
2. Selecionar `db_futebol` na barra lateral e abrir **Exportar**.
3. Selecionar exportação **Personalizada**, formato **SQL**, todas as tabelas e **estrutura e dados**.
4. Executar a exportação e guardar o arquivo com a data e a identificação “antes do Fale conosco”. Conferir que o download terminou e tem conteúdo antes de continuar.
5. Pelo gerenciador de arquivos do Plesk, baixar também uma cópia de segurança do `.env` atual. Não substituir o arquivo do servidor pelo `.env` local.

## 3. Atualizar o código pelo Terminal SSH do Plesk

```sh
cd ~/coderatech.tipi5.smpsistema.com.br/futebol
git status --short --branch
git pull --ff-only origin main
cd src
/opt/plesk/php/8.4/bin/php --version
```

Conferir que a branch do servidor é `main`. Alterações em arquivos versionados devem ser analisadas antes do pull; arquivos locais conhecidos, como imagens enviadas, backups e `composer.phar`, não devem ser adicionados ao Git. Usar PHP 8.4 pelo caminho completo: o `php` sem caminho no SSH é de outra versão.

Esta entrega não altera `composer.lock` nem dependências de frontend; não exige instalação de dependências ou build de assets.

## 4. Conferir e aplicar as migrations

Na pasta `futebol/src`:

```sh
/opt/plesk/php/8.4/bin/php artisan migrate:status
/opt/plesk/php/8.4/bin/php artisan migrate --pretend --force
```

A migration desta funcionalidade é `2026_10_31_000001_create_tbl_fale_conosco_table`: cria a caixa de entrada, seus índices e as referências opcionais a atleta/responsável. Conferir todas as migrations pendentes mostradas pelo servidor. Se houver outras mudanças não reconhecidas, esclarecer antes de aplicar.

Depois de conferir o SQL e ter o backup:

```sh
/opt/plesk/php/8.4/bin/php artisan migrate --force
```

O `--force` permite a execução no ambiente de produção. Se um comando falhar, guardar a mensagem e investigar antes de continuar. Não usar `migrate:fresh`, `db:wipe` ou importar o banco local.

## 5. Configurar o `.env` pelo Plesk

No gerenciador de arquivos, abrir `futebol/src/.env` (habilitar a exibição de arquivos ocultos, se necessário). Preencher as três chaves abaixo com os contatos oficiais recebidos do dono do projeto:

```dotenv
AACJ_CONTATO_EMAIL=
AACJ_CONTATO_TELEFONE=
AACJ_CONTATO_WHATSAPP=
```

O e-mail é o destinatário das mensagens. Não há telefone fixo: telefone e WhatsApp usam o mesmo número. Informar DDI + DDD + número; valores formatados com espaços devem ficar entre aspas. O site gera `tel:` e `wa.me` usando somente os dígitos. As chaves vazias de telefone/WhatsApp ocultam os respectivos canais.

Manter as configurações SMTP existentes (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, remetente e esquema). O remetente SMTP continua o mesmo; a resposta ao atendimento vai para o e-mail do usuário pelo `Reply-To`.

O `.env.example` versionado contém somente as chaves vazias. Os valores oficiais ficam no `.env` de cada ambiente.

## 6. Limpar os caches após editar o `.env`

De volta ao Terminal SSH, em `futebol/src`:

```sh
/opt/plesk/php/8.4/bin/php artisan optimize:clear
/opt/plesk/php/8.4/bin/php artisan migrate:status
```

## 7. Conferir a publicação

- `/AACJ/contato`: conferir e-mail, telefone e link do WhatsApp. Enviar uma mensagem de teste com o próprio e-mail e verificar a confirmação.
- `/AACJ/admin/fale-conosco`: conferir a mensagem salva e marcar lida/não lida.
- Conferir o recebimento do aviso na caixa configurada e que a opção **Responder** usa o e-mail de quem enviou.
- `/AACJ/api/documentacao`: conferir o contrato `201`, os erros e a rota `/api/v1/fale-conosco`.
- No app web, conferir `429`, `Retry-After` e `Access-Control-Expose-Headers: Retry-After` em uma requisição com `Origin`.

Se o aviso por e-mail falhar, a mensagem continua salva no painel. O detalhe mostra a falha e o log `storage/logs/laravel.log` registra “Falha ao enviar e-mail do fale conosco.” com o protocolo.
