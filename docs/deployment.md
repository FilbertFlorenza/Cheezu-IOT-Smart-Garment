# Deployment

The repository produces one application image containing Nginx, PHP-FPM 8.3,
and Supervisor. MySQL and object storage are external services. The same image
runs locally with Docker Compose or in AWS ECS/Fargate.

## Container image

Build:

```bash
docker build -f docker/Dockerfile -t garment-defect:local .
```

The multi-stage build:

1. installs npm dependencies and compiles Vite assets;
2. installs Composer dependencies with `--no-dev` and an optimised autoloader;
3. builds a PHP 8.3-FPM Alpine runtime with Nginx and Supervisor;
4. copies only the compiled assets and production vendor tree;
5. adds a `HEALTHCHECK` against `/up`.

Inspect the result:

```bash
docker run --rm garment-defect:local php artisan --version
docker image inspect garment-defect:local --format '{{.Config.ExposedPorts}}'
```

### Run without Compose

```bash
docker run --rm --publish 8080:80 \
  --env APP_ENV=production \
  --env APP_KEY="base64:$(openssl rand -base64 32)" \
  --env APP_DEBUG=false \
  --env APP_URL=http://localhost:8080 \
  --env DB_CONNECTION=mysql \
  --env DB_HOST=host.docker.internal \
  --env DB_DATABASE=garment_defect \
  --env DB_USERNAME=garment \
  --env DB_PASSWORD='...' \
  --env RUN_MIGRATIONS=true \
  --env RUN_SEED=true \
  --env WAIT_FOR_DB=true \
  garment-defect:local
```

The entrypoint:

1. fixes storage permissions;
2. refuses to start in production without `APP_KEY`;
3. optionally waits for MySQL;
4. optionally migrates and seeds;
5. caches config, routes, views, and events;
6. starts Supervisor, which runs Nginx and PHP-FPM.

`RUN_MIGRATIONS` and `RUN_SEED` default to `false` in the image. Use them for a
single-container demo, not for every replica of a production service.

## Docker Compose (local/demo)

```bash
cp .env.docker.example .env.docker
# Replace every change-me value.
docker compose --env-file .env.docker up --build
```

| Service | Role |
| --- | --- |
| `app` | Application image on <http://localhost:8080> |
| `db` | MySQL 8.4 with a persistent volume |
| `minio` | Optional S3-compatible storage (`docker-compose.s3.yml` overlay) |
| `minio-init` | Creates the private bucket once MinIO is healthy |

Set `APP_KEY` in `.env.docker` for stable sessions:

```bash
php artisan key:generate --show
```

After the first successful start, set `RUN_SEED=false`; otherwise every restart
re-applies the seeded administrator and operator passwords from `.env.docker`.

For MinIO, uncomment the S3 block in `.env.docker`, keep
`AWS_ENDPOINT=http://minio:9000`, `AWS_USE_PATH_STYLE_ENDPOINT=true`, and start
with the storage overlay:

```bash
docker compose --env-file .env.docker \
  -f docker-compose.yml -f docker-compose.s3.yml up --build
```

## AWS reference architecture

```
                         ┌──────────────────────┐
 Internet ── HTTPS ────► │  Application Load    │
                         │  Balancer (ACM TLS)  │
                         └──────────┬───────────┘
                                    │ HTTP :80
                         ┌──────────▼───────────┐
                         │  ECS Fargate service │
                         │  garment-defect      │
                         │  (1..N tasks)        │
                         └───┬────────┬─────────┘
                             │        │
                    ┌────────▼──┐  ┌──▼────────────────┐
                    │ RDS MySQL │  │ S3 private bucket │
                    │ (private) │  │ inspection images │
                    └───────────┘  └───────────────────┘
                             │
                    ┌────────▼─────────┐
                    │ Secrets Manager  │
                    │ APP_KEY, DB, etc.│
                    └──────────────────┘
                    CloudWatch Logs / ECR / IAM roles
```

| Component | Recommendation |
| --- | --- |
| Compute | ECS Fargate, 0.5 vCPU / 1 GB per task to start |
| Database | RDS MySQL 8.0, Multi-AZ for production, private subnets |
| Storage | S3 bucket with Block Public Access and SSE |
| Registry | ECR with image scanning |
| Secrets | Secrets Manager, one secret per concern |
| Sessions/cache | `database` driver, or ElastiCache Redis if scaling out |
| Logs | CloudWatch Logs via the `awslogs` driver |
| TLS | ACM certificate on the ALB; redirect HTTP to HTTPS |
| DNS | Route 53 alias to the ALB |

The repository does **not** create AWS resources. Provisioning requires an AWS
account and explicit approval; the templates and commands below are the
deployment contract.

## One-time provisioning

### 1. ECR repository

```bash
aws ecr create-repository \
  --repository-name garment-defect \
  --image-scanning-configuration scanOnPush=true \
  --encryption-configuration encryptionType=AES256
```

### 2. S3 bucket

```bash
BUCKET=garment-defect-inspection-images

aws s3api create-bucket \
  --bucket "$BUCKET" \
  --region "$AWS_REGION" \
  --create-bucket-configuration LocationConstraint="$AWS_REGION"

aws s3api put-public-access-block \
  --bucket "$BUCKET" \
  --public-access-block-configuration \
    BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

aws s3api put-bucket-encryption \
  --bucket "$BUCKET" \
  --server-side-encryption-configuration \
    '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'
```

Add a lifecycle rule if images should expire; the database keeps the inspection
row, and a missing object shows as an unavailable image rather than an error.

### 3. RDS MySQL

Create a private MySQL 8.0 instance and a database:

```sql
CREATE DATABASE garment_defect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'garment'@'%' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON garment_defect.* TO 'garment'@'%';
```

The ECS security group needs outbound access to the RDS security group on port
3306. Do not expose RDS publicly.

### 4. Secrets Manager

```bash
APP_KEY="base64:$(openssl rand -base64 32)"

aws secretsmanager create-secret \
  --name garment-defect/app-key \
  --secret-string "$APP_KEY"

aws secretsmanager create-secret \
  --name garment-defect/db-password \
  --secret-string '<strong-password>'

aws secretsmanager create-secret \
  --name garment-defect/admin-password \
  --secret-string '<strong-admin-password>'

aws secretsmanager create-secret \
  --name garment-defect/operator-password \
  --secret-string '<strong-operator-password>'
```

Use the secret ARNs in the task definition's `secrets` array.

### 5. IAM roles

**Execution role** (`garment-defect-execution`) — trusted by
`ecs-tasks.amazonaws.com`:

- `AmazonECSTaskExecutionRolePolicy` (ECR pull and CloudWatch Logs);
- `secretsmanager:GetSecretValue` on the four secret ARNs.

**Task role** (`garment-defect-task`) — trusted by `ecs-tasks.amazonaws.com`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::garment-defect-inspection-images/*"
    },
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::garment-defect-inspection-images"
    }
  ]
}
```

No static AWS keys are needed in the application.

### 6. Log group

```bash
aws logs create-log-group --log-group-name /ecs/garment-defect
aws logs put-retention-policy \
  --log-group-name /ecs/garment-defect \
  --retention-in-days 30
```

### 7. Cluster, load balancer, service

Create an ECS cluster, an ALB with an HTTPS listener, a target group on port 80
with health check path `/up`, and a Fargate service in private subnets. The
service security group allows inbound port 80 from the ALB only.

## Task definition

[`docker/ecs-task-definition.json`](../docker/ecs-task-definition.json) is a
template. Replace every `<PLACEHOLDER>`:

| Placeholder | Value |
| --- | --- |
| `<ACCOUNT_ID>` | AWS account ID |
| `<REGION>` | e.g. `ap-southeast-1` |
| `<IMAGE_TAG>` | Commit SHA or release tag |
| `<DOMAIN>` | Public hostname, e.g. `qc.example.com` |
| `<RDS_ENDPOINT>` | RDS writer endpoint |
| `<INSPECTION_BUCKET>` | S3 bucket name |
| `<ADMIN_EMAIL>` | Administrator login for the seed task |

Register it:

```bash
aws ecs register-task-definition \
  --cli-input-json file://docker/ecs-task-definition.json
```

The service task runs with `RUN_MIGRATIONS=false` and `RUN_SEED=false` so rolling
deployments never race on schema changes.

### Run migrations as a one-off task

After registering a new revision (for example `garment-defect:42`):

```bash
CLUSTER=garment-defect
TASK_DEF=garment-defect:42
SUBNETS=subnet-aaaa,subnet-bbbb
SECURITY_GROUPS=sg-cccc

TASK_ARN=$(aws ecs run-task \
  --cluster "$CLUSTER" \
  --task-definition "$TASK_DEF" \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNETS],securityGroups=[$SECURITY_GROUPS],assignPublicIp=DISABLED}" \
  --overrides '{"containerOverrides":[{"name":"app","command":["php","artisan","migrate","--force","--no-interaction"]}]}' \
  --query 'tasks[0].taskArn' --output text)

aws ecs wait tasks-stopped --cluster "$CLUSTER" --tasks "$TASK_ARN"
aws ecs describe-tasks --cluster "$CLUSTER" --tasks "$TASK_ARN" \
  --query 'tasks[0].containers[0].exitCode'
```

A non-zero exit code means the deployment must stop. The GitHub Actions
`deploy.yml` workflow performs this automatically when
`ECS_SUBNET_IDS` and `ECS_SECURITY_GROUP_IDS` are configured.

### Seed the first administrator

Run once, on a fresh database:

```bash
TASK_ARN=$(aws ecs run-task \
  --cluster "$CLUSTER" \
  --task-definition "$TASK_DEF" \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNETS],securityGroups=[$SECURITY_GROUPS],assignPublicIp=DISABLED}" \
  --overrides '{"containerOverrides":[{"name":"app","command":["php","artisan","db:seed","--force","--no-interaction"]}]}' \
  --query 'tasks[0].taskArn' --output text)

aws ecs wait tasks-stopped --cluster "$CLUSTER" --tasks "$TASK_ARN"
```

The seeder reads `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and `OPERATOR_PASSWORD` from the
task definition/secrets. In `APP_ENV=production`, a missing or default
administrator password aborts seeding.

### Issue service tokens

Tokens must be generated inside the running service (or a one-off task), because
they are written to the database:

```bash
aws ecs execute-command \
  --cluster "$CLUSTER" \
  --task <TASK_ID> \
  --container app \
  --interactive \
  --command "php artisan service:token 'Inference Service' --ability=inspections:write --ability=read"
```

ECS Exec must be enabled on the service and task role. Alternatively run a
one-off task with the token command and read the log output; the plain token is
printed once. Store it in the producer's secret manager immediately.

## CI/CD

Two GitHub Actions workflows are included:

| Workflow | Trigger | Result |
| --- | --- | --- |
| `.github/workflows/ci.yml` | Push to `main`, pull request | PHP 8.3 + MySQL tests, Pint, Vite build, Blade compile, Docker build, container health probe |
| `.github/workflows/deploy.yml` | Tag `v*` or manual dispatch | OIDC login, ECR push, task revision registration, migration task, ECS service update, wait for stability |

Configure the repository before the first deployment. See
[`aws-oidc.md`](aws-oidc.md) for the OIDC provider, IAM trust policy, and the
exact variables and secrets.

Deploy a release:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Or run **Deploy** manually and provide an image tag (defaults to the commit SHA).

## Operations

### Health checks

| Probe | Path | Use |
| --- | --- | --- |
| Framework | `/up` | ALB target group and container health check |
| Dependency | `/api/v1/health` | Database and storage-disk status |

Both return `200` when healthy. `/api/v1/health` returns `503` with
`status: "degraded"` if the database is unreachable.

### Scaling

- The application is stateless when sessions use the database and images use S3.
- Scale the ECS service horizontally; do not enable `RUN_MIGRATIONS` per task.
- Rate limits are per token/IP, not global.
- For very high ingestion volume, move forecasts or notifications to a queue and
  add workers; ingestion itself is a short synchronous transaction.

### Logs

- Application logs go to stderr and then CloudWatch (`/ecs/garment-defect`).
- Nginx access and error logs also go to stdout/stderr.
- Every API response includes `X-Request-Id`; search CloudWatch for that value.
- Set a retention policy (30 days is a reasonable default).

### Rollback

```bash
aws ecs update-service \
  --cluster garment-defect \
  --service garment-defect \
  --task-definition garment-defect:<previous-revision>
```

Database migrations are forward-only. If a bad migration shipped, roll forward
with a corrective migration; restore from a snapshot only as a last resort.

### Backups

- Enable automated RDS backups and test point-in-time recovery.
- Enable S3 versioning on the inspection bucket.
- Record the app key secret version; losing `APP_KEY` invalidates sessions and
  encrypted values.
- Back up MySQL and S3 together so evidence rows and images stay consistent.

## Security checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` injected from Secrets Manager, never committed
- [ ] RDS private, reachable only from the ECS security group
- [ ] S3 bucket private, public access blocked, encryption enabled
- [ ] Task role limited to the inspection bucket
- [ ] Execution role limited to ECR, logs, and the four secrets
- [ ] ALB HTTPS only; HTTP redirects to HTTPS
- [ ] Default administrator password replaced; no default in secrets
- [ ] Service tokens scoped per producer and stored outside the repository
- [ ] CloudWatch retention configured
- [ ] ECR image scanning enabled
- [ ] AWS Budgets/alerts configured

## Known limitations

- No infrastructure-as-code is included; the task definition and commands are a
  deployment template, not a Terraform/CDK stack.
- The default Compose stack runs a single application replica and MySQL in
  containers; use RDS and ECS for production.
- AWS resources were intentionally not created from this environment. Verify the
  image build locally or in CI, then provision with the approved account.
