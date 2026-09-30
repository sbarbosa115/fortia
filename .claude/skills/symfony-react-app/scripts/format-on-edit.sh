#!/usr/bin/env bash
# Claude Code PostToolUse hook: format the file Claude just wrote, the way the project's formatter would
# (steps/05-static-analysis.md). PHP goes through PHP-CS-Fixer, and JS/TS/CSS/JSON/MD through Prettier, each only
# when the project has that tool's config and the container is running. Otherwise it does nothing, so it is safe to
# install globally. It never fails the edit: the gate is where style is enforced.
#
# .claude/settings.json:
#   "hooks": { "PostToolUse": [ { "matcher": "Write|Edit|MultiEdit",
#       "hooks": [ { "type": "command", "command": ".claude/skills/symfony-react-app/scripts/format-on-edit.sh" } ] } ] }
#
# Settings: PHP_SERVICE (php), NODE_SERVICE (node).

file=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("tool_input", {}).get("file_path", ""))' 2>/dev/null)
[ -n "$file" ] && [ -f "$file" ] || exit 0
root=$(git -C "$(dirname "$file")" rev-parse --show-toplevel 2>/dev/null) || exit 0
[ -f "$root/docker-compose.yml" ] || [ -f "$root/compose.yaml" ] || [ -f "$root/docker-compose.yaml" ] || [ -f "$root/compose.yml" ] || exit 0

# The app folder is the closest folder above the file that has the tool's config; paths inside the container are
# relative to it (the compose services mount it as their working directory).
find_up() {  # find_up <dir> <file names…>
    local dir=$1; shift
    while [ "$dir" != "$root" ] && [ "$dir" != / ]; do
        for name in "$@"; do [ -e "$dir/$name" ] && { echo "$dir"; return 0; }; done
        dir=$(dirname "$dir")
    done
    for name in "$@"; do [ -e "$root/$name" ] && { echo "$root"; return 0; }; done
    return 1
}
running() { docker compose -f "$(ls "$root"/{docker-compose,compose}.y*ml 2>/dev/null | head -1)" ps --status running --services 2>/dev/null | grep -qx "$1"; }

cd "$root" || exit 0
case $file in
    *.php)
        app=$(find_up "$(dirname "$file")" .php-cs-fixer.dist.php .php-cs-fixer.php) || exit 0
        running "${PHP_SERVICE:-php}" || exit 0
        docker compose exec -T "${PHP_SERVICE:-php}" vendor/bin/php-cs-fixer fix --quiet "${file#"$app"/}" >/dev/null 2>&1
        ;;
    *.js|*.jsx|*.ts|*.tsx|*.mjs|*.mts|*.cjs|*.css|*.scss|*.json|*.md|*.yml|*.yaml)
        app=$(find_up "$(dirname "$file")" .prettierrc .prettierrc.json .prettierrc.js .prettierrc.mjs .prettierrc.yaml .prettierrc.yml prettier.config.js prettier.config.mjs) || exit 0
        running "${NODE_SERVICE:-node}" || exit 0
        docker compose exec -T "${NODE_SERVICE:-node}" npx prettier --write --log-level silent "${file#"$app"/}" >/dev/null 2>&1
        ;;
esac
exit 0
