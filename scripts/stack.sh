#!/usr/bin/env bash
# This checkout's Docker stack (steps/07-verify.md §7.1). Every checkout and worktree is its own Compose project;
# a worktree's gitignored .env gives it its own host ports (split.py start writes it).
#
#   scripts/stack.sh up       build, start, install, keys, databases (dev + test), migrations, demo data
#   scripts/stack.sh reset    drop the dev database and rebuild it from the migrations and the demo data
#   scripts/stack.sh status   services and the app's URLs
#   scripts/stack.sh down     stop (add --volumes to drop the databases and the vendor/var volumes)
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

port() { grep -E "^$1=" .env 2>/dev/null | cut -d= -f2 || true; }
HTTP_PORT=$(port HTTP_PORT); HTTP_PORT=${HTTP_PORT:-8080}
MAILPIT_PORT=$(port MAILPIT_PORT); MAILPIT_PORT=${MAILPIT_PORT:-8025}
php() { docker compose exec -T php "$@"; }

# backend/vendor lives in a Docker volume (docker-compose.yml). The skill's gate.sh looks for the tools under
# backend/vendor/bin and backend/node_modules/.bin on the host before running them in the containers (both are
# volumes): leave a marker for each.
markers() {
    mkdir -p backend/vendor/bin
    mkdir -p backend/node_modules/.bin
    for tool in prettier eslint; do
        [ -e "backend/node_modules/.bin/$tool" ] || printf '#!/bin/sh\n# Marker: %s runs in the node container (backend/node_modules is a volume).\n' "$tool" > "backend/node_modules/.bin/$tool"
    done
    for tool in php-cs-fixer phpstan deptrac; do
        [ -e "backend/vendor/bin/$tool" ] || printf '#!/bin/sh\n# Marker: %s runs in the php container (backend/vendor is a volume).\n' "$tool" > "backend/vendor/bin/$tool"
        chmod +x "backend/vendor/bin/$tool" 2>/dev/null || true
    done
}

seed() {
    php php bin/console doctrine:migrations:migrate -n
    php php bin/console app:seed-demo
}

case "${1:-status}" in
    up)
        docker compose up -d --build
        php composer install -n --no-progress
        markers
        php php bin/console lexik:jwt:generate-keypair --skip-if-exists -n
        php php bin/console doctrine:database:create --if-not-exists -n
        php php bin/console doctrine:database:create --if-not-exists --env=test -n
        php php bin/console doctrine:migrations:migrate --env=test -n
        seed
        echo "Waiting for the first UI build (docker compose logs -f node)…"
        until docker compose logs node 2>/dev/null | grep -qE "compiled (successfully|with)"; do sleep 3; done
        "$0" status
        ;;
    reset)
        php php bin/console doctrine:database:drop --force --if-exists -n
        php php bin/console doctrine:database:create -n
        seed
        ;;
    status)
        docker compose ps --format 'table {{.Service}}\t{{.Status}}'
        echo
        echo "Console     http://localhost:$HTTP_PORT/console   (demo accounts: see docs/tests/ui-regression.md)"
        echo "Respondent  http://localhost:$HTTP_PORT/"
        echo "API docs    http://localhost:$HTTP_PORT/api/doc.json"
        echo "Mailpit     http://localhost:$MAILPIT_PORT"
        ;;
    down)
        shift
        docker compose down "$@"
        ;;
    *)
        sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'
        exit 64
        ;;
esac
