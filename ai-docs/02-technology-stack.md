# 02 — Technology Stack

All versions below were read from `composer.lock`, `package.json`, `phpunit.xml`, the Dockerfile, and
the local runtime. Where local and container versions differ, both are stated.

## Runtime and platform

| Component | Version | Evidence |
| --- | --- | --- |
| PHP (local development) | 8.2.12 (ZTS, Windows build) | `php -v` |
| PHP (production image) | 8.3 (`php:8.3-fpm-alpine`) | `docker/Dockerfile` |
| PHP constraint | `^8.2` | `composer.json` |
| Laravel | 12.72.0 | `composer.lock` (`laravel/framework`) |
| Composer | 2.x (local), `composer:2` image stage | `docker/Dockerfile` |
| Node (local) | 26.13.1 | `node -v` |
| npm (local) | 11.20.0 | `npm -v` |
| Node (build stages and CI) | 22 (`node:22-alpine`) | `docker/Dockerfile`, `.github/workflows/ci.yml` |
| Database (local) | MariaDB 10.4.32 | `SELECT VERSION()` |
| Database (container/CI) | MySQL 8.4 (`mysql:8.4`) | `docker-compose.yml`, CI |

### Required PHP extensions

`composer.json` does not declare extension requirements beyond PHP itself (only `laravel/framework`
imports them transitively). The application and image rely on:

- `pdo_mysql` — database driver (tests and runtime).
- `mbstring`, `ctype`, `json`, `openssl`, `tokenizer`, `xml`/`libxml` — framework baseline.
- `bcmath` — installed in the image.
- `gd` — installed in CI; **not** installed in the runtime image, and no image-processing code exists.
- `fileinfo` — MIME detection for uploaded images.
- `pcntl` — installed in the image for Supervisor/PHP-FPM operation.
- `intl`, `zip` — installed in CI; `icu-libs`/`libzip` are present in the image.

Local `php -m` confirms `bcmath ctype fileinfo gd json libxml mbstring openssl pdo_mysql SimpleXML
tokenizer xml xmlreader xmlwriter`.

## Composer dependencies

### Production (`require`)

| Package | Locked version | Purpose and usage |
| --- | --- | --- |
| `laravel/framework` | v12.72.0 | Framework: routing, Eloquent, validation, Blade, filesystem, rate limiting, exception handling |
| `laravel/sanctum` | v4.4.0 | API token issuance and ability checks (`HasApiTokens`, `CheckAbilities`) |
| `laravel/tinker` | v2.11.1 | Interactive REPL via `php artisan tinker` |

### Development (`require-dev`)

| Package | Locked version | Purpose |
| --- | --- | --- |
| `pestphp/pest` | v3.8.7 | Test runner (`php artisan test`) |
| `pestphp/pest-plugin-laravel` | v3.2.0 | Laravel integration for Pest |
| `laravel/breeze` | v2.4.2 | Scaffolding source for auth controllers/views (partly unused — see 16) |
| `laravel/pint` | v1.30.4 | Code style (`vendor/bin/pint`) |
| `laravel/pail` | ^1.2.2 | Log tailing in `composer dev` |
| `laravel/sail` | ^1.41 | Optional local Docker dev environment (not configured/used) |
| `fakerphp/faker` | v1.24.1 | Factories |
| `mockery/mockery` | 1.6.15 | Test doubles |
| `nunomaduro/collision` | v8.9.6 | CLI error rendering |

## Frontend dependencies (`package.json`)

### Runtime

| Package | Declared | Purpose |
| --- | --- | --- |
| `chart.js` | `^4.6.1` | Dashboard and forecast charts, loaded via `chart.js/auto` |
| `alpinejs` | `^3.4.2` | Sidebar, dropdown, and modal interactivity |

Both are exposed globally in `resources/js/app.js` (`window.Alpine`, `window.Chart`,
`window.renderChart`).

### Development

| Package | Declared | Purpose |
| --- | --- | --- |
| `vite` | `^7.0.7` | Asset bundler |
| `laravel-vite-plugin` | `^2.0.0` | Laravel entrypoint/manifest integration |
| `tailwindcss` | `^3.1.0` | Utility CSS |
| `@tailwindcss/forms` | `^0.5.2` | Form control resets |
| `@tailwindcss/vite` | `^4.0.0` | Installed but **not** used by `vite.config.js` (see 16) |
| `postcss` | `^8.4.31` | CSS pipeline |
| `autoprefixer` | `^10.4.2` | Vendor prefixes |
| `axios` | `^1.11.0` | HTTP client set up in `resources/js/bootstrap.js`; not used by any Blade page |
| `concurrently` | `^9.0.1` | Runs the `composer dev` process group |

## CSS and frontend architecture

- **Tailwind CSS 3** via PostCSS (`postcss.config.js` uses `tailwindcss` + `autoprefixer`).
- `tailwind.config.js` scans `vendor/laravel/framework/.../Pagination`, `storage/framework/views`,
  `resources/views`, `resources/js`, and the Inter font family is configured.
- `resources/css/app.css` defines `@layer base` (x-cloak, antialiasing) and `@layer components`
  (`.chart-frame`, `.data-table`, `.panel`, `.form-input`, `.form-label`).
- No CSS framework beyond Tailwind, no component library, no TypeScript, no SPA router.

## Authentication and API packages

- **Session authentication** — Laravel's `web` guard (`config/auth.php`), database sessions.
- **API tokens** — Sanctum personal access tokens with abilities:
  `inspections:write`, `forecasts:cloud:write`, `forecasts:network:write`, `read`.
- **Ability middleware** — aliases `abilities` (`CheckAbilities`) and `ability`
  (`CheckForAnyAbility`) registered in `bootstrap/app.php`.
- No OAuth server, no JWT package, no Fortify, no Jetstream.

## Storage, cache, and queue

| Concern | Default | Notes |
| --- | --- | --- |
| Filesystem default | `local` (`storage/app/private`) | `FILESYSTEM_DISK` |
| Inspection image disk | `INSPECTION_IMAGE_DISK` → `FILESYSTEM_DISK` → `local` | `config/inspection.php` |
| Public disk | `public` (`storage/app/public`) | Declared, not used for inspection images |
| S3 disk | `s3` driver | Configured via `AWS_*` variables; optional |
| Cache store | `database` in `.env.example`; `file` in the image; `array` in tests | `config/cache.php` |
| Session driver | `database` in `.env.example`; `file` in the image | `sessions` table exists |
| Queue connection | `database` in `.env.example`; `sync` in the image | **No jobs are dispatched** |
| Broadcasting | `log` | No broadcast events |

## Development and deployment tooling

| Tool | Role |
| --- | --- |
| Vite 7 | `npm run dev`, `npm run build` |
| Pint | Style check/fix (`vendor/bin/pint --test`) |
| Pest 3 | Test suite, MySQL-backed |
| GitHub Actions | `.github/workflows/ci.yml`, `.github/workflows/deploy.yml` |
| Docker multi-stage | Node assets → Composer vendor → PHP-FPM/Nginx/Supervisor runtime |
| Docker Compose | App + MySQL, optional MinIO overlay |
| Supervisor | Runs Nginx + PHP-FPM inside the container |
| AWS ECS/Fargate template | `docker/ecs-task-definition.json` |

## Version caveats

1. **Local MariaDB 10.4 vs container MySQL 8.4.** The schema uses only portable constructs (enums,
   JSON, unsigned integers, `restrictOnDelete`, `cascadeOnDelete`), but behaviour differences between
   MariaDB and MySQL should be considered when changing SQL. CI runs MySQL 8.4.
2. **Local PHP 8.2 vs image PHP 8.3.** `composer.json` allows `^8.2`. Avoid 8.3-only syntax if local
   parity matters.
3. **Tailwind 3 with a Tailwind 4 Vite plugin installed.** The plugin is not referenced in
   `vite.config.js`; Tailwind runs through PostCSS. See [16](16-known-limitations-and-technical-debt.md).
4. **Node 26 local vs Node 22 in CI/image.** `package-lock.json` is used with `npm ci` in CI.
5. Versions are pinned by `composer.lock` and `package-lock.json`; treat those files as authoritative
   over the caret ranges in the manifests.
