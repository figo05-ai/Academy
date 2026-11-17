#!/usr/bin/env bash
# Contabo Academy deployment: app + queue worker + versioned Vite assets only.
set -Eeuo pipefail
umask 077
export PATH=/usr/sbin:/usr/bin:/sbin:/bin

readonly DEPLOY_DIR=/opt/academy/deployment
readonly LAUNCH_FILE="$DEPLOY_DIR/docker-compose.launch.yml"
readonly OVERRIDE_FILE="$DEPLOY_DIR/docker-compose.ghcr.yml"
readonly PUBLIC_DIR=/var/lib/docker/volumes/academy_shared_public/_data
readonly APP_CONTAINER=academy_app_production
readonly WORKER_CONTAINER=academy_worker_production
readonly NGINX_CONTAINER=academy_nginx_production
readonly IMAGE_REPO=ghcr.io/figo05-ai/academy
readonly HEALTH_DOMAIN=landscape-tech-office.com

log() { printf '[academy-deploy] %s\n' "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

[[ "$EUID" -eq 0 ]] || die 'Must run as root through restricted sudo.'
[[ "$#" -eq 0 ]] || die 'Command-line arguments are not accepted.'
for c in docker curl python3 flock mktemp tar sha256sum cmp install; do
  command -v "$c" >/dev/null 2>&1 || die "Missing command: $c"
done
docker compose version >/dev/null 2>&1 || die 'Docker Compose v2 required.'
[[ -f "$LAUNCH_FILE" ]] || die "Missing $LAUNCH_FILE"
[[ -f "$PUBLIC_DIR/index.php" && -f "$PUBLIC_DIR/build/manifest.json" ]] || die 'Existing public volume is not ready.'
[[ -L "$PUBLIC_DIR/storage" ]] || die 'Public storage symlink missing; manual review needed.'
[[ "$(stat -c %U "$LAUNCH_FILE")" == root ]] || die 'Compose file must be root-owned.'

exec 9>/run/lock/academy-deploy.lock
flock -n 9 || die 'Another deployment is in progress.'

# Three newline-delimited values from the pinned GitHub Actions SSH job.
IFS= read -r IMAGE || die 'Missing image digest on stdin.'
IFS= read -r GHCR_USER || die 'Missing registry username on stdin.'
IFS= read -r GHCR_TOKEN || die 'Missing registry token on stdin.'
[[ "$IMAGE" =~ ^ghcr\.io/figo05-ai/academy@sha256:[0-9a-f]{64}$ ]] || die 'Unapproved image reference; digest required.'
[[ "$GHCR_USER" =~ ^[A-Za-z0-9_.-]{1,100}$ ]] || die 'Invalid registry username.'
[[ -n "$GHCR_TOKEN" ]] || die 'Empty registry token.'

# Establish a predictable rollback before any production change.
docker inspect "$APP_CONTAINER" >/dev/null 2>&1 || die 'App container missing.'
docker inspect "$WORKER_CONTAINER" >/dev/null 2>&1 || die 'Worker container missing.'
docker inspect "$NGINX_CONTAINER" >/dev/null 2>&1 || die 'Nginx container missing.'
PREVIOUS_ID="$(docker inspect -f '{{.Image}}' "$APP_CONTAINER")"
[[ "$PREVIOUS_ID" == "$(docker inspect -f '{{.Image}}' "$WORKER_CONTAINER")" ]] || die 'App and worker images differ; manual review required.'
PREVIOUS_REF="$(docker inspect -f '{{.Config.Image}}' "$APP_CONTAINER")"
docker image inspect "$PREVIOUS_REF" >/dev/null || die 'Previous image is missing locally; rollback unsafe.'

WORK_DIR="$(mktemp -d /opt/academy/.academy-deploy.XXXXXXXX)"
chmod 0700 "$WORK_DIR"
DOCKER_CONFIG="$WORK_DIR/docker-config"
install -d -m 0700 "$DOCKER_CONFIG"
export DOCKER_CONFIG

HAD_OVERRIDE=0
if [[ -e "$OVERRIDE_FILE" ]]; then
  [[ -f "$OVERRIDE_FILE" && ! -L "$OVERRIDE_FILE" ]] || die 'Unexpected Compose override type.'
  [[ "$(stat -c %U "$OVERRIDE_FILE")" == root ]] || die 'Compose override not root-owned.'
  cp -p "$OVERRIDE_FILE" "$WORK_DIR/override.previous.yml"
  HAD_OVERRIDE=1
fi

CHANGES_STARTED=0
MANIFEST_SWITCHED=0
PREVIOUS_MANIFEST_EXISTED=0
PREVIOUS_FONTS_MANIFEST_EXISTED=0
SUCCESS=0

# Never embed untrusted user input into YAML: digest was strictly validated.
cat > "$WORK_DIR/override.next.yml" <<YAML
services:
  app:
    image: $IMAGE
    pull_policy: never
  worker:
    image: $IMAGE
    pull_policy: never
YAML
chmod 0600 "$WORK_DIR/override.next.yml"

COMPOSE_PREVIEW=(docker compose --project-name academy-production --profile cutover -f "$LAUNCH_FILE" -f "$WORK_DIR/override.next.yml")
"${COMPOSE_PREVIEW[@]}" config -q >/dev/null
SERVICES="$("${COMPOSE_PREVIEW[@]}" config --services)"
for service in app worker nginx redis_prod; do
  grep -Fxq "$service" <<< "$SERVICES" || die "Missing Compose service $service"
done

restore_previous_override() {
  if [[ "$HAD_OVERRIDE" -eq 1 ]]; then
    cp -p "$WORK_DIR/override.previous.yml" "$OVERRIDE_FILE"
  else
    rm -f -- "$OVERRIDE_FILE"
  fi
}

rollback() {
  log 'Starting rollback to previously running app/worker image.'
  if [[ "$MANIFEST_SWITCHED" -eq 1 ]]; then
    if [[ "$PREVIOUS_MANIFEST_EXISTED" -eq 1 ]]; then
      install -m 0644 "$WORK_DIR/manifest.previous.json" "$PUBLIC_DIR/build/.manifest.rollback.json"
      mv -f "$PUBLIC_DIR/build/.manifest.rollback.json" "$PUBLIC_DIR/build/manifest.json"
    fi
    if [[ "$PREVIOUS_FONTS_MANIFEST_EXISTED" -eq 1 ]]; then
      install -m 0644 "$WORK_DIR/fonts-manifest.previous.json" "$PUBLIC_DIR/build/.fonts-manifest.rollback.json"
      mv -f "$PUBLIC_DIR/build/.fonts-manifest.rollback.json" "$PUBLIC_DIR/build/fonts-manifest.json"
    elif [[ -f "$WORK_DIR/fonts-manifest.newly-added" ]]; then
      rm -f "$PUBLIC_DIR/build/fonts-manifest.json"
    fi
  fi

  restore_previous_override || log 'WARNING: Could not restore previous override.'

  export ACADEMY_IMAGE="$PREVIOUS_REF" ACADEMY_PULL_POLICY=never
  COMPOSE_ROLLBACK=(docker compose --project-name academy-production --profile cutover -f "$LAUNCH_FILE")
  if [[ "$HAD_OVERRIDE" -eq 1 ]]; then COMPOSE_ROLLBACK+=(-f "$OVERRIDE_FILE"); fi
  if "${COMPOSE_ROLLBACK[@]}" up --pull never -d --no-deps --force-recreate app worker; then
    docker exec "$NGINX_CONTAINER" nginx -t >/dev/null 2>&1 \
      && docker exec "$NGINX_CONTAINER" nginx -s reload >/dev/null 2>&1 \
      || log 'CRITICAL: Nginx reload during rollback failed.'
    log 'Rollback container recreation succeeded (verify health manually).'

  else
    log 'CRITICAL: Rollback failed. Check app/worker containers and logs now.'
  fi
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ "$SUCCESS" -ne 1 && "$CHANGES_STARTED" -eq 1 ]]; then
    rollback || log 'CRITICAL: A rollback command failed.'
  fi
  docker rm -f "academy-deploy-assets-$$" >/dev/null 2>&1 || true
  [[ -z "${DOCKER_CONFIG:-}" ]] || docker logout ghcr.io >/dev/null 2>&1 || true
  [[ -z "${WORK_DIR:-}" ]] || rm -rf -- "$WORK_DIR"
  exit "$rc"
}
trap cleanup EXIT

log "Pulling approved image digest: $IMAGE"
printf '%s' "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin >/dev/null
unset GHCR_TOKEN
# A digest reference is content-addressed; don't pull by a mutable tag.
docker pull "$IMAGE" >/dev/null
docker image inspect "$IMAGE" >/dev/null
docker run --rm --network none --entrypoint php --workdir /var/www "$IMAGE" artisan --version >/dev/null

log 'Extracting and validating new versioned frontend assets.'
docker create --name "academy-deploy-assets-$$" "$IMAGE" >/dev/null
install -d -m 0755 "$WORK_DIR/new-build"
docker cp "academy-deploy-assets-$$:/var/www/public/build/." "$WORK_DIR/new-build/"
[[ -s "$WORK_DIR/new-build/manifest.json" ]] || die 'New manifest.json missing.'
python3 -m json.tool "$WORK_DIR/new-build/manifest.json" >/dev/null
# Nginx serves the existing public directory; changing the front controller requires a separate manual release.
docker cp "academy-deploy-assets-$$:/var/www/public/index.php" "$WORK_DIR/new-index.php"
cmp -s "$WORK_DIR/new-index.php" "$PUBLIC_DIR/index.php" || die 'public/index.php changed; needs a reviewed manual rollout.'

# Hold old manifests for a transactional rollback; preserve all existing hashed assets.
if [[ -f "$PUBLIC_DIR/build/manifest.json" ]]; then
  cp -p "$PUBLIC_DIR/build/manifest.json" "$WORK_DIR/manifest.previous.json"
  PREVIOUS_MANIFEST_EXISTED=1
fi
if [[ -f "$PUBLIC_DIR/build/fonts-manifest.json" ]]; then
  cp -p "$PUBLIC_DIR/build/fonts-manifest.json" "$WORK_DIR/fonts-manifest.previous.json"
  PREVIOUS_FONTS_MANIFEST_EXISTED=1
fi
if [[ "$PREVIOUS_FONTS_MANIFEST_EXISTED" -eq 1 && ! -f "$WORK_DIR/new-build/fonts-manifest.json" ]]; then
  die 'New build has no fonts-manifest.json; review manually.'
fi
if [[ "$PREVIOUS_FONTS_MANIFEST_EXISTED" -eq 0 && -f "$WORK_DIR/new-build/fonts-manifest.json" ]]; then
  touch "$WORK_DIR/fonts-manifest.newly-added"
fi

# Copy *new* fingerprinted assets first; leave older assets for old browser sessions.
# Do not overwrite manifest files until immediately before recreating app and worker.
log 'Copying new fingerprinted assets without deleting older ones.'
(
  cd "$WORK_DIR/new-build"
  tar --exclude=./manifest.json --exclude=./fonts-manifest.json -cf - .
) | tar -C "$PUBLIC_DIR/build" -xf -

# Mutating production begins here. Every following error will trigger rollback.
CHANGES_STARTED=1
install -o root -g root -m 0600 "$WORK_DIR/override.next.yml" "$WORK_DIR/.override.install"
mv -f "$WORK_DIR/.override.install" "$OVERRIDE_FILE"

cp "$WORK_DIR/new-build/manifest.json" "$PUBLIC_DIR/build/.manifest.next.json"
chmod 0644 "$PUBLIC_DIR/build/.manifest.next.json"
mv -f "$PUBLIC_DIR/build/.manifest.next.json" "$PUBLIC_DIR/build/manifest.json"
MANIFEST_SWITCHED=1
if [[ -f "$WORK_DIR/new-build/fonts-manifest.json" ]]; then
  cp "$WORK_DIR/new-build/fonts-manifest.json" "$PUBLIC_DIR/build/.fonts-manifest.next.json"
  chmod 0644 "$PUBLIC_DIR/build/.fonts-manifest.next.json"
  mv -f "$PUBLIC_DIR/build/.fonts-manifest.next.json" "$PUBLIC_DIR/build/fonts-manifest.json"
fi

# Do not touch Nginx, Redis, MySQL or data volumes. Worker has the cutover profile.
COMPOSE=(docker compose --project-name academy-production --profile cutover -f "$LAUNCH_FILE" -f "$OVERRIDE_FILE")
"${COMPOSE[@]}" stop -t 120 worker
"${COMPOSE[@]}" up --pull never -d --no-deps --force-recreate app worker
docker exec "$NGINX_CONTAINER" nginx -t >/dev/null
docker exec "$NGINX_CONTAINER" nginx -s reload >/dev/null
EXPECTED_ID="$(docker image inspect -f '{{.Id}}' "$IMAGE")"
[[ "$(docker inspect -f '{{.Image}}' "$APP_CONTAINER")" == "$EXPECTED_ID" ]] || die 'App image mismatch.'
[[ "$(docker inspect -f '{{.Image}}' "$WORKER_CONTAINER")" == "$EXPECTED_ID" ]] || die 'Worker image mismatch.'

log 'Running HTTPS/TLS, database and container health checks.'
HEALTHY=0
for attempt in $(seq 1 30); do
  APP_RUNNING="$(docker inspect -f '{{.State.Running}}' "$APP_CONTAINER" 2>/dev/null || echo false)"
  WORKER_RUNNING="$(docker inspect -f '{{.State.Running}}' "$WORKER_CONTAINER" 2>/dev/null || echo false)"
  if [[ "$APP_RUNNING" == true && "$WORKER_RUNNING" == true ]] \
    && curl --fail --silent --show-error --max-time 10 \
       --resolve "$HEALTH_DOMAIN:443:127.0.0.1" \
       "https://$HEALTH_DOMAIN/up" >/dev/null 2>&1 \
    && docker exec "$APP_CONTAINER" php artisan migrate:status --no-interaction >/dev/null 2>&1; then
    sleep 2
    if [[ "$(docker inspect -f '{{.State.Running}}' "$WORKER_CONTAINER" 2>/dev/null || echo false)" == true ]]; then
      HEALTHY=1
      break
    fi
  fi
  sleep 3
done
[[ "$HEALTHY" -eq 1 ]] || die 'New release failed health checks.'

SUCCESS=1
log "DEPLOYMENT SUCCESS: $IMAGE"
log 'Old hashed assets were retained to avoid breaking browsers with cached HTML.'
