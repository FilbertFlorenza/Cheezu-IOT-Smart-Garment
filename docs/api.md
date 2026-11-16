# HTTP API v1

> Nine JSON endpoints under `/api/v1`: three ingestion endpoints for machines, five read endpoints for
> integrations, and one public health probe. Every response carries a request ID, and every failure
> carries a stable error code.

**Who this is for:** backend developers and anyone calling the API by hand.
**What you'll learn:** how to authenticate, how the envelope works, how to call each endpoint, and what
each error means.
**Looking for producer rules instead?** [integration-contracts.md](integration-contracts.md) explains
retry policy, idempotency, and acceptance criteria. This page explains the mechanics.

---

## The short version

1. **Base URL:** `{APP_URL}/api/v1`. Locally that is `http://127.0.0.1:8000/api/v1`.
2. **Auth:** `Authorization: Bearer <token>` plus `Accept: application/json`. Tokens are issued with
   `php artisan service:token` and carry abilities.
3. **Success:** `{ "success": true, "data": ..., "request_id": "01J...", "meta": ... }`.
4. **Failure:** `{ "success": false, "error": { "code", "message", "details" }, "request_id": "01J..." }`.
5. **Idempotent ingestion:** repeat a UUID and you get `409`, not a duplicate row.

## How it works

### Authentication and abilities

| Ability | Grants access to |
| --- | --- |
| `inspections:write` | `POST /inspections/results` |
| `forecasts:cloud:write` | `POST /forecasts/cloud` |
| `forecasts:network:write` | `POST /forecasts/network` |
| `read` | All `GET` endpoints inside the authenticated group |

A token can hold several abilities. Middleware uses Sanctum's `CheckAbilities`, which requires **all**
abilities listed on the route — so grant `read` explicitly to any token that must call `GET`.

Failure modes:

| Situation | Status | Code |
| --- | --- | --- |
| No token, bad token, revoked token | `401` | `UNAUTHENTICATED` |
| Token lacks the route's ability | `403` | `FORBIDDEN` |
| Owning service account is deactivated | `403` | `ACCOUNT_INACTIVE` |
| Too many requests | `429` | `RATE_LIMITED` |

### Response envelope

Every endpoint except `/health` on a degraded database uses one of these two shapes.

**Success**

```json
{
  "success": true,
  "data": {},
  "request_id": "01J8Z3K6R5T0N9M2P4Q7S1V8XW",
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

| Field | Meaning |
| --- | --- |
| `success` | Always `true` here |
| `data` | Object, array, or `null` |
| `request_id` | ULID; also returned in the `X-Request-Id` header and written to logs |
| `meta` | Present only when non-empty; carries `pagination` or `duplicate` |

Pagination has exactly four fields. There are no `links`, `first_page_url`, or `next_page_url` keys.

**Error**

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
  "request_id": "01J8Z3K6R5T0N9M2P4Q7S1V8XW"
}
```

`details` is omitted when empty, except validation errors, which always include field errors.

### Error codes

| Status | Code | Meaning | Typical fix |
| --- | --- | --- | --- |
| `401` | `UNAUTHENTICATED` | Missing, invalid, or revoked token | Re-issue the token |
| `403` | `FORBIDDEN` | Token lacks the required ability | Add the ability or use the right token |
| `403` | `ACCOUNT_INACTIVE` | The service account was deactivated | Ask an administrator to reactivate it |
| `404` | `NOT_FOUND` | Unknown route, UUID, or model | Check the path and UUID |
| `409` | `DUPLICATE_INSPECTION` | `inspection_id` already stored | Treat as success; do not change the UUID |
| `409` | `DUPLICATE_FORECAST` | `forecast_id` already stored for this source | Treat as success |
| `422` | `VALIDATION_ERROR` | Field or cross-field rule failed | Read `details` and fix the payload |
| `422` | `UNKNOWN_DEVICE` | `device_code` is not registered | Register the device first |
| `422` | `CONTRACT_MISMATCH` | Service-level consistency failure | Make status and count agree |
| `429` | `RATE_LIMITED` | Throttle exceeded | Wait for `Retry-After`, then retry |
| varies | `HTTP_ERROR` | Another HTTP exception | Inspect `message` and status |
| `500` | `SERVER_ERROR` | Unexpected failure | Retry with backoff; escalate with `request_id` |

Producer retry behaviour for each code is in
[integration-contracts.md](integration-contracts.md#handling-responses).

### Rate limits

| Limiter | Applies to | Limit |
| --- | --- | --- |
| `health` | `GET /health` | 30/min per IP |
| `inspections` | `POST /inspections/results` | 120/min per token owner |
| `forecasts` | Both forecast ingestion endpoints | 30/min per token owner |
| `api-read` | All read endpoints | 120/min per token owner |

Exceeding a limit returns `429` with `Retry-After` and `X-RateLimit-*` headers.

### Pagination and query rules

| Endpoint family | `per_page` range | Default |
| --- | --- | --- |
| Inspections, devices | 1–100 | 25 |
| Forecast history | 1–50 | 10 |

Unknown query parameters are ignored. Truthy booleans accept `1` and `true`.

## Step by step: a five-minute walkthrough

### 0. Issue a token

```bash
php artisan service:token "Inference Service" \
  --ability=inspections:write --ability=read
```

Copy the printed value. It is shown once.

```bash
export APP_URL="http://127.0.0.1:8000"
export TOKEN="paste-the-token-here"
```

PowerShell: `$env:APP_URL = "http://127.0.0.1:8000"` and `$env:TOKEN = "..."`.

### 1. Check the service is up

```bash
curl -s "$APP_URL/api/v1/health"
```

**You should see** `"status":"ok"` and `"database":"ok"` with HTTP 200. No token is needed.

### 2. Send a PASS

```bash
curl -i -X POST "$APP_URL/api/v1/inspections/results" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "inspection_id": "6f1a0f3e-8c1e-4d33-9a2e-2a9d6f4b1c77",
    "device_code": "DEVICE-001",
    "inspected_at": "2026-11-16T04:55:12Z",
    "overall_status": "PASS",
    "defect_count": 0,
    "processing_time_ms": 812,
    "model_version": "v1.1",
    "detections": []
  }'
```

**You should see** `HTTP/1.1 201 Created` and `"meta":{"duplicate":false}`.

### 3. Send the same PASS again

Run the exact same command. **You should see** `HTTP/1.1 409 Conflict` with
`"code":"DUPLICATE_INSPECTION"`. The stored row is unchanged. That is the intended idempotency
behaviour.

### 4. Send a DEFECT with an image

`multipart/form-data` carries the JSON document in `payload` and the file in `image`:

```bash
curl -i -X POST "$APP_URL/api/v1/inspections/results" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F 'payload={
    "inspection_id": "0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10",
    "device_code": "DEVICE-003",
    "inspected_at": "2026-11-16T04:58:40Z",
    "overall_status": "DEFECT",
    "defect_count": 1,
    "model_version": "v1.1",
    "detections": [
      {"defect_class": "hole", "confidence": 0.91,
       "bounding_box": {"x": 210, "y": 88, "width": 64, "height": 71}}
    ]
  }' \
  -F "image=@fabric.jpg;type=image/jpeg"
```

**You should see** `201` and an `image_url` valid for 10 minutes. Use it immediately; it expires.

### 5. Send a forecast

```bash
curl -i -X POST "$APP_URL/api/v1/forecasts/cloud" \
  -H "Authorization: Bearer $CLOUD_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "forecast_id": "b7d4a2f0-6c3e-4a1b-9e8d-2f5c7a9b1d34",
    "model_name": "cloud-lstm",
    "model_version": "v1.2",
    "forecast_horizon_minutes": 240,
    "generated_at": "2026-11-16T04:30:00Z",
    "predictions": [
      {
        "metric_name": "cpu_utilization",
        "metric_unit": "%",
        "predicted_value": 68.4,
        "actual_value": 64.1,
        "predicted_at": "2026-11-16T05:00:00Z",
        "anomaly_risk": 0.78,
        "risk_level": "HIGH"
      }
    ]
  }'
```

**You should see** `201`, `"status":"ACTIVE"`, and the stored predictions with `metric_label`.

### 6. Read it back

```bash
curl -s "$APP_URL/api/v1/inspections?status=DEFECT&per_page=5" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

curl -s "$APP_URL/api/v1/forecasts/cloud?latest=1" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

**You should see** paginated inspections and the active cloud forecast with its predictions.

## Endpoint reference

### `POST /inspections/results`

Requires `inspections:write`. Accepts `application/json`, or `multipart/form-data` when an image is
attached.

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `inspection_id` | UUID | yes | Unique forever; the idempotency key |
| `device_code` | string(50) | yes | Must match a registered device |
| `device_id` | string(50) | alias | Used only when `device_code` is absent |
| `inspected_at` | ISO 8601 | yes | ≤ 5 min future, ≥ `INSPECTION_BACKFILL_DAYS` old (7 by default) |
| `overall_status` | `PASS` / `DEFECT` | yes | Must match the detection count |
| `defect_count` | integer 0–65535 | yes | Must equal `count(detections)` |
| `processing_time_ms` | integer 0–600000 | no | |
| `model_version` | string(50) | yes | |
| `metadata` | object | no | Stored as JSON |
| `detections` | array 0–100 | **yes** | Send `[]` for a PASS; omitting the field is invalid |
| `detections[].defect_class` | string(50) | yes | Free-form; configured classes are hints |
| `detections[].confidence` | number 0–1 | yes | |
| `detections[].bounding_box` | object | no | All four fields or none |
| `detections[].bounding_box.x` / `.y` | integer ≥ 0 | with box | Pixels |
| `detections[].bounding_box.width` / `.height` | integer ≥ 1 | with box | Pixels |
| `detections[].metadata` | object | no | |
| `image` | file | no | jpg, jpeg, png, webp; ≤ 5120 KB; ≤ 4096 px per axis by default |

Cross-field rules:

1. `defect_count` equals the number of detections.
2. Zero detections means `PASS`; one or more means `DEFECT`.
3. `inspected_at` is inside the backfill and future tolerance window.

The payload wrapper:

- `payload` may be a JSON string or an object.
- Invalid JSON or a non-object returns a `422` error on `payload`.
- The decoded payload is merged over the top-level fields, so **the payload wins** if a key appears in
  both places.
- `device_id` is copied to `device_code` only when `device_code` is missing.
- `device_code` is trimmed; the form request does not uppercase it.

Example — two detections:

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
      "bounding_box": { "x": 210, "y": 88, "width": 64, "height": 71 }
    },
    {
      "defect_class": "stain",
      "confidence": 0.7735,
      "bounding_box": { "x": 502, "y": 310, "width": 120, "height": 96 }
    }
  ]
}
```

Created response (`201`):

```json
{
  "success": true,
  "data": {
    "inspection_id": "0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10",
    "device": {
      "device_code": "DEVICE-003",
      "device_name": "Line B — Inspection Camera 01",
      "status": "ACTIVE",
      "is_online": true,
      "last_seen_at": "2026-11-16T04:58:41+00:00"
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
        "metadata": null
      }
    ],
    "created_at": "2026-11-16T04:58:41+00:00"
  },
  "request_id": "01J...",
  "meta": { "duplicate": false }
}
```

Notes:

- `image_url` is a 10-minute signed URL, or `null` when no image was stored.
- `detections` is present on creation and detail responses, not in list responses.
- `device.is_online` is recomputed at serialisation time.

Duplicate (`409`):

```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_INSPECTION",
    "message": "An inspection with this inspection_id has already been recorded.",
    "details": { "inspection_id": "0c9b4f2a-..." }
  },
  "request_id": "01J..."
}
```

Unknown device (`422`):

```json
{
  "success": false,
  "error": {
    "code": "UNKNOWN_DEVICE",
    "message": "The device is not registered. Register the device before submitting inspections.",
    "details": { "device_code": "DEVICE-999" }
  },
  "request_id": "01J..."
}
```

### `POST /forecasts/cloud` and `POST /forecasts/network`

Require `forecasts:cloud:write` and `forecasts:network:write` respectively. Both accept the same body;
the URL decides `source_type`.

| Field | Type | Required | Rules |
| --- | --- | --- | --- |
| `forecast_id` | UUID | yes | Unique per source |
| `model_name` | string(100) | yes | |
| `model_version` | string(50) | yes | |
| `forecast_horizon_minutes` | integer | yes | 1 … `FORECAST_MAX_HORIZON_MINUTES` (10080 by default) |
| `generated_at` | ISO 8601 | yes | ≤ 5 min future |
| `metadata` | object | no | |
| `predictions` | array | yes | 1 … `FORECAST_MAX_PREDICTIONS` (500 by default) |
| `predictions[].metric_name` | string(50) | yes | `^[a-z0-9_]+$` |
| `predictions[].metric_unit` | string(20) | no | |
| `predictions[].predicted_value` | number | yes | |
| `predictions[].actual_value` | number | no | |
| `predictions[].predicted_at` | ISO 8601 | yes | |
| `predictions[].anomaly_risk` | number 0–1 | no | |
| `predictions[].risk_level` | `LOW` / `MEDIUM` / `HIGH` / `CRITICAL` | no | |
| `predictions[].metadata` | object | no | |

Within one submission, `(metric_name, predicted_at)` must be unique. Across submissions it may repeat;
that is how history works.

Response `201` with `forecast_id`, `source`, `status: "ACTIVE"`, and every prediction including
`metric_label`. A successful new forecast marks the previous `ACTIVE` forecast **for the same source**
`SUPERSEDED`. A duplicate `(source_type, forecast_id)` returns `409 DUPLICATE_FORECAST` and changes
nothing.

### `GET /devices`

Requires `read`.

| Parameter | Values |
| --- | --- |
| `search` | Matches code, name, or location |
| `status` | `ACTIVE`, `INACTIVE`, `MAINTENANCE` |
| `per_page` | 1–100, default 25 |

Ordered by `device_code`. Each item includes `is_online` and `inspections_count`;
`latest_inspection_at` is omitted because the relation is not loaded for this endpoint.

### `GET /inspections`

Requires `read`.

| Parameter | Values |
| --- | --- |
| `search` | Matches inspection UUID, model version, or device code/name |
| `device` | Device code, e.g. `DEVICE-001` |
| `status` | `PASS` or `DEFECT` |
| `defect_class` | Inspections containing at least one detection of this class |
| `from` | Lower bound on `inspected_at` (date) |
| `to` | Upper bound on `inspected_at` (date); must be ≥ `from` |
| `per_page` | 1–100, default 25 |

Newest first. Each item includes a compact `device` object and `detections_count`, but **not** the
detection rows.

### `GET /inspections/{inspection_id}`

Requires `read`. The path parameter is the UUID, not the numeric ID. Loads the device and every
detection. Unknown UUID → `404 NOT_FOUND`.

```bash
curl "$APP_URL/api/v1/inspections/0c9b4f2a-3f6d-4c8e-b1a7-5e2d9c7a4b10" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

### `GET /forecasts/cloud` and `GET /forecasts/network`

Require `read`.

| Parameter | Values |
| --- | --- |
| `latest` | `1` or `true` returns the newest `ACTIVE` forecast, or `data: null` |
| `per_page` | 1–50, default 10 (history mode) |

**Latest mode** includes the full prediction list and `warning_count`.

**History mode** is paginated, newest `generated_at` first, and includes `SUPERSEDED` rows. Because
predictions are not eager-loaded, history items **omit** `predictions` and `warning_count`; use latest
mode, or the web UI's forecast detail page, to see the points.

No active forecast:

```json
{ "success": true, "data": null, "request_id": "01J..." }
```

### `GET /health`

Public, no token, 30 requests/minute per IP.

```json
{
  "success": true,
  "data": {
    "status": "ok",
    "checks": { "database": "ok", "storage_disk": "local" },
    "timestamp": "2026-11-16T05:00:00+00:00",
    "request_id": "01J..."
  }
}
```

- `200` when the database answers; `503` with `"status":"degraded"` and `success: false` otherwise.
- This endpoint reports its own shape: `request_id` sits inside `data`, and a degraded response has no
  `error` object.
- It reports the configured disk name but does not test storage I/O.
- `/up` is the separate framework-level liveness probe used by the container and load balancer.

## Reference

### Endpoint summary

| Method | Path | Auth | Ability |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | none | — |
| POST | `/api/v1/inspections/results` | token | `inspections:write` |
| POST | `/api/v1/forecasts/cloud` | token | `forecasts:cloud:write` |
| POST | `/api/v1/forecasts/network` | token | `forecasts:network:write` |
| GET | `/api/v1/devices` | token | `read` |
| GET | `/api/v1/inspections` | token | `read` |
| GET | `/api/v1/inspections/{inspection_id}` | token | `read` |
| GET | `/api/v1/forecasts/cloud` | token | `read` |
| GET | `/api/v1/forecasts/network` | token | `read` |

There are no update or delete endpoints. The API is write-once for ingestion and read-only otherwise.

### Versioning

Only `v1` exists; the prefix is hardcoded in `routes/api.php`. Additive response fields may appear
without a version bump. Producers should ignore unknown fields. A breaking change would ship as `/v2`.

### Useful cURL snippets

```bash
# Health
curl -s "$APP_URL/api/v1/health"

# List devices
curl -s "$APP_URL/api/v1/devices?per_page=10" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Defective inspections for one device
curl -s "$APP_URL/api/v1/inspections?status=DEFECT&device=DEVICE-001" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Latest network forecast
curl -s "$APP_URL/api/v1/forecasts/network?latest=1" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

## Common problems

| Symptom | Cause | Fix |
| --- | --- | --- |
| `401 UNAUTHENTICATED` with a token you just created | Header missing, malformed, or token revoked | Use `Authorization: Bearer <token>`; re-issue if revoked |
| `403 FORBIDDEN` on a `GET` | Token has a write ability but not `read` | Issue a token with `--ability=read` |
| `403 ACCOUNT_INACTIVE` | Service account deactivated in user management | Reactivate it, or issue a token for an active account |
| `409 DUPLICATE_INSPECTION` | Retry of an already-stored UUID | Treat as success; do not generate a new UUID |
| `422 VALIDATION_ERROR` on `detections` | Field omitted instead of sent as `[]` | Always send `detections`, even for PASS |
| `422 UNKNOWN_DEVICE` | Device code not registered or typed differently | Register the device; codes are matched exactly |
| `422` on `inspected_at` | Clock skew or old backlog | Sync the device clock; replay within the backfill window |
| `429 RATE_LIMITED` | Too many requests for the token owner | Wait for `Retry-After` |
| `image_url` is `null` but an image was sent | Object missing or URL generation failed | Check the disk configuration and file existence |
| `latest=1` returns `data: null` | No `ACTIVE` forecast for that source | Ingest one, or check the source URL |
| History response has no predictions | History mode does not eager-load them | Use `latest=1` or the web detail page |
| `/health` returns `503` | Database unreachable | Check `DB_*` values and MySQL availability |

More environment- and deployment-level symptoms are in [troubleshooting.md](troubleshooting.md).

## Related

- Producer obligations and retry matrix: [integration-contracts.md](integration-contracts.md)
- How requests reach the database: [architecture.md](architecture.md)
- Stored fields and types: [database.md](database.md)
- Implementation-level detail: [`../ai-docs/07-api-documentation.md`](../ai-docs/07-api-documentation.md)
