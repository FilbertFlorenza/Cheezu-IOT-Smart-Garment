# 07 — API Documentation

Base path: `/api/v1`. All responses are JSON. The API is implemented in `routes/api.php` and the
controllers under `app/Http/Controllers/Api/V1/`.

For exhaustive request/response examples and producer acceptance checklists, see
[`../docs/api.md`](../docs/api.md) and [`../docs/integration-contracts.md`](../docs/integration-contracts.md).

## Authentication

| Surface | Mechanism |
| --- | --- |
| Ingestion and read endpoints | `Authorization: Bearer <token>` (Sanctum personal access token) |
| Health endpoint | None |

Tokens are issued only through `php artisan service:token` for `SERVICE` accounts. Abilities are
cumulative and checked by the `abilities` middleware (Sanctum `CheckAbilities`, which requires **all**
listed abilities).

| Ability | Grants |
| --- | --- |
| `inspections:write` | `POST /inspections/results` |
| `forecasts:cloud:write` | `POST /forecasts/cloud` |
| `forecasts:network:write` | `POST /forecasts/network` |
| `read` | All `GET` endpoints under the authenticated group |

A service account can be granted several abilities. The seeder creates three service accounts:
Inference Service, Cloud Forecaster, and Network Forecaster.

Authentication failures:

| Situation | Status | Code |
| --- | --- | --- |
| Missing/invalid token | 401 | `UNAUTHENTICATED` |
| Token lacks a required ability | 403 | `FORBIDDEN` |
| Account deactivated (`is_active = false`) | 403 | `ACCOUNT_INACTIVE` |
| Rate limit exceeded | 429 | `RATE_LIMITED` |

## Response envelope

### Success

```json
{
  "success": true,
  "data": {},
  "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX",
  "meta": {}
}
```

- `data` is an object, array, or `null`.
- `meta` is present only when non-empty (for example `pagination`).
- `request_id` echoes `X-Request-Id` when supplied, otherwise a generated ULID. It is also returned in
  the `X-Request-Id` response header.

### Paginated success

```json
{
  "success": true,
  "data": [],
  "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX",
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 25,
      "total": 0,
      "last_page": 1
    }
  }
}
```

There are no `links` or `first_page_url` fields.

### Error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted payload failed validation.",
    "details": {
      "defect_count": [
        "defect_count (2) must equal the number of submitted detections (1)."
      ]
    }
  },
  "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX"
}
```

`details` is omitted when empty (except validation errors, which always include field errors).

### Error codes

| Code | HTTP | Raised by |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Laravel validation on API routes |
| `CONTRACT_MISMATCH` | 422 | `InspectionIngestionService` cross-field mismatch |
| `UNKNOWN_DEVICE` | 422 | Device code not registered |
| `DUPLICATE_INSPECTION` | 409 | `inspection_id` already stored (pre-check or duplicate key) |
| `DUPLICATE_FORECAST` | 409 | `(source_type, forecast_id)` already stored |
| `UNAUTHENTICATED` | 401 | Missing/invalid token |
| `FORBIDDEN` | 403 | Ability mismatch |
| `ACCOUNT_INACTIVE` | 403 | `EnsureUserIsActive` |
| `NOT_FOUND` | 404 | Unknown route/model |
| `RATE_LIMITED` | 429 | Throttling (includes `Retry-After`/limit headers) |
| `HTTP_ERROR` | varies | Other HTTP exceptions |
| `SERVER_ERROR` | 500 | Unhandled exception |

## Rate limits

| Limiter | Applies to | Limit |
| --- | --- | --- |
| `health` | `GET /health` | 30/min per IP |
| `inspections` | `POST /inspections/results` | 120/min per token owner |
| `forecasts` | Both forecast ingestion endpoints | 30/min per token owner |
| `api-read` | All read endpoints | 120/min per token owner |

## Endpoint summary

| Method | Path | Auth | Ability | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/health` | No | — | Liveness + database check |
| POST | `/api/v1/inspections/results` | Yes | `inspections:write` | Ingest inspection result |
| POST | `/api/v1/forecasts/cloud` | Yes | `forecasts:cloud:write` | Ingest cloud forecast |
| POST | `/api/v1/forecasts/network` | Yes | `forecasts:network:write` | Ingest network forecast |
| GET | `/api/v1/devices` | Yes | `read` | List devices |
| GET | `/api/v1/inspections` | Yes | `read` | List inspections |
| GET | `/api/v1/inspections/{inspection_id}` | Yes | `read` | Inspection detail |
| GET | `/api/v1/forecasts/cloud` | Yes | `read` | Cloud forecasts (latest or history) |
| GET | `/api/v1/forecasts/network` | Yes | `read` | Network forecasts (latest or history) |

The API is read-only except for the three ingestion endpoints. There are no update or delete
endpoints.

---

## GET `/api/v1/health`

Unauthenticated. Attempts `select 1` on the default database connection.

```json
{
  "success": true,
  "data": {
    "status": "ok",
    "checks": {
      "database": "ok",
      "storage_disk": "local"
    },
    "timestamp": "2026-11-16T05:00:00+00:00",
    "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX"
  }
}
```

- `200` when the database responds; `503` with `success: false` and `status: "degraded"` otherwise.
- This endpoint does **not** use the standard `ApiResponse` envelope (note `request_id` is inside
  `data` and there is no `error` object).
- It reports the configured storage disk name but does not probe storage I/O.

---

## POST `/api/v1/inspections/results`

Content types:

- `application/json` with top-level fields, or
- `multipart/form-data` with an `image` file plus either top-level fields or a `payload` JSON string.

### Request fields

| Field | Type | Rules |
| --- | --- | --- |
| `inspection_id` | UUID string | required, valid UUID, unique |
| `device_code` | string ≤50 | required; alias `device_id` accepted |
| `inspected_at` | ISO-8601 date-time | required; within backfill window; not >5 min future |
| `overall_status` | `PASS` \| `DEFECT` | required; must match detection count |
| `defect_count` | integer 0–65535 | required; must equal `count(detections)` |
| `processing_time_ms` | integer 0–600000 | nullable |
| `model_version` | string ≤50 | required |
| `metadata` | object | nullable |
| `detections` | array ≤100 | **required** (`present`); empty array allowed for PASS |
| `detections.*.defect_class` | string ≤50 | required |
| `detections.*.confidence` | number 0–1 | required |
| `detections.*.bounding_box` | object | nullable |
| `detections.*.bounding_box.x` | integer ≥0 | required with bounding box |
| `detections.*.bounding_box.y` | integer ≥0 | required with bounding box |
| `detections.*.bounding_box.width` | integer ≥1 | required with bounding box |
| `detections.*.bounding_box.height` | integer ≥1 | required with bounding box |
| `detections.*.metadata` | object | nullable |
| `image` | file | nullable; jpg/jpeg/png/webp; size and dimensions from config |

Cross-field rules (`after()` in `StoreInspectionResultRequest`):

1. `defect_count === count(detections)`.
2. `overall_status` is `DEFECT` iff `count(detections) > 0`.
3. `inspected_at >= now - INSPECTION_BACKFILL_DAYS` (default 7 days) and
   `<= now + INSPECTION_FUTURE_TOLERANCE_MINUTES` (default 5 minutes).

The service re-checks rules 1–2 and returns `CONTRACT_MISMATCH` if a caller bypasses the request.

### Payload wrapper

If `payload` is present:

- A JSON **string** is decoded.
- An **array** is merged as-is.
- Any other type → 422 validation error on `payload`.
- Invalid JSON → 422 `The payload field must contain valid JSON.`

Top-level fields are merged first; if the same key appears in both places, the `payload` value wins
because it is merged into the request after the top-level input.

### Example — PASS with no detections

```json
{
  "inspection_id": "6f1a0f3e-8c1e-4d33-9a2e-2a9d6f4b1c77",
  "device_code": "DEVICE-001",
  "inspected_at": "2026-11-16T04:55:12Z",
  "overall_status": "PASS",
  "defect_count": 0,
  "processing_time_ms": 812,
  "model_version": "v1.1",
  "detections": []
}
```

### Example — DEFECT with detections

```json
{
  "inspection_id": "0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10",
  "device_code": "DEVICE-003",
  "inspected_at": "2026-11-16T04:58:40Z",
  "overall_status": "DEFECT",
  "defect_count": 2,
  "processing_time_ms": 1042,
  "model_version": "v1.1",
  "metadata": { "line": "B" },
  "detections": [
    {
      "defect_class": "hole",
      "confidence": 0.9142,
      "bounding_box": { "x": 210, "y": 88, "width": 64, "height": 71 },
      "metadata": { "reviewed": false }
    },
    {
      "defect_class": "stain",
      "confidence": 0.7735,
      "bounding_box": { "x": 502, "y": 310, "width": 120, "height": 96 }
    }
  ]
}
```

### Responses

| Status | Body |
| --- | --- |
| 201 | Success envelope with the created `InspectionResource` and `meta.duplicate = false` |
| 422 | `VALIDATION_ERROR`, `CONTRACT_MISMATCH`, or `UNKNOWN_DEVICE` |
| 409 | `DUPLICATE_INSPECTION` with `details.inspection_id` |
| 401/403/429 | Auth, ability, inactive, or rate limit errors |

Created response shape:

```json
{
  "success": true,
  "data": {
    "inspection_id": "0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10",
    "device": {
      "device_code": "DEVICE-003",
      "device_name": "Line B — Inspection Camera 01",
      "description": "ESP32-CAM fabric inspection unit at Line B.",
      "location": "Line B",
      "status": "ACTIVE",
      "is_online": true,
      "last_seen_at": "2026-11-16T04:58:41+00:00",
      "created_at": "2026-11-10T01:12:00+00:00"
    },
    "device_id": 3,
    "inspected_at": "2026-11-16T04:58:40+00:00",
    "received_at": "2026-11-16T04:58:41+00:00",
    "overall_status": "DEFECT",
    "defect_count": 2,
    "processing_time_ms": 1042,
    "model_version": "v1.1",
    "image_url": null,
    "metadata": { "line": "B" },
    "detections": [
      {
        "defect_class": "hole",
        "confidence": 0.9142,
        "bounding_box": { "x": 210, "y": 88, "width": 64, "height": 71 },
        "metadata": { "reviewed": false }
      },
      {
        "defect_class": "stain",
        "confidence": 0.7735,
        "bounding_box": { "x": 502, "y": 310, "width": 120, "height": 96 },
        "metadata": null
      }
    ],
    "created_at": "2026-11-16T04:58:41+00:00"
  },
  "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX",
  "meta": { "duplicate": false }
}
```

Notes:

- `device.is_online` is computed at serialisation time.
- `image_url` is a 10-minute signed URL (local `storage.local` route or S3) or `null` when no image was
  stored or the object is missing.
- `detections` is omitted from list responses when the relation is not loaded; detail/creation load it.

---

## POST `/api/v1/forecasts/cloud` and `/api/v1/forecasts/network`

Both accept the same body; the endpoint determines `source_type`.

### Request fields

| Field | Type | Rules |
| --- | --- | --- |
| `forecast_id` | UUID | required; unique per source |
| `model_name` | string ≤100 | required |
| `model_version` | string ≤50 | required |
| `forecast_horizon_minutes` | integer | required, 1..`FORECAST_MAX_HORIZON_MINUTES` (default 10080) |
| `generated_at` | date-time | required; not >5 minutes future |
| `metadata` | object | nullable |
| `predictions` | array | required, 1..`FORECAST_MAX_PREDICTIONS` (default 500) |
| `predictions.*.metric_name` | string ≤50 | required; `^[a-z0-9_]+$` |
| `predictions.*.metric_unit` | string ≤20 | nullable |
| `predictions.*.predicted_value` | numeric | required |
| `predictions.*.actual_value` | numeric | nullable |
| `predictions.*.predicted_at` | date-time | required |
| `predictions.*.anomaly_risk` | numeric 0–1 | nullable |
| `predictions.*.risk_level` | `LOW`\|`MEDIUM`\|`HIGH`\|`CRITICAL` | nullable |
| `predictions.*.metadata` | object | nullable |

Cross-field rule: the combination `(metric_name, predicted_at)` must be unique within the payload
(timestamps compared in UTC).

### Example

```json
{
  "forecast_id": "b7d4a2f0-6c3e-4a1b-9e8d-2f5c7a9b1d34",
  "model_name": "cloud-lstm",
  "model_version": "v1.2",
  "forecast_horizon_minutes": 240,
  "generated_at": "2026-11-16T04:30:00Z",
  "metadata": { "region": "ap-southeast-1" },
  "predictions": [
    {
      "metric_name": "cpu_utilization",
      "metric_unit": "%",
      "predicted_value": 68.4,
      "actual_value": 64.1,
      "predicted_at": "2026-11-16T05:00:00Z",
      "anomaly_risk": 0.78,
      "risk_level": "HIGH"
    },
    {
      "metric_name": "memory_utilization",
      "metric_unit": "%",
      "predicted_value": 74.9,
      "predicted_at": "2026-11-16T05:00:00Z"
    }
  ]
}
```

### Responses

| Status | Body |
| --- | --- |
| 201 | `ForecastResource` including predictions (loaded by the service) |
| 422 | `VALIDATION_ERROR` |
| 409 | `DUPLICATE_FORECAST` with `details.source` and `details.forecast_id` |

Side effect: all previously `ACTIVE` forecasts for the same source become `SUPERSEDED` in the same
transaction.

---

## GET `/api/v1/devices`

Query parameters:

| Parameter | Type | Rules |
| --- | --- | --- |
| `search` | string ≤100 | matches `device_code`, `device_name`, or `location` (`LIKE`) |
| `status` | `ACTIVE`\|`INACTIVE`\|`MAINTENANCE` | exact match |
| `per_page` | integer 1–100 | default 25 |

Ordered by `device_code`. Each item includes `inspections_count` (withCount) but not
`latest_inspection_at` (relation not loaded).

---

## GET `/api/v1/inspections`

Query parameters:

| Parameter | Type | Rules |
| --- | --- | --- |
| `search` | string ≤100 | `inspection_id`, `model_version`, device code/name |
| `device` | string ≤50 | device code |
| `status` | `PASS`\|`DEFECT` | exact match |
| `defect_class` | string ≤50 | inspections having a detection of this class |
| `from` | date | `inspected_at >= from 00:00:00` |
| `to` | date | `inspected_at <= to 23:59:59`; must be `after_or_equal:from` |
| `per_page` | integer 1–100 | default 25 |

Ordered by `inspected_at` descending. Includes `device` summary and `detections_count`; detections
themselves are **not** included in the list.

---

## GET `/api/v1/inspections/{inspection_id}`

- `{inspection_id}` is the UUID (`Inspection::getRouteKeyName()`), not the numeric ID.
- Loads `device` and `detections`.
- Unknown UUID → 404 `NOT_FOUND`.

---

## GET `/api/v1/forecasts/cloud` and `/api/v1/forecasts/network`

Query parameters:

| Parameter | Type | Rules |
| --- | --- | --- |
| `latest` | boolean | when truthy, returns the newest `ACTIVE` forecast for the source or `data: null` |
| `per_page` | integer 1–50 | default 10 (history mode) |

History mode returns forecasts of that source ordered by `generated_at` descending. Predictions are
**not** eager-loaded in history mode, so `predictions` and `warning_count` are omitted.

Latest mode response:

```json
{
  "success": true,
  "data": {
    "forecast_id": "b7d4a2f0-6c3e-4a1b-9e8d-2f5c7a9b1d34",
    "source": "CLOUD",
    "model_name": "cloud-lstm",
    "model_version": "v1.2",
    "forecast_horizon_minutes": 240,
    "generated_at": "2026-11-16T04:30:00+00:00",
    "received_at": "2026-11-16T04:30:12+00:00",
    "status": "ACTIVE",
    "metadata": { "region": "ap-southeast-1" },
    "predictions": [
      {
        "metric_name": "cpu_utilization",
        "metric_label": "CPU Utilization",
        "metric_unit": "%",
        "predicted_value": 68.4,
        "actual_value": 64.1,
        "predicted_at": "2026-11-16T05:00:00+00:00",
        "anomaly_risk": 0.78,
        "risk_level": "HIGH",
        "metadata": null
      }
    ],
    "warning_count": 1
  },
  "request_id": "01JCH6Z3K8V9Q2M4N7P1R5T8WX"
}
```

`metric_label` is resolved from `config/forecast.php` by source, falling back to a humanised name.
`warning_count` counts predictions whose `risk_level` is `HIGH` or `CRITICAL`.

---

## cURL examples

```bash
# Health (no token)
curl -s http://localhost:8000/api/v1/health

# Ingest a PASS
curl -s -X POST http://localhost:8000/api/v1/inspections/results \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "inspection_id": "6f1a0f3e-8c1e-4d33-9a2e-2a9d6f4b1c77",
    "device_code": "DEVICE-001",
    "inspected_at": "2026-11-16T04:55:12Z",
    "overall_status": "PASS",
    "defect_count": 0,
    "model_version": "v1.1",
    "detections": []
  }'

# Ingest with an image
curl -s -X POST http://localhost:8000/api/v1/inspections/results \
  -H "Authorization: Bearer $TOKEN" \
  -F 'payload={"inspection_id":"0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10","device_code":"DEVICE-003","inspected_at":"2026-11-16T04:58:40Z","overall_status":"DEFECT","defect_count":1,"model_version":"v1.1","detections":[{"defect_class":"hole","confidence":0.91}]}' \
  -F "image=@fabric.jpg"

# List defective inspections for a device
curl -s -G http://localhost:8000/api/v1/inspections \
  -H "Authorization: Bearer $TOKEN" \
  --data-urlencode "device=DEVICE-003" \
  --data-urlencode "status=DEFECT"

# Latest network forecast
curl -s "http://localhost:8000/api/v1/forecasts/network?latest=1" \
  -H "Authorization: Bearer $TOKEN"
```

## Versioning

- Only `v1` exists. The prefix is hardcoded in `routes/api.php`.
- There is no deprecation mechanism, content negotiation, or OpenAPI document.
- API changes that break producers must be treated as contract changes: update `docs/api.md`,
  `docs/integration-contracts.md`, and add tests.
