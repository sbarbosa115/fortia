---
name: run-fortia-mappi
description: Run, start, drive and screenshot the Mappi app (Symfony API + React console and respondent UIs in Docker). Use when asked to launch the stack, log into the console as a demo account, click through a page, take a screenshot, check a UI change in the real app, smoke the API with curl, or run the PHP/Vitest tests.
---

# Run Mappi

Mappi runs as a Docker Compose stack (`php`, `worker`, `reloader`, `nginx` on :8080, `database`, `node` running
Encore watch, `mailpit` on :8025). The agent drives the UI with **`drive.sh`**: it runs `driver.mjs` (Playwright 1.55
+ headless Chromium) in the compose `e2e` image, so nothing is installed on the host. It drives the API with `curl`.
Paths below are relative to the repo root. The commands run in the **Bash tool** (Git Bash on Windows).

## Prerequisites

Docker Desktop running. That's all: no host Node/Playwright/Chromium needed.

## Start the stack

```bash
scripts/stack.sh up        # build, composer install, JWT keys, dev+test DBs, migrations, demo data, waits for the UI build
scripts/stack.sh status    # services + URLs
```

Demo accounts (password `password123`): `owner@acme.test` (root, Pro), `admin@acme.test`, `reader@acme.test`
(read-only), `owner@globex.test`, `owner@newco.test` (onboarding pending), `admin@mappi.test` (platform admin).

## Run (agent path): drive the UI

```bash
.claude/skills/run-fortia-mappi/drive.sh login owner@acme.test ss home
.claude/skills/run-fortia-mappi/drive.sh login owner@acme.test goto /console/questionnaires \
  fill "Search questionnaires" "safety audit" wait 1500 ss q-search click "Safety audit" wait 2000 ss q-open
```

The arguments are steps run in order in one browser session:

| step | does |
|---|---|
| `login <email> [password]` | signs in on `/console/login` (default password `password123`), waits for the redirect |
| `goto <path>` | opens `/console/...`, `/q/<questionnaireId>`, `/a/<assignationId>`… and waits for network idle |
| `click <text>` | clicks the button / link / tab with that accessible name (exact), or else any element with that exact text |
| `fill <label> <value>` | types into the field labelled `<label>` (a `Field` label or an `aria-label`) |
| `press <key>` | `Enter`, `Escape`… |
| `wait <ms or text>` | sleeps, or waits for the text to appear |
| `ss <name>` | full-page screenshot → **`backend/var/run-shots/<name>.png`** (gitignored). Open it with Read and look at it |
| `text` | prints the page's visible text (first 3000 chars) |
| `eval <js>` | evaluates an expression in the page and prints the JSON result |

It prints `[http 4xx/5xx]`, `[console.error]` and `[pageerror]` lines as they happen. When a step fails, it exits 1,
prints `! <error>` and saves `backend/var/run-shots/_failure.png`. Find labels when you don't know them:

```bash
.claude/skills/run-fortia-mappi/drive.sh login owner@acme.test goto /console/questionnaires \
  eval "[...document.querySelectorAll('label, input[aria-label], button')].map(e => e.textContent.trim() || e.getAttribute('aria-label')).filter(Boolean)"
```

A respondent run (no login), for a questionnaire that isn't assigned to anyone:

```bash
.claude/skills/run-fortia-mappi/drive.sh goto /q/<questionnaireId> click "Comenzar cuestionario" wait 1000 ss respondent-q1 text
```

The first run takes ~60 s: it pulls the Playwright image and installs `playwright@1.55.0` into the `mappi_playwright`
volume. After that a run takes ~4 s.

## Run (agent path): the API

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/token -H 'Content-Type: application/json' \
  -d '{"email":"owner@acme.test","password":"password123"}' | sed -E 's/.*"id_token":"([^"]+)".*/\1/')
curl -s "http://localhost:8080/api/v1/questionnaire?search=safety" -H "Authorization: Bearer $TOKEN"
```

Find questionnaires that anonymous respondents can open. A 200 means it's open. Each probe **creates a session**:

```bash
for q in $(curl -s "http://localhost:8080/api/v1/questionnaire?per_page=50" -H "Authorization: Bearer $TOKEN" \
  | grep -oE '"questionnaire_id":"[^"]+"' | cut -d'"' -f4); do
  echo "$(curl -s -o /dev/null -w '%{http_code}' -X POST http://localhost:8080/api/v1/questionnaire/$q/session) $q"; done
```

## Run (human path)

`scripts/stack.sh up`, then open http://localhost:8080/console in a browser. Mail goes to http://localhost:8025.

## Test

```bash
docker compose exec -T node npx vitest run assets/console/pages/login   # one slice (~12 s); `npm test` for all
docker compose exec -T php php bin/phpunit --filter AuthToken           # ~45 s the first time after a PHP change
.claude/skills/symfony-react-app/scripts/gate.sh --fix                   # cs, phpstan, deptrac, prettier, eslint, tsc
```

## Gotchas

- **The pages contain absolute `http://localhost:8080` URLs** (`APP_URL` is baked into the HTML: the API base,
  `/console`). Inside the e2e container localhost:8080 is nothing, so a plain `goto http://nginx/…` loads the shell
  and then every API call fails with `ERR_CONNECTION_REFUSED`. The driver keeps `localhost:8080` as the browser's
  origin and proxies every request to `http://nginx` with `route.fetch` + `route.fulfill`.
  `route.continue({url})` to another host fails with `net::ERR_BLOCKED_BY_CLIENT`.
- **Git Bash rewrites `/console/...` arguments** into `C:/Program Files/Git/console/...`. `drive.sh` sets
  `MSYS_NO_PATHCONV=1`. Keep that if you call `docker compose run` yourself.
- **The Symfony debug toolbar shows even with `APP_DEBUG=0`.** It covers the console's bottom bar (language and
  user menu). The driver hides `.sf-toolbar` in every page.
- **`/q/:id` takes the questionnaire UUID, not the slug.** `/f/:id` is the quiz-funnel flow, so
  `/f/acme-safety-audit` says "This flow could not be found." **An assigned questionnaire is a 404 for an anonymous
  respondent** ("This questionnaire does not exist."). In acme's demo data, Safety audit, Supplier compliance and the
  other store questionnaires are assigned. The Team pulse ones are open. Assigned ones are opened through
  `/a/<assignationId>`, and the link comes from Mailpit (run `bin/console app:assignations:send-reminders`).
- **The respondent UI follows the questionnaire's language, not the browser's.** The buttons are Spanish
  (`Comenzar cuestionario`, `Finalizar`) while the questions are English. `click` needs the exact text, so run `text`
  first.
- **Sign-in is rate-limited to 10 per email and IP every 15 minutes, in dev too.** Every `drive.sh login` and every
  `curl …/auth/token` counts. Chain the steps of a flow in one `drive.sh` call, and reuse `$TOKEN`. To reset it:
  `docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter`.
- The API wraps answers in `{"message","data"}`. The token is `data.id_token`. The list endpoint is `/questionnaire`
  (singular): `/questionnaires` is a 404 NOT_FOUND.
- **The first request after a PHP change takes ~30 s** (the reloader rebuilds the cache). The step timeout is 60 s.
  Raise it with `STEP_TIMEOUT_MS=120000` before `drive.sh` if a step times out right after you edit `src/`.
- Claude-in-Chrome works too, but when Chrome is in the background its tab is hidden: screenshots time out and
  timers freeze. `drive.sh` doesn't depend on the user's browser.

## Troubleshooting

| symptom | fix |
|---|---|
| `[console.error] Failed to load resource: net::ERR_CONNECTION_REFUSED`, then `waitForURL` times out on login | `BASE_URL` was set to `http://nginx`. Leave it unset (default `http://localhost:8080`) |
| `[http 429] POST …/auth/token` then `waitForURL` times out, or `$TOKEN` is `{"error":{"code":"TOO_MANY_ATTEMPTS"…` | `docker compose exec -T php php bin/console cache:pool:clear cache.rate_limiter` |
| `net::ERR_BLOCKED_BY_CLIENT` on every resource | the proxy uses `route.continue({url})`. Use `route.fetch` + `fulfill` (as `driver.mjs` does) |
| `locator.click: Timeout` on a respondent button | wrong language or text: run `text` and copy the label exactly |
| `[http 404] POST /api/v1/questionnaire/<id>/session` | the questionnaire is assigned or inactive: use an open one (see the API loop) or `/a/<assignationId>` |
| `[http 400] … INVALID_UUID` | you passed a slug where an id is expected |
