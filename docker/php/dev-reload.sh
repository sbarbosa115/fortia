#!/bin/sh
# Dev only. The php and worker containers run Symfony without debug-mode freshness checks (APP_DEBUG=0): on Docker
# Desktop, checking every source file through the bind mount on each request cost seconds per request. Instead, this
# loop notices a change in the code or the config and clears the dev cache (the next request rebuilds it) and asks
# the worker to restart on the new code.
set -u
cd /app || exit 1
mkdir -p var
stamp=var/.dev-reload-stamp
[ -f "$stamp" ] || touch "$stamp"
while true; do
    if find src config templates translations -newer "$stamp" -type f 2>/dev/null | head -n 1 | grep -q .; then
        touch "$stamp"
        rm -rf var/cache/dev
        php bin/console messenger:stop-workers >/dev/null 2>&1 || true
        echo "$(date -u +%H:%M:%S) code changed: dev cache cleared, worker restarting"
    fi
    sleep 2
done
