# HTTP API v1

Base URL: `{APP_URL}/api/v1`

All endpoints return JSON. Protect machine endpoints with a Sanctum bearer token
issued by `php artisan service:token`.

## Authentication

```
Authorization: Bearer <token>
Accept: application/json
```

Tokens carry abilities. A token can only call endpoints covered by its abilities:

| Ability | Grants |
| --- | --- |
| `inspections:write` | `POST /inspections/results` |
| `forecasts:cloud:write` | `POST /forecasts/cloud` |
| `forecasts:network:write` | `POST /forecasts/network` |
| `read` | All `GET` endpoints |

A token without the right ability receives `403 FORBIDDEN`. A missing or invalid
token receives `401 UNAUTHENTICATED`. If the owning service account was
deactivated, the request receives `403 ACCOUNT_INACTIVE`.

## Response envelope

Success:

```json
{
  "success": true,
  "data": {},
  "request_id": "01J8Z3K6R5T0N9M2P4Q7S1V8XW",
  "meta": {}
}
```

`meta` is present when it carries pagination or ingestion flags.

Error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted payload failed validation.",
    "details": {
      "inspection_id": ["The inspection id field is required."]
    }
  },
  "request_id": "01J8Z3K6R5T0N9M2P4Q7S1V8XW"
}
```

`request_id` is also returned in the `X-Request-Id` response header and appears in
server logs.

### Error codes

| HTTP | Code | Meaning |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | No valid bearer token |
| 403 | `FORBIDDEN` | Token lacks the required ability |
| 403 | `ACCOUNT_INACTIVE` | The service account is disabled |
| 404 | `NOT_FOUND` | Unknown route or resource |
| 409 | `DUPLICATE_INSPECTION` | `inspection_id` already stored |
| 409 | `DUPLICATE_FORECAST` | `forecast_id` already stored for this source |
| 422 | `VALIDATION_ERROR` | Field or cross-field validation failed |
| 422 | `UNKNOWN_DEVICE` | `device_code` is not registered |
| 422 | `CONTRACT_MISMATCH` | Domain-level contract violation |
| 429 | `RATE_LIMITED` | Throttle exceeded; honour `Retry-After` |
| 500 | `SERVER_ERROR` | Unexpected failure |

### Rate limits

| Limiter | Limit |
| --- | --- |
| Inspection ingestion | 120 requests/minute per token/IP |
| Forecast ingestion | 30 requests/minute per token/IP |
| Read endpoints | 120 requests/minute per token/IP |
| Health | 30 requests/minute per IP |

Exceeding a limit returns `429` with `Retry-After` and `X-RateLimit-*` headers.

## Pagination

List endpoints accept `per_page` and return:

```json
{
  "success": true,
  "data": [],
  "request_id": "01J...",
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 25,
      "total": 128,
      "last_page": 6
    }
  }
}
```

`per_page` ranges: inspections and devices `1–100` (default 25), forecasts `1–50`
(default 10). Unknown query parameters are ignored.

---

## POST `/inspections/results`

Store one inspection result. Requires `inspections:write`.

### Content types

- `application/json` — the payload is the request body.
- `multipart/form-data` — send the JSON document in a `payload` field and the
  image in an `image` field.

### JSON body

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `inspection_id` | uuid | yes | Idempotency key |
| `device_code` | string(50) | yes | Must match a registered device |
| `device_id` | string(50) | alias | Accepted only when `device_code` is absent |
| `inspected_at` | ISO 8601 date | yes | Not more than 5 minutes in the future; not older than 7 days |
| `overall_status` | `PASS` or `DEFECT` | yes | Must match the detection count |
| `defect_count` | integer 0–65535 | yes | Must equal the number of detections |
| `processing_time_ms` | integer 0–600000 | no | Model latency |
| `model_version` | string(50) | yes | |
| `metadata` | object | no | Stored as JSON |
| `detections` | array 0–100 | yes | Send `[]` for a PASS |
| `detections[].defect_class` | string(50) | yes | e.g. `hole`, `stain`, `tear` |
| `detections[].confidence` | number 0–1 | yes | |
| `detections[].bounding_box` | object | no | All four fields or none |
| `detections[].bounding_box.x` | integer ≥ 0 | with box | |
| `detections[].bounding_box.y` | integer ≥ 0 | with box | |
| `detections[].bounding_box.width` | integer ≥ 1 | with box | |
| `detections[].bounding_box.height` | integer ≥ 1 | with box | |
| `detections[].metadata` | object | no | |

Cross-field rules:

1. `defect_count` must equal `count(detections)`.
2. Zero detections requires `overall_status = PASS`.
3. One or more detections requires `overall_status = DEFECT`.

### Image upload

`multipart/form-data` fields:

| Field | Value |
| --- | --- |
| `payload` | JSON string containing the inspection document |
| `image` | File: jpg, jpeg, png, or webp; ≤ `INSPECTION_IMAGE_MAX_KB` (default 5120 KB); max dimension `INSPECTION_IMAGE_MAX_DIMENSION` |

Example:

```bash
curl --request POST "$APP_URL/api/v1/inspections/results" \
  --header "Authorization: Bearer $TOKEN" \
  --header "Accept: application/json" \
  --form 'payload={
    "inspection_id":"7fbb462d-83ed-4285-a8f4-48592c8cac3d",
    "device_code":"DEVICE-002",
    "inspected_at":"2026-11-16T04:54:42Z",
    "overall_status":"PASS",
    "defect_count":0,
    "processing_time_ms":540,
    "model_version":"v1.1",
    "detections":[]
  }' \
  --form "image=@fabric.png;type=image/png"
```

The stored image is private. The response contains a signed URL valid for ten
minutes:

```json
"image_url": "https://host/storage/inspections/2026/11/<uuid>.png?expires=...&signature=..."
```

### Example — two detections

```bash
curl --request POST "$APP_URL/api/v1/inspections/results" \
  --header "Authorization: Bearer $TOKEN" \
  --header "Content-Type: application/json" \
  --header "Accept: application/json" \
  --data '{
    "inspection_id": "a3baacb9-527b-4ab5-adeb-0b7c4e2b775c",
    "device_code": "DEVICE-001",
    "inspected_at": "2026-11-16T04:54:18Z",
    "overall_status": "DEFECT",
    "defect_count": 2,
    "processing_time_ms": 812,
    "model_version": "v1.1",
    "metadata": {"batch": "B-1042"},
    "detections": [
      {"defect_class": "hole", "confidence": 0.9412,
       "bounding_box": {"x": 120, "y": 80, "width": 60, "height": 55}},
      {"defect_class": "stain", "confidence": 0.88}
    ]
  }'
```

Response `201 Created`:

```json
{
  "success": true,
  "data": {
    "inspection_id": "a3baacb9-527b-4ab5-adeb-0b7c4e2b775c",
    "device": {
      "device_code": "DEVICE-001",
      "device_name": "Line A — Inspection Camera 01",
      "status": "ACTIVE",
      "is_online": true,
      "last_seen_at": "2026-11-16T04:54:19+00:00"
    },
    "inspected_at": "2026-11-16T04:54:18+00:00",
    "received_at": "2026-11-16T04:54:18+00:00",
    "overall_status": "DEFECT",
    "defect_count": 2,
    "processing_time_ms": 812,
    "model_version": "v1.1",
    "metadata": {"batch": "B-1042"},
    "detections": [
      {
        "defect_class": "hole",
        "confidence": 0.9412,
        "bounding_box": {"x": 120, "y": 80, "width": 60, "height": 55},
        "metadata": null
      },
      {
        "defect_class": "stain",
        "confidence": 0.88,
        "bounding_box": null,
        "metadata": null
      }
    ],
    "created_at": "2026-11-16T04:54:18+00:00"
  },
  "request_id": "01J...",
  "meta": {"duplicate": false}
}
```

### Duplicate submission

```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_INSPECTION",
    "message": "An inspection with this inspection_id has already been recorded.",
    "details": {"inspection_id": "a3baacb9-..."}
  },
  "request_id": "01J..."
}
```

HTTP `409`. The original row is untouched. Treat `409 DUPLICATE_INSPECTION` as a
successful retry unless the payload differs materially.

### Unknown device

HTTP `422`:

```json
{
  "success": false,
  "error": {
    "code": "UNKNOWN_DEVICE",
    "message": "The device is not registered. Register the device before submitting inspections.",
    "details": {"device_code": "DEVICE-999"}
  },
  "request_id": "01J..."
}
```

---

## GET `/devices`

Requires `read`.

Query parameters:

| Parameter | Values |
| --- | --- |
| `search` | Matches code, name, or location |
| `status` | `ACTIVE`, `INACTIVE`, `MAINTENANCE` |
| `per_page` | 1–100, default 25 |

Each device includes `inspections_count` and a computed `is_online` flag.

```bash
curl "$APP_URL/api/v1/devices?status=ACTIVE&per_page=50" \
  --header "Authorization: Bearer $TOKEN" \
  --header "Accept: application/json"
```

---

## GET `/inspections`

Requires `read`.

| Parameter | Values |
| --- | --- |
| `search` | Matches inspection UUID or device code/name |
| `device` | Device code, e.g. `DEVICE-001` |
| `status` | `PASS` or `DEFECT` |
| `defect_class` | Filter to inspections containing a class |
| `from` | Date/time lower bound on `inspected_at` |
| `to` | Date/time upper bound; must be ≥ `from` |
| `per_page` | 1–100, default 25 |

Results are ordered newest first and include `detections_count`.

## GET `/inspections/{inspection_id}`

Requires `read`. Resolves by the public UUID and includes the device and every
detection.

```bash
curl "$APP_URL/api/v1/inspections/a3baacb9-527b-4ab5-adeb-0b7c4e2b775c" \
  --header "Authorization: Bearer $TOKEN" \
  --header "Accept: application/json"
```

Unknown UUID → `404 NOT_FOUND`.

---

## POST `/forecasts/cloud`

Store a cloud-resource forecast. Requires `forecasts:cloud:write`.

## POST `/forecasts/network`

Store a network-traffic forecast. Requires `forecasts:network:write`.

Both use the same body, with different metric catalogues:

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `forecast_id` | uuid | yes | Unique per source |
| `model_name` | string(100) | yes | |
| `model_version` | string(50) | yes | |
| `forecast_horizon_minutes` | integer | yes | 1 … `FORECAST_MAX_HORIZON_MINUTES` (default 10080) |
| `generated_at` | ISO 8601 date | yes | Not more than 5 minutes in the future |
| `metadata` | object | no | |
| `predictions` | array | yes | 1 … `FORECAST_MAX_PREDICTIONS` (default 500) |
| `predictions[].metric_name` | string(50) | yes | `^[a-z0-9_]+$` |
| `predictions[].metric_unit` | string(20) | no | |
| `predictions[].predicted_value` | number | yes | |
| `predictions[].actual_value` | number | no | |
| `predictions[].predicted_at` | ISO 8601 date | yes | |
| `predictions[].anomaly_risk` | number 0–1 | no | |
| `predictions[].risk_level` | `LOW`, `MEDIUM`, `HIGH`, `CRITICAL` | no | |
| `predictions[].metadata` | object | no | |

Within one submission, `(metric_name, predicted_at)` must be unique.

Configured cloud metrics: `cpu_utilization`, `memory_utilization`,
`disk_io_read`, `disk_io_write`, `network_in`, `network_out`.

Configured network metrics: `network_throughput`, `packet_count`,
`traffic_volume`, `latency`, `packet_loss`, `congestion_risk`.

Other metric names are accepted and displayed with a humanised label.

Example:

```bash
curl --request POST "$APP_URL/api/v1/forecasts/cloud" \
  --header "Authorization: Bearer $CLOUD_TOKEN" \
  --header "Content-Type: application/json" \
  --header "Accept: application/json" \
  --data '{
    "forecast_id": "097a83a7-0473-45af-a1bf-0df1b03ddb88",
    "model_name": "cloud-lstm",
    "model_version": "v9.9",
    "forecast_horizon_minutes": 120,
    "generated_at": "2026-11-16T04:55:03Z",
    "metadata": {"region": "ap-southeast-1"},
    "predictions": [
      {"metric_name":"cpu_utilization","metric_unit":"%","predicted_value":71.4,
       "actual_value":68.2,"predicted_at":"2026-11-16T05:25:03Z",
       "anomaly_risk":0.82,"risk_level":"HIGH"},
      {"metric_name":"memory_utilization","metric_unit":"%","predicted_value":88.0,
       "predicted_at":"2026-11-16T05:25:03Z","risk_level":"CRITICAL"}
    ]
  }'
```

Response `201 Created` with `source` (`CLOUD` or `NETWORK`), `status: "ACTIVE"`,
and every stored prediction including `metric_label`.

A second successful submission for the same source marks the previous one
`SUPERSEDED`. Duplicate `(source_type, forecast_id)` returns
`409 DUPLICATE_FORECAST`.

---

## GET `/forecasts/cloud`

## GET `/forecasts/network`

Require `read`.

| Parameter | Values |
| --- | --- |
| `latest` | `1`/`true` returns only the newest ACTIVE forecast, or `data: null` |
| `per_page` | 1–50, default 10 |

Without `latest`, the response is a paginated history ordered by `generated_at`
descending, including `SUPERSEDED` rows. Each item includes `warning_count` and
its predictions.

```json
{
  "success": true,
  "data": {
    "forecast_id": "097a83a7-...",
    "source": "CLOUD",
    "model_name": "cloud-lstm",
    "model_version": "v9.9",
    "forecast_horizon_minutes": 120,
    "generated_at": "2026-11-16T04:55:03+00:00",
    "status": "ACTIVE",
    "predictions": [],
    "warning_count": 2
  },
  "request_id": "01J..."
}
```

When no active forecast exists and `latest=1` is requested:

```json
{"success": true, "data": null, "request_id": "01J..."}
```

---

## GET `/health`

Public, unauthenticated, 30 requests/minute per IP.

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
    "request_id": "01J..."
  }
}
```

Returns `200` when the database is reachable and `503` with
`status: "degraded"` when it is not. This endpoint is used by the container and
load-balancer health checks. `/up` is the lightweight framework-level probe.

`/health` is the one endpoint that reports its own shape; it still includes
`success`, `data`, and `request_id`, but a degraded response carries the status
inside `data` rather than using the error envelope.

---

## cURL reference

```bash
APP_URL=http://127.0.0.1:8000
TOKEN="1|..."

# Health
curl "$APP_URL/api/v1/health"

# List devices
curl "$APP_URL/api/v1/devices?per_page=10" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# List defective inspections for one device
curl "$APP_URL/api/v1/inspections?status=DEFECT&device=DEVICE-001" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Latest cloud forecast
curl "$APP_URL/api/v1/forecasts/cloud?latest=1" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```
