# Architecture

## System context

```
 ┌──────────────────┐        multipart / JSON        ┌─────────────────────────┐
 │ ESP32-CAM units  │ ───────────────┐               │  Laravel application    │
 └──────────────────┘                │               │                         │
                                     ▼               │  • Web UI (Blade)       │
 ┌──────────────────────────┐  inference service ───► │  • REST API v1          │
 │ Fabric inspection line   │                        │  • RBAC & policies      │
 └──────────────────────────┘                        │  • Ingestion services   │
                                                     │  • Dashboard aggregates │
 ┌──────────────────────────┐   POST forecasts       │                         │
 │ Cloud resource forecaster│ ─────────────────────► │                         │
 ├──────────────────────────┤                        │                         │
 │ Network traffic forecaster│ ────────────────────► │                         │
 └──────────────────────────┘                        └───────────┬─────────────┘
                                                                 │
                                        ┌────────────────────────┼────────────────────────┐
                                        ▼                        ▼                        ▼
                                  MySQL 8 / MariaDB      local private disk        S3-compatible
                                  (system of record)     or S3 bucket              object storage
```

The Laravel application is the **monitoring and reporting tier**. Defect
detection, cloud-resource forecasting, and network-traffic forecasting happen
in external services. The backend validates, stores, and presents their output.
It never fabricates a prediction, a detection, or a device state.

## Runtime components

| Component | Responsibility |
| --- | --- |
| Nginx | TLS termination is delegated to the load balancer; Nginx serves the app on port 80 and forwards PHP requests to PHP-FPM |
| PHP-FPM 8.3 | Executes Laravel; the production image installs only required extensions |
| Supervisor | Runs Nginx and PHP-FPM in one container, restarts either process on failure |
| MySQL | Durable system of record for users, devices, inspections, detections, forecasts, and predictions |
| Object storage | Private inspection images, either the local private disk or S3/MinIO |
| Laravel scheduler / queues | Not required for ingestion; the default sync queue keeps the deployment single-purpose |

The image is intentionally one deployable unit: a container listens on port 80,
serves both the UI and `/api/v1`, and exposes `/up` for health checks. There is no
separate frontend build at runtime; Vite assets are compiled during the Docker
build.

## Application layering

```
HTTP request
   │
   ├─ Middleware: request ID, authentication, active-account check, role/ability, throttles
   │
   ├─ Form Request: shape, types, ranges, cross-field consistency
   │
   ├─ Controller: validates, delegates, formats — no business rules
   │
   ├─ Service: transaction, idempotency, supersede, storage side effects
   │
   ├─ Eloquent model / query scopes / enum casts
   │
   └─ API Resource (JSON) or Blade view (HTML)
```

### Controllers

Controllers are deliberately thin. For example the inspection ingestion
controller builds a DTO and calls `InspectionIngestionService`; all duplicate,
device, consistency, image, and transaction handling lives in the service.

### Services

| Service | Responsibility |
| --- | --- |
| `InspectionIngestionService` | Resolves the device, rejects duplicates, stores the inspection and its detections atomically, stores the image, updates `last_seen_at` |
| `ForecastIngestionService` | Inserts a forecast, supersedes the previous active forecast for that source, stores predictions in one transaction |
| `ForecastPresentationService` | Builds chart series, active warnings, metric labels, and history for the UI |
| `DashboardMetricsService` | Computes KPI cards, trends, device status, recent inspections, and forecast summaries |
| `ImageStorageService` | Generates server-side file names, stores on a configurable disk, returns short-lived URLs, deletes files |

### DTOs and enums

`InspectionPayload`, `DetectionData`, `ForecastPayload`, and `PredictionData`
carry validated data between the HTTP layer and services. Enums
(`Role`, `DeviceStatus`, `InspectionStatus`, `ForecastSource`, `ForecastStatus`,
`RiskLevel`) are cast by Eloquent and serialised by resources.

## Authentication and authorisation

Two independent authentication paths share the same `users` table:

1. **Web sessions** — Laravel session guard with a hardened login controller.
   There is no public registration; administrators create accounts.
2. **API tokens** — Sanctum personal access tokens for `SERVICE` accounts.
   Tokens carry explicit abilities:
   - `inspections:write`
   - `forecasts:cloud:write`
   - `forecasts:network:write`
   - `read`

The `SERVICE` role cannot sign in to the web UI. Service accounts are hidden from
user management and from the login flow; their tokens are managed from the
Settings screen or `php artisan service:token`.

Role enforcement is layered:

- `role:ADMINISTRATOR` middleware protects user and settings routes.
- Sanctum `abilities` / `ability` middleware protects machine endpoints.
- Policies protect model actions (for example device management).
- The `active` middleware signs out deactivated staff and rejects tokens whose
  owning service account was disabled.

## Inspection ingestion flow

```
POST /api/v1/inspections/results
        │
        ├─ authenticate token + active account + inspections:write
        ├─ Form Request
        │    ├─ unwrap optional multipart `payload` JSON
        │    ├─ accept legacy device_id alias (normalised to device_code)
        │    ├─ validate fields, detection bounds, image type/dimensions
        │    └─ enforce defect_count == detections and status consistency
        │
        ├─ InspectionIngestionService
        │    ├─ resolve device by device_code (422 UNKNOWN_DEVICE)
        │    ├─ check inspection_id uniqueness (409 DUPLICATE_INSPECTION)
        │    ├─ DB transaction
        │    │    ├─ insert inspection
        │    │    ├─ insert every detection with its index
        │    │    ├─ store image (server-generated name)
        │    │    └─ update device.last_seen_at
        │    └─ return the loaded inspection
        │
        └─ 201 JSON envelope with the stored resource
```

Retries are safe: submitting the same `inspection_id` again returns 409 and
does **not** overwrite the original evidence. The inference service should treat
409 as success when retrying.

## Forecast ingestion flow

```
POST /api/v1/forecasts/cloud | /network
        │
        ├─ authenticate token + source-specific ability
        ├─ validate forecast fields and every prediction
        │    ├─ metric names are lower_snake_case
        │    ├─ no duplicate metric_name + predicted_at pairs
        │    └─ generated_at is not more than 5 minutes in the future
        │
        ├─ ForecastIngestionService
        │    ├─ reject duplicate (source_type, external_id) with 409
        │    ├─ transaction
        │    │    ├─ mark previous ACTIVE forecast for this source SUPERSEDED
        │    │    ├─ insert forecast as ACTIVE
        │    │    └─ insert all predictions
        │    └─ return the forecast
        │
        └─ 201 JSON envelope
```

Only the newest successful submission per source is `ACTIVE`. Older submissions
remain queryable as `SUPERSEDED` history. A duplicate forecast ID for the same
source is rejected; the same ID may be used once for cloud and once for network
because uniqueness is scoped to `(source_type, external_id)`.

## Dashboard and presentation

The dashboard and forecast pages are server-rendered from database queries:

- KPI totals and rates come from `DashboardMetricsService`.
- Trend buckets are computed from stored inspection timestamps.
- Device status is derived from `status = ACTIVE` **and** a recent `last_seen_at`,
  using `INSPECTION_ONLINE_THRESHOLD_MINUTES`.
- Forecast charts are built from stored predictions; no series is invented when a
  metric has no data.
- Risk warnings are driven by submitted `risk_level` and `anomaly_risk` values.

Timestamps are stored in UTC. `APP_DISPLAY_TIMEZONE` controls rendering only, so
changing the display zone does not rewrite stored evidence.

## API conventions

Every JSON response uses one envelope:

```json
{
  "success": true,
  "data": { },
  "request_id": "01J...",
  "meta": { }
}
```

Errors use:

```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_INSPECTION",
    "message": "An inspection with this inspection_id has already been recorded.",
    "details": { }
  },
  "request_id": "01J..."
}
```

A request ID is assigned by middleware, echoed in the `X-Request-Id` header, and
written to logs so a client report can be correlated with server activity.

## Security decisions

- **No public registration.** Administrator-created accounts only.
- **Least privilege.** Each machine client gets only the ability it needs.
- **Private evidence.** Images are not publicly listable; API access uses a
  short-lived signed URL (10 minutes), and the UI serves images through an
  authenticated route.
- **Transactional ingestion.** A failed detection insert rolls back the parent
  inspection, so partial evidence cannot be stored.
- **Idempotency over overwrites.** Duplicate IDs are rejected, not upserted.
- **Secrets at runtime.** `APP_KEY`, database passwords, and admin passwords are
  injected from the environment or a secret store; they are not committed or
  baked into the image.
- **Explicit contract failures.** Unknown devices, contract mismatches, and
  duplicates have stable machine-readable error codes.

## Non-goals

- Running the defect-detection model or forecast models inside Laravel.
- Managing ESP32 firmware.
- Public self-service registration or a public marketing site.
- Multi-tenant isolation; this deployment serves one organisation.
- Real-time WebSocket streaming; pages refresh from the database.
