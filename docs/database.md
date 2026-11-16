# Database

The application uses MySQL 8 or MariaDB 10.4+ through Laravel's `mysql`
connection. Tests use a separate database, `garment_defect_test`, configured in
`phpunit.xml`.

All timestamps are stored in **UTC**. `APP_DISPLAY_TIMEZONE` is applied only when
the UI renders a timestamp.

## Entity relationship diagram

```
users (1) ───< personal_access_tokens
  │
  │ role = ADMINISTRATOR | OPERATOR | SERVICE
  │
devices (1) ───< inspections (1) ───< inspection_detections
                     │
                     └── inspection_id is a public UUID

forecasts (1) ───< forecast_predictions
  │
  └── unique (source_type, external_id)
```

## Tables

### `users`

Staff and machine identities.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `name` | varchar(255) | Display name |
| `email` | varchar(255), unique | Login identifier |
| `email_verified_at` | timestamp, nullable | Set for seeded accounts |
| `password` | varchar(255) | Bcrypt hash; never returned by the API |
| `role` | enum `ADMINISTRATOR`, `OPERATOR`, `SERVICE` | Default `OPERATOR`, indexed |
| `is_active` | boolean | Default true, indexed; inactive users cannot act |
| `last_login_at` | timestamp, nullable | Updated on successful web login |
| `remember_token` | varchar(100), nullable | Session remember-me |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique `email`, index `role`, index `is_active`.

`SERVICE` accounts cannot sign in to the web UI and are excluded from user
management screens. They exist only to own Sanctum tokens.

### `personal_access_tokens`

Laravel Sanctum tokens. Tokens are hashed; the plain value is shown once.

### `sessions`

Database-backed web sessions (`SESSION_DRIVER=database`). Also present in the
default deployment so sessions survive container replacement.

### `devices`

Registered inspection units.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | Internal identifier |
| `device_code` | varchar(50), unique | Canonical external identifier (`DEVICE-001`) |
| `device_name` | varchar(100) | Human-readable name |
| `description` | text, nullable | |
| `location` | varchar(150), nullable | Line or area |
| `status` | enum `ACTIVE`, `INACTIVE`, `MAINTENANCE` | Default `ACTIVE`, indexed |
| `last_seen_at` | timestamp, nullable, indexed | Updated on every accepted inspection |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | timestamp, nullable | Soft delete |

A device is shown as **online** only when `status = ACTIVE` and `last_seen_at` is
within `INSPECTION_ONLINE_THRESHOLD_MINUTES` (default 15). Liveness is never
stored as a separate mutable flag.

Devices with inspections cannot be hard-deleted (`restrictOnDelete`). The UI
offers deactivation instead.

### `inspections`

One accepted result from an inference unit.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `inspection_id` | uuid, unique | Client-supplied idempotency key |
| `device_id` | bigint unsigned, FK → `devices.id` | Restrict on delete |
| `inspected_at` | timestamp, indexed | Time the inspection happened |
| `received_at` | timestamp, nullable | Server receipt time |
| `overall_status` | enum `PASS`, `DEFECT` | Indexed |
| `defect_count` | smallint unsigned | Must equal the number of detections |
| `processing_time_ms` | int unsigned, nullable | Model latency |
| `model_version` | varchar(50) | Model that produced the result |
| `image_path` | varchar(255), nullable | Private storage path, never a public URL |
| `image_mime` | varchar(50), nullable | |
| `image_size_bytes` | int unsigned, nullable | |
| `metadata` | json, nullable | Producer-defined payload |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique `inspection_id`, `inspected_at`, `overall_status`,
`(device_id, inspected_at)`, `(overall_status, inspected_at)`.

The public API and UI resolve inspections by `inspection_id`, not by the internal
numeric key.

### `inspection_detections`

One row per detected defect. A PASS has zero rows; a DEFECT has one or more.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `inspection_id` | bigint unsigned, FK → `inspections.id` | Cascade on delete |
| `detection_index` | smallint unsigned | Original order in the submitted array |
| `defect_class` | varchar(50), indexed | Free-form; configured classes are hints only |
| `confidence` | decimal(5,4) | 0.0000–1.0000 |
| `bbox_x`, `bbox_y` | int unsigned, nullable | Pixel coordinates |
| `bbox_width`, `bbox_height` | int unsigned, nullable | Positive when present |
| `metadata` | json, nullable | |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(inspection_id, detection_index)`, `defect_class`.

Bounding boxes are optional as a group: either the whole box is supplied or none
of it is. The API stores the box fields flat and reconstructs the nested object
in JSON responses.

### `forecasts`

One submission from a forecasting service.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `external_id` | uuid | Client-supplied forecast ID |
| `source_type` | enum `CLOUD`, `NETWORK` | Indexed |
| `model_name` | varchar(100) | |
| `model_version` | varchar(50) | |
| `forecast_horizon_minutes` | int unsigned | 1 … `FORECAST_MAX_HORIZON_MINUTES` |
| `generated_at` | timestamp, indexed | Producer generation time |
| `received_at` | timestamp, nullable | Server receipt time |
| `status` | enum `ACTIVE`, `SUPERSEDED`, `EXPIRED` | Default `ACTIVE`, indexed |
| `metadata` | json, nullable | |
| `created_at`, `updated_at` | timestamps | |

Constraints and indexes:

- unique `(source_type, external_id)` — the same ID may exist once per source;
- `(source_type, generated_at)`;
- `(source_type, status, generated_at)`.

When a new forecast for a source is accepted, the previous `ACTIVE` row for that
source becomes `SUPERSEDED` inside the same transaction. History is retained.

### `forecast_predictions`

One predicted value for one metric at one timestamp.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned, PK | |
| `forecast_id` | bigint unsigned, FK → `forecasts.id` | Cascade on delete |
| `metric_name` | varchar(50) | Lower snake case, e.g. `cpu_utilization` |
| `metric_unit` | varchar(20), nullable | `%`, `Mbps`, `ms`, … |
| `predicted_value` | decimal(20,6) | |
| `actual_value` | decimal(20,6), nullable | Observed value when known |
| `predicted_at` | timestamp | Target timestamp |
| `anomaly_risk` | decimal(5,4), nullable | 0–1 |
| `risk_level` | enum `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`, nullable | Indexed |
| `metadata` | json, nullable | |
| `created_at`, `updated_at` | timestamps | |

Unique `(forecast_id, metric_name, predicted_at)` prevents duplicate points
inside one forecast. Index `(metric_name, predicted_at)` supports chart queries.

## Enumerations

| Enum | Values |
| --- | --- |
| `Role` | `ADMINISTRATOR`, `OPERATOR`, `SERVICE` |
| `DeviceStatus` | `ACTIVE`, `INACTIVE`, `MAINTENANCE` |
| `InspectionStatus` | `PASS`, `DEFECT` |
| `ForecastSource` | `CLOUD`, `NETWORK` |
| `ForecastStatus` | `ACTIVE`, `SUPERSEDED`, `EXPIRED` |
| `RiskLevel` | `LOW`, `MEDIUM`, `HIGH`, `CRITICAL` |

## Migrations

| Migration | Purpose |
| --- | --- |
| `0001_01_01_000000_create_users_table` | Users, password reset tokens, sessions |
| `0001_01_01_000001_create_cache_table` | Cache and cache locks |
| `0001_01_01_000002_create_jobs_table` | Queue jobs, batches, failed jobs |
| `2026_11_16_044032_create_personal_access_tokens_table` | Sanctum tokens |
| `2026_11_16_050000_create_garment_monitoring_tables` | Devices, inspections, detections, forecasts, predictions |

Run migrations with:

```bash
php artisan migrate
```

On a fresh local database:

```bash
php artisan migrate:fresh --seed
```

In production, run `php artisan migrate --force` as a single one-off task before
rolling out new application revisions. Do not enable automatic migrations on
every service replica.

## Data retention and integrity

- Inspections and detections are append-only from the API's perspective; there is
  no update endpoint. Duplicate IDs are rejected.
- Deleting a device is restricted while inspections exist; deactivate instead.
- Deleting a forecast cascades to its predictions.
- Deleting an inspection cascades to its detections; the stored image file is
  deleted by the ingestion service when a replacement or cleanup occurs.
- Images are stored privately. The database stores only a relative path and
  metadata, never a permanent public URL.
- Back up MySQL and the object-storage bucket together; a database row without its
  image is detectable because `image_path` is present but the object is missing.

## Test database

`phpunit.xml` sets:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garment_defect_test
DB_USERNAME=root
DB_PASSWORD=
```

Create the database once:

```sql
CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Pest uses `RefreshDatabase`, so the schema is rebuilt for each test run.
