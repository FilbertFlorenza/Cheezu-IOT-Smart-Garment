# 01 — Project Overview

## Purpose

The Smart Garment Defect Detection backend is the **monitoring and reporting tier** of a fabric
inspection system. It receives inspection results from ESP32-CAM inspection units (via an inference
service) and resource forecasts from cloud/network forecasting services, stores them in MySQL, and
presents them through a Blade dashboard and a read API.

It does **not** run defect-detection inference. No image analysis, model loading, or ML code exists
in this repository.

## Main objectives

1. Persist inspection evidence — device, timestamp, PASS/DEFECT outcome, detections, bounding boxes,
   optional image — without silently inventing missing data.
2. Reject duplicate submissions idempotently (UUID uniqueness) instead of overwriting evidence.
3. Track inspection devices, their operational status, and liveness.
4. Ingest and visualise cloud-resource and network-traffic forecasts, including risk warnings.
5. Provide a small, versioned JSON API for machine producers and reporting consumers.
6. Operate as an immutable container image with runtime-injected configuration.

## Main users and roles

Roles are stored in `users.role` as a PHP enum (`App\Enums\Role`) backed by a MySQL enum column.

| Role | Value | UI access | Purpose |
| --- | --- | --- | --- |
| Administrator | `ADMINISTRATOR` | Full web UI including user management and settings | Manages accounts, devices, service tokens, and views all monitoring data |
| Operator | `OPERATOR` | Dashboard, inspections, devices (read-only), forecasts, profile | Day-to-day quality monitoring |
| Service | `SERVICE` | **No web login** (denied at login) | Machine-to-machine API token holder only |

`SERVICE` accounts are deliberately hidden from user management: `Role::assignable()` returns only
`Administrator` and `Operator`, and `User::scopeStaff()` excludes service accounts.

## Core features

### Implemented

- **Session authentication** with rate-limited login, no public registration.
- **Role-based authorisation** via middleware (`role:ADMINISTRATOR`), `active` middleware, and policies.
- **Operations dashboard**: totals, pass/defect counts, defect rate, today/24h counts, 14-day trend,
  defect-class distribution, recent inspections, device health, active forecast warnings.
- **Device management**: list/search/filter, register, edit, activate, deactivate, view recent
  inspections and pass/defect totals.
- **Inspection history**: filter by search, device, status, defect class, and date range; detail view
  with detections, bounding boxes, and private image access.
- **Inspection ingestion API**: UUID idempotency, 0..100 detections, optional image, contract
  consistency enforcement, device lookup, `last_seen_at` update.
- **Forecast ingestion API**: separate cloud and network endpoints, UUID uniqueness per source,
  supersede semantics, duplicate rejection, metric validation.
- **Forecast dashboards**: active forecast charts (predicted vs actual), history, HIGH/CRITICAL
  warnings, per-source metric catalogues.
- **Read API**: devices, inspections (list/detail), latest or paginated forecasts.
- **User management** (administrators): create, edit, activate/deactivate, with guards against
  removing the last active administrator or demoting oneself.
- **Settings** (administrators): system facts and service-token listing/revocation.
- **Profile**: update name/email and change password.
- **Operations**: request IDs on every response, JSON error envelope for `api/*`, rate limiters,
  `/up` health check, `/api/v1/health` with database probe, service-token CLI command.
- **Delivery**: multi-stage Docker image, Compose stack with MySQL, MinIO overlay, GitHub Actions CI,
  ECS/Fargate deployment workflow and task-definition template.

### Partially implemented

- **Expired forecasts** — `ForecastStatus::Expired` exists in the enum and schema, but no code path
  transitions forecasts to `EXPIRED`. Only `ACTIVE → SUPERSEDED` occurs, at ingestion time.
- **Device soft deletes** — `devices` has `deleted_at` and the model uses `SoftDeletes`, but no route,
  controller action, or UI triggers a device delete. The `DevicePolicy::delete` method is unused.
- **Profile account deletion** — `ProfileController::destroy()` exists but no route registers it, and
  the profile view has no delete form.
- **S3 image storage** — implemented through Laravel's filesystem abstraction and exercised by the
  Compose MinIO overlay configuration, but not verified end-to-end against real AWS S3 in this
  repository (no provisioned bucket).

### Not implemented

- Public registration, email verification, password reset, and password confirmation flows. The
  Breeze controllers and Blade views exist on disk but **no route references them**.
- Queue jobs, batch jobs, scheduled tasks, events/listeners, and notifications. The framework
  `jobs`/`job_batches`/`failed_jobs` tables exist from the default migration only.
- Data retention, archival, or purge jobs for inspections, detections, forecasts, or images.
- Audit logging of administrative actions.
- OpenAPI/Swagger specification or generated API reference.
- Multi-tenancy, plant/site scoping, or per-customer isolation.
- WebSocket/live-updating dashboards; pages render server-side and charts are built per request.
- AWS infrastructure itself (ECR, RDS, S3, ECS, IAM, Secrets Manager). Only templates and workflows
  exist; nothing is provisioned by this repository.

## Major modules

| Module | Primary code | Responsibility |
| --- | --- | --- |
| Authentication | `routes/auth.php`, `app/Http/Controllers/Auth/`, `app/Http/Requests/Auth/LoginRequest.php` | Session login/logout, password change, login throttling |
| Authorisation | `app/Http/Middleware/`, `app/Policies/`, `app/Enums/Role.php` | Role gates, active-account enforcement, policy checks |
| Devices | `app/Http/Controllers/Web/DeviceController.php`, `app/Models/Device.php`, `app/Http/Requests/Web/StoreDeviceRequest.php` | Device registry and liveness |
| Inspections | `app/Services/InspectionIngestionService.php`, `InspectionController` (API + Web), `Inspection` model | Ingestion, history, detail, image serving |
| Forecasting | `ForecastIngestionService`, `ForecastPresentationService`, `Forecast`/`ForecastPrediction` models, `ForecastController` (API + Web) | Ingestion, supersede semantics, chart data, warnings |
| Dashboard | `DashboardMetricsService`, `Web/DashboardController` | Aggregated KPIs and warnings |
| Users & settings | `Web/UserController`, `Web/SettingsController`, policies, requests | Staff administration, token revocation |
| API plumbing | `app/Support/ApiResponse.php`, `app/Exceptions/ApiException.php`, `bootstrap/app.php` | Envelope, error codes, exception rendering |
| Storage | `app/Services/ImageStorageService.php`, `config/filesystems.php` | Private image persistence and temporary URLs |
| Operations | `app/Console/Commands/IssueServiceToken.php`, `app/Http/Middleware/AssignRequestId.php`, `AppServiceProvider` | Token issuance, request IDs, rate limits |

## External integrations

| Integration | Direction | Contract |
| --- | --- | --- |
| Inference service (ESP32-CAM pipeline) | → backend | `POST /api/v1/inspections/results`, Bearer token with `inspections:write` |
| Cloud forecaster | → backend | `POST /api/v1/forecasts/cloud`, ability `forecasts:cloud:write` |
| Network forecaster | → backend | `POST /api/v1/forecasts/network`, ability `forecasts:network:write` |
| Reporting consumers | ← backend | Read endpoints requiring the `read` ability |
| MySQL | backend ↔ DB | Eloquent over `pdo_mysql` |
| Local disk / S3-compatible bucket | backend ↔ storage | Inspection images only |
| SMTP | not used | `MAIL_MAILER=log` by default; no mail is sent by application code |

The inference service and forecasters are external systems. Their implementations are **not** part of
this repository; only the accepted contracts are defined here.

## Current implementation status

- Application code, migrations, seeders, views, and tests are complete for the scope above.
- Verified test suite: **87 Pest tests / 310 assertions** passing against MySQL, Pint clean, Vite
  build succeeds, and all Blade templates compile.
- The production image builds and runs; health endpoints and the login/ingestion flows were verified
  in a temporary container.
- AWS deployment is **template-only**. No cloud resource is provisioned.
- No Git repository is initialised in this working copy.

## Related documents

- Technology versions: [02-technology-stack.md](02-technology-stack.md)
- Architecture and diagrams: [03-system-architecture.md](03-system-architecture.md)
- Feature-level workflows: [09-business-logic-and-workflows.md](09-business-logic-and-workflows.md)
- Gaps and debt: [16-known-limitations-and-technical-debt.md](16-known-limitations-and-technical-debt.md)
