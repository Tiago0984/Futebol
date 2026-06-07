# Projeto: Escolinha de Futebol (Laravel)

## Stack
- Laravel 13.6.0 / PHP 8.3
- Docker Compose (serviços: mysql, php, nginx)
- Bootstrap 3 (site público) + AdminLTE (área admin)
- Banco: MySQL — banco `db_futebol`

## Rodar comandos Laravel
```bash
docker compose exec php php artisan <comando>
```

## Estrutura de autenticação (2 guards)
- **admin** → model `User` → tabela `tbl_usuarios`
  - campos: `email_usuario`, `senha_usuario`, `remember_token_usuario`
- **aluno** → model `Atleta` → tabela `tbl_atletas`
  - campos: `email_atleta`, `password`, `remember_token`, `token_cadastro`

## Tabelas principais
- `tbl_atletas` — atletas (status: PENDENTE / ATIVO / INATIVO)
- `tbl_responsavel` — responsáveis dos atletas
- `tbl_endereco` — endereços (atleta e responsável)
- `tbl_atleta_responsavel` — pivot com `grau_parentesco_responsavel`
- `tbl_autorizacoes` — autorização de matrícula com `token_assinatura`, `status_autorizacao`
- `tbl_usuarios` — usuários admin
- `tbl_campeonato`, `tbl_time`, `tbl_jogo`, `tbl_categoria`, `tbl_inscricao`
- `tbl_banner`, `tbl_galeria`, `tbl_noticias`

## Rotas importantes
- `/` → site público
- `/cadastro` → formulário público de matrícula do atleta
- `/assinar/{token}` → página mobile de assinatura do responsável
- `/admin/login` → login admin
- `/admin` → dashboard (protegido por `auth:admin`)

## Fluxo de cadastro público
1. Responsável preenche formulário em `/cadastro`
2. Sistema salva atleta com `status_atleta = PENDENTE`
3. Gera `token_assinatura` e envia link via WhatsApp
4. Responsável assina digitalmente em `/assinar/{token}` (mobile)
5. Admin aprova na área admin

## Controllers relevantes
- `App\Http\Controllers\Site\CadastroController` — cadastro público
- `App\Http\Controllers\Site\AssinaturaController` — assinatura digital
- `App\Http\Controllers\Admin\LoginController` — login admin
- `App\Http\Controllers\Admin\AtletasController` — CRUD de atletas

## Arquivos de configuração alterados
- `src/config/auth.php` — guards admin e aluno
- `src/bootstrap/app.php` — redirect de guests para `admin.login`
- `src/routes/web.php` — todas as rotas

## Models com Authenticatable
- `App\Models\User` — `getAuthPassword()` retorna `senha_usuario`
- `App\Models\Atleta` — auth via `email_atleta` + `password`

## Branch de trabalho
- Branch ativa: **v2**
- `git pull origin v2` para sincronizar
