# 16 — Known Limitations and Technical Debt

Everything below is based on the current source. Each item states the evidence, impact, and a safe
direction. Nothing here is a planned commitment.

## Unused or dead code

### 1. Breeze auth controllers without routes

**Evidence:** `app/Http/Controllers/Auth/` contains `RegisteredUserController`,
`EmailVerificationPromptController`, `EmailVerificationNotificationController`, `VerifyEmailController`,
`PasswordResetLinkController`, `NewPasswordController`, and `ConfirmablePasswordController`.
`routes/auth.php` references only `AuthenticatedSessionController` and `PasswordController`.

**Impact:** Features look present but are unreachable. An agent might "fix" a view instead of realising
there is no route. Registration and password reset are **Not Implemented**.

**Direction:** Either delete the unused controllers/views or wire them deliberately with tests. Do not
assume they work.

### 2. Unrouted auth views

**Evidence:** `resources/views/auth/{register,forgot-password,reset-password,verify-email,confirm-password}.blade.php`
exist; no route renders them.

**Impact:** Same as above. They also reference components (`x-input-label`, `x-text-input`, etc.) that
remain available.

### 3. `ProfileController::destroy` has no route

**Evidence:** The method exists and validates `current_password`, but `routes/web.php` only registers
`profile.edit` and `profile.update`. `profile/edit.blade.php` has no delete form.

**Impact:** Users cannot delete their own accounts; the method is dead code and can be removed or
routed intentionally.

### 4. Leftover Breeze layout and views

**Evidence:** `resources/views/layouts/navigation.blade.php`, `welcome.blade.php`,
`components/{nav-link,responsive-nav-link,dropdown-link,dropdown,modal,secondary-button,danger-button}.blade.php`
are unreferenced (or only referenced by each other). `application-logo` is used only by the guest
layout.

**Impact:** Noise when navigating the view tree; `welcome.blade.php` could be mistaken for the root
page, which actually redirects.

### 5. `database/database.sqlite`

**Evidence:** Present at `database/database.sqlite`; `config/database.php` defaults to `sqlite` only
when `DB_CONNECTION` is unset, and `.env.example` sets `mysql`.

**Impact:** Unused artifact; deleting it is safe but unnecessary.

### 6. Skeleton tests and helper

**Evidence:** `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`, and the `something()`
helper in `tests/Pest.php`.

**Impact:** Minimal; they add two trivial tests to the suite.

## Functional gaps

### 7. No forecast expiry transition

**Evidence:** `ForecastStatus::Expired` exists in the enum and schema. `ForecastIngestionService` only
writes `SUPERSEDED`. `grep` finds no `Expired` assignment.

**Impact:** Old forecasts remain `SUPERSEDED` forever. The `EXPIRED` state is decorative and may
confuse consumers filtering by status.

**Direction:** Add a scheduled command or compute expiry from `generated_at + horizon` at read time.
Either way, document the chosen semantics.

### 8. Supersede is not concurrency-safe

**Evidence:** The transaction updates `ACTIVE` rows then inserts a new `ACTIVE` row. There is no
unique constraint enforcing one active forecast per source and no row lock.

**Impact:** Two simultaneous valid ingestions for the same source can both insert `ACTIVE` rows. The
read paths take the newest by `generated_at`, so the dashboard usually looks correct, but the
invariant is not guaranteed.

**Direction:** Lock the source (for example `SELECT ... FOR UPDATE` on a source row) or add a partial
unique index if the database supports it.

### 9. No retention or purge

**Evidence:** No scheduled tasks, jobs, or delete endpoints for inspections, detections, forecasts,
predictions, or images.

**Impact:** Tables and the image bucket grow without bound. `DEVICE` and `INSPECTION` rows cannot be
removed (FK restricts/cascades, no delete route).

**Direction:** Define a retention policy and implement a scheduled command with batching and image
cleanup. This is a product decision, not a bug fix.

### 10. No audit trail

**Evidence:** No audit/event table or logging of administrative mutations. Only ingestion and
unexpected API exceptions are logged.

**Impact:** No record of who changed a device, created a user, or revoked a token.

### 11. Device soft deletes are unused

**Evidence:** `devices.deleted_at`, `SoftDeletes`, and `DevicePolicy::delete` exist; no delete route or
UI. Inspections use `restrictOnDelete`.

**Impact:** Dead capability; if a delete is added, decide whether inspections block it or are
archived.

### 12. Image URLs are bearer-style signed links

**Evidence:** `ImageStorageService::temporaryUrl()` returns a 10-minute signed URL (local `storage.local`
route or S3). The signature is not bound to a user or session.

**Impact:** Anyone who obtains the URL can fetch the image until it expires. This is normal for signed
storage URLs, but it means the link must be treated as sensitive.

**Direction:** Shorten the TTL if needed, or proxy images through an authorised controller.

### 13. No API for writing devices/users/settings

**Evidence:** Only ingestion and read endpoints exist under `/api/v1`.

**Impact:** External provisioning must use the UI or database/CLI. This may be intentional.

### 14. No OpenAPI specification

**Evidence:** No `openapi.*`, Swagger, or Scribe dependency.

**Impact:** API documentation is hand-maintained in `docs/api.md` and
[07](07-api-documentation.md); drift is possible.

## Frontend debt

### 15. Tailwind 3 with `@tailwindcss/vite` 4 installed

**Evidence:** `package.json` has `tailwindcss ^3.1.0` and `@tailwindcss/vite ^4.0.0`;
`vite.config.js` does **not** include the Tailwind Vite plugin; `postcss.config.js` uses the v3
PostCSS plugin.

**Impact:** An unused v4 package adds confusion and install weight. Removing it is safe, but verify
the build afterwards.

### 16. No pagination component abstraction

**Evidence:** Views call `$paginator->links()` directly with default Tailwind markup.

**Impact:** Styling varies with the framework default; acceptable but not customised.

### 17. Axios configured but unused

**Evidence:** `resources/js/bootstrap.js` sets `window.axios`; no page performs XHR/fetch.

**Impact:** Small unused bundle contribution; safe to remove if no client-side HTTP is planned.

### 18. Charts are inline per page

**Evidence:** Each page pushes its own `window.renderChart(...)` configuration.

**Impact:** Chart options are duplicated across dashboard and forecast views; drift is possible.

### 19. Accessibility and responsive testing are manual

**Evidence:** No automated accessibility tests; layout relies on Tailwind breakpoints and Alpine.

## Backend and data debt

### 20. MySQL-only assumptions

**Evidence:** `phpunit.xml` and `.env.example` use MySQL. Queries use `DATE(inspected_at)` and
`selectRaw`, which are MySQL/MariaDB-specific.

**Impact:** SQLite/PostgreSQL are not supported despite the framework default connection.

### 21. Duplicate detection relies on string matching

**Evidence:** Services translate `QueryException` when `errorInfo[1] === 1062` or the message contains
`Duplicate entry`.

**Impact:** Tied to MySQL/MariaDB error formats; other drivers would surface 500s.

### 22. `generated_at` future tolerance is hardcoded

**Evidence:** `StoreForecastRequest::after()` uses `now()->addMinutes(5)`, unlike inspection tolerance
which is configurable.

**Impact:** Inconsistent configurability; changing it requires a code change.

### 23. Metric catalogue is display-only

**Evidence:** `config/forecast.php` labels/colours; unknown metric names are accepted.

**Impact:** Producers can submit arbitrary metrics. This is intentional flexibility but means the
catalogue cannot be used for validation without a policy change.

### 24. No database-level check constraints

**Evidence:** `defect_count == detections`, confidence range, and status/count consistency are
application-enforced only.

**Impact:** Direct SQL inserts can violate invariants.

### 25. Rate limits are per token owner, not per token

**Evidence:** `Limit::by($request->user()?->id)`.

**Impact:** Multiple tokens for one service share a bucket; this is usually desirable but worth knowing.

### 26. Health endpoint has its own envelope

**Evidence:** `HealthController` returns `{success, data:{status, checks, timestamp, request_id}}`
rather than `ApiResponse`.

**Impact:** Consumers must special-case `/health`. Documented but inconsistent.

## Delivery and operations debt

### 27. AWS is not provisioned

**Evidence:** Only `docker/ecs-task-definition.json` and `deploy.yml` exist. All ARNs/IDs are
placeholders.

**Impact:** Deployment is **Not Implemented** until infrastructure is created.

### 28. No queue workers or scheduler in ECS

**Evidence:** `QUEUE_CONNECTION=database` in the task template; no worker service; no scheduled tasks.

**Impact:** Safe today because no jobs exist. Adding jobs requires a worker service and a scheduler.

### 29. Compose has no TLS and uses development defaults

**Evidence:** App listens on host `8080` over HTTP; `APP_ENV=local` default; `RUN_SEED=true` default.

**Impact:** Not production-ready as-is; intended for local/demo.

### 30. Docker runtime disk pressure was observed on the authoring machine

**Evidence:** Prior attempts hit Docker content-store I/O errors with the `C:` drive full. Configuration
files validate, but a full end-to-end Compose run was not completed on this host.

**Impact:** Treat Compose runtime behaviour as `Needs Verification` on a machine with free disk.

### 31. No backups, log retention, or alerting configuration

**Evidence:** Not present in workflows or templates.

**Impact:** Operational procedures must be defined before production use.

### 32. No Git repository in the working copy

**Evidence:** No `.git` directory. CI/deploy workflows cannot run until the project is initialised and
pushed.

## Documentation debt addressed by this set

The original `docs/` set (8 files) predates this AI-oriented documentation. It remains accurate in
substance, but `docs/` and `ai-docs/` can drift. When behaviour changes, update the numbered document
first, then the corresponding `docs/` deep dive if it contains contract-level detail.

## How to prioritise safely

| Priority | Item | Reason |
| --- | --- | --- |
| High | 7, 8, 9 | Correctness of forecast state, concurrency, and unbounded growth |
| High | 1–3 | Dead code causes wrong assumptions about features |
| Medium | 10, 11, 12 | Operational visibility and API completeness |
| Medium | 15, 17, 18 | Frontend clarity |
| Low | 5, 6, 16, 19 | Cosmetic/cleanup |

Any fix should include tests and a documentation update. Do not combine unrelated refactors with
behavioural fixes.
