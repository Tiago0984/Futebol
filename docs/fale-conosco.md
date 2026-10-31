# Fale conosco

A API, o formulário público `/contato` e a caixa de entrada `/admin/fale-conosco` usam `tbl_fale_conosco`. A migration é `2026_10_31_000001_create_tbl_fale_conosco_table`.

## Configuração

Adicionar ao `.env` do ambiente, sem substituir as configurações SMTP existentes:

```dotenv
AACJ_CONTATO_EMAIL=
AACJ_CONTATO_TELEFONE=
AACJ_CONTATO_WHATSAPP=
```

Os contatos oficiais estão somente no `.env` local, fora do Git. O `.env.example` contém apenas as chaves vazias. O dono do projeto preencherá o `.env` do servidor ao publicar; não copiar o `.env` local para produção.

Preencher telefone e WhatsApp com DDI + DDD + número, por exemplo no formato `+55 (DD) NUMERO`. Como a AACJ não tem telefone fixo, ambos usam o número oficial do WhatsApp. O site monta os links `tel:` e `wa.me` apenas com os dígitos. Canais sem número configurado ficam ocultos.

Após mudar o `.env`, limpar ou reconstruir o cache de configuração conforme o procedimento do ambiente. Nenhum contato tem valor padrão no código.

O envio usa o mailer já configurado, de forma síncrona. Localmente, `MAIL_MAILER=log` registra o e-mail no log; não envia e-mail externo. O remetente continua sendo o SMTP configurado; `Reply-To` aponta para quem enviou a mensagem. Os testes usam `Mail::fake()` e endereços fictícios, sem entrega externa.

## Contrato para o app

`POST /api/v1/fale-conosco`, com `Authorization: Bearer TOKEN` de atleta ativo ou responsável com filho ativo:

```json
{ "assunto": "Dúvida sobre os treinos", "mensagem": "Qual é o horário?" }
```

Resposta `201 Created`:

```json
{
  "success": true,
  "message": "Mensagem recebida com sucesso.",
  "data": {
    "id_fale_conosco": 123,
    "data_envio": "2026-10-31T10:00:00-03:00"
  }
}
```

- `201` confirma que a mensagem foi salva; não garante entrega externa do e-mail de aviso.
- `422` traz `{ "success": false, "message": "...", "errors": { "assunto": ["..."], "mensagem": ["..."] } }`, com os campos inválidos em português. Assunto: até 100 caracteres; mensagem: até 2000; ambos obrigatórios.
- `429` traz `{ "success": false, "message": "Muitas tentativas. Aguarde um pouco e tente de novo." }` e `Retry-After` em segundos. São 3 requisições por minuto por conta, independentemente do token/dispositivo; perfil e ID distinguem as contas. Requisições inválidas também contam. `config/cors.php` expõe `Retry-After` em `Access-Control-Expose-Headers` para leitura pelo app web.
- `401`: token ausente, inválido ou vencido. `403`: perfil ou cadastro sem acesso.
- `404` em servidor sem a rota: `{ "success": false, "message": "Endereço não encontrado." }`. O app deve apresentar o recurso como indisponível nesse servidor.

O contrato também está em `/api/documentacao`, incluindo a tabela de todas as rotas. A interface do app fica no outro repositório.

## Atendimento e falhas

O painel exige login administrativo e permite listar, abrir, filtrar e marcar mensagens como lidas ou não lidas. Abrir uma mensagem não altera a leitura automaticamente. Nome e e-mail ficam preservados como eram no envio, mesmo após alteração ou exclusão do cadastro.

A mensagem é salva antes do e-mail. `status_email_fale_conosco` indica `PENDENTE`, `ENVIADO` ou `FALHOU`; o painel mostra esse estado no detalhe. `ENVIADO` significa aceito pelo mailer configurado, não confirmação de entrega na caixa do destinatário. Em caso de falha, há registro no log com o protocolo e a classe do erro, sem copiar o conteúdo da mensagem. A secretaria pode atender pelo painel; não há reenvio automático de e-mail nem necessidade de o usuário repetir a mensagem.

O site mantém CSRF, validação em português e limite independente de 3 requisições por minuto por IP. Campos válidos são preservados quando há erro; ao exceder o limite, a página retorna `429` com `Retry-After`.

## Verificação

Com Docker ligado, executar os testes no banco isolado `db_futebol_test`, protegido por `RefreshBancoDeTestes`:

```sh
docker compose exec php php artisan test --filter=FaleConosco
```

Antes de aplicar a migration no banco da aplicação: fazer backup e conferir `php artisan migrate --pretend`. Publicação em produção e push dependem de autorização do dono do projeto.
