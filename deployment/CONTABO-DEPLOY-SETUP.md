# Academy CI/CD to Contabo VPS — Runbook v2.0 (2026-11-16)

## Target and boundaries

Repo: `figo05-ai/Academy`; deploy only by GitHub Actions -> GHCR -> SSH -> Contabo.
Current server: `13.140.218.18` (`vmi3727876`). All server commands require explicit access by operator; the ZIP has made **no changes** to the server.

**App deployment only**: `academy_app_production`, `academy_worker_production`, and versioned static files inside `/var/lib/docker/volumes/academy_shared_public/_data/build`.

**Never target** `academy_db_prod` (MySQL 8.4), `academy_redis_production`, `academy_nginx_production`, volumes, TLS private keys, Restic backups or `/opt/academy/app/storage-public`.
The live Docker Compose source is `/opt/academy/deployment/docker-compose.launch.yml`, not `docker-compose.prod.yml` in the repository or the conflicting server-side `docker-compose.production.yml`.

## A. Preflight — VPS read-only checks

Run in a **root SSH session on Contabo**:

```bash
set -Eeuo pipefail
cd /opt/academy
sudo docker compose --project-name academy-production --profile cutover \
  -f deployment/docker-compose.launch.yml config -q
sudo docker ps --format 'table {{.Names}}\t{{.Status}}'
sudo docker inspect academy_app_production -f '{{.Image}}'
sudo docker inspect academy_worker_production -f '{{.Image}}'
curl --fail --show-error --silent --max-time 10 \
  --resolve landscape-tech-office.com:443:127.0.0.1 \
  https://landscape-tech-office.com/up
systemctl is-active academy-backup.timer
```

Only proceed if both app and worker image IDs are equal, `/up` is healthy with valid origin TLS, and latest offsite backup/restore tests remain good.

## B. Install restricted deployment command (does not start a deployment)

From the **local workstation**:

```bash
cd ~/Academy-Contabo
scp deployment/academy-deploy.sh root@13.140.218.18:/tmp/academy-deploy.sh
scp deployment/academydeploy.sudoers root@13.140.218.18:/tmp/academydeploy.sudoers
```

On the **VPS as root**:

```bash
set -Eeuo pipefail
bash -n /tmp/academy-deploy.sh
visudo -cf /tmp/academydeploy.sudoers
install -o root -g root -m 0700 /tmp/academy-deploy.sh /usr/local/sbin/academy-deploy.sh
install -o root -g root -m 0440 /tmp/academydeploy.sudoers /etc/sudoers.d/academy-deploy
visudo -cf /etc/sudoers.d/academy-deploy
sudo -l -U academydeploy
rm -f /tmp/academy-deploy.sh /tmp/academydeploy.sudoers
```

Do **not** add `academydeploy` to the Docker group or grant general root sudo. Ensure the parent directories for the script and its root-owned Compose files are not writable by `academydeploy`.

## C. Generate a dedicated SSH key on the LOCAL workstation

```bash
ssh-keygen -t ed25519 -f ~/.ssh/academy_contabo_actions -C 'academy-actions-contabo' -N ''
```

Manually install its **public** key on the VPS in `/home/academydeploy/.ssh/authorized_keys` (append, do not replace). The recommended authorized-key line is:

```text
restrict,command="sudo -n /usr/local/sbin/academy-deploy.sh" ssh-ed25519 AAAAC3... academy-actions-contabo
```

Keep the generated key in GitHub Actions Secret `CONTABO_SSH_KEY` only. Verify host-key fingerprint through a **trusted independent SSH console** before putting `ssh-keyscan` output into `CONTABO_KNOWN_HOSTS`. Do not trust `ssh-keyscan` output without this verification.

On the VPS, permission checks:

```bash
stat -c '%U %a %n' /home/academydeploy/.ssh /home/academydeploy/.ssh/authorized_keys
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Only after independently verifying the host key, obtain known_hosts entry on workstation using `ssh-keyscan -t ed25519 -p 22 13.140.218.18`; its complete output is the secret value.

## D. Set GitHub Secrets and deployment gate

In `figo05-ai/Academy` -> Settings -> Secrets and variables -> Actions:

Repository secrets:
- `CONTABO_HOST=13.140.218.18` (this is not a password, but keep as Secret for consistency)
- `CONTABO_USERNAME=academydeploy`
- `CONTABO_SSH_PORT=22` (adjust only if SSH really uses another port)
- `CONTABO_SSH_KEY` = full dedicated **private** key (do not show in chat or Git)
- `CONTABO_KNOWN_HOSTS` = independently verified SSH host-key line

Repository variable (Variables tab):
- `CONTABO_DEPLOY_ENABLED=false` **initially**. A deployment job only starts after an approved change sets it to exactly `true`.

The workflow uses short-lived `GITHUB_TOKEN` with `packages:write` for GHCR push and `packages:read` for pulls during deployment. For a private GHCR image, make sure the package is connected to the repository or grants the repository Actions read permission. Do NOT create or save a long-lived GHCR PAT on the VPS.

## E. Deployment behavior and rollback

1. Normal CI runs on PRs and pushes to `main`.
2. On `main`, the scanned image is published to GHCR with a SHA tag and the push returns a **digest**, which is passed to the deploy job.
3. The job runs **only if** `CONTABO_DEPLOY_ENABLED == 'true'`.
4. The restricted root script verifies the digest, Compose configuration, existing app/worker image identity, pulls the image to a short-lived Docker login session, and extracts `public/build`.
5. The script aborts if the new image's `public/index.php` differs from the live shared front controller; this requires manual review.
6. It adds new versioned assets without deleting old files, backs up both manifests, writes the fixed-image Compose override, and recreates **app + worker only**.
7. The health check uses `curl --resolve` to test `https://landscape-tech-office.com/up` directly against `127.0.0.1` **without disabling certificate verification**. It checks a read-only Laravel database migration status and worker-running state.
8. On failure after production mutation, it restores the previous Compose override, manifests and previous cached image; production Nginx, Redis and MySQL are untouched.
9. The script does NOT run migrations, delete hashed assets, restart MySQL, or wipe mounted uploads. PHP migrations and changes to `public/index.php` require a separate change plan approved with the backend developer.

**Important risk:** This rollback targets application images/manifests only. It does not roll back database schema changes, changes to non-Vite static files, or external Object Storage objects. Automated deployment must wait until those dependencies are handled in the release plan.

## F. Removing AWS EC2 deployment secrets

Current `main` CI does not reference `EC2_HOST`, `EC2_USERNAME` or `EC2_SSH_KEY`. After confirming no other enabled workflow, environment, repository or branch uses them, remove them in GitHub Settings -> Secrets and variables -> Actions. The prior AWS infrastructure has already been retired according to the operator.

**Do not remove** the `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` variable names from `/root/.secrets/contabo-s3.env`; these are for Restic's S3-compatible Contabo access. Do not rotate/delete working Restic credentials without setting up replacement access and a successful backup/restore check.

The Laravel `AWS_*` entries shown in `.env.example` are blank; these are placeholders, not AWS secrets. The developer owns modifications to `config/filesystems.php`, `config/queue.php`, `config/services.php`, etc.

## G. Local validation and PR review (no deployment)

On local workstation:

```bash
cd ~/Academy-Contabo
bash -n deployment/academy-deploy.sh
git diff --check
ACADEMY_IMAGE=academy-app:contabo-v2 docker compose \
  -f docker-compose.prod.yml --profile cutover config -q
# Optional: python3 -c 'import yaml; ...' to parse workflow if PyYAML installed
```

Create a PR from `ops/contabo-ghcr-deploy` to `main`. Leave `CONTABO_DEPLOY_ENABLED` off for the first merge. Review CI results and verify image publication. Test restricted SSH invocation and a controlled release only after approving change window, having a recent backup and a rollback rehearsal.

## H. What remains outside this package

- Contabo bucket credentials for future LMS uploads and backend file authorization (developer owns code).
- Restic retention / alerting / local dump cleanup and periodic DR exercise.
- Post-deploy 1-2 day monitoring and release notes.
- Removal of historical tracked logs and secret rotation if a real credential is found in repository history.

Never paste secret values or private keys into a chat.
