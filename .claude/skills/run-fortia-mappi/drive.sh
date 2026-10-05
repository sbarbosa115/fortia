#!/usr/bin/env bash
# Runs driver.mjs in the compose `e2e` image (Playwright 1.55 + Chromium) against the running stack.
# Usage: .claude/skills/run-fortia-mappi/drive.sh login owner@acme.test goto /console/questionnaires ss list
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
export MSYS_NO_PATHCONV=1   # Git Bash on Windows would rewrite /console/... and /pw into C:/Program Files/Git/...
docker compose --profile e2e run --rm --no-deps -T \
  -e STEP_TIMEOUT_MS -e BASE_URL -e UPSTREAM_URL \
  -v mappi_playwright:/pw \
  -v "$PWD/.claude/skills/run-fortia-mappi/driver.mjs:/driver.mjs:ro" \
  e2e sh -c '[ -d /pw/node_modules/playwright ] || npm i --silent --prefix /pw playwright@1.55.0 >&2; node /driver.mjs "$@"' sh "$@"
