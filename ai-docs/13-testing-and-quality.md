# 13 — Testing and Quality

## Test stack

| Item | Value |
| --- | --- |
| Runner | Pest 3 (`php artisan test`) |
| Base framework | PHPUnit (via Pest) |
| Laravel plugin | `pestphp/pest-plugin-laravel` |
| Database | MySQL `garment_defect_test` (real connection, no SQLite) |
| Isolation | `RefreshDatabase` on all Feature tests |
| Factories | Faker-backed model factories |
| Style | Laravel Pint |
| CI | `.github/workflows/ci.yml` |

## Current status

| Check | Result |
| --- | --- |
| `php artisan test` | **87 tests / 310 assertions** passing |
| `vendor/bin/pint --test` | Passing |
| `npm run build` | Passing |
| `php artisan view:cache` | Passing (all Blade templates compile) |
| Container smoke test | `/up` 200, `/api/v1/health` 200, login 302, dashboard/settings 200, ingestion 201, duplicate 409 |

These figures were verified against the current working tree. Re-run them after any change.

## Test environment

`phpunit.xml`:

```xml
<env name="APP_ENV" value="testing"/>
<env name="BCRYPT_ROUNDS" value="4"/>
<env name="CACHE_STORE" value="array"/>
<env name="BROADCAST_CONNECTION" value="null"/>
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="garment_defect_test"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
<env name="DB_URL" value=""/>
<env name="INSPECTION_IMAGE_DISK" value="local"/>
```

Create the test database before the first run:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS garment_defect_test CHARACTER SET utf8mb4"
```

`RefreshDatabase` migrates it and wraps each test in a transaction. Tests do not use `.env` values for
the keys above.

## Test helpers (`tests/Pest.php`)

| Helper | Returns | Purpose |
| --- | --- | --- |
| `staffUser(Role $role = Operator, array $attributes = [])` | `User` | Active administrator or operator |
| `serviceToken(array $abilities, array $attributes = [])` | `[User, string]` | Service user + plain-text Sanctum token |
| `bearer(string $token)` | `array` | `Authorization: Bearer …` header array |

Feature tests extend `Tests\TestCase` and use `RefreshDatabase` automatically. Unit tests do not.

## Test inventory

| File | Focus |
| --- | --- |
| `Feature/Api/InspectionIngestionTest.php` | Contract, duplicates, unknown device, image, aliases, clock windows |
| `Feature/Api/ForecastIngestionTest.php` | Cloud/network ingestion, supersede, duplicates, validation |
| `Feature/Api/ReadApiTest.php` | Auth, abilities, devices/inspections/forecasts reads, pagination, latest |
| `Feature/Auth/AuthenticationTest.php` | Login, logout, invalid passwords |
| `Feature/Auth/PasswordUpdateTest.php` | Password change validation/update |
| `Feature/ProfileTest.php` | Profile display/update, unique email, service/inactive access, no registration |
| `Feature/Web/DashboardUiTest.php` | Access, KPIs, warnings, empty state |
| `Feature/Web/DeviceManagementTest.php` | Role gates, create/update, activation |
| `Feature/Web/InspectionUiTest.php` | Filters, detail, missing-image 404, guest redirect |
| `Feature/Web/ForecastUiTest.php` | Cloud/network pages, detail, warnings |
| `Feature/Web/SettingsTest.php` | Admin-only settings, token listing/revocation |
| `Feature/Web/UserManagementTest.php` | CRUD, role restrictions, last-admin guard |
| `Feature/ExampleTest.php` | Skeleton route check |
| `Unit/InspectionPayloadTest.php` | DTO consistency rules |
| `Unit/ExampleTest.php` | Skeleton truth assertion |

`Feature/ExampleTest.php`, `Unit/ExampleTest.php`, and the `something()` helper in `Pest.php` are
leftovers from the Laravel skeleton.

## What is covered

- API authentication failures (401), ability failures (403), inactive accounts (403).
- Inspection validation: missing fields, invalid UUID, count/status mismatch, clock windows,
  duplicate detection, image rules, `payload` wrapper, `device_id` alias.
- Duplicate inspection and forecast responses (409) and payload details.
- Forecast supersede semantics and per-source isolation.
- Read endpoint filtering, pagination, and latest-forecast behaviour.
- Web role gates for devices, users, settings, forecasts, inspections.
- Last-active-administrator protection and self-demotion prevention.
- Login success/failure and logout; inactive and service-account rejection; forced sign-out of
  inactive sessions; absence of a `/register` route.
- Image access authorisation and the missing-image 404 path.
- DTO consistency unit tests.

## What is not covered

- Real S3/MinIO upload and signed-URL retrieval (local disk is used).
- Browser/JS behaviour (Alpine, Chart.js rendering).
- Email verification, password reset, registration (unrouted).
- Queue jobs, scheduling, or broadcasting (none exist).
- Concurrency races (simultaneous duplicates or forecast supersedes).
- Load/performance characteristics and index efficiency at scale.
- Accessibility and visual regression.
- AWS deployment workflow execution (no provisioned resources).

## Commands

```bash
# Full suite
php artisan test

# Compact output
php artisan test --compact

# Single file / filter
php artisan test tests/Feature/Api/InspectionIngestionTest.php
php artisan test --filter="duplicate"

# Style
vendor/bin/pint --test
vendor/bin/pint            # auto-fix

# Frontend build
npm run build

# Blade compilation
php artisan view:cache

# Route surface sanity check
php artisan route:list --except-vendor
```

## Writing new tests

Follow the existing style:

```php
it('rejects a duplicate inspection', function () {
    [$service, $token] = serviceToken(['inspections:write']);
    $device = Device::factory()->create(['device_code' => 'DEVICE-001']);

    $payload = [/* … */];

    $this->withHeaders(bearer($token))
        ->postJson('/api/v1/inspections/results', $payload)
        ->assertCreated();

    $this->withHeaders(bearer($token))
        ->postJson('/api/v1/inspections/results', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'DUPLICATE_INSPECTION');
});
```

Guidelines:

1. Use factories rather than manual inserts.
2. Use `staffUser(Role::Administrator)` / `staffUser()` for web tests.
3. Use `serviceToken([...])` + `bearer()` for API tests.
4. Assert both status and stable error codes; do not assert full messages where wording may change.
5. Use `Carbon::setTestNow()` for clock-window tests and reset it in `finally`/afterEach.
6. Add a test for every new route, validation rule, policy branch, and invariant.
7. Keep Feature tests on real HTTP routes; use Unit tests only for pure logic (DTOs, enums).

## CI pipeline checks

`ci.yml` `backend` job:

1. `composer install`
2. `cp .env.example .env` + `php artisan key:generate`
3. `npm ci` + `npm run build`
4. `php artisan test`
5. `vendor/bin/pint --test`
6. `php artisan view:cache`

`container` job builds the production image and runs it against MySQL, polling `/up` and calling
`/api/v1/health`.

## Quality conventions

- PHP: PSR-12 via Pint; strict comparisons; typed properties/returns; readonly DTOs.
- No `dd()`/`dump()`/`var_dump()` in committed code.
- No business logic in Blade.
- API responses always through `ApiResponse`.
- Validation in FormRequests for writes; inline validation acceptable for read filters.
- Exceptions that map to API codes use `ApiException`.
- Every migration has a `down()`.
- Secrets never in tests or fixtures.

## Related documents

- Workflows under test: [09-business-logic-and-workflows.md](09-business-logic-and-workflows.md)
- CI/CD detail: [14-deployment-and-infrastructure.md](14-deployment-and-infrastructure.md)
- Known gaps: [16-known-limitations-and-technical-debt.md](16-known-limitations-and-technical-debt.md)
