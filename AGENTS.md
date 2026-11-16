# AGENTS.md

Instructions for AI coding agents working on this repository. Read this before changing code, then
open the relevant document in [`ai-docs/`](ai-docs/).

## Project

Laravel 12 backend for **Smart Garment Defect Detection**. It stores inspection results produced by
ESP32-CAM units (via an external inference service) and resource forecasts from cloud/network
services, then presents them through a Blade dashboard and a Sanctum-authenticated `/api/v1` JSON API.
It does **not** run inference.

- PHP `^8.2` (local 8.2.12, image 8.3) — Laravel 12.72.0 — Sanctum 4.4.0
- MySQL 8.4 / MariaDB 10.4+ — Blade + Tailwind 3 + Alpine 3 + Chart.js 4
- Pest 3 tests against MySQL `garment_defect_test`; Pint for style
- Docker (Nginx + PHP-FPM + Supervisor); GitHub Actions CI and ECS deploy templates

## Mandatory rules

1. **Inspect before changing.** Read the route, controller, request, service, model, view, and tests
   that already implement the closest behaviour. Run `php artisan route:list --except-vendor`.
2. **Reuse existing patterns.** Services, FormRequests, DTOs, API resources, enums, policies,
   `ApiResponse`, `ApiException`, and Blade components already exist; extend them instead of adding
   parallel abstractions.
3. **No unnecessary dependencies.** Prefer the framework and what is already installed. Justify any
   new Composer/npm package.
4. **No duplicated business logic.** Ingestion invariants, duplicate handling, supersede semantics,
   and dashboard aggregation belong in their existing services.
5. **Schema changes are migrations plus consumers.** Add a new migration; update models, factories,
   requests, resources, filters, views, and tests. Never edit an applied migration.
6. **API contracts are public.** Do not rename/remove fields, codes, or endpoints without updating
   [ai-docs/07-api-documentation.md](ai-docs/07-api-documentation.md), `docs/api.md`, and
   `docs/integration-contracts.md`, and adding tests.
7. **No hardcoded secrets or environment values.** Use `config()` and environment variables; never
   commit `.env`, `.env.docker`, tokens, keys, or passwords.
8. **Do not assume a feature exists because a file exists.** Registration, password reset, email
   verification, forecast expiry, device deletion, queue jobs, and AWS infrastructure are **not**
   implemented. See [ai-docs/16-known-limitations-and-technical-debt.md](ai-docs/16-known-limitations-and-technical-debt.md).
9. **Run the checks.** `php artisan test`, `vendor/bin/pint --test`, `npm run build`,
   `php artisan view:cache`.
10. **Update documentation** when architecture, schema, API contracts, configuration, or workflows
    change.

## Where things live

| Need | Location |
| --- | --- |
| Routes | `routes/web.php`, `routes/api.php`, `routes/auth.php` |
| Middleware aliases, API exception rendering | `bootstrap/app.php` |
| Web controllers | `app/Http/Controllers/Web/` (+ `ProfileController`, `Auth/`) |
| API controllers | `app/Http/Controllers/Api/V1/` |
| Validation | `app/Http/Requests/` |
| DTOs | `app/Data/` |
| Domain logic | `app/Services/` |
| Models, casts, scopes | `app/Models/` |
| API serialisation | `app/Http/Resources/` |
| Enums and labels | `app/Enums/` |
| Policies | `app/Policies/` |
| Envelope, error codes | `app/Support/ApiResponse.php`, `app/Exceptions/ApiException.php` |
| Schema | `database/migrations/` |
| Seeders | `database/seeders/` |
| Views/components | `resources/views/` |
| Frontend entry | `resources/js/app.js`, `resources/css/app.css` |
| Tests | `tests/Feature/`, `tests/Unit/` |
| Deployment | `docker/`, `docker-compose*.yml`, `.github/workflows/` |

## Conventions

**PHP**

- PSR-12, Pint, typed signatures, strict comparisons.
- Controllers are thin: validate → service → resource/view.
- Writes use FormRequests; read filters may use inline `$request->validate()`.
- `final readonly class` DTOs with `fromArray()`, when modelling inbound payloads.
- Backed string enums with `label()`/`color()` helpers when displayed.
- Multi-write operations use `DB::transaction`; translate duplicate-key errors to `ApiException`.
- Log meaningful outcomes (`inspection.ingested`, `forecast.ingested`); never log secrets.

**Database**

- Eloquent with explicit `$fillable` and `casts()`.
- Route key: `Inspection` uses `inspection_id` (UUID); others use numeric IDs.
- Timestamps stored in UTC; display via `display_datetime()`/`display_date()`.
- Add indexes for new filter/join columns and justify them.

**API**

- Always return `ApiResponse::success()`, `paginated()`, or `error()`.
- Error codes are stable: `VALIDATION_ERROR`, `CONTRACT_MISMATCH`, `UNKNOWN_DEVICE`,
  `DUPLICATE_INSPECTION`, `DUPLICATE_FORECAST`, `UNAUTHENTICATED`, `FORBIDDEN`, `ACCOUNT_INACTIVE`,
  `NOT_FOUND`, `RATE_LIMITED`, `SERVER_ERROR`.
- Protect routes with `auth:sanctum`, `active`, `abilities:<ability>`, and a throttle.
- Serialise timestamps with `toIso8601String()`; never expose internal paths.

**Frontend**

- `x-app-layout` + `x-page-header`; reuse `.panel`, `.data-table`, `.form-input`, `.form-label`,
  `x-stat-card`, `x-status-badge`, `x-empty-state`.
- Tailwind utilities; no new CSS framework or component library.
- Alpine for local interactivity only; Chart.js via `window.renderChart`.
- Escape with `{{ }}`; authorise server-side, not by hiding links.

**Auth**

- Roles: `ADMINISTRATOR`, `OPERATOR`, `SERVICE` (`App\Enums\Role`).
- Web: session guard, `active` middleware, policies, `role:ADMINISTRATOR` for admin areas.
- API: Sanctum tokens with the four documented abilities; service accounts cannot log in.
- Hidden UI is not security: add a policy/middleware check.

## Critical invariants

- `inspection_id` unique → duplicate returns `409 DUPLICATE_INSPECTION`.
- `defect_count == count(detections)`; PASS = 0 detections, DEFECT ≥ 1.
- `(source_type, external_id)` unique → `409 DUPLICATE_FORECAST`.
- New forecast supersedes prior `ACTIVE` forecasts of the **same source only**.
- `(forecast_id, metric_name, predicted_at)` unique.
- Images are private; local disk is served through an authorised route, S3 via 10-minute URLs.
- At least one active administrator must remain.

## Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
mysql -u root -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4"
mysql -u root -e "CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4"
php artisan migrate --seed
npm run build
php artisan serve

php artisan test
vendor/bin/pint --test
php artisan view:cache
php artisan route:list --except-vendor
```

Service tokens:

```bash
php artisan service:token "Inference Service" --ability=inspections:write --ability=read
php artisan service:token "Cloud Forecaster" --ability=forecasts:cloud:write --ability=read
php artisan service:token "Network Forecaster" --ability=forecasts:network:write --ability=read
```

## Documentation map

| Document | Contents |
| --- | --- |
| [01 Project overview](ai-docs/01-project-overview.md) | Purpose, users, features, status |
| [02 Technology stack](ai-docs/02-technology-stack.md) | Versions and dependencies |
| [03 System architecture](ai-docs/03-system-architecture.md) | Diagrams, layers, request lifecycle |
| [04 Project structure](ai-docs/04-project-structure.md) | Directory map and extension points |
| [05 Database design](ai-docs/05-database-design.md) | Schema, ERD, indexes, invariants |
| [06 Backend architecture](ai-docs/06-backend-architecture.md) | Controllers, services, DTOs, resources |
| [07 API documentation](ai-docs/07-api-documentation.md) | Endpoints, envelopes, errors, examples |
| [08 Frontend documentation](ai-docs/08-frontend-documentation.md) | Blade, Tailwind, Alpine, Chart.js |
| [09 Business logic](ai-docs/09-business-logic-and-workflows.md) | Ingestion, forecasts, dashboard, roles |
| [10 Auth and authorisation](ai-docs/10-authentication-and-authorization.md) | Guards, tokens, policies |
| [11 Storage and media](ai-docs/11-file-storage-and-media.md) | Disks, images, signed URLs |
| [12 Configuration](ai-docs/12-configuration-and-environment.md) | Every env variable, secrets |
| [13 Testing](ai-docs/13-testing-and-quality.md) | Test suite, helpers, CI checks |
| [14 Deployment](ai-docs/14-deployment-and-infrastructure.md) | Docker, Compose, CI/CD, ECS |
| [15 Development guide](ai-docs/15-development-guide.md) | Setup, tasks, debugging |
| [16 Limitations and debt](ai-docs/16-known-limitations-and-technical-debt.md) | Gaps and unused code |
| [17 AI agent guide](ai-docs/17-ai-agent-guide.md) | Task routing and checklists |

`docs/` contains the original human-oriented deep dives (`api.md`, `architecture.md`,
`integration-contracts.md`, `database.md`, `environment.md`, `local-setup.md`, `deployment.md`,
`aws-oidc.md`). It is preserved unchanged; prefer it for exhaustive examples and AWS provisioning
detail.

## Final reminder

If something cannot be verified, say so and mark it `Unknown`, `Not Implemented`, or
`Needs Verification`. Never document planned work as complete, and never invent endpoints, tables, or
features.
