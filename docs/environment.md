# Environment configuration

Laravel reads configuration from environment variables. Values are resolved at
boot, so changing a variable requires a restart (and, when config is cached, a
re-cache).

Never commit a `.env` file containing real credentials. `.env.example` documents
the shape only. In production, inject secrets from AWS Secrets Manager, SSM
Parameter Store, or the platform's secret mechanism.

## Application

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_NAME` | `Garment QC` | UI and mail display name |
| `APP_ENV` | `local` | `local`, `staging`, or `production`; production enables safer seeding checks |
| `APP_KEY` | — | 32-byte base64 key. Required in production. Generate with `php artisan key:generate --show` |
| `APP_DEBUG` | `true` | Must be `false` in production |
| `APP_URL` | `http://localhost:8000` | Absolute URL used for links and signed storage URLs |
| `APP_DISPLAY_TIMEZONE` | `Asia/Manila` | Zone used only when rendering UTC timestamps |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `en`, `en`, `en_US` | Localisation |
| `APP_MAINTENANCE_DRIVER` | `file` | Maintenance mode driver |
| `LOG_CHANNEL` | `stack` | Use `stderr` in containers |
| `LOG_STACK` | `single` | |
| `LOG_LEVEL` | `debug` | Use `info` or `warning` in production |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

> `APP_URL` must match the public URL, including scheme and host. Signed image
> URLs generated for S3/local storage are validated against it.

## Database

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `127.0.0.1` | RDS endpoint in AWS |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `garment_defect` | |
| `DB_USERNAME` | `root` | Use a dedicated application user in production |
| `DB_PASSWORD` | — | Inject from a secret store in production |

The tests read a separate set of values from `phpunit.xml`
(`garment_defect_test`).

## Sessions, cache, and queues

| Variable | Default | Purpose |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | Database sessions survive container replacement |
| `SESSION_LIFETIME` | `120` | Minutes |
| `SESSION_ENCRYPT` | `false` | |
| `SESSION_DOMAIN` | `null` | Set when sharing cookies across subdomains |
| `CACHE_STORE` | `database` | `file` is used in the single-container CI probe |
| `QUEUE_CONNECTION` | `database` | Ingestion is synchronous; queues are available if added later |
| `BROADCAST_CONNECTION` | `log` | |

For a multi-replica ECS service, keep sessions and cache on the database (or
introduce Redis) rather than a per-container file.

## Filesystem and object storage

| Variable | Default | Purpose |
| --- | --- | --- |
| `FILESYSTEM_DISK` | `local` | Default disk |
| `INSPECTION_IMAGE_DISK` | `local` | Disk for inspection images; set `s3` in AWS |
| `AWS_ACCESS_KEY_ID` | — | Prefer an IAM task role instead of static keys |
| `AWS_SECRET_ACCESS_KEY` | — | |
| `AWS_DEFAULT_REGION` | `us-east-1` | |
| `AWS_BUCKET` | — | Private inspection bucket |
| `AWS_URL` | — | Optional CDN/base URL |
| `AWS_ENDPOINT` | — | Set for MinIO or another S3-compatible service |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | `true` for MinIO |

The `local` disk stores files under `storage/app/private` and issues signed URLs
through Laravel. The bucket must remain private; the API returns short-lived
signed URLs only.

## Inspection monitoring

| Variable | Default | Purpose |
| --- | --- | --- |
| `INSPECTION_ONLINE_THRESHOLD_MINUTES` | `15` | A device is online when ACTIVE and seen within this window |
| `INSPECTION_BACKFILL_DAYS` | `7` | Oldest accepted `inspected_at` |
| `INSPECTION_FUTURE_TOLERANCE_MINUTES` | `5` | Future clock tolerance |
| `INSPECTION_IMAGE_MAX_KB` | `5120` | Upload size limit |
| `INSPECTION_IMAGE_MAX_DIMENSION` | `4096` | Maximum width/height in pixels |

These values are validated at the API boundary and also drive UI hints.

## Forecast ingestion

| Variable | Default | Purpose |
| --- | --- | --- |
| `FORECAST_MAX_PREDICTIONS` | `500` | Maximum points per forecast |
| `FORECAST_MAX_HORIZON_MINUTES` | `10080` | Maximum declared horizon (7 days) |

Metric labels, units, and chart colours live in `config/forecast.php`, not in
environment variables.

## Seeded accounts

| Variable | Default | Purpose |
| --- | --- | --- |
| `ADMIN_EMAIL` | `admin@garment.test` | Administrator login created by the seeder |
| `ADMIN_PASSWORD` | `password` in local | Required in production; default passwords abort production seeding |
| `OPERATOR_PASSWORD` | `password` in local | In production, a random password is generated when unset |

These are only read by `php artisan db:seed`. They are not used after seeding
unless the seeder is run again (which resets these accounts' passwords).

## Container runtime flags

These are read by `docker/entrypoint.sh`, not by Laravel.

| Variable | Default | Purpose |
| --- | --- | --- |
| `RUN_MIGRATIONS` | `false` | Run `php artisan migrate --force` before starting |
| `RUN_SEED` | `false` | Run `php artisan db:seed --force` before starting |
| `WAIT_FOR_DB` | `false` | Wait for a reachable MySQL database |
| `DB_WAIT_ATTEMPTS` | `30` | Two-second intervals before giving up |
| `OPTIMIZE` | `true` | Cache config, routes, views, and events at startup |

In production, run migrations as a separate one-off ECS task rather than
`RUN_MIGRATIONS=true` on every replica.

## Secret handling rules

1. **Never commit** `APP_KEY`, database passwords, admin passwords, AWS keys, or
   API tokens.
2. **Never bake** secrets into the Docker image. Build args are visible in image
   history; use runtime secrets instead.
3. **Use one secret per concern.** Database, application key, and bootstrap
   passwords belong in separate Secrets Manager entries with separate rotation
   policies.
4. **Rotate deliberately.** Changing `APP_KEY` logs everyone out; rotating the
   database password requires a task restart.
5. **Use IAM roles in AWS.** The ECS task role grants S3 access; do not ship
   static `AWS_ACCESS_KEY_ID` values to Fargate.
6. **Revoke tokens, do not share them.** Issue one Sanctum token per service
   and revoke it from Settings when decommissioned.

## Config caching

In production the entrypoint runs:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

With a cached config, `env()` calls outside `config/*.php` return `null`. The
application follows this rule: all environment reads happen in config files and
code uses `config(...)`. If you add a new variable, expose it through a config
file.

To inspect effective values:

```bash
php artisan about
php artisan config:show inspection
php artisan config:show forecast
```

Clear caches during development:

```bash
php artisan optimize:clear
```
