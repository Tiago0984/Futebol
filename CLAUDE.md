# CLAUDE.md — Escolinha de Futebol AACJ

Contexto permanente do projeto. Leia antes de qualquer tarefa. Se algo aqui divergir do código ou do banco, **avise antes de assumir**.

---

## 1. Projeto e ambiente

- **Stack:** Laravel + MySQL 8, em Docker (WSL/Ubuntu). Produção futura em Plesk. **O site ainda não está no ar**: não há banco de produção com dados reais.
- **Pastas:** repositório em `~/dev/senac/Futebol`; código Laravel em `src/`; `.gitignore` na raiz; backups em `backup/` (ignorado pelo Git).
- **Serviços Docker:** `php` (futebol_php), `mysql` (futebol_mysql), `nginx` (futebol_nginx).
- **URLs locais:** site e admin em `http://localhost:8080` (admin em `/admin`); MySQL no host pela porta `3308` (Workbench).
- **Logins:**
  - Admin: guard `admin` (sessão), model `User` → `tbl_usuarios`.
  - App (Fase 9), Sanctum (token de 30 dias), API em `routes/api.php` (`/api/v1/...`), campos `email`, `senha` e `perfil`:
    - **atleta** (padrão sem `perfil`): model `Atleta` → `tbl_atletas`, senha em `password`; exige `status_atleta = ATIVO`;
    - **responsavel**: model `Responsavel` → `tbl_responsavel`, senha em `senha_responsavel`; exige algum filho ATIVO.
  - Documentação da API para o app: página `/api/documentacao` (`resources/views/api/documentacao.blade.php`). **Rota nova da API entra na tabela "Todas as rotas"** (o `DocumentacaoApiTest` falha sem ela).
- **Fotos de atleta em dois lugares:** o cadastro do site grava no disco `public` (`storage/app/public/atletas/...`, servido em `/storage`); o do admin grava em `public/futebol/images/our-teams/` (e `default-player.jpg` quando não há foto). Use `Atleta::urlFoto()` nas telas.
- **Deploy precisa rodar `php artisan storage:link`** (cria `public/storage`, fora do Git). Sem ele, as fotos enviadas pelo site ficam quebradas. Localmente já foi criado (link absoluto `/var/www/html/...`, válido dentro dos containers).
- **Fuso horário de Brasília:** app em `America/Sao_Paulo` (`APP_TIMEZONE`, padrão em `config/app.php`) e sessão MySQL em `-03:00` (`DB_TIMEZONE`, em `config/database.php`), para `CURRENT_TIMESTAMP`/`NOW()` do banco baterem com o `now()` do Laravel. Datas no JSON da API em ISO 8601 com o deslocamento e sem milissegundos (`2099-05-01T19:00:00-03:00`), pelo trait `App\Models\Concerns\SerializaDatasComFuso`. Colunas DATETIME gravadas antes da troca estão em UTC (3 horas adiantadas; dados de teste).
- **App do atleta:** fica em outro repositório (a tela Agenda ainda usa dados fixos). A API dos **dois perfis de login, atleta e responsável**, está pronta (Fase 9); o app ainda precisa ser adaptado (seção 7).
- **E-mail:** hoje `MAIL_MAILER=log` (os e-mails vão para `storage/logs/laravel.log`, em quoted-printable). Para tirar o último link de senha do log:
  `docker compose exec php php -r '$l = quoted_printable_decode(file_get_contents("storage/logs/laravel.log")); preg_match_all("#http://localhost:8080/senha/[^\"<\s]+#", $l, $m); echo end($m[0]), "\n";'`
- **Branch de trabalho:** `feature/agenda-eventos`. **Nunca fazer push sem autorização.**
- **Dois computadores (casa e Senac):** o código vai pelo **GitHub** e o banco por **dump** (`backup/db_futebol_para_senac.sql` e similares). Os backups de `backup/` ficam **só no computador onde foram feitos** (a pasta é ignorada pelo Git). Num computador novo: criar o `db_futebol_test` (seção 6, "Testes") e rodar o `storage:link`.
- **Relógio do WSL2:** pode voltar alguns segundos (correção da hora depois de suspender o Windows). Em 05/10 isso inverteu a ordem entre o id e a hora de algumas notificações. **A ordem real é a do id** (`innodb_autoinc_lock_mode = 2`); por isso as listas ordenam pela data e, no empate, pelo id.

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
  - `tbl_grade_treino.id_grade_treino` e `tbl_evento_calendario.id_grade_treino`: `INT UNSIGNED`.
  - `tbl_usuarios.id_usuario`: `BIGINT UNSIGNED`.
  - `tbl_notificacao.id_notificacao`: `INT UNSIGNED`; nela, `id_atleta` INT com sinal, `id_evento_calendario` INT UNSIGNED (nullable) e `id_usuario` BIGINT UNSIGNED (nullable).
  - Não usar `foreignId()` sem conferir o tipo da coluna referenciada.
- **ENUMs da Fase 8:**
  - `tbl_notificacao.tipo_notificacao`: `INSCRICAO, REMOCAO, ALTERACAO, CANCELAMENTO, REATIVACAO, AGENDA` (rótulos em `Notificacao::TIPOS`).
  - `tbl_evento_atleta.origem_evento_atleta`: `CATEGORIA, INDIVIDUAL, ELENCO` (rótulos em `EventoAtleta::ORIGENS`; `ELENCO` acrescentado no fim, batch 23).
- **Coluna JSON:** `tbl_notificacao.dados_notificacao` é a primeira do projeto. JSON não tem collation (o MySQL guarda em binário próprio), então não fura a regra do `general_ci`. Na inserção em massa (`insert`), o JSON vai codificado à mão (`json_encode`), porque o cast do model não atua.
- **Fase 9 (login do app):**
  - `tbl_responsavel.senha_responsavel`: VARCHAR(255) nullable (hash; fora do `$fillable` e do JSON). NULL = ainda sem senha.
  - `tbl_responsavel.email_responsavel`: índice único `email_responsavel_unique`; gravado normalizado (minúsculas, sem espaços; vazio vira NULL, que pode repetir) pelo mutator do model.
  - `password_reset_tokens_atletas` e `password_reset_tokens_responsaveis` (um broker por perfil, formato do Laravel): `email` VARCHAR(255) PK, `token` VARCHAR(255) (hash), `created_at` TIMESTAMP nullable. Fora do padrão `tbl_*`/`campo_tabela` de propósito (o broker do Laravel lê esses nomes).
  - `tbl_notificacao_leitura` (leitura dos avisos pelos responsáveis; a do atleta continua em `tbl_notificacao.data_leitura_notificacao`): `id_notificacao_leitura` INT UNSIGNED AI; `id_notificacao` INT UNSIGNED (FK `fk_notificacao_leitura_notificacao`); `id_responsavel` INT com sinal (FK `fk_notificacao_leitura_responsavel`); `data_notificacao_leitura` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP; único `notificacao_leitura_unique` (id_notificacao, id_responsavel). FKs NO ACTION.
  - `personal_access_tokens` (Sanctum) guarda tokens dos dois perfis: `tokenable_type` = `App\Models\Atleta` ou `App\Models\Responsavel`. Não tem FK: quem apaga atleta/responsável apaga os tokens.
- **Rascunho do evento (Fase 10, Etapa 4):** `tbl_evento_calendario.data_publicacao_evento_calendario` DATETIME nullable, `DEFAULT CURRENT_TIMESTAMP` (batch 26). NULL = rascunho; preenchida = publicado. O default é de propósito: quem grava sem pensar no rascunho cria evento publicado; o rascunho grava NULL explicitamente. Fora do `$fillable`; o `EventoCalendario::booted()` preenche na criação (o model não relê o default do banco) e só o `publicar()` muda depois.
- **Tabela própria em vez da `notifications` do Laravel** (Fase 8): a padrão é polimórfica (`notifiable_id` BIGINT UNSIGNED, sem FK possível para `id_atleta` INT), grava nome de classe PHP e guarda os dados num JSON opaco.
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
- **Atleta ATIVO sempre com categoria** (linha ATIVO em `tbl_categoria_atleta`): obrigatória no cadastro pelo admin e na edição de quem fica ATIVO (o campo vazio encerraria a linha); o botão de status não ativa quem está sem categoria; na edição, a categoria atual inativa aparece como "(inativa)" (manter é aceito, trocar exige categoria ativa). Inativo pode ficar sem; pendente e rejeitado recebem a categoria na aprovação.

### Usuários (dashboard)
- PK de `tbl_usuarios` é **`id_usuario`** (feito na Fase 2).
- **`cargo_usuario`** (função exibida, VARCHAR, lista em `User::CARGOS`) e **`nivel_usuario`** (permissão no sistema, ENUM). Valores provisórios (seção 8).
- **Responsável pelo evento = usuário logado que o criou** (`id_usuario` no evento). Gravado só na criação, **nunca sobrescrito** na edição.

### Eventos (tudo vira evento)
- Treinos, jogos (campeonato e amistoso), eventos individuais (exame médico, avaliação física) e outros (reunião, viagem, café da manhã) são **eventos**.
- Evento guarda **`id_categoria`** (nullable: preenchido em evento de categoria, vazio em evento individual).
- Nova tabela **`tbl_evento_atleta`** (inscrição, **sem status**). **O atleta só vê na agenda os eventos em que está inscrito.**
- Cancelar um treino de um dia específico: editar **aquele evento** e mudar para cancelado.
- **Lista de eventos do admin por mês** (`?mes=AAAA-MM`, padrão o mês atual; setas e select), com filtro **Origem** (Grade/Manual). Depois de criar, editar, cancelar ou ocultar, a lista abre no mês do evento.

### Inscrição em eventos (Fase 5)
- **Sem status:** remover a inscrição apaga a linha. Ficam registrados **origem** (`CATEGORIA` = automática pela categoria do evento; `ELENCO` = automática pelo elenco dos times do jogo, Fase 8; `INDIVIDUAL` = escolha do admin, nenhuma sincronização mexe nela), **quem** inscreveu e **quando**. Único (evento, atleta).
- **Jogo (evento com `tbl_jogos`) não segue as regras da categoria desta seção:** quem joga é o **elenco** (seção "Jogos"). Ficam de fora a inscrição pela categoria na criação, a sincronização por troca de categoria, o "Atualizar inscritos pela categoria" e o "Mover inscrições" do atleta (`EventoCalendario::ehJogo()`).
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
- **Sem `status_jogo` e sem exclusão de jogo:** cancelar e ocultar são ações do evento (como na Fase 4). `status_jogo` e `data_jogo` saíram de `tbl_jogos` na Etapa 2 (batch 20).
- **Placar nullable:** NULL = ainda não jogado (os dois ou nenhum). A classificação conta só jogos com placar e evento não cancelado nem oculto.
- **Título do evento** gerado como "Casa x Visitante", na criação e quando os times mudam.
- **Categoria do evento:** a do campeonato; no **amistoso** (sem campeonato, opção explícita "Amistoso" no formulário), o admin escolhe (sugestão: categoria do time mandante) ou deixa sem. **Desde a Fase 8, a categoria do jogo é só exibição** (site, lista, aviso de fora da categoria): mudá-la não mexe nas inscrições.
- **Jogo inscreve o elenco, não a categoria** (Fase 8, `2cd6ee1`). Motivo: a escolinha às vezes forma dois times no mesmo campeonato, e o atleta de um time era inscrito e avisado dos jogos do outro.
  - **Criar:** inscreve o elenco ativo (`tbl_atleta_time`) dos times **INTERNOS** que jogam, com origem **`ELENCO`** e **já escalado** no time; quem está nos dois elencos entra **sem time** (`Jogo::inscreverElenco()`). Conflito conferido pelo elenco.
  - **Time interno sem elenco ativo:** o jogo fica sem inscritos daquele lado e a lista de jogos mostra um **aviso amarelo** ("Sem elenco cadastrado: …").
  - **"Preencher pelo elenco"** (único botão): inscreve quem falta do elenco (origem `ELENCO`) e escala quem está inscrito sem time; ninguém sai. Ao lado, quantos do elenco ainda não estão inscritos. No jogo, o card "Pela categoria" e o "Atualizar inscritos pela categoria" não existem.
  - **Trocar time** (`Jogo::sincronizarPeloElenco()`): sai quem veio pelo elenco de um time que saiu (a não ser que também seja do elenco de um time que ficou); entra o elenco do time novo; as `INDIVIDUAL` ficam (sem time, se estavam no que saiu). **Inverter o mando não troca ninguém.** Jogo **concluído** não muda as inscrições, só tira da escalação quem estava no time que saiu. Regra de quem sai única (`Jogo::idsQueSaemNaTroca()`), usada também pelo alerta de conflito.
  - **Atleta que entra no elenco depois:** não é inscrito sozinho (usar "Preencher pelo elenco"). **Atleta que sai do elenco:** a inscrição fica; a tela do jogo marca **"Fora do elenco"** (aviso no cadastro do atleta é débito, seção 7).
  - **"Fora do elenco"** compara com o **time escalado** (08/10/2026; igual na tela do jogo e na de jogadores do time no jogo): escalado num time de cujo elenco não é = "Fora do elenco" (com "· é do Time X" se for do elenco do outro time do jogo); sem time escolhido, a tela só informa "Elenco: …" (ou "Fora do elenco", se não é de nenhum dos dois). Só aviso, não bloqueia.
  - **Jogos anteriores à regra:** sem migração de dados (os inscritos pela categoria ficam como estão).
  - **Provisório** (seção 8, pergunta 17): elenco ativo inteiro (sem convocação por jogo); atleta pode estar em dois times do mesmo campeonato (entra sem time); a notificação do jogo não cita o time.
- **Escalação** (Fase 6, Etapa 3): `tbl_evento_atleta.id_time`, só o mandante ou o visitante do jogo, só time INTERNO. O único (evento, atleta) já impede o atleta nos dois times. Inscrever pela tela do evento já escalando inscreve como **INDIVIDUAL** (com alerta de conflito). Atleta de outra categoria/sexo: **aviso**, sem bloquear (seção 8, pergunta 14).
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

### Site público (decisão do dono do projeto, 06/10/2026; seção 8, pergunta 3)
- **Página Calendário** (lista de eventos e "Próximo Evento") mostra **só eventos do tipo CAMPEONATO e jogos de campeonato** (evento JOGO com `tbl_jogos` e `id_campeonato` preenchido): escopo `EventoCalendario::daAgendaPublica()`.
- **Ficam de fora:** amistosos, treinos (criados à mão ou gerados pela grade), eventos JOGO antigos sem `tbl_jogos` e todos os outros tipos (EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO).
- **Continua igual:** cancelado aparece com o selo "Cancelado"; oculto (`INATIVO`) não aparece; o "Próximo Evento" considera só eventos ativos; a **tabela da grade de treinos** continua na página.
- **Sem nenhum evento público**, a página funciona: sem o bloco "Próximo Evento" e com "Nenhum evento agendado no momento." na lista. O filtro "Treinos Especiais" saiu (ficaram Todos, Jogos e Campeonatos).
- **Destaque "Próximo jogo" da home segue a mesma regra da agenda:** só jogo de campeonato (amistoso não aparece), ativo, de hoje em diante; sem jogo futuro de campeonato, o último jogo de campeonato visível; sem nenhum, a seção mostra o rótulo padrão "LIGA PREMIERE". A regra fica num lugar só: `Jogo::daAgendaPublica()` delega ao escopo do evento.
- **O resto da home e a página do campeonato não mudaram:** listam os jogos de cada campeonato (o amistoso não tem campeonato, então já não entrava).
- Pode mudar se o professor pedir.

### Notificações (Fase 8)
- **Tabela própria `tbl_notificacao`** (seção 3), gravada **direto, na mesma transação da ação**, sem fila e sem e-mail. Título e mensagem ficam **congelados no envio**; `id_usuario` = admin que fez a ação; lida = `data_leitura_notificacao` preenchida.
- **Ponto único:** textos e regras ficam no model `Notificacao` (`inscricao`, `remocao`, `alteracao`, `mudancaDeStatus`, `agendaDoMes`, `inscricoesMovidas`); nada de texto nos controllers. Gravação em lote (`gravarParaAtivos`).
- **Quem recebe:** só atleta **ATIVO**. INSCRICAO, REMOCAO, ALTERACAO, CANCELAMENTO e REATIVACAO exigem evento **publicado, ATIVO e não concluído** (`EventoCalendario::avisaAtletas()`; para o status, vale o evento publicado e não concluído). Rascunho não avisa nada (Fase 10, Etapa 4).
- **Tipos:**
  - **INSCRICAO:** dentro do `EventoCalendario::inscrever()`, quando a inscrição é criada (todos os caminhos: evento ou jogo criado, individual, adicionar categoria, atualizar inscritos, preencher pelo elenco, troca de categoria ou de time). Texto: "Nova atividade na sua agenda" / "Treino Sub-15 Masculino · ter, 06/10 · 18:00 às 19:30 · Campo A".
  - **REMOCAO:** `removerInscricao()` e quem sai na sincronização por categoria ou por elenco.
  - **ALTERACAO:** a edição mudou **data, horário ou local** (calendário e jogos). Uma por inscrito, listando só os campos alterados ("Mudanças: data 05/05 → 06/05; local Campo A → Campo B"). Título, tipo, descrição, categoria e placar **não avisam**. Uma por edição (não substitui a não lida).
  - **CANCELAMENTO / REATIVACAO:** no `mudarStatus()`. Sair de ATIVO avisa CANCELAMENTO (cancelar, ou **ocultar** um evento ativo); voltar a ATIVO avisa REATIVACAO (reativar, ou **mostrar** de volta como ativo). Entre cancelado e oculto não avisa.
  - **AGENDA** (sem evento): **geração do mês** = uma por atleta do lote, com a quantidade de treinos dele, em inserção em massa ("Agenda de dezembro disponível" / "Seus 18 treinos de dezembro de 2026 já estão na agenda."); **"Mover inscrições"** do atleta = um resumo só ("Sua categoria agora é …: você saiu de N atividades da … e entrou em M"), sem INSCRICAO/REMOCAO por evento.
- **Sem duplicar:** na edição que muda categoria (ou times) e data juntas, quem entra recebe só INSCRICAO (já com os dados novos), quem sai só REMOCAO, quem fica só ALTERACAO (`salvarEdicaoDoEvento` no trait `ConfirmaConflitos`: histórico → jogo/times → categoria → ALTERACAO, numa transação).
- **Não avisam:** inscrição em evento cancelado, oculto ou concluído; escalação (trocar o time do inscrito); edição sem mudança real.
- **Mensagens do admin:** toda ação que inscreve, remove ou muda o status informa "N atleta(s) notificado(s)." (inclusive 0, para conferir).
- **Tela do evento no admin:** seção **"Notificações (N)"** com quando, tipo, atleta, título e mensagem, quem fez a ação e se foi lida, da mais nova para a mais antiga. Só no admin (dados de menores). As AGENDA não têm evento: ficam para a página geral da Fase 10.
- **Provisórios** (seção 8, pergunta 16): remoção avisa, reativar avisa, ocultar avisa como cancelamento e mostrar como reativação, inscrição em evento cancelado não avisa, escalação não avisa, sem e-mail.

### App: perfis, senhas, agenda e avisos (Fase 9)
- **Dois perfis de login, atleta e responsável**, cada um com o próprio e-mail e senha. O mesmo e-mail pode ser dos dois (o `perfil` do login escolhe a conta). **Todo atleta de 9 a 17 anos com e-mail pode ter login**; atleta sem e-mail entra só pelo perfil do responsável.
- **Cada perfil tem a própria área:** o token do atleta só entra nas rotas do atleta e o do responsável só nas `/responsavel...` (middleware `perfil:atleta|responsavel`, 403 no outro).
- **Atleta:** só ATIVO entra; inativar/rejeitar apaga os tokens (`Atleta::booted`) e a rota confere a cada requisição (`atleta.ativo`).
- **Responsável vê tudo o que o atleta vê, em modo leitura, e escolhe o filho:** dados, agenda e avisos de cada filho, nas mesmas formas; edita só os próprios e-mail, telefone e WhatsApp (nome, CPF, RG e endereço ficam com a secretaria). **Lista de filhos só ATIVO**; sem nenhum filho ATIVO, não entra (403) e perde os tokens (`responsavel.ativo`). No servidor, sempre: o filho precisa ser dele e estar ATIVO, senão 404 (o mesmo 404 para outra família, inativado ou inexistente); o aviso precisa ser daquele atleta.
- **Uma classe só para os dois perfis:** `App\Services\AppDoAtleta` (dados, agenda, avisos, leitura) e `Api\V1\AppDoAtletaController`; as rotas de agenda e avisos são declaradas uma vez (`$rotasDaAgendaEDosAvisos` em `routes/api.php`).
- **Agenda (contrato oficial, `/api/documentacao`):** só eventos com inscrição do atleta, ATIVO ou CANCELADO (oculto fora); `situacao` só `CONFIRMADO`/`CANCELADO` (nunca "Alterado"); `proximos` = não concluídos, 20 por página (`?page=N`, bloco `paginacao`), sem horário no fim do dia; `passados` = os 3 últimos concluídos e não cancelados; no jogo, bloco com campeonato, times, placar e `time_do_atleta` (a escalação). "Concluído" na consulta: `EventoCalendario::concluidos()`/`naoConcluidos()`, a mesma regra de `estaConcluido()`.
- **Leitura dos avisos por pessoa:** o atleta marca em `tbl_notificacao.data_leitura_notificacao`; cada responsável em `tbl_notificacao_leitura`. Um não mexe na leitura do outro, e a contagem de não lidos é de cada um. A tela do evento no admin mostra "N de M responsáveis leram".
- **Links de senha de 24 h**, um broker por perfil (`config/auth.php`, `atletas` e `responsaveis`; trait `App\Models\Concerns\AcessaOApp`), página do site `/senha/{perfil}/{token}?email=`:
  - **convite** "Defina sua senha" na **aprovação da matrícula** (para o atleta, se tiver e-mail, e para o responsável sem senha), pelo **"Reenviar convite"** da tela de Atletas (só atleta ATIVO) e pelo **"Enviar convites pendentes"** (ATIVO e sem senha); falha no envio mostra o link para copiar (só naquele momento: o banco guarda o hash);
  - **"Esqueci minha senha"** (`POST /v1/auth/esqueci-senha`): resposta sempre igual; envia só para quem pode entrar; no máximo um link por minuto por conta;
  - definir a senha apaga o token do link e as sessões do app **daquele perfil**; link vencido, já usado ou com e-mail inexistente mostra a mesma página ("use Esqueci minha senha").
- **Atleta "sem senha"** = `password` NULL (cadastro do admin) **ou** `token_cadastro` preenchido (o site grava senha aleatória e o token); definir a senha pelo link limpa o `token_cadastro` (`Atleta::semSenha()`/`aindaSemSenha()`). Responsável sem senha = `senha_responsavel` NULL.
- **Token de 30 dias** (`Atleta::VALIDADE_TOKEN_DIAS`, vale para os dois perfis). Limites: login 5/min por IP e 5/min por e-mail **e perfil**; esqueci-senha 5/min por IP e 3 a cada 10 min por e-mail e perfil.
- **Responsável único pelo CPF e e-mail único:** cadastro do site e do admin reaproveitam o responsável do mesmo CPF (só dígitos, `Responsavel::porCpf`); o site recusa CPF já cadastrado com outro e-mail; e-mail do responsável único entre responsáveis (normalizado). Os repetidos antigos foram fundidos pela migration 000005 (seção 6).
- **Selo "Sem acesso ao app"** na lista de atletas quando nem o atleta nem algum responsável tem e-mail.

### Grade de treino
- Tem **`id_categoria`** (FK, nullable para itens gerais como "Integrado" e "Treino Livre"), feito na Fase 3. Horários femininos entram como linhas novas.
- **Um dia por linha** (08/10/2026): `dia_semana_grade_treino` ENUM `segunda, terca, quarta, quinta, sexta, sabado, domingo` (rótulos e ordem em `GradeTreino::DIAS_SEMANA`); cada linha gera um evento por semana. Antes eram `segunda_quarta`/`terca_quinta`/`sexta`/`sabado`; a migration `2026_10_08_000001` separou cada linha de dois dias (a original ficou com o primeiro dia e uma cópia com o segundo, levando os eventos gerados pela **data de origem**).
  - **"Novo Horário"** tem uma caixa por dia: cada dia marcado vira uma linha, com os mesmos dados (tudo ou nada). A **edição** muda uma linha só (um dia).
  - **Site:** um cartão por dia com horário ativo, na ordem da semana; a frase "Os treinos de domingo são reservados para repouso." só aparece sem horário ativo no domingo.
- Vira **modelo**: gera **eventos reais por data**, já com os atletas da categoria inscritos.
- **Origem do evento gerado:** linha da grade + data de origem (`id_grade_treino` + `data_grade_evento_calendario`), únicas juntas. A data de origem fica separada da data do evento (o treino pode mudar de dia sem ser gerado de novo). O formulário de evento nunca grava nem troca a origem (fora do `$fillable`).
- **Mapeamento:** título "Treino {rótulo}" (rótulo que já começa com "Treino" fica como está); `LIVRE` vira `TREINO` com subtipo "Livre"; horários, local e categoria vêm direto da linha.
- **Não gera:** tipo `JOGO`, linha inativa, categoria inativa e linha sem horário de início (`GradeTreino::motivoQueNaoGera()`).
- **Linha que já gerou eventos não é excluída**, só inativada.
- **Linhas sem categoria** (Integrado, Treino Livre): inscrevem **todos os atletas ATIVO**, origem `INDIVIDUAL` (provisório, seção 8, pergunta 15). Quem entra depois é adicionado pelo admin.
- **Geração:** botão **"Gerar agenda do mês"** na aba Grade, com **prévia** (datas novas, já geradas, não gera com o motivo, atletas e inscrições). Só o **mês atual e os 2 seguintes** (`GradeTreino::MESES_A_FRENTE`), de hoje em diante; o **treino de hoje já concluído é pulado** (a prévia avisa). Categoria sem atleta ATIVO **gera o evento vazio** (a prévia destaca "0 atletas"). Lote tudo ou nada, com trava nas linhas da grade; gerar de novo não duplica.
- **Conflito na geração:** calculado **em lote** na prévia (`EventoCalendario::conflitosEmLote`), contra os eventos existentes e entre os eventos do próprio lote, **agrupado por par de eventos** (até 5 nomes; o resto em `<details>`). Conflito real: **confirmação única** ("Confirmar mesmo assim e gerar"); sem confirmar, **nada é gravado**. Aviso fraco só informa (na prévia e, depois de gerar, no aviso azul da lista).
- **Mudar ou inativar um horário não altera os eventos já gerados:** a tela só avisa quantos eventos futuros ativos ele tem (editar na lista de eventos, filtro Origem: Grade; cancelar à mão os que não vão acontecer).

### Conflito de horário
O atleta pode estar em mais de um time. Ao **inscrever ou escalar** um atleta num evento que **sobrepõe** outro em que ele já está, o admin recebe um **alerta**.
- Sobreposição: **mesmo dia** e `inícioA < fimB` e `inícioB < fimA`; cancelados e ocultos não contam; só atletas ATIVO.
- **Sem horário de fim:** duração padrão por tipo, numa constante **provisória** (seção 8, pergunta 12): JOGO 2h, TREINO 1h30, AVALIAÇÃO 1h, CAMPEONATO o dia todo, demais 2h.
- **Sem horário de início:** não dá para calcular sobreposição; **aviso fraco** "mesmo dia, horário a definir".
- **Conflito real:** alerta amarelo "Conflito de horário" com **"Confirmar mesmo assim"** (nada é salvo sem confirmar).
- **Só aviso fraco:** **não pede confirmação**; salva direto e mostra um aviso informativo azul "Mesmo dia — confira o horário".
- **Os dois tipos na mesma ação:** pede confirmação e lista os dois, separados por tipo.
- **Onde é verificado:** criar evento com categoria, editar data/horário/categoria, inscrição individual, "Adicionar todos de uma categoria", "Atualizar inscritos pela categoria", Reativar/Mostrar (avisa sem bloquear) e "Mover inscrições" do atleta. **No jogo** (Fase 8): criar (pelo elenco), trocar time (quem ficará inscrito depois da troca, `Jogo::idsInscritosDepoisDaTroca()`), editar data/horário e "Preencher pelo elenco".

### Menu do dashboard (Fase 10; decisões do dono do projeto)
- **Padrão:** item pai só abre e fecha os subitens; cada item abre a tela dele; "Ver todos" abre a mesma tela com todos daquele nível. Nenhum nome de atleta na barra. Dados e item ativo montados no `App\View\Composers\MenuAdminComposer` (registrado no `layout.admin`, 3 consultas fixas).
- **ESPORTE › Eventos:** Calendário; **Campeonatos** (o texto abre a tela de Campeonatos com todos, sem filtro; a seta abre e fecha); **Amistosos**; **Individuais**. Treinos e Outros **saíram do menu** (os ramos continuam no filtro "Ramo" do Calendário e na linha de caminho).
  - **Cada campeonato em andamento** vira um item **"Jogos"** dentro de Campeonatos (com mais de um, "Jogos · Nome"): o texto abre a lista de Jogos filtrada pelo campeonato (`?campeonato=ID`); único subitem: **"Times"** (os do campeonato). Sem campeonato em andamento, Campeonatos fica sem seta. Não há mais "Ver todos", "Ver todos os jogos" nem o próximo jogo no menu (08/10/2026); as telas continuam aceitando `?campeonato=ID` e `?jogo=ID` (linha de caminho e tela de Times).
  - **Amistosos:** o nome abre a lista de Jogos dos amistosos; subitem **"Times"**.
  - **"Times" (campeonato e amistosos):** um bloco por jogo, em duas **abas** com contador, no visual das do Calendário (sem recarregar; abre em "Próximos", ou em "Já realizados" se não houver próximo): próximos e os 10 últimos realizados (`Jogo::separadosParaTelaDeTimes`, partial `admin.partials.blocos-de-jogos`) com o cartão do mandante e do visitante (visual da antiga Escalação); o cartão abre os **jogadores escalados por aquele time naquele jogo**, só leitura (`/admin/jogos/{id}/times/{time}`). No campeonato, no fim, os participantes sem jogo na lista (abrem o elenco, só leitura).
- **ESPORTE:** Jogos; Grade de treino (com "Gerar agenda do mês"); Notificações. **CADASTROS:** Categorias; Times (o **Elenco** de cada time interno, editável, abre pela linha: a antiga "Escalação").
- **Ramos (`EventoCalendario::RAMOS`, `doRamo()`/`ramo()`):** Campeonatos = tipo CAMPEONATO e jogos com campeonato; Amistosos = jogos sem campeonato; Treinos = TREINO; **Individuais = AVALIACAO, REUNIAO e EVENTO sem categoria**; Outros = CONFRATERNIZACAO e esses tipos com categoria.
- **Evento individual** (botão em Individuais): o técnico descreve "o que o atleta vai fazer" e marca os atletas (caixas por categoria, com busca); só eles são inscritos (INDIVIDUAL) e avisados. A notificação de inscrição leva a descrição resumida (120 caracteres, `Notificacao::RECADO_MAX`); o texto inteiro vai na agenda (campo `descricao` da API).

---

## 5. Recomendações em uso (confirmar com o professor quando possível)

- **Geração da grade:** manual, botão "gerar agenda do mês", com chave única (grade + data) para não duplicar. Um dia por linha (um evento por semana).
- **Notificação dos treinos gerados:** uma única por atleta ("agenda do mês disponível").
- **Mudança na grade:** atualizar os eventos futuros já gerados, perguntando a partir de qual data. Evento gerado e **editado à mão** não é sobrescrito; a tela lista os que ficaram de fora.
- **Geração (Fase 7):**
  - Só de hoje em diante, no mês atual ou num mês futuro.
  - Feriado: gerar normalmente e o admin cancela o evento do dia.
  - Evento gerado e depois cancelado ou oculto **não é recriado** (a chave grade + data continua existindo).
  - Lote **tudo ou nada** (uma transação).
  - Treinos (gerados ou à mão) ficam **fora do site público**, na lista e no Próximo Evento (seção 4, "Site público").
  - Inscrição em massa fora do `inscrever()` só na geração; a Fase 8 manda **uma notificação por atleta**.
  - Conflito verificado também **entre os eventos do próprio lote**.
- **Conflito:** verificar entre **todos** os eventos (menos cancelados/inativos); usar horário de fim de cada evento (duração padrão por tipo se vazio); sem margem de deslocamento por enquanto; **alerta com confirmação** (não bloqueia); verificar também quando um evento é alterado.
- **Jogo ↔ evento:** `tbl_jogos.id_evento` (1:1); data, horário e local **só no evento**; `id_campeonato` nullable (amistoso = jogo sem campeonato). ⚠️ `stat-facts.blade.php:8` e a `HomeController` usam `campeonato`/`data_jogo`.
- **Escalação:** `tbl_evento_atleta.id_time` (nullable) + único (evento, atleta). O atleta não pode estar nos dois times do mesmo jogo.
- **Menu:** vai até campeonato/jogo; times e escalação ficam **na tela do jogo**. "Em andamento" = status ATIVO **e** hoje dentro do período. Link "Ver todos" para concluídos. Individual → exame por data → jogadores. Ramos extras: Treinos e Outros. Seção **Cadastros** com Categorias, Times e Grade.
- **Site público:** nunca exibir exames e avaliações (dados de saúde de menores). A agenda do site mostra só campeonatos e jogos de campeonato (seção 4, "Site público").

---

## 6. Estado atual

### Commits na branch `feature/agenda-eventos`
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
- `70cdbf1` docs: Fase 5 concluída
- `af55dce` fix: aprovação de matrícula exige autorização assinada e idade de 9 a 17
- `87d186b` feat: reativar envia o link de assinatura e fotos do site no admin
- `99c0d45` chore: deixa de versionar os dumps de `backup/`
- `ec8d344` chore: `.gitattributes` com fim de linha LF na raiz
- `74d4cf4` feat: jogo vinculado ao evento e amistoso (Fase 6, Etapa 1)
- `63ba16b` feat: site, API e dashboard leem o jogo pelo evento (Fase 6, Etapa 2)
- `ac2f632` feat: escalação do jogo na tela do evento (Fase 6, Etapa 3)
- `60f11bf`, `d87335d` docs: Fase 6 concluída
- `f65d58f` feat: base da geração de eventos pela grade (Fase 7, Etapa 1)
- `69c5bed` fix: atleta ativo sempre com categoria no admin
- `4854967` fix: horário de Brasília no app, no banco e nas datas da API
- `20566ba` docs: andamento da Fase 7
- `c293a55` feat: gerar agenda do mês pela grade, com prévia (Fase 7, Etapa 2)
- `c9929c9` feat: alerta de conflito em lote na geração da agenda (Fase 7, Etapa 3)
- `e0c4c5b` feat: aviso ao mudar ou inativar horário da grade com eventos gerados (Fase 7)
- `00e211e` docs: Fase 7 concluída
- `5b373a4` feat: tabela de notificações do atleta (Fase 8, Etapa 1)
- `8eb7fb1` feat: notificações de inscrição, remoção e agenda (Fase 8, Etapa 2)
- `fcb73b5` feat: notificações de alteração, cancelamento e reativação (Fase 8, Etapa 3)
- `2cd6ee1` feat: jogo inscreve o elenco dos times, com origem ELENCO (Fase 8)
- `bb45924` feat: notificações enviadas na tela do evento (Fase 8)
- `5a22955` feat: agenda e destaque da home do site só com campeonatos
- `75f8358` docs: Fase 8 concluída e regra do site público
- `546ac97` feat: segurança do acesso do app (tokens, status e limite de tentativas) (Fase 9, Etapa A)
- `29052cf` feat: migrations de senha, tokens e leitura, e responsável único (Fase 9)
- `19c7f1f` feat: limpeza de responsáveis repetidos antes do e-mail único (Fase 9)
- `fcd8fcf` feat: login do responsável, perfis no app e links de senha (Fase 9)
- `47939dc` feat: agenda e avisos do app para atleta e responsável (Fase 9)
- `3645f7a` docs: documentação da API do app (Fase 9)
- `ea06166` docs: Fase 9 concluída
- `05bdae0` feat: menu real do admin, filtros pela URL e exclusões bloqueadas (Fase 10, Etapa 1)
- `fb36c69` feat: fluxo de eventos na tela do evento, elenco editável e correções (Fase 10, Etapa 2)
- `2df8ec7` feat: página geral de notificações no admin (Fase 10, Etapa 3)
- `e30907e` feat: notificações filtradas por evento, de qualquer mês (Fase 10, Etapa 3)
- `eb6600d` feat: menu de Eventos com jogos e times por jogo, e evento individual (Fase 10, Etapa 3)
- `b87c2a1` docs: andamento da Fase 10 (menu, etapas 1 a 3)
- `ec67114` feat: grade de treino com um dia por linha, de segunda a domingo
- `5132a25` feat: menu de Campeonatos com Jogos e Times, e abas na tela de Times
- `d7176c6` feat: escalação do jogo lado a lado por time, com logos e dicas da origem

### Fase 1 encerrada
- 1.1 collation, 1.2 tipos sem acento (`5094b36`) e 1.3 exclusão de atleta concluídas.
- Tipos do ENUM: `JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO`; rótulos com acento em `EventoCalendario::TIPOS`.

### Testes
- Rodam no banco **`db_futebol_test`** (MySQL, `utf8mb4_general_ci`, `GRANT ALL` para o `user` do `.env`), configurado no `phpunit.xml`. Precisam do Docker ligado:
  `docker compose exec php php artisan config:clear && docker compose exec php php artisan test`
- O `RefreshDatabase` roda `migrate:fresh`. Testes que usam o banco usam o trait **`Tests\RefreshBancoDeTestes`** (no lugar do `RefreshDatabase`), que aborta se a conexão não for `db_futebol_test`.
- **As migrations montam o banco do zero** (conferido no fim da Fase 9: 63 migrations, 35 tabelas, todas `general_ci`, 35 FKs, o mesmo número de tabelas do `db_futebol`). Isso vale para o primeiro deploy.
- **Banco de testes num computador novo:** `CREATE DATABASE db_futebol_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;` e `GRANT ALL ON db_futebol_test.* TO 'user'@'%';` (pelo `root` do container).
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

### Entre as Fases 5 e 6 (`af55dce`, `87d186b`, `99c0d45`, `ec8d344`)
- **Matrículas** (`af55dce`): aprovar exige autorização assinada e idade de 9 a 17 anos (selo na lista e nos detalhes, botão desabilitado).
- **Reativar** (`87d186b`): gera a autorização pendente/token se faltar e envia o link de assinatura ao responsável (sem e-mail ou com falha no envio, pede para copiar o link); fotos do site aparecem no admin.
- **Repositório:** dumps de `backup/` fora do Git (`99c0d45`); `.gitattributes` com LF na raiz (`ec8d344`).

### Fase 6 concluída — jogos ↔ evento (`74d4cf4`, `63ba16b`, `ac2f632`)
- **Jogo vinculado ao evento** (`74d4cf4`): `tbl_jogos.id_evento` (INT UNSIGNED, único, FK); jogos existentes ganharam um evento cada, sem inscrições; amistoso (sem campeonato); título "Casa x Visitante"; alerta de conflito centralizado em `Admin\Concerns\ConfirmaConflitos`.
- **Leitura pelo evento** (`63ba16b`): site (home, calendário, campeonato), API e dashboard leem data, horário, local e status do evento; `data_jogo` e `status_jogo` saíram de `tbl_jogos`; a API continua devolvendo `data_jogo` (calculado).
- **Escalação** (`ac2f632`): coluna "Time" e "Preencher pelo elenco" na tela do evento do jogo; trocar os times limpa a escalação do time que saiu; aviso para atleta de outra categoria/sexo (seção 8, pergunta 14). Também: aviso de conflito com o tipo do evento e grade do admin com horário sem segundos.
- **Migrations no `db_futebol`:** batches 19 (vincula jogos ao evento) e 20 (remove `data_jogo` e `status_jogo`). Etapa 3 sem migration.
- **Backups** em `backup/`: `db_futebol_antes_fase6_jogos_20261004_092041.sql`, `..._remove_data_jogo_20261004_094426.sql`.
- 238 testes passando; roteiros das Etapas 1, 2 e 3 validados no navegador.

### Fase 7 concluída — grade → eventos (regras na seção 4, "Grade de treino", e na seção 5)
- **Etapa 1 — base** (`f65d58f`): `tbl_evento_calendario.id_grade_treino` (FK `fk_evento_grade`, NO ACTION) e `data_grade_evento_calendario`, único `evento_grade_data_unique`; `GradeTreino::datasNoMes()`, `dadosEventoPara()`, `motivoQueNaoGera()`/`geraEventos()`; exclusão da linha bloqueada quando já gerou eventos. Migration no `db_futebol`: batch 21.
- **Etapa 2 — prévia e gerar** (`c293a55`): `GradeTreino::previaDoMes()`/`gerarMes()` e `EventoCalendario::criarDaGrade()` (junto do `criarPor`, mesma normalização e responsável); inscrição em massa fora do `inscrever()`; página `admin/calendario/gerar.blade.php`; lista de eventos por mês com filtro Origem; site sem os gerados.
- **Etapa 3 — conflito em lote** (`c9929c9`): `EventoCalendario::compararIntervalos()` (regra única, usada também por `conflitosPara()`) e `conflitosEmLote()` (3 consultas, qualquer que seja o lote); partial `admin/calendario/_grupos_conflito.blade.php`.
- **Fechamento** (`e0c4c5b`): aviso ao mudar ou inativar um horário com eventos futuros gerados (`GradeTreino::contarEventosFuturosAtivos()`). A atualização em massa dos eventos ficou como débito (seção 7).
- **Backups** em `backup/`: `db_futebol_antes_fase7_grade_20261004_140325.sql`, `..._gerar_20261004_145943.sql`, `..._conflito_20261004_190552.sql`.
- **Dados de teste no `db_futebol`:** outubro e novembro de 2026 gerados; dezembro livre.
- 310 testes passando; roteiros das Etapas 1, 2 e 3 e do aviso do fechamento validados no navegador.

### Entre as Etapas 1 e 2 da Fase 7 (`69c5bed`, `4854967`)
- **Atleta ativo sempre com categoria** (`69c5bed`): regras na seção 4, "Atletas". Trait `CriaDadosDeAtleta`: `dadosCadastro` manda a Sub-13 M e `dadosEdicao` manda a categoria atual (como o modal).
- **Fuso de Brasília** (`4854967`): seção 1. `FusoHorarioTest` confere o fuso e `now()` = `NOW()` do banco; `ApiDatasTest` confere as datas da API. Datas de verão antigas (antes de 2019) saem com `-02:00`.
- 279 testes passando; roteiros validados no navegador.

### Fase 8 concluída — notificações e jogo pelo elenco (regras na seção 4, "Notificações" e "Jogos")
- **Etapa 1 — tabela** (`5b373a4`): `tbl_notificacao` (seção 3), 3 FKs NO ACTION, índices `(id_atleta, data_notificacao)` para a lista e `(id_atleta, data_leitura_notificacao)` para as não lidas; model `Notificacao` (`TIPOS`, `naoLidas()`, `marcarComoLida()`, `marcarTodasComoLidas()`, `SerializaDatasComFuso`, `data_leitura` fora do `$fillable`); `Atleta::notificacoes()` (nome em português, para não chocar com o `notifications()` do Laravel); `excluirComDependencias()` apaga as notificações. Migration no `db_futebol`: batch 22.
- **Etapa 2 — inscrição, remoção e agenda** (`8eb7fb1`): disparos no `inscrever()`/`removerInscricao()` (com `$notificar`, falso só no "Mover inscrições"), REMOCAO na sincronização (ids lidos antes do delete em massa), AGENDA na geração do mês e no "Mover inscrições"; contador `EventoCalendario::$atletasNotificados` (só na instância) para as mensagens do admin.
- **Etapa 3 — alteração, cancelamento e reativação** (`fcb73b5`): `atualizarComHistorico()` devolve o que mudou; `salvarEdicaoDoEvento()` no trait grava edição, sincronização e ALTERACAO numa transação; `mudarStatus()` avisa CANCELAMENTO/REATIVACAO.
- **Jogo pelo elenco** (`2cd6ee1`): origem `ELENCO` (migration, batch 23); `Jogo::elencoDosTimes()`, `inscreverElenco()`, `sincronizarPeloElenco()`, `idsQueSaemNaTroca()`, `idsInscritosDepoisDaTroca()`, `idsFaltantesDoElenco()`, `timesSemElenco()`; `criarPor(..., inscreverCategoria: false)` nos jogos; jogo fora da sincronização por categoria e do "Mover inscrições" (`futurosAtivosDaCategoria()` sem jogos).
- **Tela do evento:** seção "Notificações" (`EventoCalendario::notificacoes()`).
- **Backups** em `backup/` (só no computador do Senac): `db_futebol_antes_fase8_notificacao_20261005_110512.sql`, `..._disparos_20261005_112126.sql`, `..._alteracao_20261005_113657.sql`, `..._elenco_20261006_083114.sql`.
- **Testes:** `NotificacaoTest` (tabela e model), `NotificacaoDisparoTest` (inscrição, remoção, mover, casos que não avisam, tela do evento), `NotificacaoAlteracaoStatusTest`, `JogoElencoTest`; geração em `GradeGeracaoMesTest`. 357 testes passando no fim da fase.
- **Depois da fase — agenda do site e destaque da home** (06/10): escopo `EventoCalendario::daAgendaPublica()` no lugar de `TIPOS_PUBLICOS` e `foraDaGrade()` (removidos) e `Jogo::daAgendaPublica()` no "Próximo jogo" da home; testes em `JogoSiteTest` e ajustes em `CalendarioTest`, `EventoStatusTest`, `EventoHistoricoTest` e `GradeGeracaoMesTest`. **360 testes passando.**
- **Roteiros no navegador validados:** Etapa 1; Etapa 2 (10 passos; dezembro gerado com 8 atletas notificados); Etapa 3 (13 passos); jogo pelo elenco (9 passos).
- **Dados de teste no `db_futebol`:** outubro a dezembro de 2026 gerados; os jogos dos roteiros (12/12 e 13/12) estão ocultos; o Treino Integrado de 30/10 ficou com a categoria Sub-13 M (teste manual).

### Fase 9 concluída — API do app com dois perfis (regras na seção 4, "App: perfis, senhas, agenda e avisos"; tabelas na seção 3)
- **Etapa A — segurança do acesso** (`546ac97`): tokens apagados ao inativar/rejeitar (`Atleta::booted`), middleware `atleta.ativo`, token de 30 dias (`expires_at`), limitador `login-api`. `ApiAcessoTest`.
- **Migrations e responsável único** (`29052cf`): 000001 `senha_responsavel`, 000002 tabelas de token dos brokers, 000003 `tbl_notificacao_leitura`, 000010 índice único do e-mail (para com a lista se houver repetidos, sem mexer em nada); `Responsavel::porCpf()`, `digitosCpf()`, `normalizarEmail()` e o mutator do e-mail; site e admin reaproveitam o responsável pelo CPF; `NotificacaoLeitura`; `excluirComDependencias()` apaga as leituras. `ResponsavelUnicoTest`.
- **Limpeza dos repetidos** (`19c7f1f`): migration de dados 000005, aprovada por regra geral (sem ids fixos), na ordem 1 → 3 → 2:
  1. mesmo CPF (só dígitos): fica o de menor id; vínculos, autorizações e leituras passam para ele sem repetir o par (na autorização, a assinada vale mais); sem e-mail, herda o do outro; o outro é apagado;
  3. sem atleta e sem autorização: não é apagado, só fica sem e-mail;
  2. e-mail ainda repetido (normalizado): fica no de menor id.
  - Resultado no `db_futebol`: 14 → 12 responsáveis (o 6 fundido no 5 e o 13 no 9); o e-mail que estava repetido ficou só no 5; 7, 8, 10, 11 e 12 sem e-mail. Ensaiada no `db_futebol_test` com o backup antes do migrate.
- **Login, perfis e senhas** (`fcd8fcf`): `Responsavel` autenticável (Sanctum, `senha_responsavel`), trait `AcessaOApp`, `perfil` no login, middlewares `perfil` e `responsavel.ativo`, área `/v1/responsavel`, brokers de 24 h, `DefinirSenhaMail`, página `/senha/...` (`Site\SenhaController`), `POST /v1/auth/esqueci-senha` (limitador `esqueci-senha-api`), convites no admin (`Admin\Concerns\EnviaConvitesDoApp`, partial `admin/atletas/_links_convite`). `AppPerfisTest`, `AppSenhasTest`.
- **Agenda e avisos** (`47939dc`): `App\Services\AppDoAtleta` e `AppDoAtletaController` para os dois perfis; `/v1/agenda`, `/v1/notificacoes` (+ `nao-lidas`, `{id}/lida`, `lidas`), `/v1/responsavel/atletas` e as mesmas rotas sob `/v1/responsavel/atletas/{idAtleta}`; `EventoCalendario::concluidos()`/`naoConcluidos()`; classe de pivô `CategoriaAtleta` (datas do pivô com fuso em `GET /v1/atleta`); coluna "Responsáveis" (N de M leram) na tela do evento. `AppAgendaEAvisosTest`.
- **Documentação:** página `/api/documentacao` com todos os endpoints, exemplos, erros, datas, paginação e token. `DocumentacaoApiTest` confere que toda rota de `api/v1` está na tabela.
- **Migrations no `db_futebol` (computador de casa):** 000001, 000002, 000003, 000005 e 000010 num `migrate` só, **batch 24**. **A limpeza e as migrations do batch 24 rodaram só no computador de casa; o banco do Senac é atualizado restaurando o dump** (não rodar o `migrate` lá sobre o banco antigo).
- **Backup** em `backup/` (só no computador de casa): `db_futebol_antes_fase9_responsaveis_20261006_170409.sql`.
- **Testes:** **423 passando** no fim da fase.
- **Conferências feitas:**
  - ensaio das 5 migrations no `db_futebol_test` a partir do backup (antes e depois da 000005; a 000010 criou o índice sem parar), `--pretend` e o `migrate` com o resultado igual ao ensaio;
  - roteiro do responsável único no navegador (6 passos);
  - login e senhas, no navegador e no terminal: convite, página de senha, login do responsável, separação dos perfis, esqueci-senha e login do atleta sem perfil;
  - agenda e avisos, no terminal e no admin, com a conta de teste do atleta (id 7) e o responsável de teste (id 5). A senha de teste é definida localmente e não fica registrada no repositório.

### Fase 10 em andamento — menu e telas finais com dados reais
- **Etapa 1** (`05bdae0`): barra lateral real (sem a árvore fictícia); filtros das listas de Calendário e Jogos pela URL (ramo, tipo, origem, situação; ocultos fora por padrão, filtro "Oculto"); resources sem páginas create/edit/show que davam 500; exclusão de time, campeonato e categoria em uso bloqueada com mensagem; Escalação virou o **Elenco** (`/admin/times/{id}/elenco`). `MenuAdminTest`.
- **Etapa 2** (`fb36c69`): tela do evento com linha de caminho, ações (editar, placar rápido do jogo, cancelar/reativar, ocultar/mostrar) e "Voltar" pela origem; criar jogo ou evento sem categoria abre a tela dele; o Calendário não cria nem edita evento JOGO (só o JOGO antigo sem `tbl_jogos`); times do jogo de campeonato só entre os participantes; aviso de fora da categoria ao criar o jogo; sugestões de Local e de Subtipo (partial `admin.partials.sugestoes`); elenco editável (adicionar/remover); tipo do campeonato em `Campeonato::TIPOS` (maiúsculas); composers do site só nas views do site (lista do Calendário: 27 → 12 consultas). `EventoFluxoTest`.
- **Etapa 3** (`2df8ec7`, `e30907e`, `eb6600d`): página `/admin/notificacoes` (todas, inclusive AGENDA; filtros de mês, atleta, tipo, leitura e **evento**, este de qualquer mês; 50 por página; "ver todas" da tela do evento abre o filtro do evento) e o menu de Eventos da seção 4 ("Menu do dashboard"), com o evento individual. `NotificacoesPaginaTest`, `MenuAdminTest`.
- **482 testes passando** no fim da Etapa 3.
- **Grade com um dia por linha** (08/10; regras na seção 4, "Grade de treino"): migration `2026_10_08_000001_grade_treino_um_dia_por_linha` (trava de datas de origem antes do ALTER; `down()` junta só pares idênticos), caixas de dias no "Novo Horário", site com um cartão por dia. `GradeUmDiaPorLinhaMigrationTest` roda **sem a transação** do `RefreshDatabase` (o ALTER confirmaria a transação) e marca o banco para ser recriado no fim de cada teste.
  - **Migration no `db_futebol` (computador do Senac): batch 25.** 11 → 17 linhas; os 166 eventos gerados ficaram na linha do dia de origem. Ensaiada antes no `db_futebol_test` com o backup. **O banco de casa é atualizado restaurando o dump do Senac** (não rodar o `migrate` lá sobre o banco antigo: os ids das linhas novas poderiam ser outros).
  - **Backup** em `backup/` (só no computador do Senac): `db_futebol_antes_grade_um_dia_20261008_085656.sql`.
  - **493 testes passando.**
- **Ajustes de 08/10 no Senac** (`ec67114`, `5132a25`, `d7176c6`; regras na seção 4): grade com um dia por linha; menu de Campeonatos (Campeonatos abre todos; um "Jogos" por campeonato em andamento, filtrado por ele, com "Times"; sem "Ver todos" nem próximo jogo no menu; composer sem a consulta do próximo jogo); abas "Próximos"/"Já realizados" na tela de Times; escalação do jogo **lado a lado** (bloco por time com a logo, "Sem time" embaixo em amarelo, formulários de inscrição embaixo; partials `_inscritos_do_bloco`, `_time_da_inscricao`, `_remover_inscricao`); "Fora do elenco" pelo time escalado; dica de cada origem ao passar o mouse (`EventoAtleta::DICAS_ORIGEM`, `origem_dica`). **498 testes passando.**
- **Dump para o computador de casa:** `backup/db_futebol_para_casa_20261008.sql` (feito no Senac, depois do batch 25). Em casa: restaurar este dump (não rodar o `migrate` da grade lá) e `php artisan view:clear`.

- **Em casa, 08/10:** dump do Senac restaurado (chegou como `backup/db_futebol_para_casa.sql`); o banco anterior de casa ficou em `backup/db_futebol_casa_antes_restaurar_20261008.sql`.

#### Em andamento: Etapa 4 — jogo em rascunho e publicação (plano APROVADO pelo dono do projeto em 08/10)
- **Etapa A concluída (computador de casa):** migration `2026_10_08_000002_add_data_publicacao_to_tbl_evento_calendario` no `db_futebol`, **batch 26** (199 eventos, todos publicados; ensaiada antes no `db_futebol_test` com o backup). `EventoCalendario::estaPublicado()`, `scopePublicados()`, `publicar()` (trava a linha, grava a data e o histórico "Publicação: Rascunho → Publicado em …"; já publicado devolve false) e `booted()`; `avisaAtletas()` e `Notificacao::mudancaDeStatus()` respeitam o rascunho. Ainda não há caminho no admin que crie rascunho (Etapa B). `EventoPublicacaoTest`, `EventoPublicacaoMigrationTest` (sem transação). **512 testes passando.**
  - **Backup** em `backup/` (só no computador de casa): `db_futebol_antes_publicacao_20261008_135956.sql`.
  - **O banco do Senac é atualizado restaurando um dump de casa** (ou rodando só esta migration lá, que não depende de ids).
Ideia: montar o jogo inteiro (inscritos, escalação) sem avisar ninguém e, no fim, **"Publicar e avisar os atletas"**: cada um recebe um aviso só, já com tudo certo. Decisões: **só para jogos** (Calendário, evento individual e grade continuam avisando na hora); **rascunho escondido no app e no site** até publicar; **a notificação cita o time** ("… Você joga pelo Time Azul.", resolve a parte do time da pergunta 17).
- **Etapa A (migration, com backup e ensaio; feita, ver acima):** `tbl_evento_calendario.data_publicacao_evento_calendario` DATETIME NULL (NULL = rascunho); a migration preenche todos os eventos existentes (ficam publicados); fora do `$fillable`. Model: `estaPublicado()`, escopo `publicados()`; `avisaAtletas()` exige publicado (cobre inscrição, remoção e alteração); `Notificacao::mudancaDeStatus()` também passa a respeitar o rascunho (hoje não usa o `avisaAtletas()`); publicação registrada no histórico do evento.
- **Etapa B:** criar jogo (tela de Jogos) nasce rascunho (elenco inscrito e escalado, ninguém avisado; mensagem "Rascunho: os atletas serão avisados ao publicar"); os outros caminhos de criação nascem publicados. Botão **"Publicar e avisar os atletas"** (com confirmação) na tela do jogo: INSCRICAO para cada inscrito ativo, com o time na mensagem (sem time: sem a frase). Rascunho concluído, cancelado ou oculto pode ser publicado sem avisar ninguém. Sem "despublicar" (usar cancelar/ocultar). Faixa amarela na tela do jogo; selo "Rascunho" na lista de Jogos.
- **Etapa C:** app (`AppDoAtleta::eventosInscritos`) só publicados (atleta e responsável); `Jogo::visiveis()` e `EventoCalendario::daAgendaPublica()` exigem publicado (home, página do campeonato, API de campeonatos, agenda do site) e a classificação não conta rascunho. **Conflito de horário continua contando o rascunho.**
- **Testes:** rascunho não avisa (inscrição, remoção, troca de time, alteração, cancelamento); publicar avisa uma vez cada um, com o time; rascunho fora do app, site, API e classificação; publicar jogo passado não avisa; eventos que não são jogo avisam na hora. Atualizar as seções 3, 4 e 6.
- **Falta depois (próximas etapas):** dashboard com dados reais (com contador de jogos em rascunho); app no outro repositório mostrar a `descricao` da agenda.

---

## 7. Débitos técnicos conhecidos (planejar, não implementar sem OK)

- **CPF único:** hoje não há índice único em `cpf_atleta`. O índice `cpf_atleta_UNIQUE` **existia** no dump de estrutura gerado pelo dono do projeto por volta de 24/09 (esse dump não ficou salvo em `backup/`); os dumps de `backup/` e a migration de criação não o têm, então ele se perdeu em algum momento. A **edição de atleta no admin** não valida CPF único (`AtletasController.php:149`). Duplicado de teste: atletas 7 e 10 (`000.000.000-00`). Plano: limpar duplicados, normalizar para só dígitos, validar CPF no cadastro (site e admin), recriar o índice.
- **Assinaturas:** gravadas em `public/` (acessíveis por URL), nome previsível, caminho salvo no **responsável** (sobrescreve quando ele tem dois atletas), sem validar se é PNG, e o arquivo não é apagado na exclusão. Plano: coluna `tbl_autorizacoes.arquivo_assinatura`, `Storage::disk('local')` com UUID, rota protegida no admin, comando para migrar os arquivos, apagar após o commit da transação.
- **Responsável e endereço** ficam no banco após excluir o atleta (o responsável pode ter outros atletas).
- **Virada do ano:** com a idade pelo ano de nascimento, metade dos atletas muda de categoria todo 1º de janeiro (quem fica com idade par sai de Sub-11/13/15). Plano futuro: tela/relatório para o admin com a lista de atletas cuja categoria esperada mudou; a troca continua **manual** (fechar a linha antiga de `tbl_categoria_atleta` e abrir uma nova).
- **Camisa repetida na lista de atletas:** um atleta em dois times mostra "Camisa Nº 15" duas vezes, sem dizer de qual time é cada número.
- **`novalidate` no formulário de cadastro de atleta do admin** (`atletas/modals/create.blade.php`): o `required` do HTML não atua ali; a validação é só do servidor. Os outros formulários do admin não usam `novalidate`.
- **Manutenção da agenda gerada** (tratar depois da Fase 8):
  - (a) Mudança na grade atualizar os eventos futuros gerados (a partir de uma data), **sem sobrescrever o que foi editado à mão**; hoje a tela só avisa.
  - (b) Atleta aprovado no meio do mês **não entra** nos eventos já gerados (hoje: "Atualizar inscritos pela categoria" evento por evento).
  - (c) Quem entra depois nas linhas sem categoria (Integrado, Treino Livre) só é adicionado **categoria por categoria** ("Adicionar todos de uma categoria").
- **Notificações (Fase 8):**
  - **Atleta que sai do elenco:** só a marca "Fora do elenco" na tela do jogo. Falta um aviso no cadastro do atleta ("está em N jogos futuros do Time X", com botão, como o "Mover inscrições").
  - **Aviso de fora da categoria ao criar o jogo:** o elenco pode ser de outra categoria, mas o aviso só aparece na tela do jogo (a lista de jogos não mostra `avisos_categoria`).
  - **Página geral de notificações no admin** (com as AGENDA, que não têm evento): Fase 10.
  - **Push e e-mail das notificações:** não existem (o aviso fica só no app). Quando vierem, precisam de fila e de um worker (`queue:work`) no Plesk; hoje não há worker nem agendador (ver "App e e-mail").
  - **Limpeza das notificações antigas** (comando agendado): definir o prazo.
  - **Jogos anteriores à regra do elenco** (ex.: jogo de 06/10) ficaram com inscritos pela categoria; o "Mover inscrições" não mexe neles.
- **App e e-mail (Fase 9):**
  - **SMTP de verdade e worker de fila:** hoje `MAIL_MAILER=log` e o envio é síncrono (convites, links de senha, assinatura). Em produção: configurar o SMTP no Plesk, mandar os e-mails para a fila e manter um worker (`queue:work`) rodando.
  - **Tempo de resposta do esqueci-senha com e-mail real:** a resposta é sempre igual, mas demora mais quando o e-mail existe (o envio é síncrono), o que deixa adivinhar quem está cadastrado. Resolve junto com a fila (ou com um tempo mínimo de resposta).
  - **Notificações push:** não existem; o aviso só aparece quando o app consulta `/v1/notificacoes`.
  - **Limpeza de tokens vencidos (agendador):** tokens do Sanctum vencidos (`sanctum:prune-expired`) e links de senha vencidos (`auth:clear-resets`) ficam no banco; falta o agendador (`schedule:run` no cron do Plesk).
  - **Endereços que sobraram da fusão de responsáveis:** os endereços dos responsáveis 6 e 13 (ids 7 e 11 em `tbl_endereco`) ficaram sem dono (ligado ao débito "Responsável e endereço").
  - **App no outro repositório:** adaptar ao contrato de `/api/documentacao`: login com escolha de perfil, escolha do filho (responsável), agenda (hoje com dados fixos) e avisos com a contagem de não lidos, "Esqueci minha senha".
  - **Mensagens de validação da API em inglês** (422 padrão do Laravel; o app deve mostrar a própria mensagem). Tradução (`lang/pt_BR`) fica para quando o app for usado.
---

## 8. Perguntas em aberto para o professor

1. Níveis de usuário: quais valores e o que cada um pode fazer? **Provisório:** `nivel_usuario` = `ADMIN` / `EDITOR` / `LEITURA` e `User::CARGOS` = Professor, Nutricionista, Fisiologista, Médico, Coordenador. Valores de nível e cargo aguardam o professor.
2. ~~Sub-9, Sub-11, Sub-13 e Sub-17 existem?~~ ✅ **RESOLVIDA** (professor, 03/10/2026): a escolinha atende de 9 a 17 anos e as categorias da grade **devem existir**. **Não criar categorias sem OK** (plano da Fase 3).
   - **Faixas:** Sub-9 (9), Sub-11 (10–11), Sub-13 (12–13), Sub-15 (14–15), Sub-17 (16–17).
   - **Idade pelo ANO de nascimento:** idade = ano atual − ano de nascimento (a data exata não importa).
   - **Masculino e feminino com as mesmas 5 faixas:** total de **10 categorias** (cada faixa em `M` e `F`).
   - **Todos os dados do banco local são de teste**; nada precisa ser preservado (inclui a Sub-12, 10–12, e a Sub-15, 13–15, atuais).
3. ~~Calendário do site público: mostra tudo, só jogos/campeonatos, ou nada? E os treinos gerados pela grade?~~ ✅ **RESOLVIDA** (dono do projeto, 06/10/2026): a página Calendário mostra **só eventos CAMPEONATO e jogos de campeonato**; amistosos, treinos e os outros tipos ficam de fora; cancelado com o selo, oculto não aparece; a tabela da grade continua (seção 4, "Site público"). **Pode mudar se o professor pedir.**
4. A linha "Jogos" (tipo JOGO) da grade continua, já que jogos viram eventos?
5. Atleta com cartões: pode ser excluído (apagando histórico) ou só inativado?
6. Responsável e endereço de atleta excluído: apagar quando não houver outro atleta vinculado, ou anonimizar?
7. ~~Link de definição de senha: e-mail do atleta ou do responsável?~~ ✅ **RESOLVIDA e implementada na Fase 9**: o app tem **dois perfis de login, atleta e responsável**, cada um com o próprio e-mail e senha; o link vai para o e-mail que **cada um** informou no cadastro (seção 4, "App: perfis, senhas, agenda e avisos").
8. Assinaturas antigas com valor `assinatura.png` (responsáveis 1, 2 e 3): considerar inválidas?
9. ~~O feminino treina junto com o masculino da mesma faixa?~~ ✅ **RESOLVIDA** (professor): feminino treina **só com feminino**. A grade usa **uma coluna** `id_categoria` (sem tabela de ligação); horários femininos entram como **linhas novas** da grade.
10. ~~Atleta pode jogar numa categoria acima da idade?~~ ✅ **RESOLVIDA** (professor): **pode, a critério do técnico** (atleta mais robusto). Escolher categoria **acima** da sugerida gera **aviso** (não bloqueia) e exige um **motivo**, gravado em `observacao_categoria_atleta`.
11. Atleta numa categoria **abaixo** da idade: hoje **bloqueado** (provisório). Confirmar com o professor se há exceção.
12. **Duração padrão dos eventos sem horário de fim** (usada no alerta de conflito), hoje provisória: JOGO 2h, TREINO 1h30, AVALIAÇÃO 1h, CAMPEONATO o dia todo, demais 2h. Confirmar os valores com o professor.
13. **Critério de desempate da classificação** (site: home e página do campeonato), hoje provisório em `Jogo::classificacao()`: pontos (vitória 3, empate 1), vitórias, saldo de gols, gols marcados e, por fim, nome do time (sem acentos e sem maiúsculas). Confirmar com o professor (confronto direto? cartões?).
14. **Escalar ou inscrever num jogo atleta de outra categoria ou sexo** (ex.: Sub-15 Feminino num jogo Sub-11 Masculino): hoje só **avisa** (`EventoCalendario::avisosForaDaCategoria`), não bloqueia. Deve ser bloqueado? Há exceção (atleta acima da idade, como na pergunta 10)?
15. **Quem participa das linhas da grade sem categoria:** o **Integrado** inclui o feminino? O **Treino Livre** vale para todos mesmo em dia de jogo? **Provisório:** todos os atletas ATIVO são inscritos (seção 4, "Grade de treino").
16. **Notificações** (seção 4, "Notificações"). Hoje, provisório:
    - **remoção** da inscrição avisa o atleta;
    - **reativar** um evento cancelado avisa;
    - **ocultar** um evento ativo e futuro avisa como **cancelamento**, e **mostrar** de volta como ativo avisa como **reativação**;
    - inscrição em evento **já cancelado** não avisa;
    - **escalação** (por qual time joga) não avisa;
    - **sem e-mail**: a notificação fica só no app.
17. **Jogo pelo elenco** (seção 4, "Jogos"). Hoje, provisório:
    - o **elenco ativo inteiro** é inscrito (titulares e reservas, sem **convocação** por jogo; `tbl_atleta_time` já tem `status_atleta_time` e `convocacao_atleta_time`);
    - o atleta pode estar em **dois times da escolinha no mesmo campeonato** (entra sem time e o admin escolhe);
    - a notificação do jogo **não cita o time** do atleta.
18. **Consentimento (LGPD) para mostrar ao responsável os dados do menor no app** (dados, agenda, avisos, inclusive exames e avaliações): a autorização assinada na matrícula cobre isso, ou é preciso um termo próprio? E quando o atleta faz 18 anos (ou um responsável deixa de ser responsável), o acesso dele termina? **Hoje:** todo responsável vinculado a um filho ATIVO vê tudo do filho.
19. **Família sem e-mail** (nem o atleta nem o responsável têm e-mail; selo "Sem acesso ao app"): a secretaria cadastra um e-mail depois, o app fica sem uso para essa família, ou há outro meio (WhatsApp, código impresso)? **Hoje:** fica sem acesso até alguém informar um e-mail no cadastro.

---

## 9. Comandos úteis

```bash
# na pasta ~/dev/senac/Futebol (WSL)
docker compose exec php php artisan migrate:status
docker compose exec php php artisan migrate --pretend
docker compose exec php php artisan tinker --execute="echo ...;"
docker compose exec php php -l app/Models/Atleta.php

# login pela API (campo da senha é "senha"; sem "perfil", vale atleta)
curl -X POST http://localhost:8080/api/v1/auth/login -H "Accept: application/json" \
  -d "email=EMAIL&senha=SENHA&perfil=responsavel"   # ⚠️ troque EMAIL e SENHA; perfil atleta ou responsavel

# agenda com o token devolvido no login
curl http://localhost:8080/api/v1/agenda -H "Accept: application/json" -H "Authorization: Bearer TOKEN"   # ⚠️ troque TOKEN
```

Contrato completo da API: `http://localhost:8080/api/documentacao`.

Testes destrutivos no tinker: envolver em `DB::beginTransaction()` ... `DB::rollBack()`.
