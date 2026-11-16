# 15 — Development Guide

## Prerequisites

| Tool | Requirement |
| --- | --- |
| PHP | 8.2+ with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `bcmath` |
| Composer | 2.x |
| Node.js | 20+ (22 used in CI) |
| npm | 10+ |
| MySQL | 8.x or MariaDB 10.4+ |
| Optional | Docker Desktop for the Compose stack |

Local versions observed in this workspace: PHP 8.2.12, Node 26.13.1, npm 11.20.0, MariaDB 10.4.32.

## First-time setup

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Databases (development and test)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 4. Schema + accounts (+ demo data when APP_ENV=local)
php artisan migrate --seed

# 5. Assets
npm run build

# 6. Serve
php artisan serve
```

Open `http://localhost:8000`.

Seeded accounts (local defaults from `.env.example`; replace via `ADMIN_PASSWORD` /
`OPERATOR_PASSWORD` before seeding anything shared):

| Role | Email |
| --- | --- |
| Administrator | `admin@garment.test` |
| Operator | `operator@garment.test` |

The password is the local demo value defined in `.env.example`. Do not reuse it outside a local
database.

`APP_ENV=local` also runs `DemoDataSeeder`, which creates 8 devices, ~30 days of inspections, and
cloud/network forecasts. It skips if any inspection already exists.

## Running the app

| Command | Purpose |
| --- | --- |
| `php artisan serve` | PHP development server (default :8000) |
| `npm run dev` | Vite dev server with HMR |
| `composer dev` | Concurrent server + queue listener + Pail logs + Vite |
| `php artisan pail` | Tail application logs |

`composer dev` starts a queue listener even though no jobs are dispatched; it is harmless.

## Issuing service tokens

```bash
php artisan service:token "Inference Service" \
  --ability=inspections:write --ability=read

php artisan service:token "Cloud Forecaster" \
  --ability=forecasts:cloud:write --ability=read

php artisan service:token "Network Forecaster" \
  --ability=forecasts:network:write --ability=read
```

- The plain-text token is printed once; copy it into the producer's configuration.
- Re-running without `--keep-existing` revokes previous tokens for that account.
- Verify the account exists: `php artisan tinker` then `App\Models\User::where('role','SERVICE')->get()`.

## Testing

```bash
php artisan test
php artisan test --compact
php artisan test tests/Feature/Api/InspectionIngestionTest.php
vendor/bin/pint --test
```

The test database must already exist and be reachable at the credentials in `phpunit.xml`.

## Frontend workflow

- Edit Blade in `resources/views/`.
- Tailwind utility classes require the view to be within the `content` paths in `tailwind.config.js`.
- Component classes (`.panel`, `.form-input`, `.data-table`, `.chart-frame`) are in
  `resources/css/app.css`.
- Run `npm run dev` while editing; run `npm run build` before verifying production output.
- Charts: push a `<script>` that calls the global `renderChart('elementId', config)` inside
  `@push('scripts')`.

## Common tasks

### Add a web page

1. Add a named route in `routes/web.php` inside the `auth`+`active` group.
2. Add a controller action under `app/Http/Controllers/Web/`; call `$this->authorize(...)`.
3. Create `resources/views/<area>/<page>.blade.php` using `x-app-layout` and `x-page-header`.
4. Add a feature test covering access control and rendering.

### Add an API endpoint

1. Add the route under `api/v1` with `auth:sanctum`, `active`, the correct `abilities:*`, and a
   throttle.
2. Add a FormRequest for inbound data (rules + `after()` invariants).
3. Add a DTO in `app/Data/` if the payload is non-trivial.
4. Put persistence in a service; wrap multi-write operations in `DB::transaction`.
5. Return through `ApiResponse` and a `JsonResource`.
6. Document it in [07-api-documentation.md](07-api-documentation.md) and add tests.

### Add a model/migration

```bash
php artisan make:migration create_widgets_table
php artisan make:model Widget -f
```

Then add `$fillable`, `casts()`, relationships, factory states, and tests. Do not edit an applied
migration.

### Add an enum value

Update the PHP enum, the migration column if the database enum is constrained, any `match` expressions,
display helpers, and tests. Check `config/forecast.php` and Blade badges.

### Add a policy

Create `app/Policies/<Model>Policy.php` (auto-discovered) and call `$this->authorize(...)` in the
controller. Add tests for each role.

### Add a rate limiter

Define it in `AppServiceProvider::boot()` and apply `throttle:<name>` to the route.

## Debugging

| Symptom | Checks |
| --- | --- |
| `could not find driver` | Enable `pdo_mysql` in `php.ini` |
| `Vite manifest not found` | `npm run build` (or run `npm run dev`) |
| Blank charts | Browser console; ensure `window.renderChart` exists and the canvas ID matches |
| 419 on POST | Missing `@csrf` or expired session |
| 403 on admin page | Role is not `ADMINISTRATOR` or account inactive |
| 401 on API | Missing/invalid token, or token belongs to an inactive user |
| 403 on API | Token missing a required ability |
| 409 on ingestion | Duplicate UUID — treat as accepted, do not retry with a new ID |
| 422 `UNKNOWN_DEVICE` | Register the device first |
| Images 404 | Check `INSPECTION_IMAGE_DISK`, file existence, and `storage/app/private` permissions |
| `php artisan test` cannot connect | Create `garment_defect_test` and check `phpunit.xml` credentials |

Useful commands:

```bash
php artisan route:list --except-vendor
php artisan about
php artisan config:clear
php artisan view:clear
php artisan tinker
```

## Docker workflow

```bash
cp .env.docker.example .env.docker
# replace every change-me value
docker compose --env-file .env.docker up --build
```

App: `http://localhost:8080`. Set `RUN_SEED=false` after the first successful start.

S3-compatible storage:

```bash
docker compose --env-file .env.docker \
  -f docker-compose.yml -f docker-compose.s3.yml up --build
```

## Code style

- Run `vendor/bin/pint` before committing.
- Follow PSR-4/PSR-12; one class per file; typed signatures.
- Keep controllers thin; business rules belong in services/requests/DTOs.
- Use `ApiResponse`/`ApiException` for API output.
- Use `display_datetime()`/`display_date()` in Blade.
- Avoid new dependencies unless clearly necessary.

## Git and repository state

- This working copy is **not** a Git repository. `git init` is required before using branches,
  commits, or the GitHub workflows.
- `.gitignore` already excludes `vendor`, `node_modules`, `.env`, storage artifacts, and build output
  as per Laravel defaults.
- Never commit `.env`, `.env.docker`, tokens, or credentials.

## Related documents

- Environment reference: [12-configuration-and-environment.md](12-configuration-and-environment.md)
- Testing details: [13-testing-and-quality.md](13-testing-and-quality.md)
- Agent-specific rules: [17-ai-agent-guide.md](17-ai-agent-guide.md)
- Original setup walkthrough: [`../docs/local-setup.md`](../docs/local-setup.md)
