# 06 — Backend Architecture

## Request handling map

| Surface | Entry file | Guard | Middleware | Controllers |
| --- | --- | --- | --- | --- |
| Web UI | `routes/web.php` | `web` session | `auth`, `active`, `role:ADMINISTRATOR` (admin group) | `App\Http\Controllers\Web\*`, `ProfileController` |
| Login/logout | `routes/auth.php` | `web` session | `guest`, `auth` + `active` | `Auth\AuthenticatedSessionController`, `Auth\PasswordController` |
| API v1 | `routes/api.php` | `sanctum` | `active`, `abilities:*`, `throttle:*` | `Api\V1\*` |
| Health | `routes/api.php` | none | `throttle:health` | `Api\V1\HealthController` |
| CLI | `routes/console.php`, `app/Console/Commands` | — | — | `service:token` |

## Controllers

### Base class

`app/Http/Controllers/Controller.php` is an abstract class using `AuthorizesRequests` and
`ValidatesRequests`. All controllers extend it.

### Web controllers

| Controller | Actions | Notes |
| --- | --- | --- |
| `Web\DashboardController` | `__invoke` | Single-action; injects `DashboardMetricsService` |
| `Web\DeviceController` | `index`, `create`, `store`, `show`, `edit`, `update`, `deactivate`, `activate` | Policies on every action |
| `Web\InspectionController` | `index`, `show`, `image` | `image` streams or redirects to private storage |
| `Web\ForecastController` | `cloud`, `network`, `show` | Delegates chart shaping to `ForecastPresentationService` |
| `Web\UserController` | `index`, `create`, `store`, `edit`, `update`, `toggleStatus` | Administrator-only; guards last admin |
| `Web\SettingsController` | `index`, `revokeToken` | System facts + service token listing |
| `ProfileController` | `edit`, `update`, `destroy` | `destroy` is unrouted |

Conventions:

- Authorise **before** querying where the action is model-scoped.
- Validate filters inline with `$request->validate([...])`; form writes use FormRequests.
- Redirect with a `status` flash message after mutations.
- Return `View` or `RedirectResponse`; no JSON from web controllers.

### API controllers

| Controller | Actions | Middleware/abilities |
| --- | --- | --- |
| `Api\V1\HealthController` | `__invoke` | `throttle:health` |
| `Api\V1\InspectionResultController` | `store` | `inspections:write` |
| `Api\V1\ForecastIngestionController` | `storeCloud`, `storeNetwork` | `forecasts:cloud:write` / `forecasts:network:write` |
| `Api\V1\DeviceController` | `index` | `read` |
| `Api\V1\InspectionController` | `index`, `show` | `read` |
| `Api\V1\ForecastController` | `cloud`, `network` | `read` |

API controllers return `JsonResponse` built through `ApiResponse`. They never return models directly.

## Services

| Service | Public API | Responsibility |
| --- | --- | --- |
| `InspectionIngestionService` | `ingest(InspectionPayload, ?UploadedFile): Inspection` | Contract check, device lookup, duplicate check, transaction, image persistence, cleanup on failure, `last_seen_at` update |
| `ForecastIngestionService` | `ingest(ForecastSource, ForecastPayload): Forecast` | Duplicate check, supersede active rows, insert forecast + predictions, duplicate-key translation |
| `DashboardMetricsService` | `summary`, `trend`, `defectDistribution`, `recentInspections`, `deviceStats`, `deviceOverview`, `latestForecast`, `warnings`, `totalWarnings` | Read-only aggregate queries |
| `ForecastPresentationService` | `series`, `warnings`, `availableSources` | Chart-ready series from stored predictions only |
| `ImageStorageService` | `diskName`, `disk`, `store`, `delete`, `temporaryUrl`, `isLocal`, `absolutePath` | Disk selection, server-generated filenames, 10-minute URLs |

Service conventions:

- Constructor-inject collaborators (`ImageStorageService`, `ForecastPresentationService`) or accept
  them as method arguments (`DashboardMetricsService`, `InspectionIngestionService` in controllers).
- No service depends on HTTP request objects except when a controller passes validated data.
- Services may throw `ApiException` for stable API failures.
- Services log successful ingestion with `Log::info('inspection.ingested' | 'forecast.ingested', …)`.

## DTOs (`app/Data/`)

All four are `final readonly class` with a static `fromArray()`.

| DTO | Input | Notable behaviour |
| --- | --- | --- |
| `InspectionPayload` | validated inspection array | Parses timestamps to UTC `CarbonImmutable`; `isConsistent()` |
| `DetectionData` | one detection + index | Maps `bounding_box.{x,y,width,height}` to flat fields |
| `ForecastPayload` | validated forecast array | Converts `forecast_id` → `externalId`, maps predictions |
| `PredictionData` | one prediction | Parses timestamps to UTC, casts risk enum |

DTOs contain no persistence logic. They are the boundary between HTTP validation and services.

## Enums (`app/Enums/`)

Backed string enums with display helpers:

- `label()` — human text.
- `color()` / `dotColor()` — Tailwind class strings (presentation lives in the enum by convention).
- `ForecastSource::route()`, `metricConfigKey()`.
- `RiskLevel::isWarning()` — true for `HIGH` and `CRITICAL`.
- `Role::assignable()` — only `ADMINISTRATOR`, `OPERATOR`.

When adding a case, update `match` expressions; PHP will throw `UnhandledMatchError` otherwise.

## Form requests

### API

| Request | Key rules |
| --- | --- |
| `StoreInspectionResultRequest` | UUID `inspection_id`; `device_code` (aliases `device_id`); `inspected_at`; enum status; `defect_count` 0–65535; `detections` present, max 100; confidence 0–1; optional bounding box integers; `image` jpg/jpeg/png/webp, size/dimension from config; `after()` consistency and clock window; `payload` field accepts JSON string or array |
| `StoreForecastRequest` (abstract) | UUID `forecast_id`; `model_name`, `model_version`; horizon 1..config max; `generated_at`; 1..config max predictions; metric name `^[a-z0-9_]+$`; numeric values; risk enum; `after()` duplicate metric+timestamp and future tolerance |
| `StoreCloudForecastRequest`, `StoreNetworkForecastRequest` | Empty subclasses of `StoreForecastRequest` |

`prepareForValidation()` is used for payload unwrapping and aliases. `after()` callbacks run only
when the base rules pass, and are where cross-field invariants live.

### Web

| Request | Notes |
| --- | --- |
| `StoreDeviceRequest` / `UpdateDeviceRequest` | Uppercase/trim `device_code`; regex `^[A-Z0-9][A-Z0-9\-_]*$`; unique ignoring current; enum status; `authorize()` uses the Device policy |
| `StoreUserRequest` / `UpdateUserRequest` | Role restricted to assignable roles; password min 8 + confirmed; update allows blank password |
| `ProfileUpdateRequest` | Name + unique lowercase email |
| `Auth\LoginRequest` | Email/password; rate limiter; rejects inactive and service accounts |

## API resources

| Resource | Fields |
| --- | --- |
| `DeviceResource` | `device_code`, `device_name`, `description`, `location`, `status`, `is_online`, `last_seen_at`, conditional `inspections_count`, `latest_inspection_at`, `created_at` |
| `InspectionResource` | `inspection_id`, conditional `device`, `device_id`, timestamps, `overall_status`, `defect_count`, `processing_time_ms`, `model_version`, conditional `image_url`, `metadata`, conditional `detections`, `detections_count`, `created_at` |
| `InspectionDetectionResource` | `defect_class`, `confidence`, `bounding_box`, `metadata` |
| `ForecastResource` | `forecast_id` (= `external_id`), `source`, model fields, horizon, timestamps, `status`, `metadata`, conditional `predictions`, conditional `warning_count` |
| `ForecastPredictionResource` | `metric_name`, `metric_label`, `metric_unit`, `predicted_value`, `actual_value`, `predicted_at`, `anomaly_risk`, `risk_level`, `metadata` |

Timestamps are serialised with `toIso8601String()` (UTC). `image_url` is generated only when
`image_path` is not null and is a temporary URL (may be `null` if the object is missing).

## Support

`app/Support/ApiResponse.php`:

- `success($data, $status = 200, $meta = [])`
- `paginated(LengthAwarePaginator $paginator, string $resource)`
- `error($code, $message, $status, $details = [])`
- private `requestId()` reads the `request_id` attribute set by `AssignRequestId`.

`app/Support/helpers.php` (autoloaded via `composer.json` `files`):

- `display_timezone()`, `display_datetime()`, `display_date()` — render UTC timestamps in
  `APP_DISPLAY_TIMEZONE`.
- `metric_label()`, `metric_color()` — fall back to humanised names/default colour.

## Middleware

| Middleware | Registration | Behaviour |
| --- | --- | --- |
| `AssignRequestId` | `$middleware->append()` (global) | Reuses `X-Request-Id` or generates a ULID; sets request attribute and response header |
| `EnsureRole` | alias `role` | Aborts 403 unless the user's role value is in the allowed list |
| `EnsureUserIsActive` | alias `active` | Inactive users: API → 403 `ACCOUNT_INACTIVE`; web → logout + redirect to login with error |
| Sanctum `CheckAbilities` | alias `abilities` | Token must have **all** listed abilities |
| Sanctum `CheckForAnyAbility` | alias `ability` | Token needs any listed ability (registered, unused) |

## Exceptions

`ApiException` carries `status`, `errorCode`, `message`, `details`. Named constructors:

| Method | HTTP | Code |
| --- | --- | --- |
| `duplicateInspection($id)` | 409 | `DUPLICATE_INSPECTION` |
| `duplicateForecast($source, $id)` | 409 | `DUPLICATE_FORECAST` |
| `unknownDevice($code)` | 422 | `UNKNOWN_DEVICE` |
| `contractMismatch($message, $details)` | 422 | `CONTRACT_MISMATCH` |

All API exception rendering is centralised in `bootstrap/app.php` (see 03). Web requests fall through
to Laravel's default handling and the custom error views.

## Rate limiting

Defined in `AppServiceProvider::boot()`:

| Limiter | Limit | Key |
| --- | --- | --- |
| `inspections` | 120/min | user ID or IP |
| `forecasts` | 30/min | user ID or IP |
| `api-read` | 120/min | user ID or IP |
| `health` | 30/min | IP |

Login throttling is separate: 5 attempts per email+IP in `LoginRequest`, cleared on success.

## Console

`php artisan service:token {service} {--ability=*} {--name=service-token} {--keep-existing}`

- Finds a `SERVICE` user by email or name.
- Requires at least one ability.
- Deletes existing tokens unless `--keep-existing`.
- Prints the plain-text token once.

No scheduled tasks are registered in `routes/console.php` beyond the default `inspire` command.

## Transactions and concurrency

- Inspection ingestion wraps image store + inspection + detections + device update in one
  `DB::transaction`. On exception, a stored image is deleted and duplicate-key errors are translated.
- Forecast ingestion wraps supersede + insert forecast + insert predictions in one transaction.
- The duplicate pre-checks are advisory; unique indexes are the real guard.
- Forecast supersede relies on a bulk `UPDATE ... WHERE source_type = ? AND status = ACTIVE`. This is
  not serialised across concurrent requests.

## Logging

- Channel from `LOG_CHANNEL` (`stack`/`single` locally, `stderr` in containers).
- Application logs two structured events: `inspection.ingested` and `forecast.ingested`.
- Unexpected API exceptions are `report()`ed before returning `SERVER_ERROR`.
- Health probe failures report the database exception.

## Extending the backend safely

1. Read the closest existing controller/service/resource trio.
2. Add route + middleware + FormRequest + DTO (if inbound) + service + resource.
3. Reuse `ApiResponse` and `ApiException`; do not invent a second envelope.
4. Add a policy method and `authorize()` call for any web mutation.
5. Add feature tests that hit the route, including authorisation and validation failures.
6. Update [07](07-api-documentation.md) and, if contracts change,
   `docs/integration-contracts.md`.
