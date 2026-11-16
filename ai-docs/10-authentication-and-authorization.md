# 10 — Authentication and Authorization

## Guards and providers

`config/auth.php`:

| Setting | Value |
| --- | --- |
| Default guard | `web` (`AUTH_GUARD`) |
| Web driver | `session` |
| Provider | Eloquent, `App\Models\User` |
| Password broker | `users` (configured, **no reset routes**) |
| Password reset table | `password_reset_tokens` (created, unused) |

Sanctum adds the `sanctum` guard for API requests. `User` uses `Laravel\Sanctum\HasApiTokens`.

## Session authentication

| Route | Middleware | Controller |
| --- | --- | --- |
| `GET /login` | `guest` | `Auth\AuthenticatedSessionController@create` |
| `POST /login` | `guest` | `Auth\AuthenticatedSessionController@store` |
| `PUT /password` | `auth`, `active` | `Auth\PasswordController@update` |
| `POST /logout` | `auth`, `active` | `Auth\AuthenticatedSessionController@destroy` |

Login behaviour (`App\Http\Requests\Auth\LoginRequest`):

- Validation: required email (string, email) and password (string).
- Rate limit: 5 attempts keyed by transliterated lowercase email + IP.
  - Exceeding it fires `Lockout` and returns a validation error with retry seconds/minutes.
- On failed credentials: increment limiter, generic `auth.failed` message.
- On success: verify the user is `is_active` and **not** `isService()`. Otherwise log out, increment
  the limiter, and return the same generic failure (prevents role/state enumeration).
- On success: clear limiter; controller regenerates the session, records `last_login_at`, redirects to
  the intended URL.

`AuthenticatedSessionController@destroy` logs out, invalidates the session, regenerates the token,
and redirects to `/`.

### Password change

`Auth\PasswordController@update` (Breeze) validates `current_password`, a new password with
`Password::defaults()` rules, and confirmation, then updates the hash. It is linked from the profile
page. It is **not** a reset flow.

### Unrouted auth code

These controllers exist but no route references them:

- `RegisteredUserController` (public registration)
- `PasswordResetLinkController`, `NewPasswordController` (forgot/reset)
- `EmailVerificationPromptController`, `EmailVerificationNotificationController`,
  `VerifyEmailController`
- `ConfirmablePasswordController`

Corresponding views (`auth/register`, `auth/forgot-password`, `auth/reset-password`,
`auth/verify-email`, `auth/confirm-password`) are also unreachable. Do not assume these features work.
See [16](16-known-limitations-and-technical-debt.md).

`ProfileController::destroy()` is also unrouted; account self-deletion is unavailable.

## Active-account enforcement

`EnsureUserIsActive` is aliased as `active` and applied to:

- All authenticated web routes (`routes/web.php` group).
- Password update and logout.
- All authenticated API routes.

Behaviour:

| Surface | Inactive user result |
| --- | --- |
| API (`api/*`) | `403 { "error": { "code": "ACCOUNT_INACTIVE", ... } }` |
| Web | Log out, invalidate session, redirect to login with `email` error `This account has been deactivated.` |

## Role enforcement

Two mechanisms exist and both are used:

1. **Route middleware** — `role:ADMINISTRATOR` wraps the user-management and settings routes via
   `EnsureRole`.
2. **Policies** — checked inside controllers with `$this->authorize(...)`.

### Middleware

`EnsureRole` compares `$user->role->value` against the provided role strings using strict `in_array`.
Failure aborts 403 with `You do not have permission to access this area.`

### Policies

| Policy | Method | Rule |
| --- | --- | --- |
| `DevicePolicy` | `viewAny`, `view` | Administrator or Operator |
| | `create`, `update`, `delete` | Administrator |
| `InspectionPolicy` | `viewAny`, `view` | Administrator or Operator |
| | `create`, `update`, `delete` | Always false (inspections are append-only) |
| `ForecastPolicy` | `viewAny`, `view` | Administrator or Operator |
| `UserPolicy` | `viewAny`, `create` | Administrator |
| | `update` | Administrator and target is not a service account |
| | `delete` | Administrator, target not service, and not self |

Policies are auto-discovered by Laravel's naming convention (`App\Models\X` → `App\Policies\XPolicy`).
There is no explicit registration in `AppServiceProvider`.

`DevicePolicy::delete` and `UserPolicy::delete` are not exercised by any route.

## API token authentication

### Token issuance

Only via:

```bash
php artisan service:token "Inference Service" --ability=inspections:write --ability=read
php artisan service:token "Cloud Forecaster" --ability=forecasts:cloud:write --ability=read
php artisan service:token "Network Forecaster" --ability=forecasts:network:write --ability=read
```

Command behaviour (`app/Console/Commands/IssueServiceToken.php`):

- Resolves a `SERVICE` user by exact email or name.
- Requires at least one `--ability`.
- Deletes existing tokens unless `--keep-existing`.
- Token label defaults to `service-token`.
- Prints the plain-text token once; only its hash is stored.

The seeder creates these service accounts:

| Name | Email |
| --- | --- |
| Inference Service | `inference-service@service.local` |
| Cloud Forecaster | `cloud-forecaster@service.local` |
| Network Forecaster | `network-forecaster@service.local` |

Their passwords are random and never usable for web login (service accounts are rejected).

### Abilities

| Ability | Endpoint |
| --- | --- |
| `inspections:write` | `POST /api/v1/inspections/results` |
| `forecasts:cloud:write` | `POST /api/v1/forecasts/cloud` |
| `forecasts:network:write` | `POST /api/v1/forecasts/network` |
| `read` | All authenticated `GET` endpoints |

Middleware alias `abilities` maps to Sanctum `CheckAbilities` (**all** abilities required). `ability`
maps to `CheckForAnyAbility` and is registered but unused.

`*` is supported by Sanctum's ability checker if ever issued, but the CLI/docs do not encourage it.

### Token lifecycle

- Stored in `personal_access_tokens` with hashed token, label, abilities, `last_used_at`.
- No expiry is set by the issuance command (`expires_at` remains null).
- Administrators can revoke service tokens in Settings (`DELETE /settings/tokens/{token}`).
- Re-issuing without `--keep-existing` revokes all previous tokens for that account.

## CSRF and session security

- All web mutations use `@csrf` and are verified by Laravel's `VerifyCsrfToken` middleware.
- API routes are stateless token auth and are not CSRF-protected.
- Session cookies use the configured driver (`database` locally, `file` in the image) and standard
  Laravel cookie settings (`SESSION_*` variables).
- No remember-me token rotation beyond Laravel defaults.
- No 2FA, SSO, or IP allow-listing.

## Authorization summary by route

| Route group | Guard(s) |
| --- | --- |
| `/login` | `guest` |
| `/dashboard`, `/inspections*`, `/devices*`, `/forecasts*`, `/profile` | `auth`, `active` + policies |
| `/users*`, `/settings*` | `auth`, `active`, `role:ADMINISTRATOR` + policies |
| `/password`, `/logout` | `auth`, `active` |
| `/api/v1/health` | none + `throttle:health` |
| `/api/v1/*` (authenticated) | `auth:sanctum`, `active`, `abilities:*`, `throttle:*` |

## Security-relevant behaviour

- Login failures do not reveal whether an account exists, is inactive, or is a service account.
- Service accounts are excluded from staff listings and user management.
- Deactivated users are logged out on their next web request.
- Inspection images require `view` authorisation; there is no public URL.
- Duplicate submissions are rejected rather than overwriting evidence.
- No registration route, so account creation is administrator-only.
- Rate limits reduce token/credential brute force and ingestion floods.

## Related documents

- Business workflows: [09-business-logic-and-workflows.md](09-business-logic-and-workflows.md)
- API auth details: [07-api-documentation.md](07-api-documentation.md)
- Environment secrets: [12-configuration-and-environment.md](12-configuration-and-environment.md)
