# 12 — Configuration and Environment

Configuration is read from environment variables (`.env`, container env, or platform secrets) and
Laravel config files. **Never commit real values.** This document lists variable names and defaults
only.

## Configuration files

| File | Project-specific? | Notes |
| --- | --- | --- |
| `config/inspection.php` | Yes | Liveness, image limits, ingestion clock, defect classes, display timezone |
| `config/forecast.php` | Yes | Metric catalogue, prediction/horizon limits, risk levels |
| `config/filesystems.php` | Partly | Private local disk + S3 disk |
| `config/auth.php` | Partly | Session guard + unused password broker |
| `config/app.php` | Laravel default | `timezone` is hardcoded `UTC` |
| Others | Laravel default | cache, database, logging, mail, queue, services, session |

Config is cached in production by the container entrypoint (`config:cache`). After changing
environment variables in a running container, restart it because cached config is not re-read.

## Application

| Variable | Default in `.env.example` | Purpose |
| --- | --- | --- |
| `APP_NAME` | `Garment QC` | UI title |
| `APP_ENV` | `local` | Environment (`production` changes seeder guards and image behaviour) |
| `APP_KEY` | empty | Encryption key; **required** in production |
| `APP_DEBUG` | `true` | Debug pages; must be `false` in production |
| `APP_URL` | `http://localhost:8000` | Base URL for generated links |
| `APP_DISPLAY_TIMEZONE` | `Asia/Manila` | Display timezone; storage remains UTC |
| `APP_LOCALE` | `en` | Locale |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale |
| `APP_FAKER_LOCALE` | `en_US` | Faker locale |
| `APP_MAINTENANCE_DRIVER` | `file` | Maintenance mode driver |

`config/app.php` sets `'timezone' => 'UTC'` directly; there is no `APP_TIMEZONE` variable.

## Database

| Variable | Default | Notes |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Only MySQL is exercised |
| `DB_HOST` | `127.0.0.1` | `db` in Compose |
| `DB_PORT` | `3306` | `3307` exposed to host in Compose |
| `DB_DATABASE` | `garment_defect` | Tests use `garment_defect_test` |
| `DB_USERNAME` | `root` | Compose creates `garment` |
| `DB_PASSWORD` | empty | Compose requires a value |
| `DB_URL` | — | Optional full DSN; `phpunit.xml` blanks it |

## Sessions, cache, queue

| Variable | Default | Notes |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` (`.env.example`), `file` (image) | `sessions` table exists |
| `SESSION_LIFETIME` | `120` | Minutes |
| `SESSION_ENCRYPT` | `false` | |
| `SESSION_PATH` | `/` | |
| `SESSION_DOMAIN` | `null` | |
| `CACHE_STORE` | `database` (`.env.example`), `file` (image), `array` (tests) | |
| `CACHE_PREFIX` | — | Commented out |
| `QUEUE_CONNECTION` | `database` (`.env.example`), `sync` (image) | No jobs dispatched |
| `BROADCAST_CONNECTION` | `log` | No broadcasts |

## Logging

| Variable | Default | Notes |
| --- | --- | --- |
| `LOG_CHANNEL` | `stack` | `stderr` in the image |
| `LOG_STACK` | `single` | |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | |
| `LOG_LEVEL` | `debug` | `info` in Compose/ECS templates |

## Mail

| Variable | Default | Notes |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | No application mail is sent |
| `MAIL_SCHEME` | `null` | |
| `MAIL_HOST` | `127.0.0.1` | |
| `MAIL_PORT` | `2525` | |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | `null` | |
| `MAIL_FROM_ADDRESS` | `no-reply@garment.local` | |

Because no mail is sent, these values are operationally irrelevant but remain configurable.

## Seeded accounts

| Variable | Default | Notes |
| --- | --- | --- |
| `ADMIN_EMAIL` | `admin@garment.test` | Administrator login |
| `ADMIN_PASSWORD` | demo value in `.env.example` | Required in production |
| `OPERATOR_PASSWORD` | demo value in `.env.example` | Random if unset in production |

Production guards in `DatabaseSeeder`:

- Missing `ADMIN_PASSWORD` → `RuntimeException`.
- `ADMIN_PASSWORD` equal to a known default → `RuntimeException`.
- Missing `OPERATOR_PASSWORD` → random 40-character value (not printed).

The seeded demo passwords are for local databases only. Do not reuse them anywhere shared.

## Inspection monitoring

| Variable | Default | Purpose |
| --- | --- | --- |
| `INSPECTION_ONLINE_THRESHOLD_MINUTES` | `15` | Liveness window for `Device::isOnline()` |
| `INSPECTION_BACKFILL_DAYS` | `7` | Oldest accepted `inspected_at` |
| `INSPECTION_FUTURE_TOLERANCE_MINUTES` | `5` | Allowed clock skew into the future |
| `INSPECTION_IMAGE_MAX_KB` | `5120` | Upload size limit |
| `INSPECTION_IMAGE_MAX_DIMENSION` | `4096` | Per-axis pixel limit |
| `INSPECTION_IMAGE_DISK` | `local` | `local` or `s3` |

The defect-class catalogue (`hole`, `stain`, `tear`, `missed_stitch`, `slub`, `color_variation`,
`wrinkle`, `foreign_fiber`, `selvedge_damage`) is defined in `config/inspection.php` and is **not**
environment-driven.

## Forecast ingestion

| Variable | Default | Purpose |
| --- | --- | --- |
| `FORECAST_MAX_PREDICTIONS` | `500` | Max predictions per payload |
| `FORECAST_MAX_HORIZON_MINUTES` | `10080` | Max horizon (7 days) |

The metric catalogues and risk levels are defined in `config/forecast.php`, not in env.

## Object storage

| Variable | Default | Notes |
| --- | --- | --- |
| `FILESYSTEM_DISK` | `local` | Default disk |
| `AWS_ACCESS_KEY_ID` | empty | Prefer IAM task role in AWS |
| `AWS_SECRET_ACCESS_KEY` | empty | |
| `AWS_DEFAULT_REGION` | `us-east-1` | |
| `AWS_BUCKET` | empty | Private bucket |
| `AWS_URL` | empty | Optional custom URL |
| `AWS_ENDPOINT` | empty | MinIO/S3-compatible endpoint |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | `true` for MinIO |

## Container runtime flags

Read by `docker/entrypoint.sh`, **not** by Laravel:

| Variable | Default | Effect |
| --- | --- | --- |
| `RUN_MIGRATIONS` | `false` | Run `migrate --force` at start |
| `RUN_SEED` | `false` | Run `db:seed --force` at start |
| `WAIT_FOR_DB` | `false` | Wait for a PDO connection before continuing |
| `DB_WAIT_ATTEMPTS` | `30` | Poll attempts × 2 seconds |
| `OPTIMIZE` | `true` | `config:cache`, `route:cache`, `view:cache`, `event:cache` |

`APP_KEY` is mandatory in production; the entrypoint exits if it is missing.

## Frontend

| Variable | Default | Notes |
| --- | --- | --- |
| `VITE_APP_NAME` | `${APP_NAME}` | Exposed to Vite |
| `APP_PORT` | `8080` | Compose host port only |
| `DB_PORT_FORWARD` | `3307` | Compose host port only |
| `DB_ROOT_PASSWORD` | required in Compose | MySQL root |
| `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` | required in S3 overlay | MinIO admin |
| `MINIO_PORT` / `MINIO_CONSOLE_PORT` | `9000` / `9001` | Host ports |

## Secret handling rules

1. Never commit `.env`, `.env.docker`, tokens, keys, or passwords.
2. `.env.example` and `.env.docker.example` contain placeholders only.
3. In AWS, inject `APP_KEY` and `DB_PASSWORD` from Secrets Manager (see
   `docker/ecs-task-definition.json`); prefer task roles over static AWS keys.
4. Service tokens are printed once by `service:token`; store them in the producer's secret store.
5. Do not log request headers, tokens, passwords, or image contents.
6. Changing `APP_KEY` invalidates encrypted cookies/sessions; plan rotation.

## Test environment overrides (`phpunit.xml`)

| Variable | Value |
| --- | --- |
| `APP_ENV` | `testing` |
| `BCRYPT_ROUNDS` | `4` |
| `CACHE_STORE` | `array` |
| `BROADCAST_CONNECTION` | `null` |
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` | `garment_defect_test` |
| `INSPECTION_IMAGE_DISK` | `local` |

Tests do not load `.env` values for these keys.

## Related documents

- Full variable reference with prose: [`../docs/environment.md`](../docs/environment.md)
- Deployment values: [14-deployment-and-infrastructure.md](14-deployment-and-infrastructure.md)
- Local setup: [15-development-guide.md](15-development-guide.md)
