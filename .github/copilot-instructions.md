# Copilot Instructions for Futebol

- This repository contains a Laravel application nested in `src/`. Treat `src/` as the working application root; the repository root only houses Docker compose and deployment scaffolding.
- Active page routes are defined in `src/routes/web.php` and are all GET pages. Controllers in `src/app/Http/Controllers/` are intentionally thin and usually just return a Blade view.
- The current app is server-rendered with Blade templates under `src/resources/views/`. Main layout logic lives in `src/resources/views/layout/site.blade.php` and `src/resources/views/partials/`.
- Page markup is composed from partials. For example, `src/resources/views/site/home/home.blade.php` includes section fragments like `banner`, `stat-facts`, and `gallery`.
- Frontend assets are still loaded from the legacy static theme under `src/public/futebol/` and `src/public/coderatech/`. The Blade head and script partials explicitly reference CSS/JS from `asset('futebol/...')`.
- Do not assume Vite is fully wired into the current page templates. `src/resources/css/app.css` and `src/resources/js/app.js` exist, but the layout currently uses legacy `public/futebol` assets.
- Key commands from the app root (`src/`):
  - `composer setup` to install dependencies, copy `.env.example`, generate key, migrate, install npm packages, and build assets.
  - `composer test` or `php artisan test` to run the test suite.
  - `npm run dev` / `npm run build` for frontend assets if working on Vite/Tailwind.
- Docker-based development is available from the repository root:
  - `docker-compose up -d`
  - App served at `http://localhost:8080`.
  - `src/` is mounted into containers at `/var/www/html`.
- Database note: `.env.example` defaults to `sqlite`, but the Docker stack includes MySQL. If using Docker, set `DB_CONNECTION=mysql` and configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` accordingly.
- The project currently has no API route definitions in `routes/web.php`; most work should stay within route/view/controller/view composition.
- When changing layout or page structure, update the `src/resources/views/partials/` files and the corresponding page templates in `src/resources/views/site/`.
- Preserve the existing legacy `public/futebol` asset references unless the task explicitly migrates the frontend to Vite/Tailwind.
- Keep changes within `src/` unless the task explicitly targets Docker config or repository-level tooling.

If any section is unclear or if you need more detail about the Blade partials, the route conventions, or the legacy asset integration, ask for feedback before proceeding.