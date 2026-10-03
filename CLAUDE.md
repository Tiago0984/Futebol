# CLAUDE.md — Escolinha de Futebol AACJ

Contexto permanente do projeto. Leia antes de qualquer tarefa. Se algo aqui divergir do código ou do banco, **avise antes de assumir**.

---

## 1. Projeto e ambiente

- **Stack:** Laravel + MySQL 8, em Docker (WSL/Ubuntu). Produção futura em Plesk. **O site ainda não está no ar**: não há banco de produção com dados reais.
- **Pastas:** repositório em `~/dev/senac/Futebol`; código Laravel em `src/`; `.gitignore` na raiz; backups em `backup/` (ignorado pelo Git).
- **Serviços Docker:** `php` (futebol_php), `mysql` (futebol_mysql), `nginx` (futebol_nginx).
- **URLs locais:** site e admin em `http://localhost:8080` (admin em `/admin`); MySQL no host pela porta `3308` (Workbench).
- **Dois logins:**
  - Admin: guard `admin` (sessão), model `User` → `tbl_usuarios`.
  - Atleta: Sanctum (token), model `Atleta` → `tbl_atletas`. API em `routes/api.php` (`/api/v1/...`). O login exige `status_atleta = ATIVO`; os campos do login são `email` e `senha`.
- **App do atleta:** fica em outro repositório (a tela Agenda ainda usa dados fixos).
- **Branch de trabalho:** `feature/agenda-eventos`. **Nunca fazer push sem autorização.**

---

## 2. Regras de trabalho

1. Responder em **português do Brasil**, de forma direta, explicando o porquê das decisões (o dono do projeto está aprendendo).
2. Trabalhar **em fases**. Ao fim de cada fase, **parar**, mostrar o diff e **esperar aprovação**.
3. **Não rodar** `migrate`, `migrate:fresh`, `db:wipe`, `db:seed`, `composer/npm install`, `commit` ou `push` sem OK explícito. `migrate --pretend` e consultas só de leitura são permitidos.
4. Toda alteração de banco é feita **por migration**, nunca direto no banco.
5. **Backup antes de migration** (dump com dados, dentro do container):
   `mysqldump --default-character-set=utf8mb4 --single-transaction --no-tablespaces db_futebol`
6. SQL pelo container **sempre** com `--default-character-set=utf8mb4`: o cliente `mysql` do container conecta em latin1 por padrão, e foi isso que corrompeu o enum no passado.
7. Valores corrompidos em PHP são escritos com **escapes de bytes** (`"\xC3\x83\xC6\x92"`), nunca colados.
8. Comandos com valores a substituir: marcar com ⚠️ e dizer o que trocar.
9. Commits **sem a linha `Co-Authored-By`**. Um assunto por commit.
10. `tinker --execute` só imprime com `echo`.
11. Ler o código existente antes de alterar e seguir os padrões do projeto.

---

## 3. Convenções do banco

- Tabelas `tbl_*`; colunas no formato `campo_tabela`; status em MAIÚSCULAS.
- **Collation única: `utf8mb4_general_ci`** (tabelas, colunas, schema, `DB_COLLATION` no `.env` e default em `config/database.php`). Ela **ignora acentos**: um ENUM não aceita `'REUNIÃO'` e `'REUNIAO'` juntos.
- **Valores de ENUM sem acento**; o texto com acento fica só no rótulo, no model (ex.: `EventoCalendario::TIPOS`).
- **Tipos de chave (FK exige o mesmo tipo e sinal):**
  - `id_atleta`, `id_categoria`, `id_time` e a maioria dos ids: `INT` com sinal.
  - `id_evento_calendario`: `INT UNSIGNED`.
  - `tbl_usuarios.id_usuario`: `BIGINT UNSIGNED`.
  - Não usar `foreignId()` sem conferir o tipo da coluna referenciada.
- Todas as FKs existentes estão em `NO ACTION`; não usar `ON DELETE CASCADE` sem discutir.
- O banco nasceu de script SQL; as migrations `create_*` e `add_foreign_keys_*` estão com batch [0] (marcadas, nunca executadas).

---

## 4. Regras de negócio decididas

### Atletas
- Na tela de **Atletas** o atleta só é **inativado**. Exclusão definitiva **só em Matrículas Rejeitadas**, via `Atleta::excluirComDependencias()`.
- Atleta **com cartões não é excluído** (preserva o histórico dos jogos). Provisório até o professor decidir.
- Matrícula aprovada não volta para rejeitada.
- Fonte oficial da categoria do atleta: **`tbl_categoria_atleta`** (com início, fim e status). `tbl_inscricao` deve ser **descontinuada**.
- Troca de categoria: **fechar a linha antiga** (data_fim + status) e **criar uma nova**, nunca só dar UPDATE.

### Usuários (dashboard)
- PK de `tbl_usuarios` é **`id_usuario`** (feito na Fase 2).
- **`cargo_usuario`** (função exibida, VARCHAR, lista em `User::CARGOS`) e **`nivel_usuario`** (permissão no sistema, ENUM). Valores provisórios (seção 8).
- **Responsável pelo evento = usuário logado que o criou** (`id_usuario` no evento). Gravado só na criação, **nunca sobrescrito** na edição.

### Eventos (tudo vira evento)
- Treinos, jogos (campeonato e amistoso), eventos individuais (exame médico, avaliação física) e outros (reunião, viagem, café da manhã) são **eventos**.
- Evento guarda **`id_categoria`** (nullable: preenchido em evento de categoria, vazio em evento individual).
- Nova tabela **`tbl_evento_atleta`** (inscrição, **sem status**). **O atleta só vê na agenda os eventos em que está inscrito.**
- Evento de categoria: o admin cria e inscreve os atletas da categoria (consultando `tbl_categoria_atleta`). Evento individual: o admin inscreve o atleta direto.
- Troca de categoria: o **admin reinscreve manualmente** o atleta nos eventos futuros.
- Cancelar um treino de um dia específico: editar **aquele evento** e mudar para cancelado.

### Status
- **Gravado no evento:** `ATIVO` / `CANCELADO` / `INATIVO` (inativo = escondido; cancelado = continua visível com o selo).
- **Derivados (não gravados):**
  - **Alterado:** mudou **data, horário ou local** e o evento ainda não aconteceu. Aparece **só no dashboard**.
  - **Concluído:** a data já passou (cancelado continua cancelado).
- **Histórico de alterações:** campo, valor antigo, valor novo, quem alterou, quando.
- **App do atleta:** mostra só **Confirmado** (inscrito e ativo) ou **Cancelado**. **Nunca o selo "Alterado"**; a alteração chega por notificação.
- App mostra os **últimos 3 eventos passados**.
- ⚠️ Conflita com o código atual: os commits `59c9743` e `86b8e78` gravam `CONFIRMADO/ALTERADO/CANCELADO`. A Fase 4 desfaz isso.

### Site público (provisório até o professor decidir)
- O calendário do site mostra só os tipos **JOGO, TREINO e CAMPEONATO** (`EventoCalendario::TIPOS_PUBLICOS`), na lista e no próximo evento. EVENTO, REUNIAO, CONFRATERNIZACAO e AVALIACAO ficam só no admin.
- Ligado à pergunta 3 da seção 8.

### Notificações
O atleta é notificado em três casos: **inscrição, alteração e cancelamento**.

### Grade de treino
- Ganha **`id_categoria`** (FK, nullable para itens gerais como "Integrado" e "Treino Livre").
- Vira **modelo**: gera **eventos reais por data**, já com os atletas da categoria inscritos.

### Conflito de horário
O atleta pode estar em mais de um time. Ao **inscrever ou escalar** um atleta num evento que **sobrepõe** outro em que ele já está, o admin recebe um **alerta**.

### Menu do dashboard
- **Eventos** centraliza tudo: Campeonato → jogos; Amistoso → jogo; Individual → tipo (exame médico, avaliação física).
- Saem do menu: **Categorias, Calendário e Escalação**.
- A sidebar atual (commit `eb5e65a`) usa **dados fictícios**.

---

## 5. Recomendações em uso (confirmar com o professor quando possível)

- **Geração da grade:** manual, botão "gerar agenda do mês", com chave única (grade + data) para não duplicar. `segunda_quarta`/`terca_quinta` geram dois eventos.
- **Notificação dos treinos gerados:** uma única por atleta ("agenda do mês disponível").
- **Mudança na grade:** atualizar os eventos futuros já gerados, perguntando a partir de qual data.
- **Conflito:** verificar entre **todos** os eventos (menos cancelados/inativos); usar horário de fim de cada evento (duração padrão por tipo se vazio); sem margem de deslocamento por enquanto; **alerta com confirmação** (não bloqueia); verificar também quando um evento é alterado.
- **Jogo ↔ evento:** `tbl_jogos.id_evento` (1:1); data, horário e local **só no evento**; `id_campeonato` nullable (amistoso = jogo sem campeonato). ⚠️ `stat-facts.blade.php:8` e a `HomeController` usam `campeonato`/`data_jogo`.
- **Escalação:** `tbl_evento_atleta.id_time` (nullable) + único (evento, atleta). O atleta não pode estar nos dois times do mesmo jogo.
- **Menu:** vai até campeonato/jogo; times e escalação ficam **na tela do jogo**. "Em andamento" = status ATIVO **e** hoje dentro do período. Link "Ver todos" para concluídos. Individual → exame por data → jogadores. Ramos extras: Treinos e Outros. Seção **Cadastros** com Categorias, Times e Grade.
- **Site público:** nunca exibir exames e avaliações (dados de saúde de menores).

---

## 6. Estado atual

### Commits na branch `feature/agenda-eventos` (sem push)
- `eb5e65a` wip: sidebar de Eventos com dados fictícios
- `6f8a0b0` fix: centraliza exclusão de atleta e corrige erro de FK (Fase 1.3)
- `5b28bc7` chore: deixa de versionar assinaturas
- `3537ea8` fix: mantém pasta de assinaturas com `.gitkeep`
- `c86d509` fix: padroniza collation em `utf8mb4_general_ci` (Fase 1.1)
- `5094b36` fix: remove acentos dos valores do enum de tipo de evento (Fase 1.2)
- `3b5ba93` test: testes de feature do calendário em banco MySQL próprio
- `7b2171d` feat: site público mostra só JOGO, TREINO e CAMPEONATO
- `1dd4b0b` chore: ignora `Zone.Identifier` também no git do Windows
- `776ca56` docs: adiciona CLAUDE.md
- `bc63372` feat: renomeia id de usuário e adiciona cargo e nível (Fase 2)
- `5252c45` feat: checkbox "Lembrar-me" no login do admin (Fase 2)

### Fase 1 encerrada
- 1.1 collation, 1.2 tipos sem acento (`5094b36`) e 1.3 exclusão de atleta concluídas.
- Tipos do ENUM: `JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO`; rótulos com acento em `EventoCalendario::TIPOS`.

### Testes
- Rodam no banco **`db_futebol_test`** (MySQL, `utf8mb4_general_ci`, `GRANT ALL` para o `user` do `.env`), configurado no `phpunit.xml`. Precisam do Docker ligado:
  `docker compose exec php php artisan config:clear && docker compose exec php php artisan test`
- O `RefreshDatabase` roda `migrate:fresh`. Testes que usam o banco usam o trait **`Tests\RefreshBancoDeTestes`** (no lugar do `RefreshDatabase`), que aborta se a conexão não for `db_futebol_test`.
- **As migrations montam o banco do zero** (conferido: 42 migrations, 31 tabelas, todas `general_ci`, 21 FKs, igual ao `db_futebol` exceto a tabela `users`). Isso vale para o primeiro deploy.
- `UserFactory` usa as colunas de `tbl_usuarios`, com estados `->admin()`, `->editor()` e `->leitura()` (padrão: `LEITURA`). Testes do admin usam `->admin()`.

### Fase 2 concluída — usuários (`bc63372`, `5252c45`)
- Migration aplicada no `db_futebol` (batch 9; backup em `backup/db_futebol_antes_fase2_20261003_091003.sql`). 28 testes passando; roteiro no navegador validado (login, lembrar-me, logout, nome no header e na sidebar).
- `tbl_usuarios.id` → `id_usuario` (migration e `User::$primaryKey` **no mesmo commit**); índice `users_email_unique` → `email_usuario_unique`.
- `nivel_usuario` ENUM `ADMIN/EDITOR/LEITURA`, `NOT NULL DEFAULT 'LEITURA'`; usuários existentes viram `ADMIN`. **Nesta fase não há restrição de rota por nível.** Fica fora do `$fillable` (evita autopromoção).
- `cargo_usuario` VARCHAR(30) nullable; valores em `User::CARGOS`, validar com `Rule::in`.
- Checkbox "Lembrar-me" no login do admin; `AdminLoginTest` cobre login, erro, visitante, logout, lembrar-me, nome no painel e schema.

### Próximas fases (ordem recomendada)
3. Categoria e grade: descontinuar `tbl_inscricao`; `tbl_grade_treino.id_categoria`; resolver categorias da grade que não existem.
4. Evento base: `id_categoria`, `id_usuario` (BIGINT UNSIGNED), status ATIVO/CANCELADO/INATIVO, status derivados, histórico.
5. Inscrição e conflito: `tbl_evento_atleta` (+ `id_time`), inscrição por categoria ou individual, alerta de conflito.
6. Jogos ↔ evento: `tbl_jogos.id_evento`, amistoso, migrar jogos, adaptar a home do site.
7. Grade → eventos: botão "gerar mês".
8. Notificações: tabela `notifications`, `Notifiable` no Atleta.
9. API do app: `/v1/agenda`, `/v1/notificacoes`. **Pré-requisito:** acesso do atleta (abaixo).
10. Menu e telas finais com dados reais.

---

## 7. Débitos técnicos conhecidos (planejar, não implementar sem OK)

- **CPF único:** hoje não há índice único em `cpf_atleta`. O índice `cpf_atleta_UNIQUE` **existia** no dump de estrutura gerado pelo dono do projeto por volta de 24/09 (esse dump não ficou salvo em `backup/`); os dumps de `backup/` e a migration de criação não o têm, então ele se perdeu em algum momento. A **edição de atleta no admin** não valida CPF único (`AtletasController.php:149`). Duplicado de teste: atletas 7 e 10 (`000.000.000-00`). Plano: limpar duplicados, normalizar para só dígitos, validar CPF no cadastro (site e admin), recriar o índice.
- **Acesso do atleta ao app:** o cadastro público grava senha aleatória (`Str::random(20)`), então nenhum atleta consegue logar. `token_cadastro` é gerado e nunca lido. Plano: password broker do Laravel (broker `atletas`, e-mail em `email_atleta`), link "defina sua senha" na aprovação e "esqueci minha senha". Pendente: o link vai para o e-mail do atleta ou do responsável?
- **Assinaturas:** gravadas em `public/` (acessíveis por URL), nome previsível, caminho salvo no **responsável** (sobrescreve quando ele tem dois atletas), sem validar se é PNG, e o arquivo não é apagado na exclusão. Plano: coluna `tbl_autorizacoes.arquivo_assinatura`, `Storage::disk('local')` com UUID, rota protegida no admin, comando para migrar os arquivos, apagar após o commit da transação.
- **Responsável e endereço** ficam no banco após excluir o atleta (o responsável pode ter outros atletas).
- **Grade** cita Sub-9, Sub-11, Sub-13 e Sub-17, que não existem em `tbl_categoria` (só Sub-15 casa). O professor confirmou que devem existir, em M e F (seção 8, pergunta 2); criação planejada na Fase 3.
- **Virada do ano:** com a idade pelo ano de nascimento, metade dos atletas muda de categoria todo 1º de janeiro (quem fica com idade par sai de Sub-11/13/15). Plano futuro: tela/relatório para o admin com a lista de atletas cuja categoria esperada mudou; a troca continua **manual** (fechar a linha antiga de `tbl_categoria_atleta` e abrir uma nova).
- **Camisa repetida na lista de atletas:** um atleta em dois times mostra "Camisa Nº 15" duas vezes, sem dizer de qual time é cada número.
- `STATUS_VISIVEIS` do site inclui `ALTERADO` (muda na Fase 4).
- **Tabela `users` sobrando:** a migration padrão `0001_01_01_000000_create_users_table` cria `users`, que o projeto não usa (o admin usa `tbl_usuarios`). Ela não existe no `db_futebol` (foi apagada à mão), mas é criada no `migrate:fresh` e seria criada no primeiro deploy. Plano: tirar a criação de `users` dessa migration ou criar uma migration que a remova.
---

## 8. Perguntas em aberto para o professor

1. Níveis de usuário: quais valores e o que cada um pode fazer? **Provisório:** `nivel_usuario` = `ADMIN` / `EDITOR` / `LEITURA` e `User::CARGOS` = Professor, Nutricionista, Fisiologista, Médico, Coordenador. Valores de nível e cargo aguardam o professor.
2. ~~Sub-9, Sub-11, Sub-13 e Sub-17 existem?~~ ✅ **RESOLVIDA** (professor, 03/10/2026): a escolinha atende de 9 a 17 anos e as categorias da grade **devem existir**. **Não criar categorias sem OK** (plano da Fase 3).
   - **Faixas:** Sub-9 (9), Sub-11 (10–11), Sub-13 (12–13), Sub-15 (14–15), Sub-17 (16–17).
   - **Idade pelo ANO de nascimento:** idade = ano atual − ano de nascimento (a data exata não importa).
   - **Masculino e feminino com as mesmas 5 faixas:** total de **10 categorias** (cada faixa em `M` e `F`).
   - **Todos os dados do banco local são de teste**; nada precisa ser preservado (inclui a Sub-12, 10–12, e a Sub-15, 13–15, atuais).
3. Calendário do site público: mostra tudo, só jogos/campeonatos, ou nada?
4. A linha "Jogos" (tipo JOGO) da grade continua, já que jogos viram eventos?
5. Atleta com cartões: pode ser excluído (apagando histórico) ou só inativado?
6. Responsável e endereço de atleta excluído: apagar quando não houver outro atleta vinculado, ou anonimizar?
7. Link de definição de senha: e-mail do atleta ou do responsável?
8. Assinaturas antigas com valor `assinatura.png` (responsáveis 1, 2 e 3): considerar inválidas?
9. ~~O feminino treina junto com o masculino da mesma faixa?~~ ✅ **RESOLVIDA** (professor): feminino treina **só com feminino**. A grade usa **uma coluna** `id_categoria` (sem tabela de ligação); horários femininos entram como **linhas novas** da grade.
10. ~~Atleta pode jogar numa categoria acima da idade?~~ ✅ **RESOLVIDA** (professor): **pode, a critério do técnico** (atleta mais robusto). Escolher categoria **acima** da sugerida gera **aviso** (não bloqueia) e exige um **motivo**, gravado em `observacao_categoria_atleta`.
11. Atleta numa categoria **abaixo** da idade: hoje **bloqueado** (provisório). Confirmar com o professor se há exceção.

---

## 9. Comandos úteis

```bash
# na pasta ~/dev/senac/Futebol (WSL)
docker compose exec php php artisan migrate:status
docker compose exec php php artisan migrate --pretend
docker compose exec php php artisan tinker --execute="echo ...;"
docker compose exec php php -l app/Models/Atleta.php

# login do atleta pela API (campo da senha é "senha")
curl -X POST http://localhost:8080/api/v1/auth/login -H "Accept: application/json" \
  -d "email=EMAIL&senha=SENHA"   # ⚠️ troque EMAIL e SENHA
```

Testes destrutivos no tinker: envolver em `DB::beginTransaction()` ... `DB::rollBack()`.
