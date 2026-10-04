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
- **Fotos de atleta em dois lugares:** o cadastro do site grava no disco `public` (`storage/app/public/atletas/...`, servido em `/storage`); o do admin grava em `public/futebol/images/our-teams/` (e `default-player.jpg` quando não há foto). Use `Atleta::urlFoto()` nas telas.
- **Deploy precisa rodar `php artisan storage:link`** (cria `public/storage`, fora do Git). Sem ele, as fotos enviadas pelo site ficam quebradas. Localmente já foi criado (link absoluto `/var/www/html/...`, válido dentro dos containers).
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
- Fonte oficial da categoria do atleta: **`tbl_categoria_atleta`** (com início, fim e status `ATIVO`/`ENCERRADO`). `tbl_inscricao` foi **descontinuada** (Fase 3).
- Troca de categoria: **fechar a linha antiga** (data_fim + status) e **criar uma nova**, nunca só dar UPDATE.

### Usuários (dashboard)
- PK de `tbl_usuarios` é **`id_usuario`** (feito na Fase 2).
- **`cargo_usuario`** (função exibida, VARCHAR, lista em `User::CARGOS`) e **`nivel_usuario`** (permissão no sistema, ENUM). Valores provisórios (seção 8).
- **Responsável pelo evento = usuário logado que o criou** (`id_usuario` no evento). Gravado só na criação, **nunca sobrescrito** na edição.

### Eventos (tudo vira evento)
- Treinos, jogos (campeonato e amistoso), eventos individuais (exame médico, avaliação física) e outros (reunião, viagem, café da manhã) são **eventos**.
- Evento guarda **`id_categoria`** (nullable: preenchido em evento de categoria, vazio em evento individual).
- Nova tabela **`tbl_evento_atleta`** (inscrição, **sem status**). **O atleta só vê na agenda os eventos em que está inscrito.**
- Cancelar um treino de um dia específico: editar **aquele evento** e mudar para cancelado.

### Inscrição em eventos (Fase 5)
- **Sem status:** remover a inscrição apaga a linha. Ficam registrados **origem** (`CATEGORIA` = automática pela categoria do evento; `INDIVIDUAL` = escolha do admin), **quem** inscreveu e **quando**. Único (evento, atleta).
- **Evento com categoria:** ao ser criado, já inscreve os atletas **ATIVO** com linha **ATIVO** em `tbl_categoria_atleta` nela (origem `CATEGORIA`).
- **Inscrição individual:** o admin escolhe atletas ativos um a um; botão **"Adicionar todos de uma categoria"** (pode usar várias vezes, para eventos de várias categorias como a avaliação física; já inscritos são ignorados sem erro; origem `INDIVIDUAL`).
- **Evento futuro que muda de categoria:** sincroniza as inscrições **automáticas** (sai quem não é da nova, entra quem é) e **mantém as individuais**; ficando **sem categoria**, as automáticas saem; evento **concluído** não muda nada. A tela mostra o resumo.
- **Evento cancelado ou oculto:** mantém as inscrições.
- **Atleta muda de categoria:** aviso com os eventos futuros e não cancelados da categoria antiga e da nova + botão **"Mover inscrições"** (com confirmação). Nada é movido sem o admin confirmar.
- **Atleta inativado ou rejeitado:** a inscrição **não é apagada**; listas, conflitos e o app consideram **só atletas ATIVO** (se voltar a ativo, a inscrição volta a valer). A exclusão definitiva (Matrículas Rejeitadas) apaga as inscrições.
- **Quem entra na categoria depois que o evento existe:** não é inscrito automaticamente. Botão **"Atualizar inscritos pela categoria"** (só acrescenta) e a tela mostra quantos atletas da categoria ainda não estão inscritos.
- **`id_time`** (escalação no jogo): coluna criada na Fase 5 (nullable, FK); a tela de escalação por jogo fica para a Fase 6.
- **Site público nunca mostra inscritos** (dados de menores).

### Jogos (Fase 6)
- **Jogo = evento JOGO** (`tbl_jogos.id_evento`, INT UNSIGNED, único, FK). **Data, horário, local e status ficam só no evento** (histórico, Alterado, Concluído, cancelar/ocultar, inscrição e conflito valem para o jogo). Em `tbl_jogos` ficam campeonato, times e placar.
- **Jogos existentes:** ganham um evento cada (título "Casa x Visitante", data e hora do jogo, local e categoria do campeonato, responsável NULL), **sem inscrições** (já aconteceram).
- **Eventos JOGO antigos sem `tbl_jogos`** continuam eventos comuns; jogos novos nascem pela tela de **Jogos**.
- **Sem `status_jogo` e sem exclusão de jogo:** cancelar e ocultar são ações do evento (como na Fase 4). `status_jogo` e `data_jogo` saem na Etapa 2; até lá `data_jogo` é uma cópia mantida pelo evento.
- **Placar nullable:** NULL = ainda não jogado (os dois ou nenhum). A classificação conta só jogos com placar e evento não cancelado nem oculto.
- **Título do evento** gerado como "Casa x Visitante", na criação e quando os times mudam.
- **Categoria do evento:** a do campeonato; no **amistoso** (sem campeonato, opção explícita "Amistoso" no formulário), o admin escolhe (sugestão: categoria do time mandante) ou deixa sem. Com categoria, os atletas ativos dela são inscritos (regra da Fase 5); a escalação marca quem joga em cada time.
- **Escalação** (Etapa 3): `tbl_evento_atleta.id_time`, só o mandante ou o visitante do jogo, só time INTERNO. O único (evento, atleta) já impede o atleta nos dois times. Escalar quem não está inscrito **inscreve** (INDIVIDUAL, com alerta de conflito).
- **API:** continua devolvendo `data_jogo` no JSON (calculado do evento), para não quebrar o app.

### Status
- **Gravado no evento:** `ATIVO` / `CANCELADO` / `INATIVO` (inativo = escondido, rótulo "Oculto"; cancelado = continua visível com o selo).
- **Status muda só por ações** no admin: **Cancelar ↔ Reativar** (`CANCELADO` ↔ `ATIVO`) e **Ocultar ↔ Mostrar** (`INATIVO` ↔ `ATIVO`). A edição do evento não altera o status.
  - "Mostrar" restaura o **status anterior** ao ocultar, pelo histórico (cancelado e depois ocultado volta como cancelado; sem histórico, volta `ATIVO`).
- **Sem exclusão de evento** (rota, método e botão removidos): **ocultar** faz esse papel e preserva o registro.
- **Site público:** mostra eventos **cancelados com o selo "Cancelado"**; `INATIVO` continua escondido. O destaque "Próximo Evento" considera só eventos ativos.
- **Derivados (não gravados):**
  - **Alterado:** mudou **data, horário ou local** e o evento ainda não aconteceu. Aparece **só no dashboard** (nunca no site nem no app).
  - **Concluído:** data anterior a hoje, ou hoje com o horário de fim já passado (sem fim, vale o início; sem nenhum dos dois, só no dia seguinte). **Cancelado continua cancelado** mesmo depois da data.
- **Histórico de alterações:** campo, valor antigo, valor novo, quem alterou, quando. Registra **data, horário de início e de fim, local, status, título, tipo e categoria**; só data, horário e local contam para "Alterado".
- **Responsável (`id_usuario`):** quem criou; os 6 eventos anteriores à Fase 4 ficam com `id_usuario` NULL ("—" na tela).
- **App do atleta:** mostra só **Confirmado** (inscrito e ativo) ou **Cancelado**. **Nunca o selo "Alterado"**; a alteração chega por notificação.
- App mostra os **últimos 3 eventos passados**.
- Os commits `59c9743` e `86b8e78` (que gravavam `CONFIRMADO/ALTERADO/CANCELADO`) são desfeitos por **migration nova** na Fase 4, sem `git revert` (a branch já está no remoto).

### Site público (provisório até o professor decidir)
- O calendário do site mostra só os tipos **JOGO, TREINO e CAMPEONATO** (`EventoCalendario::TIPOS_PUBLICOS`), na lista e no próximo evento. EVENTO, REUNIAO, CONFRATERNIZACAO e AVALIACAO ficam só no admin.
- Ligado à pergunta 3 da seção 8.

### Notificações
O atleta é notificado em três casos: **inscrição, alteração e cancelamento**.

### Grade de treino
- Tem **`id_categoria`** (FK, nullable para itens gerais como "Integrado" e "Treino Livre"), feito na Fase 3. Horários femininos entram como linhas novas.
- Vira **modelo**: gera **eventos reais por data**, já com os atletas da categoria inscritos.

### Conflito de horário
O atleta pode estar em mais de um time. Ao **inscrever ou escalar** um atleta num evento que **sobrepõe** outro em que ele já está, o admin recebe um **alerta**.
- Sobreposição: **mesmo dia** e `inícioA < fimB` e `inícioB < fimA`; cancelados e ocultos não contam; só atletas ATIVO.
- **Sem horário de fim:** duração padrão por tipo, numa constante **provisória** (seção 8, pergunta 12): JOGO 2h, TREINO 1h30, AVALIAÇÃO 1h, CAMPEONATO o dia todo, demais 2h.
- **Sem horário de início:** não dá para calcular sobreposição; **aviso fraco** "mesmo dia, horário a definir".
- **Conflito real:** alerta amarelo "Conflito de horário" com **"Confirmar mesmo assim"** (nada é salvo sem confirmar).
- **Só aviso fraco:** **não pede confirmação**; salva direto e mostra um aviso informativo azul "Mesmo dia — confira o horário".
- **Os dois tipos na mesma ação:** pede confirmação e lista os dois, separados por tipo.
- **Onde é verificado:** criar evento com categoria, editar data/horário/categoria, inscrição individual, "Adicionar todos de uma categoria", "Atualizar inscritos pela categoria", Reativar/Mostrar (avisa sem bloquear) e "Mover inscrições" do atleta.

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
- `7c58df3` docs: registra Fase 2 concluída
- `89ed009`, `10431af` docs: respostas do professor (categorias, feminino, categoria acima)
- `bd3f7dd` refactor: remove tela morta de Inscrições (Fase 3)
- `3c78803` feat: descontinua `tbl_inscricao` (Fase 3)
- `b81235b` chore: deixa de criar a tabela `users` na migration 0001 (Fase 3)
- `fc6b3ec` feat: cria as 10 categorias oficiais, Sub-9 a Sub-17, M e F (Fase 3)
- `6dd95f9` feat: remove a categoria Sub-12 de teste (Fase 3)
- `50ff8ea` feat: sugere e valida a categoria do atleta pela regra do ano (Fase 3)
- `d00d41d` docs: débito da camisa repetida
- `5f81ea0` feat: e-mail do atleta e número de matrícula no cadastro pelo admin (Fase 3)
- `c2e982b` feat: preenche número de matrícula de aprovados sem número (Fase 3)
- `d5da33b` feat: e-mail do responsável nos modais de atleta do admin (Fase 3)
- `9422049` docs: andamento da Fase 3
- `8dbb21c` fix: grau de parentesco abre vazio na edição do atleta (Fase 3)
- `b1ef107` fix: normaliza graus de parentesco gravados em outro formato (Fase 3)
- `79d8893` feat: grade de treino aponta para a categoria (Fase 3, Etapa 4)
- `0386659` docs: Fase 3 concluída
- `d27ae6d` fix: ordena a grade de cada dia pelo horário de início
- `003b27c` feat: status do evento ATIVO/CANCELADO/INATIVO (Fase 4, Etapa 1)
- `8bce102` feat: evento com categoria e responsável (Fase 4, Etapa 2)
- `bf72311` feat: histórico de alterações do evento e status derivados (Fase 4, Etapa 3)
- `e5af80b` docs: Fase 4 concluída
- `96f58a7` feat: inscrição de atletas em eventos (Fase 5, Etapa 1)
- `5ce4c76` feat: regras das inscrições quando evento ou atleta mudam (Fase 5, Etapa 2)
- `58afdaa` feat: alerta de conflito de horário nas inscrições (Fase 5, Etapa 3)

### Fase 1 encerrada
- 1.1 collation, 1.2 tipos sem acento (`5094b36`) e 1.3 exclusão de atleta concluídas.
- Tipos do ENUM: `JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO`; rótulos com acento em `EventoCalendario::TIPOS`.

### Testes
- Rodam no banco **`db_futebol_test`** (MySQL, `utf8mb4_general_ci`, `GRANT ALL` para o `user` do `.env`), configurado no `phpunit.xml`. Precisam do Docker ligado:
  `docker compose exec php php artisan config:clear && docker compose exec php php artisan test`
- O `RefreshDatabase` roda `migrate:fresh`. Testes que usam o banco usam o trait **`Tests\RefreshBancoDeTestes`** (no lugar do `RefreshDatabase`), que aborta se a conexão não for `db_futebol_test`.
- **As migrations montam o banco do zero** (conferido: 42 migrations, 31 tabelas, todas `general_ci`, 21 FKs, igual ao `db_futebol` exceto a tabela `users`). Isso vale para o primeiro deploy.
- `UserFactory` usa as colunas de `tbl_usuarios`, com estados `->admin()`, `->editor()` e `->leitura()` (padrão: `LEITURA`). Testes do admin usam `->admin()`.
- Trait **`Tests\CriaDadosDeAtleta`**: cria atleta, categoria do atleta e os formulários completos de cadastro/edição do admin.
- Migrations de dados (limpeza da Sub-12, números de matrícula) são testadas rodando o `up()` sobre dados simulados, porque num banco novo elas não fazem nada.
- O `--pretend` não executa `SELECT`: para migrations de dados, ensaiar carregando o backup no `db_futebol_test` (conferindo a conexão antes) e rodando o `migrate` lá. **Recriar o banco de testes antes de carregar o backup** (`DROP DATABASE` + `CREATE DATABASE ... utf8mb4_general_ci`; o `GRANT` do `user` continua valendo): o dump não apaga tabelas que a suíte criou, e uma migration que cria tabela falharia com "already exists". Depois do ensaio, rodar a suíte para o banco de testes voltar ao normal.

### Fase 2 concluída — usuários (`bc63372`, `5252c45`)
- Migration aplicada no `db_futebol` (batch 9; backup em `backup/db_futebol_antes_fase2_20261003_091003.sql`). 28 testes passando; roteiro no navegador validado (login, lembrar-me, logout, nome no header e na sidebar).
- `tbl_usuarios.id` → `id_usuario` (migration e `User::$primaryKey` **no mesmo commit**); índice `users_email_unique` → `email_usuario_unique`.
- `nivel_usuario` ENUM `ADMIN/EDITOR/LEITURA`, `NOT NULL DEFAULT 'LEITURA'`; usuários existentes viram `ADMIN`. **Nesta fase não há restrição de rota por nível.** Fica fora do `$fillable` (evita autopromoção).
- `cargo_usuario` VARCHAR(30) nullable; valores em `User::CARGOS`, validar com `Rule::in`.
- Checkbox "Lembrar-me" no login do admin; `AdminLoginTest` cobre login, erro, visitante, logout, lembrar-me, nome no painel e schema.

### Fase 3 concluída — categoria e grade (`bd3f7dd` a `79d8893`)
- **Inscrição e users:** `tbl_inscricao` descontinuada (`bd3f7dd`, `3c78803`); `users` fora da 0001 (`b81235b`).
- **Categorias:** 10 oficiais, Sub-9 a Sub-17 em M e F, com índice único (nome, sexo) (`fc6b3ec`); Sub-12 de teste removida (`6dd95f9`).
- **Categoria do atleta pela regra do ano** (`50ff8ea`): `Categoria::sugeridaPara` / `erroParaAtleta` (abaixo bloqueado, acima com motivo); `Atleta::trocarCategoria` encerra a linha e abre outra; aprovação grava a categoria; idade de 9 a 17 anos pelo ano no site e no admin (`Atleta::limitesNascimento`); pendente/rejeitado só mudam de status por Matrículas.
- **Cadastro pelo admin** (`5f81ea0`, `c2e982b`, `d5da33b`): número de matrícula gerado também fora da aprovação (`Atleta::atribuirNumeroMatricula`, `lockForUpdate` + nova tentativa) e preenchido para os aprovados sem número; e-mail do atleta (único) e do responsável (não único).
- **Grau de parentesco** (`8dbb21c`, `b1ef107`): lista única `Responsavel::GRAUS_PARENTESCO` validada com `Rule::in`; valores antigos `PAI`/`MAE` normalizados.
- **Grade** (`79d8893`): `tbl_grade_treino.id_categoria` (INT com sinal, nullable, FK `fk_grade_categoria`); Sub-N → categoria masculina; Integrado, Treino Livre e Jogos sem categoria; select no admin; rótulo "Sub-13 Masculino" no admin e no site.
- **Migrations no `db_futebol`:** batches 10 (inscrição), 11 (categorias e Sub-12), 12 (números de matrícula), 13 (grau), 14 (grade).
- **Backups** em `backup/`: `db_futebol_antes_fase3_inscricao_20261003_094201.sql`, `..._categorias_20261003_095704.sql`, `..._matricula_20261003_135849.sql`, `..._grau_20261003_143920.sql`, `..._grade_20261003_144534.sql`.
- 103 testes passando.

### Fase 4 concluída — evento base (`003b27c`, `8bce102`, `bf72311`)
- **Status** (`003b27c`): ENUM `ATIVO/CANCELADO/INATIVO` (migration nova, sem `git revert` de `59c9743`/`86b8e78`); ações Cancelar↔Reativar e Ocultar↔Mostrar; sem exclusão de evento; site mostra cancelado com selo e esconde oculto; "Próximo Evento" só ativo; horário vazio = "A definir".
- **Categoria e responsável** (`8bce102`): `id_categoria` (INT, nullable) e `id_usuario` (BIGINT UNSIGNED, nullable; os 6 eventos antigos NULL); responsável gravado só na criação (`EventoCalendario::criarPor`, fora do `$fillable`); categoria inativa atual aparece como "(inativa)" na edição.
- **Histórico e derivados** (`bf72311`): `tbl_evento_historico` (campo, antigo, novo, quem, quando); `EventoCalendario::atualizarComHistorico` em edição, cancelar e ocultar; "Mostrar" restaura o status anterior; `situacao` = Ativo/Alterado/Concluído/Cancelado/Oculto, só no admin (`comAlteracao()` evita consulta por evento); histórico no modal de edição.
- **Também:** grade ordenada por horário de início (`d27ae6d`).
- **Migrations no `db_futebol`:** batches 15 (status), 16 (categoria e responsável), 17 (histórico).
- **Backups** em `backup/`: `db_futebol_antes_fase4_status_20261003_151316.sql`, `..._categoria_usuario_20261003_151851.sql`, `..._historico_20261003_185700.sql`.
- 137 testes passando; roteiros das Etapas 1, 2 e 3 validados no navegador.

### Fase 5 concluída — inscrição e conflito (`96f58a7`, `5ce4c76`, `58afdaa`)
- **Inscrição** (`96f58a7`): `tbl_evento_atleta` (origem `CATEGORIA`/`INDIVIDUAL`, quem e quando, único (evento, atleta), `id_time` nullable com FK); evento com categoria já nasce com os atletas ativos inscritos (`EventoCalendario::criarPor`); tela do evento com inscrição individual, "Adicionar todos de uma categoria", remoção e "Atualizar inscritos pela categoria".
- **Regras quando evento ou atleta mudam** (`5ce4c76`): troca de categoria do evento sincroniza só as automáticas (concluído não muda); "Mover inscrições" do atleta com confirmação; inativo/rejeitado mantém a linha mas não conta; exclusão definitiva apaga as inscrições; horário sem segundos na tela.
- **Conflito de horário** (`58afdaa`): `EventoCalendario::DURACAO_PADRAO_MINUTOS` (provisória, seção 8, pergunta 12), `intervalo()`, `conflitosPara()`, `descreverConflito()`; partial `admin/calendario/_conflitos.blade.php`; regras na seção 4.
- **Migration no `db_futebol`:** batch 18 (`tbl_evento_atleta`). Etapas 2 e 3 sem migration.
- **Backup** em `backup/`: `db_futebol_antes_fase5_inscricao_20261003_191825.sql`.
- 174 testes passando; roteiros das Etapas 1, 2 e 3 validados no navegador.

### Próxima: Fase 6 — jogos ↔ evento
(detalhes no item 6 abaixo e na seção 5)

### Próximas fases (ordem recomendada)
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
- **Virada do ano:** com a idade pelo ano de nascimento, metade dos atletas muda de categoria todo 1º de janeiro (quem fica com idade par sai de Sub-11/13/15). Plano futuro: tela/relatório para o admin com a lista de atletas cuja categoria esperada mudou; a troca continua **manual** (fechar a linha antiga de `tbl_categoria_atleta` e abrir uma nova).
- **Camisa repetida na lista de atletas:** um atleta em dois times mostra "Camisa Nº 15" duas vezes, sem dizer de qual time é cada número.
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
12. **Duração padrão dos eventos sem horário de fim** (usada no alerta de conflito), hoje provisória: JOGO 2h, TREINO 1h30, AVALIAÇÃO 1h, CAMPEONATO o dia todo, demais 2h. Confirmar os valores com o professor.
13. **Critério de desempate da classificação** (site: home e página do campeonato), hoje provisório em `Jogo::classificacao()`: pontos (vitória 3, empate 1), vitórias, saldo de gols, gols marcados e, por fim, nome do time (sem acentos e sem maiúsculas). Confirmar com o professor (confronto direto? cartões?).

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
