# Local setup

> Get the application running on your own machine: two MySQL databases, a `.env`, seeded demo data,
> built frontend assets, and a local server you can sign in to. This page is the full walkthrough; the
> root [`README.md`](../README.md) keeps only the five-line version.

**Who this is for:** anyone running the project locally for the first time.
**What you'll learn:** how to install, configure, seed, run, verify, and reset the app.
**Time:** about 15 minutes on a machine that already has PHP, Composer, Node, and MySQL.

---

## The short version

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Open <http://localhost:8000> and sign in as `admin@garment.test` / `password`. Then run
`php artisan test` to confirm the checkout is healthy.

## Before you start

| Tool | Version | Why |
| --- | --- | --- |
| PHP | 8.2 or newer | Laravel 12; the production image uses 8.3 |
| Composer | 2.x | PHP dependencies |
| Node.js | 20 or newer | Vite 7 and Tailwind 3 |
| npm | 10 or newer | Ships with Node |
| MySQL | 8.0+ | MariaDB 10.4+ also works |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` | Framework baseline |
| PHP `gd` | any recent version | Only needed for the test suite's fake image uploads |

Check what you have:

```bash
php -v
composer --version
node --version
npm --version
mysql --version
```

You should see PHP 8.2+ and Node 20+. `php -m` should list `pdo_mysql`; if it does not, enable the
extension in `php.ini` and restart your terminal.

> **Windows:** these commands work in Git Bash, WSL, or PowerShell. Where PowerShell differs, the
> equivalent is shown. If `npm` is "command not found" but Node is installed, add it to the session:
> `$env:PATH = "C:\Program Files\nodejs;$env:PATH"` (adjust the path to your install).

## Step by step

### 1. Create the two databases

The application uses `garment_defect`. The test suite uses `garment_defect_test` and **requires it to
exist** before `php artisan test` can run.

```bash
mysql -u root -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Versions with a password prompt:

```bash
mysql -u root -p -e "CREATE DATABASE garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p -e "CREATE DATABASE garment_defect_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

**You should see:** no error output. Confirm with:

```bash
mysql -u root -e "SHOW DATABASES LIKE 'garment_defect%'"
```

Two rows: `garment_defect` and `garment_defect_test`.

> If `mysql` is not on your `PATH`, create the same two databases with any MySQL client (phpMyAdmin,
> TablePlus, DBeaver). The names and charset are what matter.

### 2. Install PHP dependencies

```bash
composer install
```

**You should see:** Composer resolving packages and writing `vendor/autoload.php`. Re-run this after
pulling changes to `composer.lock`.

### 3. Create your environment file

```bash
cp .env.example .env
php artisan key:generate
```

PowerShell: `Copy-Item .env.example .env`.

**You should see:** `INFO Application key set successfully.` Open `.env` and confirm
`APP_KEY=base64:...` is populated.

The defaults assume MySQL on `127.0.0.1:3306` with user `root` and no password:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garment_defect
DB_USERNAME=root
DB_PASSWORD=
```

Change `DB_USERNAME`/`DB_PASSWORD` if your server differs, and keep `APP_URL` matching the URL you
actually open — signed image URLs are generated from it. Every variable is documented in
[environment.md](environment.md); you do not need to change anything else for local development.

### 4. Migrate and seed

```bash
php artisan migrate --seed
```

This creates the schema and the seeded accounts. Because `APP_ENV=local`, it also runs
`DemoDataSeeder`, so the dashboard is populated immediately:

- 8 devices across Lines A, B, C, the cutting room, finishing, and the fabric store;
- roughly 30 days of inspections (10–20 per day, about a quarter defective);
- 2 cloud and 2 network forecasts, each with one `ACTIVE` and one `SUPERSEDED` submission.

**You should see:** a list of migrations ending with `2026_11_16_050000_create_garment_monitoring_tables
... DONE`, then `INFO Seeding database.`

If you want the schema **without** demo rows:

```bash
php artisan migrate
php artisan db:seed --class=DatabaseSeeder
```

The demo seeder is idempotent: if any inspection already exists it prints a warning and skips. It
never duplicates devices because they are matched by `device_code`.

> To wipe and rebuild a **local** database, use `php artisan migrate:fresh --seed`.
> `migrate:fresh` drops every table in `DB_DATABASE`. Never run it against shared or production data.

### 5. Build the frontend assets

```bash
npm install
npm run build
```

**You should see:** Vite reporting `✓ built in ...` and files under `public/build/`.

While editing Blade, CSS, or JS, use the dev server instead of rebuilding:

```bash
npm run dev
```

Keep `php artisan serve` running in a second terminal.

### 6. Start the application

```bash
php artisan serve
```

**You should see:**

```text
INFO  Server running on [http://127.0.0.1:8000].
```

If port 8000 is busy, use `php artisan serve --port=8001` and update `APP_URL` to match.

### 7. Sign in

Open <http://localhost:8000>. Guests are redirected to `/login`; signed-in staff land on
`/dashboard`.

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `ADMIN_EMAIL` (default `admin@garment.test`) | `ADMIN_PASSWORD` (default `password`) |
| Operator | `operator@garment.test` | `OPERATOR_PASSWORD` (default `password`) |

**You should see:** the dashboard with KPI cards, a 14-day trend chart, recent inspections, device
status, and two forecast panels. If the charts are empty or the page has no styling, see
[common problems](#common-problems).

> These passwords are local demo values from `.env.example`. Change `ADMIN_PASSWORD` and
> `OPERATOR_PASSWORD` before seeding any shared database. In production, a missing or default
> administrator password aborts seeding instead of falling back.

### 8. Issue API tokens for the machine clients

The seeded `SERVICE` accounts exist only to own tokens. They cannot sign in to the web UI.

```bash
php artisan service:token "Inference Service" \
  --ability=inspections:write --ability=read

php artisan service:token "Cloud Forecaster" \
  --ability=forecasts:cloud:write --ability=read

php artisan service:token "Network Forecaster" \
  --ability=forecasts:network:write --ability=read
```

**You should see:** the plain token printed once, prefixed with its database ID (for example
`3|1|long-random-string`). Only a hash is stored — copy the token now.

Options:

| Option | Meaning |
| --- | --- |
| `--ability=NAME` | Repeatable; at least one is required |
| `--name=LABEL` | Token label shown on the Settings screen (default `service-token`) |
| `--keep-existing` | Keep previously issued tokens for that account instead of revoking them |

Test a token:

```bash
curl http://127.0.0.1:8000/api/v1/health
curl http://127.0.0.1:8000/api/v1/devices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

The first call needs no token and returns `200`. The second returns a paginated device list. Tokens
can be revoked from **Settings → Service API tokens**; revocation takes effect immediately.

### 9. Run the checks

```bash
php artisan test
vendor/bin/pint --test
php artisan view:cache
```

**You should see:** the full Pest suite green (currently 87 tests, 310 assertions), `pint` passing,
and `Blade templates cached successfully`.

The suite uses `garment_defect_test` from `phpunit.xml` and `RefreshDatabase`, so it rebuilds the
schema itself. It needs the database to exist, not to be migrated.

## Docker instead of a local PHP install

If Docker Desktop is available, the whole stack — app, MySQL, assets, migrations — runs in containers:

```bash
cp .env.docker.example .env.docker
# Replace every change-me value in .env.docker.
docker compose --env-file .env.docker up --build
```

Open <http://localhost:8080>. The first start runs migrations and seeds accounts. After that, set
`RUN_SEED=false` in `.env.docker` so restarts do not reset the seeded passwords.

Optional S3-compatible storage (MinIO):

```bash
docker compose --env-file .env.docker \
  -f docker-compose.yml -f docker-compose.s3.yml up --build
```

Full image, Compose, and AWS detail lives in [deployment.md](deployment.md).

## Reference

### What the seeders create

| Account | Email | Can log in? |
| --- | --- | --- |
| Administrator | `ADMIN_EMAIL` (default `admin@garment.test`) | Yes |
| Operator | `operator@garment.test` | Yes |
| Inference Service | `inference-service@service.local` | No — token only |
| Cloud Forecaster | `cloud-forecaster@service.local` | No — token only |
| Network Forecaster | `network-forecaster@service.local` | No — token only |

Re-running `db:seed` **resets** the administrator and operator passwords to the current `.env` values,
and issues no tokens. This is why container deployments switch `RUN_SEED` off after the first start.

### Where local images go

With `INSPECTION_IMAGE_DISK=local` (the default):

- files are written to `storage/app/private/inspections/YYYY/MM/<inspection-uuid>.<ext>`;
- the local disk is configured with `serve => true`, so Laravel can issue signed URLs under
  `/storage/...`, valid for 10 minutes;
- the UI also serves images through `/inspections/{inspection}/image`, which requires a signed-in
  session and `view` permission.

No `php artisan storage:link` is required for the private disk, and the `public` disk is not used for
inspection evidence.

### Useful commands

| Command | Purpose |
| --- | --- |
| `php artisan serve` | Development server on port 8000 |
| `npm run dev` | Vite dev server with hot reload |
| `composer dev` | Server + queue listener + log tail + Vite in one terminal |
| `php artisan pail` | Tail application logs |
| `php artisan route:list --except-vendor` | See every application route (39 today) |
| `php artisan db:show` | Show the configured database and table sizes |
| `php artisan about` | Environment, cache, and driver summary |
| `php artisan optimize:clear` | Clear config, route, view, and event caches |
| `php artisan tinker` | Interactive REPL |
| `php artisan migrate:status` | Which migrations have run |

The application only logs two domain events today: `inspection.ingested` and `forecast.ingested`, plus
unexpected API exceptions.

## Common problems

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `could not find driver` | `pdo_mysql` is not enabled | Enable it in `php.ini`, restart the terminal |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USERNAME`/`DB_PASSWORD` | Fix `.env`, then `php artisan config:clear` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running or wrong host/port | Start MySQL; check `DB_HOST`/`DB_PORT` |
| `SQLSTATE[HY000] [1049] Unknown database 'garment_defect_test'` | The test database was never created | Run the `CREATE DATABASE` command for it |
| `Vite manifest not found at: ...` | Assets were never built | `npm install && npm run build` |
| Page has no styling or charts are blank | Stale build or console error | `php artisan view:clear`, `npm run build`, check the browser console |
| `npm: command not found` | Node is not on `PATH` | Add the Node install directory to `PATH` |
| `419 Page Expired` on a form | Session expired or missing CSRF token | Reload the page; never hand-write forms without `@csrf` |
| `Failed to listen on 127.0.0.1:8000` | Port already in use | `php artisan serve --port=8001` and update `APP_URL` |
| Images return `404` | No `image_path`, wrong disk, or missing file | Check the inspection, `INSPECTION_IMAGE_DISK`, and `storage/app/private` |
| Login rejects a seeded account | `db:seed` was run with different `.env` values | Re-run `php artisan db:seed` or reset the password |
| `docker ps` fails with a named-pipe error | Docker Desktop is not running | Start Docker Desktop, wait for the engine, retry |

More cross-cutting symptoms, including API errors and Docker health failures, are indexed in
[troubleshooting.md](troubleshooting.md).

## Related

- Every environment variable: [environment.md](environment.md)
- How the pieces fit together: [architecture.md](architecture.md)
- Image, Compose, and production deployment: [deployment.md](deployment.md)
- Implementation-level setup detail: [`../ai-docs/15-development-guide.md`](../ai-docs/15-development-guide.md)
