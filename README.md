# Smart Garment Defect Detection — Monitoring Backend

Laravel 12 application and REST API that stores and visualises fabric inspection
results from ESP32-CAM inspection units and resource forecasts produced by
external machine-learning services. It is the monitoring and reporting tier: it
does **not** run defect-detection inference itself.

- **Web UI** — Blade + Tailwind CSS + Alpine.js + Chart.js
- **API** — versioned JSON API authenticated with Laravel Sanctum tokens
- **Database** — MySQL 8 / MariaDB 10.4+, Eloquent models and migrations
- **Storage** — private local disk or any S3-compatible bucket
- **Runtime** — PHP 8.2+ locally, PHP 8.3 in the production container
- **Delivery** — Docker image (Nginx + PHP-FPM + Supervisor), GitHub Actions CI,
  ECS/Fargate deployment template

> **New here?** Start with [`docs/README.md`](docs/README.md) — the human handbook. It explains what
> the system does, gets you running in about 15 minutes, and links to one page per topic. AI coding
> agents should start with [`AGENTS.md`](AGENTS.md) and the specification in
> [`ai-docs/`](ai-docs/01-project-overview.md).

## Features

| Area | Capability |
| --- | --- |
| Authentication | Session login, no public registration, hardened rate-limited sign-in |
| Roles | Administrator, Operator, and hidden SERVICE accounts for machines |
| Dashboard | Database-driven KPIs, pass/defect trends, device status, active forecast warnings |
| Devices | Register, edit, activate/deactivate, inspect recent results and online state |
| Inspections | Search/filter history, multi-detection detail view, bounding boxes, private images |
| Ingestion API | UUID idempotency, zero or many detections, optional image, strict contract validation |
| Forecasts | Cloud and network ingestion, active/superseded history, metric charts, risk warnings |
| Operations | Request IDs, JSON error envelope, rate limits, health endpoint, service-token management |
| Delivery | Docker image, Compose stack with MySQL and optional MinIO, CI, ECS task template |

## How the pieces fit

```
ESP32-CAM units ──► inference service ──┐
                                        │  POST /api/v1/inspections/results
                                        ▼
 Cloud forecaster ──────────────────► Laravel API ──► MySQL
 Network forecaster ────────────────►   (Sanctum)  └─► local disk / S3
                                        │
                                        ▼
                              Blade dashboard + JSON read API
```

The backend never guesses a missing result. A PASS must contain zero detections,
a DEFECT must contain at least one, and `defect_count` must equal the number of
detections. Forecast values are displayed only when they were submitted.

## Quick start (local)

Prerequisites: PHP 8.2+ with `pdo_mysql`, Composer 2, Node 20+, MySQL 8 or
MariaDB 10.4+.

```bash
cp .env.example .env
composer install
php artisan key:generate

# Create the database first, for example:
#   mysql -u root -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4"
php artisan migrate --seed

npm install
npm run build

php artisan serve
```

Open <http://localhost:8000>. With the default `.env.example` values the seeded
accounts are:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@garment.test` | `password` |
| Operator | `operator@garment.test` | `password` |

> These defaults exist for local development only. Change `ADMIN_PASSWORD` and
> `OPERATOR_PASSWORD` before seeding any shared database. In production a
> missing administrator password aborts seeding instead of falling back.

Issue a token for each machine client:

```bash
php artisan service:token "Inference Service" \
  --ability=inspections:write --ability=read

php artisan service:token "Cloud Forecaster" \
  --ability=forecasts:cloud:write --ability=read

php artisan service:token "Network Forecaster" \
  --ability=forecasts:network:write --ability=read
```

The plain token is printed once; only a hash is stored. See
[`docs/local-setup.md`](docs/local-setup.md) for the full walkthrough.

## Docker quick start

```bash
cp .env.docker.example .env.docker
# Replace every change-me value in .env.docker.
docker compose --env-file .env.docker up --build
```

Open <http://localhost:8080>. The stack starts the application and MySQL,
runs migrations, seeds accounts, and warms Laravel's caches. After the first
successful start, set `RUN_SEED=false` in `.env.docker` so restarts do not reset
the seeded account passwords. An optional MinIO overlay provides S3-compatible
storage:

```bash
docker compose --env-file .env.docker \
  -f docker-compose.yml -f docker-compose.s3.yml up --build
```

See [`docs/deployment.md`](docs/deployment.md) for image details, ECS/Fargate,
RDS, S3, Secrets Manager, and migration-task procedures.

## Tests and quality checks

```bash
php artisan test           # Pest feature + unit suite (MySQL)
vendor/bin/pint --test     # Laravel Pint style check
npm run build              # Vite production build
php artisan view:cache     # Compile every Blade template
```

The test suite uses a separate MySQL database (`garment_defect_test`) configured
in `phpunit.xml`.

## Where to look

This repository carries two documentation sets. They are deliberately separate:

- **[`docs/`](docs/README.md)** is the **human handbook**. Narrative, walkthroughs, diagrams,
  checkpoints, and troubleshooting. Start here.
- **[`ai-docs/`](ai-docs/01-project-overview.md)** is the **specification**. Exact classes, columns,
  invariants, and configuration. It is the canonical reference for implementation detail.

When the two disagree, the code wins; update both when behaviour changes.

### I want to…

| Task | Read this first |
| --- | --- |
| Understand what the system does | [`docs/README.md`](docs/README.md) → [`docs/architecture.md`](docs/architecture.md) |
| Run it locally | [`docs/local-setup.md`](docs/local-setup.md) |
| Change backend code | [`docs/architecture.md`](docs/architecture.md) → [`docs/database.md`](docs/database.md) → [`docs/api.md`](docs/api.md) |
| Build an inference or forecasting producer | [`docs/integration-contracts.md`](docs/integration-contracts.md) → [`docs/api.md`](docs/api.md) |
| Configure the environment | [`docs/environment.md`](docs/environment.md) |
| Ship it | [`docs/deployment.md`](docs/deployment.md) → [`docs/aws-oidc.md`](docs/aws-oidc.md) |
| Fix an error | [`docs/troubleshooting.md`](docs/troubleshooting.md) |
| Have an AI agent work on it | [`AGENTS.md`](AGENTS.md) → [`ai-docs/17-ai-agent-guide.md`](ai-docs/17-ai-agent-guide.md) |

### Human handbook (`docs/`)

| Page | What it answers |
| --- | --- |
| [`docs/README.md`](docs/README.md) | Orientation, glossary, reading paths, five-minute overview |
| [`docs/architecture.md`](docs/architecture.md) | Components, request lifecycle, ingestion and forecast flows, non-goals |
| [`docs/local-setup.md`](docs/local-setup.md) | Install, configure, migrate, seed, run, verify, reset |
| [`docs/database.md`](docs/database.md) | Tables, relationships, integrity rules, reporting queries |
| [`docs/api.md`](docs/api.md) | Authentication, envelopes, every endpoint, error codes |
| [`docs/integration-contracts.md`](docs/integration-contracts.md) | Producer obligations, retry policy, recipes, acceptance checklist |
| [`docs/environment.md`](docs/environment.md) | Which variables matter, defaults, secrets, config caching |
| [`docs/deployment.md`](docs/deployment.md) | Image, Compose, ECS/Fargate, migrations, rollback, operations |
| [`docs/aws-oidc.md`](docs/aws-oidc.md) | GitHub Actions → AWS role, trust policy, repository settings |
| [`docs/troubleshooting.md`](docs/troubleshooting.md) | Symptom → cause → fix index across every area |

### Specification (`ai-docs/`)

These are the canonical, implementation-verified documents organised as a 01–17 set.

| Document | Contents |
| --- | --- |
| [`ai-docs/01-project-overview.md`](ai-docs/01-project-overview.md) | Purpose, users, features, implementation status |
| [`ai-docs/02-technology-stack.md`](ai-docs/02-technology-stack.md) | Versions, PHP/npm dependencies, runtime tooling |
| [`ai-docs/03-system-architecture.md`](ai-docs/03-system-architecture.md) | Diagrams, layers, request lifecycle, data flow |
| [`ai-docs/04-project-structure.md`](ai-docs/04-project-structure.md) | Directory map and extension points |
| [`ai-docs/05-database-design.md`](ai-docs/05-database-design.md) | ERD, tables, indexes, constraints, enumerations |
| [`ai-docs/06-backend-architecture.md`](ai-docs/06-backend-architecture.md) | Controllers, services, DTOs, resources, middleware |
| [`ai-docs/07-api-documentation.md`](ai-docs/07-api-documentation.md) | Endpoints, envelopes, errors, rate limits, examples |
| [`ai-docs/08-frontend-documentation.md`](ai-docs/08-frontend-documentation.md) | Blade, Tailwind, Alpine, Chart.js, page inventory |
| [`ai-docs/09-business-logic-and-workflows.md`](ai-docs/09-business-logic-and-workflows.md) | Ingestion, forecasts, dashboard, role rules |
| [`ai-docs/10-authentication-and-authorization.md`](ai-docs/10-authentication-and-authorization.md) | Session auth, tokens, abilities, policies |
| [`ai-docs/11-file-storage-and-media.md`](ai-docs/11-file-storage-and-media.md) | Private images, local/S3 disks, signed URLs |
| [`ai-docs/12-configuration-and-environment.md`](ai-docs/12-configuration-and-environment.md) | Every environment variable and secret rule |
| [`ai-docs/13-testing-and-quality.md`](ai-docs/13-testing-and-quality.md) | Pest suite, helpers, coverage, CI checks |
| [`ai-docs/14-deployment-and-infrastructure.md`](ai-docs/14-deployment-and-infrastructure.md) | Docker, Compose, CI/CD, ECS/Fargate |
| [`ai-docs/15-development-guide.md`](ai-docs/15-development-guide.md) | Setup, common tasks, debugging |
| [`ai-docs/16-known-limitations-and-technical-debt.md`](ai-docs/16-known-limitations-and-technical-debt.md) | Gaps, unused code, risks |
| [`ai-docs/17-ai-agent-guide.md`](ai-docs/17-ai-agent-guide.md) | Task routing, invariants, verification checklist |

## Security notes

- No public registration route; accounts are created by an administrator.
- Role checks are enforced by middleware and policies, not only hidden in the UI.
- Machine clients use Sanctum tokens with least-privilege abilities.
- Inspection images are private and served through expiring signed URLs or an
  authenticated UI route.
- Duplicate inspection and forecast IDs are rejected with HTTP 409 rather than
  overwriting stored evidence.
- Secrets are injected at runtime. No credential is committed to the repository
  or baked into the container image.
