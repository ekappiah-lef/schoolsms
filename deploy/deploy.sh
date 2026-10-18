#!/bin/bash
# Run on the server by the "EduNetwork CD" workflow.
# Needs IMAGE and IMAGE_TAG; GHCR_USER + GHCR_TOKEN to pull from GitHub's registry; HEALTH_CHECK_URL is optional.
# Pulls the new image, backs the database up, switches over, waits until the app is healthy
# and goes back to the previous image if it is not.
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSE="docker compose -f docker-compose.prod.yml"
# A folder may add its own compose file (the Control Centre mounts the server helper's jobs folder).
[ -f docker-compose.local.yml ] && COMPOSE="$COMPOSE -f docker-compose.local.yml"

touch .env
setenv() { if grep -q "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else echo "$1=$2" >> .env; fi; }
PREV_TAG=$(grep '^IMAGE_TAG=' .env | cut -d= -f2 || true)
echo "Currently deployed: ${PREV_TAG:-nothing}   Deploying: $IMAGE_TAG"

if [ -n "${GHCR_TOKEN:-}" ]; then
  echo "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin >/dev/null
  docker pull "$IMAGE:$IMAGE_TAG"
fi
setenv APP_IMAGE "$IMAGE"

# Safety copy of the database before the new version runs its migrations.
if [ -n "$($COMPOSE ps -q db 2>/dev/null)" ]; then
  mkdir -p backups/pre-deploy
  $COMPOSE exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers --events "$MARIADB_DATABASE"' | gzip > "backups/pre-deploy/$(date +%F_%H%M)_before_${IMAGE_TAG}.sql.gz"
  ls -t backups/pre-deploy/*.sql.gz | tail -n +21 | xargs -r rm -f   # keep the last 20
  echo "Database backed up before deploying."
fi

healthy() {
  for _ in $(seq 1 48); do   # up to 4 minutes: the first start builds the database
    if $COMPOSE exec -T app curl -fsS http://localhost/healthz >/dev/null 2>&1; then
      if [ -z "${HEALTH_CHECK_URL:-}" ] || curl -fsS --max-time 15 "$HEALTH_CHECK_URL" >/dev/null 2>&1; then return 0; fi
    fi
    sleep 5
  done
  return 1
}

setenv IMAGE_TAG "$IMAGE_TAG"
$COMPOSE up -d --remove-orphans

if healthy; then
  echo "Deployment healthy: $IMAGE_TAG is live."
  docker image prune -af --filter "until=168h" >/dev/null 2>&1 || true
else
  echo "Health check failed for $IMAGE_TAG."
  $COMPOSE logs --tail=40 app || true
  if [ -n "$PREV_TAG" ] && [ "$PREV_TAG" != "$IMAGE_TAG" ]; then
    echo "Rolling back to $PREV_TAG"
    setenv IMAGE_TAG "$PREV_TAG"
    $COMPOSE up -d
    echo "Rolled back to $PREV_TAG."
  fi
  exit 1
fi
