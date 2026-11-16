# GitHub Actions → AWS with OIDC

The deploy workflow assumes an IAM role through GitHub's OpenID Connect provider.
No long-lived AWS access keys are stored in GitHub.

## 1. Create the OIDC provider

Once per AWS account:

```bash
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com \
  --thumbprint-list 6938fd4d98bab03faadb97b34396831e3780aea1
```

If AWS rejects the thumbprint (the value can change), create the provider in the
IAM console with **Provider URL** `https://token.actions.githubusercontent.com`
and **Audience** `sts.amazonaws.com`. AWS now validates GitHub's certificate
chain and may not require a thumbprint.

## 2. Create the deploy role

Trust policy — replace `ORG/REPO` with the GitHub repository:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
          "token.actions.githubusercontent.com:sub": "repo:ORG/REPO:environment:production"
        }
      }
    }
  ]
}
```

The workflow's deploy job declares `environment: production`, so the subject is
the environment claim. This prevents a pull request from another branch from
assuming the role.

Create the role:

```bash
aws iam create-role \
  --role-name garment-defect-deploy \
  --assume-role-policy-document file://trust-policy.json

aws iam attach-role-policy \
  --role-name garment-defect-deploy \
  --policy-arn arn:aws:iam::aws:policy/...   # or use an inline policy below
```

### Permission policy

Attach an inline policy (replace account, region, repository name, and role
names):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "EcrLogin",
      "Effect": "Allow",
      "Action": ["ecr:GetAuthorizationToken"],
      "Resource": "*"
    },
    {
      "Sid": "EcrPush",
      "Effect": "Allow",
      "Action": [
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:BatchGetImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload",
        "ecr:PutImage",
        "ecr:DescribeImages",
        "ecr:DescribeRepositories"
      ],
      "Resource": "arn:aws:ecr:<REGION>:<ACCOUNT_ID>:repository/garment-defect"
    },
    {
      "Sid": "EcsDeploy",
      "Effect": "Allow",
      "Action": [
        "ecs:DescribeTaskDefinition",
        "ecs:RegisterTaskDefinition",
        "ecs:RunTask",
        "ecs:DescribeTasks",
        "ecs:DescribeServices",
        "ecs:UpdateService"
      ],
      "Resource": "*"
    },
    {
      "Sid": "PassTaskRoles",
      "Effect": "Allow",
      "Action": "iam:PassRole",
      "Resource": [
        "arn:aws:iam::<ACCOUNT_ID>:role/garment-defect-execution",
        "arn:aws:iam::<ACCOUNT_ID>:role/garment-defect-task"
      ],
      "Condition": {
        "StringEquals": {
          "iam:PassedToService": "ecs-tasks.amazonaws.com"
        }
      }
    },
    {
      "Sid": "ReadDeployLogs",
      "Effect": "Allow",
      "Action": [
        "logs:DescribeLogStreams",
        "logs:GetLogEvents",
        "logs:FilterLogEvents"
      ],
      "Resource": "arn:aws:logs:<REGION>:<ACCOUNT_ID>:log-group:/ecs/garment-defect:*"
    }
  ]
}
```

`ecs:RegisterTaskDefinition` requires `iam:PassRole` for the execution and task
roles, hence the condition above. Keep the resource scope as tight as your
account structure allows.

## 3. Configure GitHub repository settings

### Secret

| Name | Value |
| --- | --- |
| `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::<ACCOUNT_ID>:role/garment-defect-deploy` |

### Variables

| Name | Example | Required |
| --- | --- | --- |
| `AWS_REGION` | `ap-southeast-1` | yes |
| `ECR_REPOSITORY` | `garment-defect` | yes |
| `ECS_CLUSTER` | `garment-defect` | yes |
| `ECS_SERVICE` | `garment-defect` | yes |
| `ECS_TASK_FAMILY` | `garment-defect` | yes |
| `ECS_SUBNET_IDS` | `subnet-aaaa,subnet-bbbb` | optional, enables automatic migrations |
| `ECS_SECURITY_GROUP_IDS` | `sg-cccc` | optional, enables automatic migrations |

Set them with the CLI:

```bash
gh variable set AWS_REGION --body "ap-southeast-1"
gh variable set ECR_REPOSITORY --body "garment-defect"
gh variable set ECS_CLUSTER --body "garment-defect"
gh variable set ECS_SERVICE --body "garment-defect"
gh variable set ECS_TASK_FAMILY --body "garment-defect"

gh secret set AWS_DEPLOY_ROLE_ARN \
  --body "arn:aws:iam::<ACCOUNT_ID>:role/garment-defect-deploy"
```

Also create a **production** environment in the repository and add required
reviewers if deployments need approval.

## 4. What the workflow does

`.github/workflows/deploy.yml`:

1. validates the required repository variables and secret;
2. assumes the role with `aws-actions/configure-aws-credentials` using OIDC;
3. logs in to ECR and builds/pushes the image tagged with the commit SHA (or the
   manual input) plus `latest`;
4. downloads the current task definition, replaces the image, strips read-only
   fields, and registers a new revision;
5. optionally runs `php artisan migrate --force` as a one-off Fargate task using
   the new revision;
6. updates the ECS service and waits for `services-stable`;
7. writes a summary with the image URI and task definition ARN.

The job requires `permissions: id-token: write`; the workflow already declares it.

## 5. Verify before the first deploy

```bash
# Confirm GitHub can assume the role (run the workflow manually).
gh workflow run deploy.yml

# Inspect the run.
gh run list --workflow deploy.yml
gh run watch
```

If the workflow fails at credential configuration, inspect the error:

| Error | Cause |
| --- | --- |
| `Not authorized to perform sts:AssumeRoleWithWebIdentity` | Trust policy subject or audience mismatch |
| `No OpenID Connect provider found` | Provider URL differs from `token.actions.githubusercontent.com` |
| `AccessDenied` on ECR push | Repository ARN in the policy is wrong, or the image name differs |
| `AccessDenied` on `iam:PassRole` | Execution/task role ARNs are wrong or the condition blocks the call |
| `ResourceNotFoundException` on ECS | Cluster, service, or task family variables are wrong |

## 6. Fallback (not recommended)

If OIDC is not available, the workflow could use an IAM user access key stored as
a GitHub secret. That creates a long-lived credential that must be rotated and
audited. Prefer OIDC; use static keys only as a temporary, documented exception.

## 7. Rotation and offboarding

- The role has no long-lived credential to rotate.
- To revoke GitHub access, delete the role or remove the repository/org from the
  trust policy condition.
- Removing the GitHub environment or renaming the repository changes the subject
  claim; update the trust policy at the same time.
- Review the role's CloudTrail activity periodically.
