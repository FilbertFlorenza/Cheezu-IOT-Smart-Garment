# 04 — Project Structure

This document maps the directories an agent is likely to touch. It deliberately omits vendor code and
generated assets.

## Top level

```text
.
├── app/                     Application code (PSR-4 App\)
├── bootstrap/               Framework bootstrap and cached files
├── config/                  Configuration files
├── database/                Migrations, factories, seeders, SQLite artifact
├── docker/                  Dockerfile support files and ECS template
├── docs/                    Original deep-dive documentation (8 files)
├── ai-docs/                 This AI-oriented documentation set
├── public/                  Web root (index.php, built assets)
├── resources/               Blade views, CSS, JS
├── routes/                  web.php, api.php, auth.php, console.php
├── storage/                 Logs, framework caches, private uploads
├── tests/                   Pest feature and unit tests
├── .github/workflows/       CI and deploy pipelines
├── AGENTS.md                Rules for AI coding agents
├── docker-compose.yml       App + MySQL stack
├── docker-compose.s3.yml    MinIO overlay
├── composer.json/.lock      PHP dependencies
├── package.json/.lock       JS dependencies
├── phpunit.xml              Test environment
├── vite.config.js           Asset entrypoints
└── README.md                Project overview and doc map
```

## `app/`

```text
app/
├── Console/Commands/IssueServiceToken.php
├── Data/                    DetectionData, ForecastPayload, InspectionPayload, PredictionData
├── Enums/                   DeviceStatus, ForecastSource, ForecastStatus, InspectionStatus, RiskLevel, Role
├── Exceptions/ApiException.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          Device, Forecast, ForecastIngestion, Health, Inspection, InspectionResult
│   │   ├── Auth/            Breeze controllers (mostly unrouted — see 16)
│   │   ├── Web/             Dashboard, Device, Forecast, Inspection, Settings, User
│   │   ├── Controller.php   Base class with AuthorizesRequests, ValidatesRequests
│   │   └── ProfileController.php
│   ├── Middleware/          AssignRequestId, EnsureRole, EnsureUserIsActive
│   ├── Requests/
│   │   ├── Api/V1/          StoreInspectionResultRequest, StoreForecastRequest (+ cloud/network)
│   │   ├── Auth/LoginRequest.php
│   │   ├── Web/             Store/Update Device and User requests
│   │   └── ProfileUpdateRequest.php
│   └── Resources/           Device, Forecast, ForecastPrediction, Inspection, InspectionDetection
├── Models/                  User, Device, Inspection, InspectionDetection, Forecast, ForecastPrediction
├── Policies/                Device, Forecast, Inspection, User
├── Providers/AppServiceProvider.php
├── Services/                DashboardMetrics, ForecastIngestion, ForecastPresentation, ImageStorage, InspectionIngestion
├── Support/                 ApiResponse.php, helpers.php
└── View/Components/         AppLayout, GuestLayout
```

### How to extend `app/`

| Need | Put it in | Follow |
| --- | --- | --- |
| New API endpoint | `Http/Controllers/Api/V1/`, route in `routes/api.php` | Thin controller + service + resource (see 07) |
| New web page | `Http/Controllers/Web/`, route in `routes/web.php`, view in `resources/views/` | `$this->authorize()` first, paginated queries |
| New inbound payload | `Http/Requests/Api/V1/` + `Data/` DTO | Validation in FormRequest, invariants re-checked in service |
| New multi-step persistence | `Services/` | `DB::transaction`, translate duplicate keys, log outcome |
| New vocabulary | `Enums/` | Backed string enum, `label()`/`color()` helpers if displayed |
| New authorisation rule | `Policies/` and/or middleware | Policies for model actions, middleware for route groups |
| New response shape | `Http/Resources/` | Never return raw models from API controllers |
| Reusable display logic | `Support/helpers.php` | Guard with `function_exists()` |

**Do not** put business logic in controllers, models, or Blade views. Models hold relationships,
casts, scopes, and trivial derived values; services hold workflows.

## `routes/`

| File | Contents |
| --- | --- |
| `web.php` | Root redirect, authenticated UI routes, admin group, `require auth.php` |
| `api.php` | `v1` prefix; health outside auth; ingestion and read groups behind Sanctum |
| `auth.php` | Guest login routes; authenticated password update and logout |
| `console.php` | Framework `inspire` command only |

Route names are the contract used by views (`route('inspections.show', $inspection)`). Renaming a
route requires updating every Blade reference.

## `config/`

| File | Project-specific content |
| --- | --- |
| `inspection.php` | Online threshold, image disk/size/dimension, backfill and future tolerance, defect class catalogue, display timezone |
| `forecast.php` | Metric catalogue per source (label/unit/colour), max predictions, max horizon, risk levels |
| `filesystems.php` | `local` (private), `public`, `s3` disks |
| `auth.php` | `web` guard, Eloquent provider, password broker (broker unused) |
| Others | Laravel defaults (app, cache, database, logging, mail, queue, services, session) |

## `database/`

```text
database/
├── factories/          Device, Forecast, ForecastPrediction, Inspection, InspectionDetection, User
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_11_16_044032_create_personal_access_tokens_table.php
│   └── 2026_11_16_050000_create_garment_monitoring_tables.php
├── seeders/
│   ├── DatabaseSeeder.php     Accounts + conditional demo data
│   └── DemoDataSeeder.php     8 devices, 30 days of inspections, 4 forecasts
└── database.sqlite            Leftover default artifact (unused)
```

Add schema changes as **new** migrations. Never edit an applied migration in a shared environment.

## `resources/`

```text
resources/
├── css/app.css              Tailwind entrypoint + component classes
├── js/app.js                Alpine + Chart.js globals + renderChart()
├── js/bootstrap.js          Axios setup
└── views/
    ├── layouts/             app.blade.php, guest.blade.php, error.blade.php, navigation (unused)
    ├── components/          Sidebar, page header, stat card, badges, form controls, charts, etc.
    ├── auth/                login (used) + Breeze views (unrouted)
    ├── dashboard.blade.php
    ├── devices/             index, create, edit, show
    ├── inspections/         index, show
    ├── forecasts/           index, show
    ├── users/               index, create, edit
    ├── settings/index.blade.php
    ├── profile/edit.blade.php
    ├── errors/              403, 404, 419, 429, 500
    └── welcome.blade.php    Unused default page
```

Component conventions:

- `x-app-layout` wraps every authenticated page; `x-guest-layout` wraps login.
- Page-level components: `x-page-header`, `x-stat-card`, `x-status-badge`, `x-empty-state`,
  `x-flash-message`, `x-forecast-charts`.
- Forms: `.panel`, `.form-input`, `.form-label` classes rather than a form builder.
- Charts: inline `@push('scripts')` blocks calling `window.renderChart(id, config)`.

## `tests/`

```text
tests/
├── Pest.php                 RefreshDatabase for Feature, staffUser/serviceToken/bearer helpers
├── TestCase.php             Framework base case
├── Feature/
│   ├── Api/                 ForecastIngestionTest, InspectionIngestionTest, ReadApiTest
│   ├── Auth/                AuthenticationTest, PasswordUpdateTest
│   ├── Web/                 Dashboard, Device, Forecast, Inspection, Settings, User
│   ├── ExampleTest.php
│   └── ProfileTest.php
└── Unit/
    ├── InspectionPayloadTest.php
    └── ExampleTest.php
```

Feature tests hit real routes and a **MySQL** test database (`garment_defect_test`) configured in
`phpunit.xml`; there is no SQLite in-memory fallback.

## `docker/` and deployment files

| File | Purpose |
| --- | --- |
| `docker/Dockerfile` | 3-stage build: assets, vendor, PHP 8.3 runtime |
| `docker/entrypoint.sh` | Directory prep, optional DB wait/migrate/seed, cache warm, exec |
| `docker/nginx.conf` | Nginx site config |
| `docker/php.ini` | PHP overrides |
| `docker/supervisord.conf` | Nginx + PHP-FPM supervision |
| `docker/ecs-task-definition.json` | Fargate task template with Secrets Manager references |
| `docker-compose.yml` | App + MySQL 8.4 |
| `docker-compose.s3.yml` | MinIO + bucket init overlay |
| `.github/workflows/ci.yml` | Tests, Pint, Blade compile, image build + smoke test |
| `.github/workflows/deploy.yml` | OIDC → ECR → ECS task registration → migrate → deploy |

## `docs/` vs `ai-docs/`

| Folder | Audience | Status |
| --- | --- | --- |
| `ai-docs/` | AI coding agents and new developers | Canonical, structured 01–17 |
| `docs/` | Original human deep dives | Preserved unchanged; still accurate for API examples, integration contracts, environment tables, and AWS OIDC setup |

Where `docs/` contains more exhaustive material (for example request/response examples in
`docs/api.md` or AWS role policy in `docs/aws-oidc.md`), the numbered docs link to it instead of
duplicating it.
