#!/usr/bin/env bash
# The cron line this deployment needs (cPanel › Cron Jobs, every minute). Sourced by deploy/cpanel-update.sh, which
# checks the account's crontab against it; run on its own, it prints the line:
#
#   ./deploy/cron-lines.sh            # PHP=/opt/cpanel/ea-php84/root/usr/bin/php ./deploy/cron-lines.sh to pick one
#
# One worker does all the background work: the async transport (jobs: LLM, scraping, styles; emails; analytics
# events; webhooks) and the scheduler (the follow-up reminders at 13:00 UTC, PRD §7.13). Cron starts it every minute;
# flock keeps it to one at a time, so a new one only starts once the previous one has hit its time limit. While a
# deploy runs, var/deploying exists and no worker starts (deploy/cpanel-update.sh creates and removes it).

FORTIA_DIR="${FORTIA_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
# Just under an hour: some shared hosts kill longer processes; the next minute's cron starts the next worker.
WORKER_TIME_LIMIT="${WORKER_TIME_LIMIT:-3540}"

fortia_cron_lines() {
    local php=${PHP_BIN:-${PHP:-/opt/cpanel/ea-php84/root/usr/bin/php}}
    local backend="$FORTIA_DIR/backend"
    printf '* * * * * cd %s && [ ! -e var/deploying ] && flock -n var/worker.lock %s bin/console messenger:consume async scheduler_default --time-limit=%s --memory-limit=256M --env=prod --no-debug >> var/log/worker.log 2>&1\n' \
        "$backend" "$php" "$WORKER_TIME_LIMIT"
}

# Reads a crontab on stdin; says which line is missing and which old one to replace.
check_cron_lines() {
    local crontab expected
    crontab="$(cat)"
    command -v flock >/dev/null 2>&1 || printf '\033[1;33m    flock is not on this account: the cron line needs it (ask the host for util-linux).\033[0m\n' >&2
    while IFS= read -r expected; do
        if grep -qxF -- "$expected" <<<"$crontab"; then
            printf '    The worker line is in the crontab.\n'
        elif grep -F -- "$FORTIA_DIR/backend" <<<"$crontab" | grep -q 'messenger:consume'; then
            printf '\033[1;33m    The crontab runs a different worker line. Replace it (cPanel › Cron Jobs):\033[0m\n' >&2
            grep -F -- "$FORTIA_DIR/backend" <<<"$crontab" | grep 'messenger:consume' | sed 's/^/      old: /' >&2
            printf '      new: %s\n' "$expected" >&2
        else
            printf '\033[1;33m    Add this line in cPanel › Cron Jobs (no worker means no AI jobs, emails or reminders):\033[0m\n' >&2
            printf '      %s\n' "$expected" >&2
        fi
    done < <(fortia_cron_lines)
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    fortia_cron_lines
fi
