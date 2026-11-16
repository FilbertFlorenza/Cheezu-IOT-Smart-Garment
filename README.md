# Smart Garment Defect Detection — Monitoring Backend

[![CI](https://github.com/FilbertFlorenza/cheezu-iot-smart-garment-web/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/FilbertFlorenza/cheezu-iot-smart-garment-web/actions/workflows/ci.yml)

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

> **New here?** Start with [`docs/README.md`](docs/README.md) — It explains what
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
# Replace every change-me value, then set APP_KEY:
#   php artisan key:generate --show   ->  APP_KEY=base64:...
docker compose --env-file .env.docker up --build
```

`APP_KEY` is required: the image has no `.env` file, so the container cannot generate one.

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

CI (`.github/workflows/ci.yml`) runs the same checks on every push to `master`
and every pull request, then builds the production image and boots it against
MySQL. The badge at the top of this file links to the latest runs.

## Documentation

This project has two sets of documentation, each with a different purpose.

- **[`docs/`](docs/README.md)** contains the documentation for developers. It covers how the system works, how to set it up, how to use its APIs, and how to maintain or extend the application.
- **[`ai-docs/`](ai-docs/01-project-overview.md)** contains more detailed technical references, including classes, database structures, configurations, and implementation details.

If something in the documentation doesn't match the actual code, always follow the code and update the documentation when necessary.

### Where to start

| If you want to... | Start here |
|---|---|
| Understand the project | [`docs/README.md`](docs/README.md) → [`docs/architecture.md`](docs/architecture.md) |
| Run the project locally | [`docs/local-setup.md`](docs/local-setup.md) |
| Work on the backend | [`docs/architecture.md`](docs/architecture.md) → [`docs/database.md`](docs/database.md) → [`docs/api.md`](docs/api.md) |
| Build an AI inference or forecasting service | [`docs/integration-contracts.md`](docs/integration-contracts.md) → [`docs/api.md`](docs/api.md) |
| Configure environment variables | [`docs/environment.md`](docs/environment.md) |
| Deploy the application | [`docs/deployment.md`](docs/deployment.md) → [`docs/aws-oidc.md`](docs/aws-oidc.md) |
| Troubleshoot an issue | [`docs/troubleshooting.md`](docs/troubleshooting.md) |
| Work on the project using an AI agent | [`AGENTS.md`](AGENTS.md) → [`ai-docs/17-ai-agent-guide.md`](ai-docs/17-ai-agent-guide.md) |

### Developer documentation (`docs/`)

This is the main documentation for anyone working on the project.

| Document | Description |
|---|---|
| [`docs/README.md`](docs/README.md) | Project introduction, overview, glossary, and recommended reading order |
| [`docs/architecture.md`](docs/architecture.md) | System components, request flow, data flow, and overall architecture |
| [`docs/local-setup.md`](docs/local-setup.md) | How to install, configure, run, and reset the project locally |
| [`docs/database.md`](docs/database.md) | Database tables, relationships, constraints, and useful queries |
| [`docs/api.md`](docs/api.md) | API endpoints, authentication, request and response formats, and error codes |
| [`docs/integration-contracts.md`](docs/integration-contracts.md) | How AI inference and forecasting services communicate with the backend |
| [`docs/environment.md`](docs/environment.md) | Environment variables, configuration, and secret management |
| [`docs/deployment.md`](docs/deployment.md) | Docker, AWS ECS/Fargate, deployment steps, migrations, and rollback |
| [`docs/aws-oidc.md`](docs/aws-oidc.md) | GitHub Actions and AWS authentication using OIDC |
| [`docs/troubleshooting.md`](docs/troubleshooting.md) | Common issues, possible causes, and solutions |

### Technical references (`ai-docs/`)

These documents contain more detailed technical information about the implementation. They are mainly intended as a reference when working on specific parts of the codebase.

| Document | Description |
|---|---|
| [`01-project-overview.md`](ai-docs/01-project-overview.md) | Project purpose, users, features, and implementation status |
| [`02-technology-stack.md`](ai-docs/02-technology-stack.md) | Technology versions, dependencies, and development tools |
| [`03-system-architecture.md`](ai-docs/03-system-architecture.md) | Architecture diagrams, application layers, request flow, and data flow |
| [`04-project-structure.md`](ai-docs/04-project-structure.md) | Directory structure and important files |
| [`05-database-design.md`](ai-docs/05-database-design.md) | Database tables, relationships, indexes, and constraints |
| [`06-backend-architecture.md`](ai-docs/06-backend-architecture.md) | Controllers, services, DTOs, API resources, and middleware |
| [`07-api-documentation.md`](ai-docs/07-api-documentation.md) | API endpoints, response formats, error handling, and examples |
| [`08-frontend-documentation.md`](ai-docs/08-frontend-documentation.md) | Blade, Tailwind, Alpine.js, Chart.js, and frontend pages |
| [`09-business-logic-and-workflows.md`](ai-docs/09-business-logic-and-workflows.md) | Inspection processing, forecasting, dashboard logic, and user roles |
| [`10-authentication-and-authorization.md`](ai-docs/10-authentication-and-authorization.md) | Authentication, tokens, permissions, and policies |
| [`11-file-storage-and-media.md`](ai-docs/11-file-storage-and-media.md) | Image uploads, private storage, local storage, and S3 |
| [`12-configuration-and-environment.md`](ai-docs/12-configuration-and-environment.md) | Environment variables, application settings, and secrets |
| [`13-testing-and-quality.md`](ai-docs/13-testing-and-quality.md) | Tests, testing helpers, code quality, and CI checks |
| [`14-deployment-and-infrastructure.md`](ai-docs/14-deployment-and-infrastructure.md) | Docker, CI/CD, AWS ECS/Fargate, and infrastructure |
| [`15-development-guide.md`](ai-docs/15-development-guide.md) | Local setup, common development tasks, and debugging |
| [`16-known-limitations-and-technical-debt.md`](ai-docs/16-known-limitations-and-technical-debt.md) | Known issues, incomplete features, and technical debt |
| [`17-ai-agent-guide.md`](ai-docs/17-ai-agent-guide.md) | Project context and guidelines for AI coding agents |
