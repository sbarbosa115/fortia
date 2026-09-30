# Mappi

Questionnaires that end in action: a multi-tenant SaaS to create questionnaires (often with AI), send them to
respondents and turn their answers into results — product recommendations, scored diagnostics, follow-ups with
reviews, dashboards. The requirements are [`prd.md`](prd.md); the build plan is
[`docs/pdr/prd-mappi.md`](docs/pdr/prd-mappi.md); conventions for contributors (and Claude) are in
[`CLAUDE.md`](CLAUDE.md).

One Symfony project (`backend/`) serves the JSON API (`/api/v1`), the operator console (`/console`) and the
respondent app (the public links `/q/:id`, `/f/:id`, `/a/:id`, `/session/:id/results`). Both React apps are built by
Webpack Encore from `backend/assets`.

## Run it

Requirements: Docker (Compose v2) and Git. Nothing runs on the host.

```bash
scripts/stack.sh up        # first time: build, install, JWT keys, databases, migrations, demo data
scripts/stack.sh status    # URLs
scripts/stack.sh reset     # drop the dev database and reseed it
```

- Console: http://localhost:8080/console — sign in with `owner@acme.test` / `password123` (dev only; the other demo
  accounts are in [`CLAUDE.md`](CLAUDE.md)).
- Respondent app: http://localhost:8080/ (the root redirects to the marketing site).
- Mail catcher (Mailpit): http://localhost:8025
- OpenAPI: http://localhost:8080/api/doc.json

A second checkout (a git worktree) gets its own stack: put `HTTP_PORT`, `DB_PORT` and `MAILPIT_PORT` in its
gitignored `.env`.

### External providers

Every provider is a port with a real adapter and a deterministic fake; dev and tests run offline on the fakes.
Switch in `backend/.env.local`:

| Capability | Variable | Values |
|---|---|---|
| Language model | `LLM_PROVIDER`, `ANTHROPIC_API_KEY` | `fake` (default) · `anthropic` (Claude: `claude-opus-5-5` for generation, `claude-haiku-4-5` for fast calls) |
| Payment gateway | `PAYMENT_PROVIDER`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | `fake` (a local hosted-checkout page) · `stripe` |
| E-commerce | `COMMERCE_PROVIDER`, `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET` | `fake` · `shopify` |
| Catalog scraper | `SCRAPER_PROVIDER` | `fake` · `http` (schema.org Product data) |
| Transcription | `TRANSCRIPTION_PROVIDER`, `OPENAI_API_KEY` | `browser` (Web Speech API) · `openai` |
| Google sign-in / Sheets | `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_SHEETS_CLIENT_ID` | empty = disabled |
| Email | `MAILER_DSN` | Mailpit in dev |

## Checks

```bash
.claude/skills/symfony-react-app/scripts/gate.sh --fix    # PHP-CS-Fixer, PHPStan, Deptrac, Prettier, ESLint, tsc
docker compose exec php php bin/phpunit                   # PHP unit + functional tests
docker compose exec node npm test                         # Vitest
```

## API reference

The contract is PRD §8; OpenAPI is generated from the controllers (`backend/assets/types/openapi.json`). Each row
below is added by the item that builds the endpoint.

| Path | Methods | Access | Notes / errors |
|---|---|---|---|
| `/api/v1/auth/token` | POST | public | email + password → `{id_token, refresh_token, expires_in}`. 401 `INVALID_CREDENTIALS`, 429 `TOO_MANY_ATTEMPTS` (10 per 15 min per email+IP). Replaces the identity provider's sign-in |
| `/api/v1/auth/refresh` | POST | public | `{refresh_token}` → a new pair (refresh tokens rotate, 30 days). 401 `UNAUTHORIZED` |
| `/api/v1/customer/onboarding` | GET, PATCH | signed in | `{onboarding_completed}`; PATCH `{completed}` (no extra fields). 404 `CUSTOMER_NOT_FOUND` |
| `/api/v1/customer/usage` | GET | signed in | plan, period usage, one verdict per feature |
| `/api/v1/jobs/{job_id}` | GET | public | `{job}` without its payload. 404 `JOB_NOT_FOUND` |
| `/api/v1/health` | GET | public | `data` = checks; 200 ok / 500 error |
| `/storage/upload`, `/storage/put`, `/storage/download` | POST, PUT, GET | signed URL | the local object storage's signed URLs (15 min) |

Every endpoint answers `{message, data}` or `{error: {code, message, details?}}` (PRD §8.1); `X-Assume-Customer-Id`
lets a platform Admin act as an account's root user (logged in `impersonation_log`).

## Data model decisions

- **One schema, document columns.** Questionnaires, sessions and flows keep their questions/states as JSON
  documents (the PRD's shapes, `Shared\Domain\Document`), with the listing fields as columns and indexes on the
  PRD's access patterns. MySQL's `utf8mb4_0900_ai_ci` collation gives case- and accent-insensitive search.
- **Identity in the app.** The identity provider is replaced by our own users table (bcrypt/argon hashes), JWT id
  tokens (lexik, 24 h) and rotating refresh tokens (30 days). Emails stay globally unique.
- **The usage/analytics service is absorbed** (PRD §13.8 allows it): domain events go to `domain_event_log`; an event
  with a feature counts in `usage_counter` per plan period.
- **Account plan in its own table** (`customer_plan`, owned by Billing) instead of embedded in the account row.
- **Respondent tokens** are HS256 JWTs prefixed `rt.` that expire (D6); an invalid one is a 401 (D7).
- **Signed local storage** keeps the PRD's object keys and the `{url, fields, key, expires_in}` upload contract.
- **Dev stack speed:** `vendor/` and `var/` in volumes and Symfony without debug freshness checks (a reloader clears
  the cache on change): on Docker Desktop, reading them through the bind mount cost ~20 s per request.

## Known gaps

- Everything the split's items have not built yet is a placeholder page ("This screen is on its way.").
- No S3 adapter: object storage is local with signed URLs (the port allows adding one).
- Error tracking (Sentry) and heatmaps (Clarity) are configuration only; nothing loads them yet.
- The Claude adapter is written against the official SDK but has not been run against the live API in this repo
  (no key in dev); refusals and output-token exhaustion fail the job with a clear error.
