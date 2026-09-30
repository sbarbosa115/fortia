#!/usr/bin/env bash
# The static analysis and code style gate (steps/05-static-analysis.md), run against this checkout's Docker stack.
#
#   .claude/skills/symfony-react-app/scripts/gate.sh              # check only: every tool must exit 0
#   .claude/skills/symfony-react-app/scripts/gate.sh --fix        # run the fixers first (php-cs-fixer, prettier, eslint --fix)
#   .claude/skills/symfony-react-app/scripts/gate.sh --skip=cs,prettier   # leave out checks (say so in the report)
#
# Checks: cs (PHP-CS-Fixer), phpstan, deptrac, prettier, eslint, tsc, and every executable in the project's
# .claude/gate.d/ (a project's own checks: each is run from the repo root and named after its file).
# A tool the project does not have yet is MISSING, and MISSING fails the gate like FAIL does: install it and
# create its config (steps/05, §5.2), or --skip it knowingly. Exit 0 only when every check that ran passed.
#
# Project settings, from the environment: PHP_SERVICE (php), NODE_SERVICE (node), APP_DIR (backend: the folder
# mounted into both containers, relative to the repo root).
set -uo pipefail

ROOT=$(git rev-parse --show-toplevel)
PHP_SERVICE=${PHP_SERVICE:-php}
NODE_SERVICE=${NODE_SERVICE:-node}
APP_DIR=${APP_DIR:-backend}
APP="$ROOT/$APP_DIR"
LOGS=$(mktemp -d "${TMPDIR:-/tmp}/gate.XXXXXX")
FIX=0
SKIP=","
for arg in "$@"; do
    case $arg in
        --fix) FIX=1 ;;
        --skip=*) SKIP=",${arg#--skip=}," ;;
        -h|--help) sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "unknown argument: $arg" >&2; exit 64 ;;
    esac
done

cd "$ROOT" || exit 1
running=$(docker compose ps --status running --services 2>/dev/null)
for service in "$PHP_SERVICE" "$NODE_SERVICE"; do
    grep -qx "$service" <<<"$running" || { echo "The '$service' service is not running: start this checkout's stack first (stack.py up)." >&2; exit 1; }
done

php() { docker compose exec -T "$PHP_SERVICE" "$@"; }
node() { docker compose exec -T "$NODE_SERVICE" "$@"; }
has_any() { for f in "$@"; do [ -e "$APP/$f" ] && return 0; done; return 1; }
npm_script() { grep -q "\"$1\"[[:space:]]*:" "$APP/package.json" 2>/dev/null; }

has_cs() { [ -x "$APP/vendor/bin/php-cs-fixer" ] && has_any .php-cs-fixer.dist.php .php-cs-fixer.php; }
has_prettier() { [ -e "$APP/node_modules/.bin/prettier" ] && has_any .prettierrc .prettierrc.json .prettierrc.js .prettierrc.mjs .prettierrc.yaml .prettierrc.yml prettier.config.js prettier.config.mjs; }
has_eslint() { [ -e "$APP/node_modules/.bin/eslint" ] && has_any eslint.config.js eslint.config.mjs eslint.config.cjs eslint.config.ts; }

if [ $FIX -eq 1 ]; then
    echo "Fixing…"
    [[ $SKIP != *,cs,* ]] && has_cs && php vendor/bin/php-cs-fixer fix --quiet
    [[ $SKIP != *,prettier,* ]] && has_prettier && node npx prettier --write --log-level warn .
    [[ $SKIP != *,eslint,* ]] && has_eslint && node npx eslint . --fix >/dev/null
fi

declare -a NAMES RESULTS
failed=0
record() { NAMES+=("$1"); RESULTS+=("$2"); [ "$2" = PASS ] || [ "$2" = SKIPPED ] || failed=1; }

# run <name> <available?> <command…>: the command's output goes to $LOGS/<name>.log
run() {
    local name=$1 available=$2; shift 2
    if [[ $SKIP == *,$name,* ]]; then record "$name" SKIPPED; return; fi
    if ! eval "$available"; then record "$name" MISSING; return; fi
    printf '  %-9s … ' "$name"
    if "$@" >"$LOGS/$name.log" 2>&1; then echo ok; record "$name" PASS; else echo FAIL; record "$name" FAIL; fi
}

echo "Checking…"
run cs       has_cs                                         php vendor/bin/php-cs-fixer fix --dry-run --diff --show-progress=none
run phpstan  '[ -x "$APP/vendor/bin/phpstan" ]'             php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
if grep -q '"deptrac"' "$APP/composer.json" 2>/dev/null; then
    run deptrac true                                        php composer -q deptrac
else
    run deptrac '[ -x "$APP/vendor/bin/deptrac" ]'          php vendor/bin/deptrac analyse --no-progress
fi
run prettier has_prettier                                   node npx prettier --check --log-level warn .
if npm_script lint; then run eslint has_eslint              node npm run -s lint
else run eslint has_eslint                                  node npx eslint .; fi
if npm_script typecheck; then run tsc true                  node npm run -s typecheck
else run tsc '[ -e "$APP/tsconfig.json" ]'                  node npx tsc --noEmit; fi
for extra in "$ROOT"/.claude/gate.d/*; do
    [ -x "$extra" ] && [ -f "$extra" ] && run "$(basename "$extra")" true "$extra"
done

echo
printf '%-9s %s\n' CHECK RESULT
for i in "${!NAMES[@]}"; do printf '%-9s %s\n' "${NAMES[$i]}" "${RESULTS[$i]}"; done

for i in "${!NAMES[@]}"; do
    case ${RESULTS[$i]} in
        FAIL) echo; echo "── ${NAMES[$i]} (last 40 lines; full log $LOGS/${NAMES[$i]}.log)"; tail -n 40 "$LOGS/${NAMES[$i]}.log" ;;
        MISSING) echo; echo "── ${NAMES[$i]}: not installed or no config. Install it and create its config (symfony-react-app skill, steps/05-static-analysis.md §5.2), or --skip=${NAMES[$i]} and say so." ;;
    esac
done

echo
if [ $failed -eq 0 ]; then echo "Gate: PASS"; else echo "Gate: FAIL (fix, then run again until it passes)"; fi
exit $failed
