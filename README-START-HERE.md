# Academy Contabo Infrastructure & CI/CD v2.0

Prepared: 2026-11-16 — DevOps infrastructure only. **No backend PHP files changed.**

## Safety status

- The GitHub `main` branch and Contabo VPS are **NOT changed** by this ZIP.
- `CONTABO_DEPLOY_ENABLED` is required to be exactly `true` before the deployment job runs. Leave it **unset or `false`** until the complete setup/acceptance test is approved.
- The workflow still runs PHP/JS tests and security scans, and publishes a versioned image to GHCR on pushes to main. Images are deployed by **digest**, not by a mutable `latest` tag.
- Do **not** run `docker compose down`, `docker volume prune`, `docker system prune`, or replace the live `/opt/academy/deployment/docker-compose.launch.yml` from this archive.
- No AWS EC2 deployment credentials or actual Contabo/Object Storage passwords belong in Git. The two `AWS_*` keys in `/root/.secrets/contabo-s3.env` are used by Restic for the **Contabo** S3-compatible endpoint and must remain.

## Repository files in this package

| Path | Purpose |
| --- | --- |
| `.github/workflows/ci.yml` | CI tests, Trivy scans, GHCR publication, gated deployment to Contabo |
| `.dockerignore` | Excludes `.env`, private keys, local uploads, logs, backups and runtime files from builds |
| `docker/app/Dockerfile` | PHP 8.3/Node multistage production app, without baked credentials |
| `docker-compose.yml` | Local-only Docker Compose (MySQL 8.4, Redis, Nginx HTTP on 127.0.0.1:8080) |
| `docker/nginx/dev.conf` | Local-only development Nginx configuration |
| `docker-compose.prod.yml` | Review/reference copy of the Contabo topology — no MySQL service; NOT used automatically |
| `deployment/templates/docker-compose.launch.contabo.example.yml` | Review-only copy; do not overwrite root-owned server launch file |
| `deployment/academy-deploy.sh` | Root-owned, stdin-only allowlisted deployment command with rollback |
| `deployment/academydeploy.sudoers` | Restricts `academydeploy` to the fixed root-owned deploy script with **no CLI args** |
| `deployment/CONTABO-DEPLOY-SETUP.md` | Setup, validation, SSH credentials, operational checklist |

## Apply to the user's *local* checked-out branch (NOT to the VPS)

The current checkout is `~/Academy-Contabo`, branch `ops/contabo-ghcr-deploy`.

1. Download this ZIP to `~/Downloads/academy-contabo-cicd-v2.zip`.
2. Preview the list and copy files **locally**, not on the VPS:

   ```bash
   cd ~/Academy-Contabo
   git branch --show-current
   git status --short
   unzip -l ~/Downloads/academy-contabo-cicd-v2.zip
   # Use -o only after checking the paths; they are all infra-only.
   unzip -o ~/Downloads/academy-contabo-cicd-v2.zip
   chmod +x deployment/academy-deploy.sh
   ```

3. Validate syntax and review the tracked changes:

   ```bash
   bash -n deployment/academy-deploy.sh
   git diff --check
   git status --short
   git diff --stat
   git diff -- .github/workflows/ci.yml docker-compose.yml docker-compose.prod.yml .dockerignore docker/app/Dockerfile
   # If Docker Compose is installed locally:
   ACADEMY_IMAGE=academy-app:contabo-v2 docker compose -f docker-compose.prod.yml --profile cutover config -q
   ```

4. In a review-only PR, add the selected files after validation. Do **not** merge / deploy until the separate Contabo server setup has been checked. Never add `.env`, `.ssh`, AWS access keys, Restic passwords, or generated ZIP files.

## Existing server topology (from the operator's inspection)

- `/opt/academy/deployment/docker-compose.launch.yml` is the **live** Compose base.
- `academy-production`: `app`, `nginx`, `redis_prod`, `worker` (worker has `cutover` profile).
- `academy` project: `academy_db_prod` running MySQL **8.4** via `mysql.bootstrap.yml`.
- Existing persistent volume: `academy_shared_public` and host bind `storage-public`; all tested with Restic.
- `docker-compose.production.yml` on the VPS differs from the live `launch.yml` (ports 8081/8443 vs 80/443): do not merge or invoke both in CI/CD.
- The reference `docker-compose.prod.yml` here is **not** meant to be deployed over the live root-owned Compose file.

## Secret handling and AWS cleanup

- GitHub currently contains Actions secrets named `EC2_HOST`, `EC2_SSH_KEY`, `EC2_USERNAME`. Current CI does not read them. They can be deleted from **GitHub Settings → Secrets and variables → Actions** after verifying no enabled workflow elsewhere uses them; never copy their values to Contabo secrets.
- Add `CONTABO_HOST`, `CONTABO_USERNAME`, `CONTABO_SSH_PORT`, `CONTABO_SSH_KEY`, `CONTABO_KNOWN_HOSTS` **only** through GitHub's Secrets UI, never in YAML.
- Set GitHub repository **Actions Variable** `CONTABO_DEPLOY_ENABLED` to `false` or leave it absent until authorized to deploy.
- The empty `AWS_*` entries in `.env.example` and Laravel default framework `s3`, `sqs`, `ses`, `dynamodb` config are not real secrets. Removing Laravel config belongs to the backend developer after checking usage. Do not remove Restic's S3-compatible `AWS_*` credential names.
- On the VPS, avoid modifying `/opt/academy/secrets/laravel.production.env` while the site is running; its `FILESYSTEM_DISK=local` is still intended until the developer finishes LMS storage integration.
- Historical `storage/logs/laravel.log` is tracked by Git despite being ignored. After reviewing it for sensitive content, use `git rm --cached -- storage/logs/laravel.log` in the repository and obtain developer review; if secrets were ever committed, rotate them and plan history remediation separately.

Read `deployment/CONTABO-DEPLOY-SETUP.md` before starting SSH configuration or merging any PR.
