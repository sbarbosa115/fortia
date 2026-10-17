#!/usr/bin/env bash
# Updates this Mappi deployment to the latest commit on GitHub. Made for a cPanel account over SSH:
#
#   cd ~/fortia && ./deploy/cpanel-update.sh
#
# It pauses the worker, pulls, installs the PHP dependencies, rebuilds the production cache, makes the JWT keys,
# rebuilds (or checks) the UI, backs up and migrates the database, installs the Apache rules, fixes what has to be
# writable and starts the worker again. Every step is safe to run again.
#
# First time on a new account, before this script is of any use:
#   1. git clone https://github.com/sbarbosa115/fortia.git ~/fortia   (HTTPS + a personal access token, or a deploy key)
#   2. Point the domain's document root at ~/fortia/backend/public (cPanel › Domains)
#   3. Pick PHP 8.4 for that domain (cPanel › MultiPHP Manager) with pdo_mysql, intl, zip, mbstring, sodium, dom
#      (cPanel › Select PHP Version › Extensions), and the upload limits (MultiPHP INI Editor): upload_max_filesize
#      500M, post_max_size 520M, memory_limit 512M — answers take files up to 500 MB (PRD §13.5)
#   4. Create the database and its user in cPanel (MySQL Databases), the user with ALL PRIVILEGES on it. The schema uses
#      the utf8mb4_0900_ai_ci collation: MySQL 8, or a MariaDB recent enough to know it (this script checks)
#   5. cp deploy/env.local.example backend/.env.local, fill it in, chmod 600 it
#   6. Node 22+ for the UI build (cPanel › Setup Node.js App, any app name: only its node/npm are used), or build it
#      on your machine and upload it:  docker compose run --rm -e NODE_ENV=production node npm run build
#      then copy backend/public/build/ to the account
#   7. Run this script: it creates the tables and the JWT keys on the first run
#   8. Add the cron line it prints (cPanel › Cron Jobs, every minute; deploy/cron-lines.sh): the worker that runs the
#      AI jobs, the emails and the daily reminders
#   9. Sign up at https://<domain>/console, then make that user the platform Admin:
#      PROMOTE_ADMIN=you@example.com SKIP_PULL=1 ./deploy/cpanel-update.sh
#
# Settings it takes from the environment, all optional:
#   PHP           path to the PHP 8.4 CLI  (detected)
#   COMPOSER      path to composer         (detected)
#   NPM           path to npm              (detected, also in cPanel's ~/nodevenv)
#   BRANCH        branch to pull           (the checked out one)
#   SKIP_PULL     =1 to rebuild without pulling
#   SKIP_UI       =1 to keep the public/build/ that is there (built elsewhere and uploaded)
#   ALLOW_DIRTY   =1 to run with local changes to tracked files (the pull fails if it touches one)
#   BACKUP_ALWAYS =1 to dump the database even when no migration is pending
#   BACKUP_DIR    where the dump goes (default: the home directory)
#   PROMOTE_ADMIN an email: gives that (signed up) user the platform's Admin group
#   WORKER_TIME_LIMIT seconds a worker runs before cron starts a fresh one (default 3540)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP_DIR/backend"
cd "$APP_DIR"

step() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
note() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33m    %s\033[0m\n' "$*" >&2; }
fail() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; exit 1; }

# A value as Symfony's Dotenv sees it on this account: .env, then .env.local, .env.prod, .env.prod.local (the last
# one that sets it wins). Quotes are stripped; an inline "# comment" is not supported.
env_value() {
    local key=$1 file line value=""
    for file in "$BACKEND/.env" "$BACKEND/.env.local" "$BACKEND/.env.prod" "$BACKEND/.env.prod.local"; do
        [[ -f "$file" ]] || continue
        line="$(grep -E "^$key=" "$file" | tail -1 || true)"
        [[ -n "$line" ]] && value="${line#*=}"
    done
    value="${value#\"}"; value="${value%\"}"; value="${value#\'}"; value="${value%\'}"
    printf '%s' "$value"
}
# Whether the account's own files set it (the committed .env only holds dev defaults).
set_locally() { grep -qE "^$1=.+" "$BACKEND/.env.local" "$BACKEND/.env.prod.local" 2>/dev/null; }

# Variables a web server sets make cPanel's PHP wrappers run php-cgi instead of the CLI: never inherit them.
unset GATEWAY_INTERFACE REQUEST_METHOD SCRIPT_FILENAME SCRIPT_NAME PATH_TRANSLATED REDIRECT_STATUS SERVER_SOFTWARE
# The first PHP 8.4+ CLI that exists. cPanel installs each version under /opt/cpanel (CloudLinux's selector under
# /opt/alt), and "php" is often an old one.
find_php() {
    local candidate
    for candidate in "${PHP:-}" ea-php84 /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php \
        ea-php85 /opt/cpanel/ea-php85/root/usr/bin/php /opt/alt/php85/usr/bin/php php84 php85 php; do
        [[ -n "$candidate" ]] || continue
        command -v "$candidate" >/dev/null 2>&1 || continue
        # The command-line PHP itself: a php-cgi (which some cPanel wrappers become) prints its usage for -r and exits 0.
        if [[ "$("$candidate" -r 'echo PHP_SAPI === "cli" && PHP_VERSION_ID >= 80400 ? "ok" : "no";' 2>/dev/null)" == "ok" ]]; then
            command -v "$candidate"
            return 0
        fi
    done
    return 1
}

find_composer() {
    local candidate
    for candidate in "${COMPOSER:-}" composer "$HOME/composer.phar" "$APP_DIR/composer.phar"; do
        [[ -n "$candidate" ]] || continue
        if command -v "$candidate" >/dev/null 2>&1; then command -v "$candidate"; return 0; fi
        if [[ -f "$candidate" ]]; then echo "$candidate"; return 0; fi
    done
    return 1
}

# npm on the PATH, else the newest one cPanel's "Setup Node.js App" installed (~/nodevenv/<app>/<version>/bin), else
# the EasyApache Node packages. Prints the directory to put on the PATH.
find_npm_dir() {
    local candidate
    if [[ -n "${NPM:-}" ]]; then dirname "$NPM"; return 0; fi
    if command -v npm >/dev/null 2>&1; then dirname "$(command -v npm)"; return 0; fi
    for candidate in $(ls -d "$HOME"/nodevenv/*/*/bin /opt/cpanel/ea-nodejs*/bin 2>/dev/null | sort -V -r); do
        [[ -x "$candidate/npm" ]] && { echo "$candidate"; return 0; }
    done
    return 1
}

step "Checking this account"
[[ -d "$APP_DIR/.git" ]] || fail "$APP_DIR is not a git clone: see the first-time steps at the top of this script."
[[ -f "$BACKEND/.env.local" ]] || fail "backend/.env.local is missing: copy deploy/env.local.example, fill it in, then chmod 600 it."
grep -q '^APP_ENV=prod' "$BACKEND/.env.local" || fail "backend/.env.local must set APP_ENV=prod."
# A compiled .env.local.php (composer dump-env) wins over every .env file, so edits to .env.local would be ignored.
[[ ! -f "$BACKEND/.env.local.php" ]] || warn "backend/.env.local.php exists and overrides .env.local: delete it unless you maintain it by hand."

# backend/.env holds dev defaults (dev-only secrets, Mailpit, a Docker database): each of these must come from the
# account's own file, or production would sign its tokens with a secret that is on GitHub.
MISSING=()
for key in APP_SECRET JWT_PASSPHRASE RESPONDENT_TOKEN_SECRET STORAGE_SIGNING_SECRET SETTINGS_ENCRYPTION_KEY \
    DATABASE_URL MAILER_DSN DEFAULT_URI APP_URL FRONTEND_URL ADMIN_FRONTEND_URL; do
    set_locally "$key" || MISSING+=("$key")
done
(( ${#MISSING[@]} == 0 )) || fail "backend/.env.local does not set: ${MISSING[*]} (see deploy/env.local.example)."
for key in APP_SECRET JWT_PASSPHRASE RESPONDENT_TOKEN_SECRET STORAGE_SIGNING_SECRET SETTINGS_ENCRYPTION_KEY; do
    value="$(env_value "$key")"
    [[ "$value" != *dev-only* ]] || fail "$key in backend/.env.local is still the dev-only default: php -r 'echo bin2hex(random_bytes(32));'"
    (( ${#value} >= 32 )) || fail "$key in backend/.env.local is shorter than 32 characters: php -r 'echo bin2hex(random_bytes(32));'"
done
[[ "$(env_value MAILER_DSN)" != *mailpit* ]] || fail "MAILER_DSN points at Mailpit (the dev mail catcher): set the domain's SMTP mailbox."
[[ "$(env_value DATABASE_URL)" != *@database:* ]] || fail "DATABASE_URL points at the Docker database: set the cPanel one."
for key in SMTP_ALLOW_PRIVATE_HOSTS ANALYTICS_ALLOW_PRIVATE_HOSTS; do
    [[ "$(env_value "$key")" != "1" ]] || fail "$key=1 is for development only (it lets accounts reach internal hosts): delete that line."
done
for key in APP_URL FRONTEND_URL ADMIN_FRONTEND_URL DEFAULT_URI; do
    [[ "$(env_value "$key")" == https://* ]] || warn "$key is not https: $(env_value "$key")"
done
[[ "$(env_value CORS_ALLOW_ORIGIN)" != *localhost* ]] \
    || warn "CORS_ALLOW_ORIGIN still allows only localhost: set it to the domain (see deploy/env.local.example)."
if [[ "$(env_value LLM_PROVIDER)" == "fake" || ( "$(env_value LLM_PROVIDER)" == "auto" && -z "$(env_value OPENAI_API_KEY)" ) ]]; then
    warn "No OpenAI key and LLM_PROVIDER=$(env_value LLM_PROVIDER): the AI answers from the offline fake until an account saves its own key."
fi

PHP_BIN="$(find_php)" || fail "No PHP 8.4 or newer command-line PHP found. Set PHP=/opt/cpanel/ea-php84/root/usr/bin/php and run again."
note "PHP:      $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# cPanel picks the web PHP with an AddHandler line in the document root's .htaccess, and it is easily a different
# version from the one on the command line — then Composer, the migrations and the compiled cache are built with
# one PHP while Apache runs the app with another, whose extensions may not match.
check_extensions() {
    local binary=$1 label=$2 extension
    # ctype/iconv: composer.json; sodium: the sealed settings; zip/dom: file templates and imports; openssl: JWT keys.
    for extension in pdo_mysql intl zip mbstring sodium dom ctype iconv openssl; do
        "$binary" -m | grep -qix "$extension" || fail "$label has no \"$extension\". Enable it in cPanel → Select PHP Version."
    done
    "$binary" -m | grep -qix curl || warn "$label has no \"curl\": outgoing HTTP (OpenAI, scraping) falls back to PHP streams."
}
check_extensions "$PHP_BIN" "The command-line PHP"

# "|| true": with pipefail, a missing .htaccess (a fresh account) or no handler line would abort the script.
WEB_PHP=""
if [[ -f "$BACKEND/public/.htaccess" ]]; then
    WEB_PHP="$( { grep -hoE 'x-httpd-ea-php[0-9]+' "$BACKEND/public/.htaccess" || true; } | head -1 | sed 's/x-httpd-//')"
fi
if [[ -n "$WEB_PHP" ]]; then
    # composer.lock is resolved on PHP 8.4 (docker/php/Dockerfile): an older web PHP may fail on every page.
    (( 10#${WEB_PHP#ea-php} >= 84 )) || fail "Apache runs the app with $WEB_PHP; it needs PHP 8.4. Set it in cPanel → MultiPHP Manager, then run this again."
    CLI_TAG="ea-php$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION, PHP_MINOR_VERSION;')"
    if [[ "$WEB_PHP" != "$CLI_TAG" ]]; then
        warn "Apache runs the app with $WEB_PHP, but this script is using $CLI_TAG."
        warn "Set the same version in cPanel → MultiPHP Manager, or run this with PHP=/opt/cpanel/$WEB_PHP/root/usr/bin/php."
    fi
    WEB_PHP_BIN="/opt/cpanel/$WEB_PHP/root/usr/bin/php"
    if [[ -x "$WEB_PHP_BIN" ]]; then
        check_extensions "$WEB_PHP_BIN" "$WEB_PHP (the one Apache uses)"
        note "Web PHP:  $WEB_PHP ($("$WEB_PHP_BIN" -r 'echo PHP_VERSION;'))"
    fi
fi

COMPOSER_BIN="$(find_composer || true)"
[[ -n "$COMPOSER_BIN" ]] && note "Composer: $COMPOSER_BIN" || warn "Composer not found: backend/vendor/ must already be uploaded and up to date."

console() { "$PHP_BIN" "$BACKEND/bin/console" "$@"; }

# Only tracked changes matter: they are what a fast-forward pull would fight over. Untracked files are left
# alone — a cPanel account puts its own .htaccess, .user.ini and php.ini in the document root, and they must stay.
if [[ "${ALLOW_DIRTY:-0}" == "1" ]]; then
    warn "ALLOW_DIRTY=1: keeping the local changes to tracked files. The pull will fail if it touches one of them."
elif ! git diff --quiet || ! git diff --cached --quiet; then
    git status --short --untracked-files=no
    # The usual reason on a new account: the settings were put in .env, which is the committed file of defaults.
    if ! git diff --quiet -- "$BACKEND/.env" || ! git diff --cached --quiet -- "$BACKEND/.env"; then
        warn "backend/.env is committed, so every pull will fight over it, and a secret there is one push from being public."
        warn "This account's settings belong in backend/.env.local, which overrides .env and is never committed:"
        warn "    git diff backend/.env     # the lines you changed there"
        warn "    # make sure each of them is in backend/.env.local, then:"
        warn "    git restore backend/.env"
    fi
    fail "Tracked files have local changes. Commit them, or discard them with \"git restore .\", then run again."
fi
UNTRACKED="$(git ls-files --others --exclude-standard | head -5 | tr '\n' ' ')"
if [[ -n "$UNTRACKED" ]]; then
    note "Leaving untracked files alone: $UNTRACKED"
fi

step "Pausing the worker"
# While var/deploying exists the cron line starts no worker (deploy/cron-lines.sh). The running one is asked to stop
# after its current message — with the code and the cache it was started on, so before the pull — and the deploy
# waits for its lock, so no job runs half on the old code and half on the new schema.
mkdir -p "$BACKEND/var/log"
PAUSE_FILE="$BACKEND/var/deploying"
touch "$PAUSE_FILE"
if [[ -d "$BACKEND/vendor" && -d "$BACKEND/var/cache/prod" ]]; then
    console messenger:stop-workers --env=prod --no-debug >/dev/null 2>&1 || true
fi
if command -v flock >/dev/null 2>&1; then
    if flock -w 180 "$BACKEND/var/worker.lock" true; then
        note "No worker is running."
    else
        warn "The worker is still busy after 3 minutes (a long AI job?); going on. It stops after that message."
    fi
fi
# Started again in "Starting the worker" at the end. A deploy that stops on an error leaves it paused on purpose:
# the code and the database may not match. Run this script again, or "rm backend/var/deploying" to resume as it is.

BEFORE="$(git rev-parse HEAD)"

if [[ "${SKIP_PULL:-0}" != "1" ]]; then
    BRANCH="${BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
    step "Pulling $BRANCH from GitHub"
    case "$(git remote get-url origin)" in
        git@*|ssh://*) note "The remote is SSH: this account needs a deploy key, or switch it to an HTTPS URL with a token." ;;
    esac
    git pull --ff-only origin "$BRANCH"
    if [[ "$BEFORE" == "$(git rev-parse HEAD)" ]]; then
        note "Already up to date; the steps below run anyway."
    else
        git --no-pager log --oneline "$BEFORE..HEAD" | sed 's/^/    /'
    fi
fi

if [[ -n "$COMPOSER_BIN" ]]; then
    step "PHP dependencies"
    # No scripts: they boot the kernel, which the cache warmup below does properly once the code is in place.
    (cd "$BACKEND" && COMPOSER_MEMORY_LIMIT=-1 "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader \
        --classmap-authoritative --no-interaction --no-progress --no-scripts)
else
    [[ -d "$BACKEND/vendor" ]] || fail "backend/vendor/ is missing and Composer is not available: upload vendor/ built with PHP 8.4, or install Composer."
fi

step "Production cache"
# Removed, not cache:clear'd: right after a pull, var/cache/prod still holds the previous version's container, which
# prod trusts without checking, and cache:clear would have to boot it. var/share (APP_SHARE_DIR) is kept.
rm -rf "$BACKEND/var/cache/prod"
console cache:warmup --env=prod --no-debug

step "Sign-in keys"
# The console's JWTs are signed with backend/config/jwt/private.pem (gitignored): made once with JWT_PASSPHRASE, kept
# across deploys. lexik:jwt:check-config fails when the keys and the passphrase no longer match.
console lexik:jwt:generate-keypair --skip-if-exists --env=prod --no-debug | sed 's/^/    /'
chmod 600 "$BACKEND"/config/jwt/*.pem
console lexik:jwt:check-config --env=prod --no-debug >/dev/null 2>&1 \
    || fail "The JWT keys do not open with JWT_PASSPHRASE. If the passphrase changed on purpose: rm backend/config/jwt/*.pem and run again (everyone signs in again)."
note "backend/config/jwt/ is in place."

step "Frontend build"
NPM_DIR="$(find_npm_dir || true)"
if [[ "${SKIP_UI:-0}" != "1" && -n "$NPM_DIR" ]]; then
    export PATH="$NPM_DIR:$PATH"
    note "npm $(npm -v), node $(node -v) ($NPM_DIR)"
    NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"
    (( NODE_MAJOR >= 22 )) || warn "Node $NODE_MAJOR is older than the dev stack's (24, docker-compose.yml): if the build fails, pick Node 22+ in cPanel › Setup Node.js App."
    # npm ci wipes and reinstalls node_modules, which takes minutes and a few hundred MB of the account's quota.
    # It writes node_modules/.package-lock.json, so that file's age says whether the lock has moved since.
    if [[ ! -d "$BACKEND/node_modules" || "$BACKEND/package-lock.json" -nt "$BACKEND/node_modules/.package-lock.json" ]]; then
        # NODE_ENV=production here would skip devDependencies, and Webpack Encore, Babel and TypeScript are among them.
        (cd "$BACKEND" && NODE_ENV=development npm ci --no-audit --no-fund)
    else
        note "Dependencies unchanged since the last deploy; skipping npm ci."
    fi
    (cd "$BACKEND" && npm run build)
elif [[ -f "$BACKEND/public/build/entrypoints.json" ]]; then
    [[ "${SKIP_UI:-0}" == "1" ]] && note "SKIP_UI=1: using the public/build/ that is here." \
        || note "npm is not on this account; using the public/build/ that is here."
    if [[ -n "$(find "$BACKEND/assets" -newer "$BACKEND/public/build/entrypoints.json" -print -quit 2>/dev/null)" ]]; then
        warn "assets/ is newer than public/build/: this build is stale. Build it where npm exists and upload public/build/:"
        warn "    docker compose run --rm -e NODE_ENV=production node npm run build"
    fi
else
    fail "No UI build and no npm on this account. Set up Node 22+ (cPanel › Setup Node.js App), or build backend/public/build/ elsewhere and copy it here."
fi
ENTRYPOINTS="$BACKEND/public/build/entrypoints.json"
[[ -f "$ENTRYPOINTS" ]] || fail "The build wrote no entrypoints.json. Run \"npm run build\" in backend/ and read the error above it."
for entry in console respondent; do
    grep -q "\"$entry\"" "$ENTRYPOINTS" || fail "public/build/entrypoints.json has no \"$entry\" entry: the build is incomplete."
done
# A dev build (npm run dev/watch) has unhashed names and source maps: it works, but slowly and uncached.
grep -qE '\.[0-9a-f]{8}\.js' "$ENTRYPOINTS" || warn "public/build/ is a development build (no hashed file names): build it with \"npm run build\"."

step "Database server"
# Doctrine picks its SQL dialect from serverVersion in DATABASE_URL, not from the server. A MySQL version string on
# a MariaDB server (cPanel often runs MariaDB) makes Doctrine report its migration table out of sync on every run.
REAL_VERSION="$(console dbal:run-sql 'SELECT VERSION()' --env=prod --no-debug 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9.+~-]*' | head -1 || true)"
CONFIGURED_VERSION="$(env_value DATABASE_URL | grep -oE 'serverVersion=[^&"'"'"']+' | cut -d= -f2 || true)"
[[ -n "$REAL_VERSION" ]] || fail "Could not reach the database with DATABASE_URL from backend/.env.local."
note "Server:     $REAL_VERSION"
note "Configured: serverVersion=${CONFIGURED_VERSION:-(missing)}"
real_is_mariadb=0; [[ "$REAL_VERSION" == *MariaDB* ]] && real_is_mariadb=1
conf_is_mariadb=0; [[ "$CONFIGURED_VERSION" == *[Mm]ariaDB* ]] && conf_is_mariadb=1
if [[ -z "$CONFIGURED_VERSION" || "$real_is_mariadb" != "$conf_is_mariadb" ]]; then
    SUGGESTED="${REAL_VERSION%%-*}"
    [[ "$real_is_mariadb" == "1" ]] && SUGGESTED="$SUGGESTED-MariaDB"
    fail "serverVersion does not match the database server. In backend/.env.local set serverVersion=$SUGGESTED in DATABASE_URL, then run again."
fi
# Every table is created with utf8mb4_0900_ai_ci (config/packages/doctrine.yaml, the migrations), which is also what
# makes search ignore case and accents. MySQL 8 has it; MariaDB only in its recent releases.
if ! console dbal:run-sql "SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci'" --env=prod --no-debug 2>/dev/null | grep -q 'utf8mb4_0900_ai_ci'; then
    fail "The database server has no utf8mb4_0900_ai_ci collation, which every table uses. Ask the host for MySQL 8 (or a MariaDB that has it, 11.4.5+), then run again."
fi
note "Collation:  utf8mb4_0900_ai_ci is available."

step "Database migrations"
# A migration is the only step here that can destroy data, so it never runs without a dump first. The dump is
# taken with the credentials from .env.local, through a 0600 defaults file so the password is never an argument
# (arguments are visible to every user on the box via ps).
# Exit 0 from up-to-date means nothing to run, but a console that cannot boot exits non-zero too, and the two
# must not look alike: the message decides, and whatever it said is printed before a backup is taken.
MIGRATION_CHECK="$(console doctrine:migrations:up-to-date --env=prod --no-debug 2>&1)" && CHECK_OK=1 || CHECK_OK=0
if [[ "$CHECK_OK" == "1" ]] && grep -q 'Up-to-date' <<<"$MIGRATION_CHECK" && [[ "${BACKUP_ALWAYS:-0}" != "1" ]]; then
    note "No migration is pending; the database is untouched and no backup was taken."
else
    if [[ "${BACKUP_ALWAYS:-0}" == "1" ]]; then
        note "BACKUP_ALWAYS=1."
    else
        note "Backing up because the migration check said:"
        grep -vE '^[[:space:]]*$' <<<"$MIGRATION_CHECK" | sed 's/^/      /'
    fi
    command -v mysqldump >/dev/null 2>&1 || fail "A migration is pending but mysqldump is not here: it must not run without a backup."

    DB_CONF="$(mktemp)"
    trap 'rm -f "$DB_CONF" "$DB_CONF.err"' EXIT
    chmod 600 "$DB_CONF"
    # PHP parses the DSN: it already understands the URL encoding a password needs (%40 and friends).
    DB_NAME="$(env_value DATABASE_URL | "$PHP_BIN" -r '
        $p = parse_url(trim(stream_get_contents(STDIN))) ?: exit(1);
        $conf = sprintf("[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $p["host"] ?? "localhost", $p["port"] ?? 3306,
            rawurldecode($p["user"] ?? ""), rawurldecode($p["pass"] ?? ""));
        file_put_contents($argv[1], $conf);
        echo ltrim($p["path"] ?? "", "/");
    ' "$DB_CONF")" || fail "Could not read DATABASE_URL from backend/.env.local."
    [[ -n "$DB_NAME" ]] || fail "DATABASE_URL has no database name."

    BACKUP="${BACKUP_DIR:-$HOME}/$DB_NAME-$(date +%Y%m%d-%H%M%S).sql"
    note "Backing up $DB_NAME before migrating…"
    dump() { mysqldump --defaults-extra-file="$DB_CONF" --single-transaction --no-tablespaces \
        --default-character-set=utf8mb4 --add-drop-table "$@" "$DB_NAME" > "$BACKUP"; }

    if ! dump 2>"$DB_CONF.err"; then
        # A MariaDB client against a MySQL server (or the reverse) refuses the server's self-signed certificate.
        # On a loopback connection there is nothing to protect, so retry without the check rather than block the
        # deploy; the flag differs between the two clients.
        DB_HOST="$(grep '^host=' "$DB_CONF" | cut -d= -f2-)"
        TLS_OFF=""
        case "$DB_HOST" in
            localhost|127.0.0.1|::1)
                if mysqldump --help 2>/dev/null | grep -q -- '--ssl-mode'; then
                    TLS_OFF="--ssl-mode=DISABLED"
                else
                    TLS_OFF="--skip-ssl-verify-server-cert"
                fi
                ;;
        esac
        if [[ -z "$TLS_OFF" ]] || ! dump "$TLS_OFF"; then
            sed 's/^/    /' "$DB_CONF.err" >&2
            # Leave no half-written file behind: an empty .sql in the home directory reads like a real backup.
            rm -f "$DB_CONF.err" "$BACKUP"
            fail "The backup failed, so nothing was migrated. The database is untouched."
        fi
        warn "The database refused a verified TLS connection; the backup was taken over plain loopback."
    fi
    rm -f "$DB_CONF.err"

    # A truncated dump is worse than none: mysqldump writes this line last, only on success.
    if ! tail -5 "$BACKUP" | grep -q '^-- Dump completed'; then
        rm -f "$BACKUP"
        fail "The backup came out incomplete, so nothing was migrated. The database is untouched."
    fi
    chmod 600 "$BACKUP"
    if command -v gzip >/dev/null 2>&1; then
        gzip -f "$BACKUP"
        BACKUP="$BACKUP.gz"
    fi
    note "Backup: $BACKUP ($(du -h "$BACKUP" | cut -f1))"
    rm -f "$DB_CONF"
    trap - EXIT

    # The messenger_messages table (MESSENGER_TRANSPORT_DSN has auto_setup=0) comes with the migrations too.
    console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod --no-debug
fi

step "Bundle assets"
# The API docs' page (Nelmio) serves its JS and CSS from public/bundles/, which is gitignored.
console assets:install "$BACKEND/public" --env=prod --no-debug | sed 's/^/    /'

step "Apache rewrite rules"
# cPanel writes its own .htaccess (the PHP handler) into the document root and rewrites it whenever the PHP
# version changes, so the front-controller rules are appended once, between markers, instead of owning the file.
HTACCESS="$BACKEND/public/.htaccess"
if [[ -f "$HTACCESS" ]] && grep -q '>>> fortia front controller >>>' "$HTACCESS"; then
    CURRENT_BLOCK=$(awk '/>>> fortia front controller >>>/{on=1} on{print} /<<< fortia front controller <<</{on=0}' "$HTACCESS")
    if [[ "$CURRENT_BLOCK" == "$(cat "$APP_DIR/deploy/htaccess-symfony.conf")" ]]; then
        note "Already in $HTACCESS."
    else
        cp -p "$HTACCESS" "$HTACCESS.before-fortia-update"
        awk '/>>> fortia front controller >>>/{skip=1} !skip{print} /<<< fortia front controller <<</{skip=0}' "$HTACCESS.before-fortia-update" > "$HTACCESS"
        cat "$APP_DIR/deploy/htaccess-symfony.conf" >> "$HTACCESS"
        note "Updated the front-controller rules in $HTACCESS (the old file kept as .htaccess.before-fortia-update)"
    fi
else
    [[ -f "$HTACCESS" ]] && cp -p "$HTACCESS" "$HTACCESS.before-fortia" && note "Kept a copy as .htaccess.before-fortia"
    cat "$APP_DIR/deploy/htaccess-symfony.conf" >> "$HTACCESS"
    note "Added the front-controller rules to $HTACCESS"
fi

step "Writable directories"
mkdir -p "$BACKEND/var/log" "$BACKEND/var/share"
chmod -R u+rwX "$BACKEND/var"
# STORAGE_DIR holds the answer files, prompts and templates (flysystem objects.storage), served through signed
# /storage/… URLs. It must survive deploys and never be reachable around the app.
STORAGE_DIR="$(env_value STORAGE_DIR)"
STORAGE_DIR="${STORAGE_DIR//%kernel.project_dir%/$BACKEND}"
mkdir -p "$STORAGE_DIR"
chmod u+rwX "$STORAGE_DIR"
note "Storage: $STORAGE_DIR ($(du -sh "$STORAGE_DIR" 2>/dev/null | cut -f1))"
case "$STORAGE_DIR" in
    "$BACKEND/public"|"$BACKEND/public"/*) fail "STORAGE_DIR is inside public/: answer files would be served around the app. Move it outside." ;;
esac
# The worker appends to var/log/worker.log forever; keep one old copy once it passes 50 MB.
WORKER_LOG="$BACKEND/var/log/worker.log"
if [[ -f "$WORKER_LOG" ]] && (( $(wc -c <"$WORKER_LOG") > 52428800 )); then
    mv -f "$WORKER_LOG" "$WORKER_LOG.1"
    note "Rotated var/log/worker.log (the previous one is worker.log.1)."
fi

step "PHP limits"
# Answer files go up to 500 MB (AnswerFiles::MAX_BYTES, PRD §13.5) and file templates up to 20 MB; PHP refuses a bigger
# body before the app sees it. cPanel's default is often 2M. The web PHP's settings are the ones that count
# (cPanel › MultiPHP INI Editor); the command-line one is checked when the web one is not known.
check_php_limits() {
    local binary=$1 label=$2
    "$binary" -r '
        $bytes = static function (string $v): int { $n = (int) $v; return match (strtolower(substr(trim($v), -1))) { "g" => $n << 30, "m" => $n << 20, "k" => $n << 10, default => $n }; };
        $up = ini_get("upload_max_filesize"); $post = ini_get("post_max_size"); $mem = ini_get("memory_limit");
        $limits = "upload_max_filesize $up, post_max_size $post";
        $bad = [];
        if ($bytes($up) < 20 << 20 || $bytes($post) < 21 << 20) { $bad[] = "$limits: even the 20 MB file templates fail. Set 500M / 520M."; }
        elseif ($bytes($up) < 500 << 20 || $bytes($post) < 520 << 20) { $bad[] = "$limits: bigger answer files fail (they may be up to 500 MB). Set 500M / 520M."; }
        if ("-1" !== $mem && $bytes($mem) < 256 << 20) { $bad[] = "memory_limit $mem: set at least 256M (512M in the dev stack)."; }
        foreach ($bad as $line) { fwrite(STDERR, "    $line\n"); }
        exit($bad ? 1 : 0);
    ' && note "$label: upload, post and memory limits are fine." && return 0
    warn "$label: see above (cPanel › MultiPHP INI Editor)."
}
if [[ -n "${WEB_PHP_BIN:-}" && -x "${WEB_PHP_BIN:-}" ]]; then
    check_php_limits "$WEB_PHP_BIN" "$WEB_PHP (the one Apache uses)"
else
    check_php_limits "$PHP_BIN" "The command-line PHP"
fi

if [[ -n "${PROMOTE_ADMIN:-}" ]]; then
    step "Platform Admin"
    # Admin is a group of the user (app_user.user_groups, PRD §4.2): it sees every account and can assume one. There is
    # no sign-up for it, and the demo accounts are never seeded in production, so a signed-up user is promoted.
    [[ "$PROMOTE_ADMIN" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+$ ]] || fail "PROMOTE_ADMIN is not an email: $PROMOTE_ADMIN"
    FOUND="$(console dbal:run-sql "SELECT email FROM app_user WHERE email = '$PROMOTE_ADMIN'" --env=prod --no-debug 2>/dev/null | grep -ciF "$PROMOTE_ADMIN" || true)"
    [[ "$FOUND" != "0" ]] || fail "No user $PROMOTE_ADMIN: sign up at $(env_value ADMIN_FRONTEND_URL) first."
    console dbal:run-sql "UPDATE app_user SET user_groups = JSON_ARRAY_APPEND(user_groups, '\$', 'Admin') WHERE email = '$PROMOTE_ADMIN' AND NOT JSON_CONTAINS(user_groups, '\"Admin\"')" \
        --env=prod --no-debug >/dev/null
    console dbal:run-sql "SELECT email, user_groups FROM app_user WHERE email = '$PROMOTE_ADMIN'" --env=prod --no-debug | sed 's/^/    /'
    note "$PROMOTE_ADMIN is a platform Admin (sign out and in again if the console was open)."
fi

step "Starting the worker"
rm -f "$PAUSE_FILE"
note "The next cron run (within a minute) starts it on the new code."
# shellcheck source=deploy/cron-lines.sh
source "$APP_DIR/deploy/cron-lines.sh"
{ crontab -l 2>/dev/null || true; } | check_cron_lines

step "Health check"
APP_URL="$(env_value APP_URL)"
if command -v curl >/dev/null 2>&1; then
    # The first request after a cache rebuild is the slowest; the warmup above has done most of it.
    STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 60 "$APP_URL/api/v1/health" || true)"
    if [[ "$STATUS" == "200" ]]; then
        note "$APP_URL/api/v1/health answers 200."
        CONSOLE_STATUS="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$(env_value ADMIN_FRONTEND_URL)" || true)"
        [[ "$CONSOLE_STATUS" == "200" ]] && note "The console answers 200." \
            || warn "The console ($(env_value ADMIN_FRONTEND_URL)) answers $CONSOLE_STATUS."
    else
        warn "$APP_URL/api/v1/health answers ${STATUS:-nothing}. Apache's error log is in cPanel › Errors (the app logs to it: php://stderr)."
        warn "A 404 on every path but / means the rewrite rules are not read (AllowOverride); a 500 is PHP: check the web PHP above."
    fi
else
    note "No curl here: open $APP_URL/api/v1/health in a browser; it should answer {\"status\":\"ok\"…}."
fi

step "Done"
note "Now on $(git rev-parse --short HEAD) ($(git --no-pager log -1 --format=%s))"
# Only worth offering when this run actually moved the code.
if [[ "$BEFORE" != "$(git rev-parse HEAD)" ]]; then
    note "Roll back with:  git reset --hard ${BEFORE:0:7} && SKIP_PULL=1 $0"
    note "(the code only: a migration that ran stays; the dump above is what restores the data.)"
fi
note "If a change does not show up, restart PHP from cPanel (OPcache may still hold the old files)."
