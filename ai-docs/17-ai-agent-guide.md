# 17 — AI Agent Guide

This is the working playbook for an AI coding agent. Read `AGENTS.md` first, then this file, then the
document for the area you are changing.

## Orientation in 60 seconds

- Laravel 12 monolith: Blade UI + `/api/v1` JSON API.
- Entry points: `routes/web.php`, `routes/api.php`, `routes/auth.php`.
- Domain logic: `app/Services/`; HTTP validation: `app/Http/Requests/`; DTOs: `app/Data/`.
- Envelope/errors: `app/Support/ApiResponse.php`, `app/Exceptions/ApiException.php`,
  `bootstrap/app.php`.
- Schema: `database/migrations/2026_11_16_050000_create_garment_monitoring_tables.php`.
- Run `php artisan route:list --except-vendor` to see the real surface.
- Tests: `php artisan test` (MySQL `garment_defect_test`).

## Task routing

| Task | Read first | Touch |
| --- | --- | --- |
| Change an API response field | 07, 06 | Resource, tests, this doc, `docs/api.md` |
| Add an API endpoint | 06, 07, 09 | `routes/api.php`, FormRequest, DTO, service, resource, tests |
| Change ingestion validation | 09, 07 | `Store*Request`, DTO, service, tests |
| Change duplicate/supersede behaviour | 09, 05 | Service, migration if needed, tests, `docs/integration-contracts.md` |
| Add a web page | 08, 06 | Controller, route, Blade view, policy, test |
| Change dashboard metrics | 09, 06 | `DashboardMetricsService`, view, test |
| Change role rules | 10 | Policies, middleware usage, tests |
| Change token abilities | 10, 07 | `routes/api.php`, `IssueServiceToken`, docs, tests |
| Change schema | 05, 06 | New migration, model, factory, resources/requests, tests |
| Change image storage | 11 | `ImageStorageService`, config, resource, tests |
| Change env/config | 12 | `config/*`, `.env.example`, docs |
| Change Docker/CI | 14 | Dockerfile, entrypoint, compose, workflows |
| Fix a UI bug | 08 | Blade/component/CSS; verify with `npm run build` + `view:cache` |

## Invariants you must not break

1. `inspection_id` is globally unique; duplicates return `409 DUPLICATE_INSPECTION`.
2. `defect_count == count(detections)` and status matches count (PASS = 0, DEFECT ≥ 1).
3. Inspections outside the backfill/future window are rejected.
4. `(source_type, external_id)` is unique; duplicates return `409 DUPLICATE_FORECAST`.
5. A new forecast supersedes prior `ACTIVE` forecasts **of the same source only**.
6. `(forecast_id, metric_name, predicted_at)` is unique.
7. API success/error envelopes and error codes stay stable.
8. Images are private; never expose a public URL or trust client filenames.
9. Service accounts cannot log into the web UI and are hidden from user management.
10. At least one active administrator must remain.
11. Timestamps are stored in UTC; display conversion uses `display_datetime()`.
12. Web mutations are authorised by policies, not just hidden links.

If your change conflicts with an invariant, stop and report it rather than silently altering the rule.

## Recipes

### Add a read endpoint

```php
// routes/api.php (inside the read group)
Route::get('widgets', [WidgetController::class, 'index'])->name('api.v1.widgets.index');
```

```php
public function index(Request $request): JsonResponse
{
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
    ]);

    $widgets = Widget::query()
        ->when($validated['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
        ->paginate($validated['per_page'] ?? 25)
        ->withQueryString();

    return ApiResponse::paginated($widgets, WidgetResource::class);
}
```

Then add `tests/Feature/Api/ReadApiTest.php` coverage (or a new file) for 401, 403, 200, filters, and
pagination.

### Add an ingestion endpoint

1. FormRequest with base rules + `after()` cross-field rules.
2. DTO with `fromArray()` and a consistency method.
3. Service with `DB::transaction`, duplicate pre-check, duplicate-key translation, and a log event.
4. Controller returning `ApiResponse::success((new Resource($model))->resolve($request), 201)`.
5. Route with `abilities:<new-ability>` and a throttle limiter defined in `AppServiceProvider`.
6. Tests: happy path, validation, duplicate, unknown FK, ability failure, inactive account.
7. Update [07](07-api-documentation.md), `docs/api.md`, and `docs/integration-contracts.md`.

### Add a web CRUD page

1. Policy method + `$this->authorize()`.
2. FormRequest with `authorize()` delegating to the policy.
3. Named route in the correct group (`role:ADMINISTRATOR` for admin-only).
4. Blade view using `x-app-layout`, `x-page-header`, `.panel`, `.form-input`.
5. Redirect with `->with('status', '...')`.
6. Feature test for allowed role, denied role, validation, and success.

### Change the schema

1. New migration with `up()` and `down()`.
2. Update `$fillable`, `casts()`, relationships.
3. Update factories and seeders.
4. Update resources, requests, filters, and Blade.
5. Run `php artisan migrate:fresh --seed` on a **local** database and the test suite.
6. Update [05](05-database-design.md); check `docs/database.md`.

### Add a config value

1. Add `env('NAME', default)` in the relevant config file.
2. Add the name (no secret) to `.env.example`; add to `.env.docker.example`/compose only if the
   container needs it.
3. Read it via `config('file.key')`, never `env()` outside config.
4. Document it in [12](12-configuration-and-environment.md).

## Where to be careful

| Area | Risk |
| --- | --- |
| `routes/web.php`, `routes/api.php` | Route names are used by views; middleware order matters |
| `bootstrap/app.php` | Exception rendering is global to `api/*`; a wrong order changes all error codes |
| `StoreInspectionResultRequest` / `StoreForecastRequest` | `after()` invariants are the public contract |
| `InspectionIngestionService` / `ForecastIngestionService` | Transactions, duplicate translation, image cleanup |
| `ImageStorageService` | Path naming, privacy, temporary URLs, cleanup |
| `database/migrations/2026_11_16_050000_*` | Core schema; never edit in place once deployed |
| `app/Enums/*` | `match` expressions throw on new cases |
| `AppServiceProvider` | Rate limiter names are referenced by routes |
| `docker/entrypoint.sh` | Runs migrations/seeds; a mistake can reset production data |
| `.github/workflows/deploy.yml` | Deploys and migrates production |
| `tests/Pest.php` | Global `RefreshDatabase` and helpers |

## Do not

- Do not add `auth` routes for registration/reset without implementing and testing them.
- Do not return raw models from API controllers.
- Do not create a second response envelope or ad-hoc error arrays.
- Do not use `env()` outside `config/`.
- Do not hardcode thresholds that already exist in `config/inspection.php` or `config/forecast.php`.
- Do not write images to `public/` or the `public` disk.
- Do not change `defect_count`/status semantics to "fix" bad producer data.
- Do not edit applied migrations or squash history.
- Do not add queues/jobs without also adding a worker and scheduler story.
- Do not remove `active` or `abilities` middleware from protected routes.
- Do not commit `.env`, `.env.docker`, tokens, or credentials.
- Do not delete files that look unused without checking references (several Breeze files are
  genuinely unused, but confirm with `grep`).

## Verification checklist

Run before declaring a task complete:

```bash
php artisan test
vendor/bin/pint --test
npm run build
php artisan view:cache
php artisan route:list --except-vendor
```

Then check:

- [ ] New/changed routes appear with the expected middleware.
- [ ] Every new class is referenced (no orphan controller/view).
- [ ] API changes are reflected in [07](07-api-documentation.md) and `docs/api.md`.
- [ ] Schema changes are reflected in [05](05-database-design.md) and `docs/database.md`.
- [ ] Config changes are in `.env.example` and [12](12-configuration-and-environment.md).
- [ ] No secrets, tokens, or real credentials in the diff.
- [ ] Tests assert status **and** stable error codes.
- [ ] Blade uses `{{ }}` for user data and `display_datetime()` for timestamps.
- [ ] Policy/middleware protection exists for every new mutation.
- [ ] Documentation updated if architecture, contracts, or workflows changed.

## Interpreting partial results

- A passing test suite does not prove browser behaviour; charts/Alpine are untested.
- Local disk image URLs are `null` in the API by design.
- `EXPIRED` forecasts and device deletion are **not** implemented despite existing enum/schema/code.
- AWS deployment is template-only; do not report it as live.
- If Docker cannot run on the host, say so and mark runtime checks `Needs Verification`.
- If a version differs between local and container, state both.

## Reporting a change

Include:

1. What changed and why (one paragraph).
2. Files touched, grouped by layer.
3. Invariants affected and how they were preserved.
4. Commands run and their results.
5. Documentation updated.
6. Anything still `Unknown` / `Not Implemented` / `Needs Verification`.

## Related documents

- All rules: [`../AGENTS.md`](../AGENTS.md)
- Architecture: [03-system-architecture.md](03-system-architecture.md)
- Backend patterns: [06-backend-architecture.md](06-backend-architecture.md)
- Known gaps: [16-known-limitations-and-technical-debt.md](16-known-limitations-and-technical-debt.md)
