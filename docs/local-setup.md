# Local setup

## Prerequisites

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.2 or newer | Required by Laravel 12. The production image uses 8.3 |
| Composer | 2.x | |
| Node.js | 20 or newer | Vite 7 and Tailwind |
| MySQL | 8.0+ | MariaDB 10.4+ also works |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd` | `gd` is needed by image-upload tests |

Check the CLI versions:

```bash
php -v
composer --version
node --version
npm --version
```

## 1. Create the databases

Development:

```sql
CREATE DATABASE garment_defect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Tests (required for `php artisan test`):

```sql
CREATE DATABASE garment_defect_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

With the MySQL CLI:

```bash
mysql -u root -p -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 2. Install PHP dependencies

```bash
composer install
```

## 3. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` for your database. The defaults assume a local MySQL server with the
`root` user and no password:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garment_defect
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
INSPECTION_IMAGE_DISK=local
```

Everything else can stay at its default for local development. See
[`environment.md`](environment.md) for every variable.

## 4. Migrate and seed

```bash
php artisan migrate --seed
```

This creates the schema, the administrator, one operator, and three SERVICE
accounts. Because `APP_ENV=local`, demo devices, inspections, and forecasts are
also seeded so the dashboard is populated immediately.

If demo data is not wanted:

```bash
php artisan migrate
php artisan db:seed --class=DatabaseSeeder
```

To start over:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` drops every table in the configured database. Never run it
> against a shared or production database.

### Seeded accounts

The values come from `.env`:

| Account | Email | Password |
| --- | --- | --- |
| Administrator | `ADMIN_EMAIL` (default `admin@garment.test`) | `ADMIN_PASSWORD` (default `password`) |
| Operator | `operator@garment.test` | `OPERATOR_PASSWORD` (default `password`) |

SERVICE accounts (`inference-service@service.local`,
`cloud-forecaster@service.local`, `network-forecaster@service.local`) cannot log
in; they only own API tokens.

## 5. Build frontend assets

```bash
npm install
npm run build
```

During UI development use:

```bash
npm run dev
```

Keep `php artisan serve` running in another terminal.

## 6. Run the application

```bash
php artisan serve
```

Open <http://localhost:8000> and sign in as the administrator.

The application redirects `/` to `/login` for guests and to `/dashboard` for
authenticated staff.

## 7. Issue API tokens

```bash
php artisan service:token "Inference Service" \
  --ability=inspections:write --ability=read

php artisan service:token "Cloud Forecaster" \
  --ability=forecasts:cloud:write --ability=read

php artisan service:token "Network Forecaster" \
  --ability=forecasts:network:write --ability=read
```

Options:

| Option | Meaning |
| --- | --- |
| `--ability=NAME` | Repeatable; at least one is required |
| `--name=LABEL` | Token label shown on the Settings screen (default `service-token`) |
| `--keep-existing` | Do not revoke previously issued tokens for that account |

The plain token is printed once. Only its hash is stored.

Tokens can be revoked from **Settings → Service API tokens**; this immediately
invalidates the token.

Verify a token:

```bash
curl http://127.0.0.1:8000/api/v1/health
curl http://127.0.0.1:8000/api/v1/devices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

## 8. Run the tests

```bash
php artisan test
```

The suite uses `garment_defect_test` from `phpunit.xml` and `RefreshDatabase`, so
it rebuilds the schema on every run. It needs the database created but not
migrated beforehand.

Other checks:

```bash
vendor/bin/pint --test     # style
npm run build              # assets
php artisan view:cache     # Blade compilation
```

## Docker alternative

If Docker is available:

```bash
cp .env.docker.example .env.docker
# Replace every change-me value in .env.docker.
docker compose --env-file .env.docker up --build
```

Open <http://localhost:8080>. See [`deployment.md`](deployment.md) for the full
stack, MinIO, and AWS instructions.

## Local file storage and images

With `INSPECTION_IMAGE_DISK=local`:

- images are written to `storage/app/private/inspections/YYYY/MM/<uuid>.<ext>`;
- the disk is configured with `serve => true`, so Laravel can issue signed,
  expiring URLs under `/storage/...`;
- the UI also serves images through `/inspections/{inspection}/image`, which
  requires an authenticated session.

No `php artisan storage:link` is required for the private disk. The public disk
is not used for inspection evidence.

## Troubleshooting

### `could not find driver`

Enable `pdo_mysql` in `php.ini` and restart the CLI/server:

```bash
php -m | grep pdo_mysql
```

### MySQL access denied

Verify the credentials in `.env`:

```bash
php artisan db:show
```

MySQL 8 with `caching_sha2_password` is supported by PHP 8.2+. If you use a
legacy client, create the user with `mysql_native_password`.

### `Vite manifest not found`

Build the assets:

```bash
npm install
npm run build
```

### Styles or charts do not update

Clear compiled views and rebuild:

```bash
php artisan view:clear
npm run build
```

### `php artisan test` fails to connect

- Confirm `garment_defect_test` exists.
- Confirm the credentials in `phpunit.xml` match your server.
- Run `php artisan config:clear` before tests if a cached config exists.

The test suite intentionally uses MySQL, not SQLite, so enum and JSON behaviour
matches production.

### Images return 404

- Confirm the inspection actually has an `image_path`.
- Confirm `INSPECTION_IMAGE_DISK` points at the disk where the image was stored.
- For S3, confirm bucket credentials and `AWS_ENDPOINT`/path-style settings.

### Changing APP_KEY

Changing `APP_KEY` invalidates encrypted sessions and any encrypted values.
Existing Sanctum token hashes are unaffected, but users must sign in again.

### Node not found on Windows shells

If Node is installed in a non-standard location, add it to `PATH` for the
session, for example:

```bash
export PATH="/c/Users/<you>/AppData/Local/Temp/opencode/nodejs/node-v26.13.1-win-x64:$PATH"
```

### Docker daemon not running

`docker ps` fails with a named-pipe error when Docker Desktop is not started.
Start Docker Desktop and wait for the engine, then retry the build.
