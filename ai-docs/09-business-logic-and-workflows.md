# 09 — Business Logic and Workflows

This document describes the behaviour actually encoded in services, requests, models, and
controllers. Thresholds are configuration-driven unless stated otherwise.

## Domain invariants

| # | Invariant | Enforced in |
| --- | --- | --- |
| 1 | An inspection UUID is stored at most once | `inspections.inspection_id` unique + service pre-check |
| 2 | `defect_count` equals the number of detections | `StoreInspectionResultRequest::after()`, `InspectionPayload::isConsistent()`, service |
| 3 | `PASS` has zero detections; `DEFECT` has ≥1 | Same as above |
| 4 | Inspections may not be older than `backfill_days` or further than `future_tolerance_minutes` ahead | FormRequest `after()` |
| 5 | A device must exist before an inspection is accepted | `InspectionIngestionService` → `UNKNOWN_DEVICE` |
| 6 | `(source_type, forecast_id)` is stored at most once | Unique index + service pre-check |
| 7 | Each new forecast supersedes the previous `ACTIVE` forecast of the same source | `ForecastIngestionService` transaction |
| 8 | `(metric_name, predicted_at)` is unique inside one forecast payload | `StoreForecastRequest::after()` + DB unique index |
| 9 | Only `HIGH`/`CRITICAL` predictions count as warnings | `RiskLevel::isWarning()` |
| 10 | A device is online only if `ACTIVE` and seen within the threshold | `Device::isOnline()` |
| 11 | At least one active administrator must remain | `UserController::guardAdministratorAccess()` |

## Inspection ingestion

### Step-by-step

1. **Payload normalisation** (`StoreInspectionResultRequest::prepareForValidation`)
   - If `payload` is a JSON string, decode it (invalid JSON → 422 on `payload`).
   - If it is an array, merge it.
   - If `device_code` is absent but `device_id` is present, copy `device_id` to `device_code`
     (backwards-compatible alias from an early contract draft).
   - Trim `device_code`.
2. **Validation**
   - UUID, device code, timestamps, enum status, counts, detection array, confidence, bounding box,
     image type/size/dimensions.
   - `detections` must be **present**; an empty array is valid for a PASS.
3. **Cross-field validation** (`after()`)
   - Count/status consistency.
   - Clock window against `inspection.backfill_days` and `inspection.future_tolerance_minutes`.
4. **DTO construction** — `InspectionPayload::fromArray()` converts timestamps to UTC
   `CarbonImmutable`, casts status to the enum, indexes detections from 0 in array order.
5. **Service** (`InspectionIngestionService::ingest`)
   - Re-checks `isConsistent()` → `CONTRACT_MISMATCH`.
   - Looks up `Device` by `device_code` → `UNKNOWN_DEVICE`.
   - Pre-checks duplicate UUID → `DUPLICATE_INSPECTION`.
   - Opens a transaction:
     - Store image if present (server-generated filename).
     - Insert inspection with `received_at = now()`.
     - Insert each detection with its index.
     - Update `device.last_seen_at = now()`.
   - On exception: delete a stored image, translate duplicate-key (`1062`) to
     `DUPLICATE_INSPECTION`, rethrow otherwise.
6. **Logging** — `inspection.ingested` with inspection UUID, device code, status, defect count, and
   whether an image was stored.
7. **Response** — 201 with the inspection, device, and detections; `meta.duplicate = false`.

### Why the service re-validates

The FormRequest is the public contract, but the service is also callable from tests or future
commands. Duplicating the invariant there prevents corrupt rows from any caller.

### Image name and path

`ImageStorageService::store()` writes to `inspections/YYYY/MM/<lowercase-uuid>.<ext>` on the configured
disk. Extension is derived from the detected MIME (`png`, `webp`, otherwise `jpg`). See
[11](11-file-storage-and-media.md).

### Duplicate behaviour

A duplicate is **rejected**, not merged or updated. The producer receives `409` with
`details.inspection_id` and must treat the submission as already accepted.

## Forecast ingestion

### Step-by-step

1. **Validation** (`StoreForecastRequest`)
   - UUID, model metadata, horizon, generated time, predictions array size.
   - Per-prediction metric/unit/values/timestamp/risk.
   - `after()`: duplicate metric+timestamp in the payload; `generated_at` not more than 5 minutes
     ahead (hardcoded).
2. **DTO** — `ForecastPayload::fromArray()` maps `forecast_id` → `externalId`, converts timestamps to
   UTC, maps predictions.
3. **Service** (`ForecastIngestionService::ingest`)
   - Duplicate pre-check on `(source_type, external_id)` → `DUPLICATE_FORECAST`.
   - Transaction:
     - Bulk-update all `ACTIVE` forecasts of the source to `SUPERSEDED`.
     - Insert the new forecast with `received_at = now()`, `status = ACTIVE`.
     - Insert all predictions.
   - Translate duplicate-key errors to `DUPLICATE_FORECAST`.
4. **Logging** — `forecast.ingested` with source, external id, model, model version, prediction count.
5. **Response** — 201 with predictions loaded.

### Supersede semantics

- Superseding is per source: a cloud forecast never supersedes a network forecast.
- The supersede happens even if the new forecast's `generated_at` is **older** than the current
  active one. There is no timestamp ordering check. Producers are expected to submit in order.
- `EXPIRED` is never set by code.

### Metric catalogue

`config/forecast.php` defines metrics for display (label, unit, colour). It is **not** an allow-list:
unknown metric names are accepted (regex `^[a-z0-9_]+$`) and displayed with a humanised label. This
lets producers evolve models without a backend deployment.

Cloud metrics: `cpu_utilization`, `memory_utilization`, `disk_io_read`, `disk_io_write`, `network_in`,
`network_out`.

Network metrics: `network_throughput`, `packet_count`, `traffic_volume`, `latency`, `packet_loss`,
`congestion_risk`.

Only metrics with stored predictions are charted. Values are never synthesised.

## Forecast presentation

`ForecastPresentationService::series()`:

1. Groups predictions by `metric_name`.
2. Orders metric keys by the config catalogue first, then any extra metrics in stored order.
3. Skips metrics with no predictions.
4. Sorts each series by `predicted_at`.
5. Emits: `metric`, `label`, `unit`, `color`, `labels` (display timezone), `predicted[]`, `actual[]`,
   `has_actual`, `latest_predicted`, `latest_actual`, `latest_risk`.

`warnings()` filters `HIGH`/`CRITICAL`, sorting critical first.

## Dashboard aggregation

`DashboardMetricsService`:

| Method | Query behaviour |
| --- | --- |
| `summary()` | Total count; pass count; defect = total − pass; defect rate = defect/total × 100 rounded to 1 dp; today since `startOfDay()`; last 24h since `now()->subDay()` |
| `trend($days = 14)` | Groups `DATE(inspected_at)` by status; fills **zero-value days** for the full window; labels `M j` |
| `defectDistribution($limit = 8)` | Groups detections by `defect_class`, ordered by count desc |
| `recentInspections($limit = 8)` | Latest by `inspected_at`, eager-loads device summary + detections |
| `deviceStats()` | Status counts; online = `ACTIVE` and `last_seen_at >= now - threshold` |
| `deviceOverview($limit = 6)` | Devices with `latestInspection` and `inspections_count`, ordered by `last_seen_at` desc |
| `latestForecast($source)` | Newest `ACTIVE` forecast with ordered predictions |
| `warnings($limit = 6)` | `HIGH`/`CRITICAL` predictions belonging to `ACTIVE` forecasts, newest `predicted_at` first |
| `totalWarnings()` | Count of the same |

All "today"/"24h" calculations use the application clock (UTC). Display formatting converts to
`APP_DISPLAY_TIMEZONE`.

## Device liveness

`Device::isOnline()`:

```php
status === DeviceStatus::Active
&& last_seen_at !== null
&& last_seen_at >= now()->subMinutes(config('inspection.online_threshold_minutes'))
```

`last_seen_at` is updated **only** by successful inspection ingestion. Viewing a device or editing it
does not change it. New devices start with `last_seen_at = null` and therefore appear offline.

## Device management

| Action | Route | Policy | Effect |
| --- | --- | --- | --- |
| List | `GET /devices` | `viewAny` (admin/operator) | Paginated, filterable by search/status |
| Create | `GET/POST /devices` | `create` (admin) | Validates unique uppercase code |
| View | `GET /devices/{device}` | `view` (admin/operator) | Pass/defect totals + recent inspections |
| Edit | `GET/PUT /devices/{device}` | `update` (admin) | Unique code ignoring self |
| Deactivate | `PATCH /devices/{device}/deactivate` | `update` (admin) | `status = INACTIVE` |
| Activate | `PATCH /devices/{device}/activate` | `update` (admin) | `status = ACTIVE` |

There is no delete action. `DEVICE-*` codes must match `^[A-Z0-9][A-Z0-9\-_]*$` and are uppercased in
`prepareForValidation()`.

## User management

- Only administrators can list/create/edit users; the list is restricted to `scopeStaff()`
  (administrators + operators).
- Assignable roles: `ADMINISTRATOR`, `OPERATOR` only.
- Password required on create (min 8, confirmed); optional on update (blank = unchanged).
- `guardAdministratorAccess()` blocks:
  - An administrator demoting or deactivating **themselves**.
  - Any change that would leave **zero active administrators**.
- `toggleStatus` flips `is_active` and applies the same guard.
- Service accounts never appear and cannot be edited through this UI.

## Settings and service tokens

`Web\SettingsController::index()` exposes read-only system facts (app name, environment, Laravel/PHP
versions, timezone, disk, threshold, max image size, defect classes, entity counts) and lists
`personal_access_tokens` whose owner is a `SERVICE` account.

`revokeToken()` deletes a token only when its owner is a service account; otherwise 404. Issuing is
CLI-only (`php artisan service:token`). There is no UI to mint tokens.

## Authentication workflow

1. `GET /login` renders the guest layout.
2. `POST /login` → `LoginRequest::authenticate()`:
   - Rate limited to 5 attempts per lowercase email + IP.
   - `Auth::attempt` with optional remember.
   - Rejects inactive users and service accounts by logging out and returning a generic credential
     error (no account-state disclosure).
   - On success clears the limiter, regenerates the session, updates `last_login_at`, redirects to
     the intended URL or dashboard.
3. `POST /logout` invalidates the session and regenerates the CSRF token.

`EnsureUserIsActive` runs on every authenticated request, so a user deactivated mid-session is
logged out on the next request.

## Authorization matrix

| Capability | Administrator | Operator | Service |
| --- | --- | --- | --- |
| Web login | Yes | Yes | No |
| Dashboard | Yes | Yes | No |
| Inspections list/detail/image | Yes | Yes | No |
| Devices list/detail | Yes | Yes | No |
| Device create/edit/activate/deactivate | Yes | No | No |
| Forecast pages | Yes | Yes | No |
| User management | Yes | No | No |
| Settings + token revocation | Yes | No | No |
| Profile update / password change | Yes | Yes | No |
| API ingestion | Token + ability | Token + ability | Token + ability |
| API read | Token + ability | Token + ability | Token + ability |

Staff accounts *can* hold API tokens technically (`HasApiTokens`), but the documented and seeded
pattern is service-account tokens.

## Related documents

- Exact API shapes: [07-api-documentation.md](07-api-documentation.md)
- Auth internals: [10-authentication-and-authorization.md](10-authentication-and-authorization.md)
- Image lifecycle: [11-file-storage-and-media.md](11-file-storage-and-media.md)
