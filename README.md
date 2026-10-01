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
| `/api/v1/questionnaire` | GET | signed in | listing `{items, page, page_size, total, total_pages}`: `type`, `sort_by`, `order`, `is_active`, `parent` (ROOT), `page`, `page_size` (≤ 100, default 20), `search` (every word in the title), paginated in SQL (D16, D17). 400 `INVALID_TYPE`/`INVALID_SORT`/`INVALID_ORDER`/`INVALID_IS_ACTIVE`. Admin sees every account |
| `/api/v1/questionnaire` | POST, PUT | AG | a flow `{slug?, states[], cta?, layout?, result_copy?, detail?}` (+ `questionnaire_id` on PUT); the `questionnaire` state carries the questionnaire in `parameters.questionnaire`, a `prompt` state `parameters.key` (`prompts/{customer_id}/…`) or `parameters.text`, a diagnostic its scoring in `on_completed`. 201 `{questionnaire_id}` / 200 `data:null`. 400 `VALIDATION_ERROR` (§7.5 rules), 409 `SLUG_ALREADY_IN_USE`, 409 `QUESTIONNAIRE_ALREADY_ANSWERED`, 429 plan (by type) |
| `/api/v1/questionnaire/{id}` | GET, PATCH | signed in; PATCH AG | GET: the questionnaire with the diagnostic merged into `on_completed`. PATCH: exactly `{is_active}` → the listing row. Another account's id: 404 `QUESTIONNAIRE_NOT_FOUND` |
| `/api/v1/questionnaire/{id}/copy` | POST | AG, Cap(type) | 201 the copy: "(copia) X" / "(copy) X" by the account's language (D23), slug `<base>-copia[-N]`, diagnostic and prompts copied |
| `/api/v1/questionnaire/{id}/prompts` | GET | AG | `{prompts:[{id, questionnaire_id, customer_id, s3_path, outcome, order, text}]}` |
| `/api/v1/questionnaire/{id}/answers` | GET | signed in, Own | `{items, next_cursor, total, questionnaire:{questionnaire_id, title, type, is_chain, public_id}, generated_stages[]}`: sessions newest first, each the full session (`questions` with their values) plus `member` (the organization member) and, with `include_chain=true`, `chain:{stage, total_stages}`. `status` = `completed` (default) \| `filling` \| `filled_out` \| `processing` \| `all` (legacy `in_progress`, `submitted`), `limit` 20/50/100 (default 100), `cursor` (opaque base64), extension `assignations_id` (one assignation's sessions, for its Sheets export). Paginated in SQL. 400 `INVALID_REQUEST` (status), `INVALID_PAGE_SIZE`, `INVALID_CURSOR`, `INVALID_UUID`; 404 `QUESTIONNAIRE_NOT_FOUND` (also another account's) |
| `/api/v1/questionnaire/{id}/answers/{session_id}` | GET | signed in, Own | extension for the answer detail's fallback (§10.8): `{session (as in the list), results}` of a session of the questionnaire or of one of its generated stages. 404 `SESSION_NOT_FOUND` |
| `/api/v1/questionnaire/{id}/analytics` | GET | signed in, Own, Cap(`analytics`) | `{questionnaire_id, questions_analytics:[{question_id, answers_count, values, numeric}], total_sessions, sessions_completed}`. `AnalyticsFetched` counts `analytics` |
| `/api/v1/questionnaire/{id}/dashboard` | GET | signed in, Own, Cap(`analytics`) | `{questionnaire_id, customer_id, title, type, charts[{id, chart_type, title, question_ids, order}], created_at, questions[{id, title, type, options[{label, value}], min, max}], locked}`. The first request has the LLM choose it (§7.10 cleanup; stored once, `DashboardGenerated` counts `dashboards`); without a stored one and no `dashboards` capacity: 200 `locked:{feature:"dashboards", reason}`, no charts. 502 `DASHBOARD_GENERATION_FAILED` (nothing stored) |
| `/api/v1/questionnaire/{id}/dashboard/data` | GET | signed in, Own, Cap(`analytics`) | §10.9 `{sessions:{total, completed, completion_rate, timeline, by_source, duration_seconds}, questions[], tiers[]}` computed from the stored sessions. `AnalyticsFetched` counts `analytics`. 502 `ANALYTICS_UNAVAILABLE` |
| `/api/v1/questionnaire/find?url=` | GET | public | bare `{questionnaire_url}` = `{FRONTEND_URL}/f/{flowId}` of the latest flow of that store page, else of its site. 400 `INVALID_REQUEST`, 404 `FLOW_NOT_FOUND`; rate limited |
| `/api/v1/flow/{identifier}` | GET | public | by flow id, slug or questionnaire id; 404 `FLOW_NOT_FOUND` (also by slug/flow id when the questionnaire is assigned); rate limited |
| `/api/v1/jobs/{job_id}` | GET | public | `{job}` without its payload. 404 `JOB_NOT_FOUND` |
| `/api/v1/health` | GET | public | `data` = checks; 200 ok / 500 error |
| `/storage/upload`, `/storage/put`, `/storage/download` | POST, PUT, GET | signed URL | the local object storage's signed URLs (15 min) |
| `/api/v1/questionnaire/{questionnaire_id}/session` | POST | public (optional respondent Bearer), rate limited | **bare** session (questionnaire copy, `on_completed` reduced to `{type}`). Cap(`responses`) only on root questionnaires without an assignation token. 400 `INVALID_UUID`, 401 `UNAUTHORIZED` (invalid token), 404 `QUESTIONNAIRE_NOT_FOUND` (unknown, inactive, or assigned to an organization), 429 `PLAN_LIMIT_REACHED` / `TOO_MANY_ATTEMPTS` |
| `/api/v1/questionnaire/session` | PUT | public; an assignation's session needs its respondent Bearer | saves the values only (the server keeps its copy); follow-up sessions merge (§7.11). 400 `VALIDATION_ERROR`, 401 `UNAUTHORIZED`, 404 `SESSION_NOT_FOUND`, 409 `FOLLOW_UP_COMPLETED`, 429 `TOO_MANY_ATTEMPTS` |
| `/api/v1/questionnaire/session` | POST | public; an assignation's session needs its respondent Bearer | submits and runs §7.7: `{type, …result, cta?, layout?, result_copy?}`; quiz funnel / e-commerce → 202 `{job}` (`process_completed_session`). Idempotent (a second submit answers the same, counts nothing). Same errors as PUT |
| `/api/v1/questionnaire/session/{session_id}/results` | GET | public, rate limited | `{session_id, customer_id, questionnaire_id, cta, layout, result_copy, products, ai_team_profile, diagnostic, extra}` (`extra` = samurai8/livingood report, D8). 400 `INVALID_UUID`, 404 `SESSION_RESULTS_NOT_FOUND` |
| `/api/v1/questionnaire/session/{session_id}/chain` | GET | signed in, Own | `{stages[], total_stages}`. 404 `SESSION_NOT_FOUND` (also another account's session) |
| `/api/v1/questionnaire/session/{session_id}/answers/{question_id}/evaluate` | POST | public, rate limited | the question object → 202 `{job}` (`answer_evaluation`, result `{type:"evaluation", status:"success"\|"not_sense", question}`). 404 `SESSION_NOT_FOUND`, `QUESTION_NOT_FOUND`; the job FAILS when the model fails (the client fails open) |
| `/api/v1/signed-urls` | POST | public for `answer_media` (only a session being filled, of that `customer_id`, with that question: D4); signed in and own account for `prompt` | `{url, fields, key, expires_in:900}` (form upload, 1 B–500 MB) or `{url, key, expires_in}` (PUT). 400 `VALIDATION_ERROR`, 401, 403 `FORBIDDEN`, 404 `SESSION_NOT_FOUND` / `QUESTION_NOT_FOUND` / `CUSTOMER_NOT_FOUND`, 429 |
| `/api/v1/answers-media/download-urls` | POST | signed in | `{key, disposition}` → `{url, expires_in:900}`. 400 `VALIDATION_ERROR` (key = 4 segments, no `..`), 403 `FORBIDDEN` (another account's key; Admin passes) |
| `/api/v1/transcription/token` | GET | public, rate limited | `{token, provider, expires_in}`: `provider` `browser` = transcribe with the Web Speech API (default), `openai` = Realtime client secret (`TRANSCRIPTION_PROVIDER=openai`). 502 when the provider fails |
| `/api/v1/organizations` | GET | signed in | **bare** `{organizations:[{…org, organization_users:[…]}]}`, newest first, members in one extra query (no N+1). Admin sees every account |
| `/api/v1/organizations` | POST | AG, Cap(`organizations`) | `{name, domain_email?, description?, active?, organization_users[]}` (no extra fields, also in members). 201 the organization with its members. Names/emails/phones normalized (§6.13). 400 `VALIDATION_ERROR` (each member with an email or phone, unique emails in the list), 403 `FORBIDDEN`, 409 `DOMAIN_EMAIL_CONFLICT` (any account), 429 plan. `OrganizationCreated` counts `organizations` |
| `/api/v1/organizations/{id}` | PUT | write permission, owner or Admin (D1) | partial, at least one field; `organization_users` is reconciled (by id, then email, then name + phone; the rest deleted). 200 the organization. 400 `VALIDATION_ERROR`/`INVALID_UUID`, 403 `FORBIDDEN` (read-only), 404 `ORGANIZATION_NOT_FOUND` (also another account's), 409 `DOMAIN_EMAIL_CONFLICT` |
| `/api/v1/organizations/{id}` | DELETE | write permission, owner or Admin (D1) | 204; deletes its members too (D2). 404 `ORGANIZATION_NOT_FOUND`, 409 `ORGANIZATION_HAS_ASSIGNATIONS` while assignations or projects point at it. `OrganizationDeleted` counts `organizations` |
| `/api/v1/assignations` | GET | signed in | `{assignations:[enriched], pagination}`, newest first, `page_size` default 20 (≤ 100), `type` = default \| follow_up, `questionnaire_id` (extension: the form's one-organization check). Enriched = the §6.14 fields + `organization_name`, `questionnaire_name`, `questionnaire_url`, `audience_size`, `attempt`, `progress {completed, total, unit, current_question}`, `completed`, `review_status`, `attempts[]`. Admin sees every account |
| `/api/v1/assignations` | POST | AG, Cap(`assignations`) | `{organization_id, questionnaire_id, name, description?, max_follow_ups, active?, type, due_date? (follow-up only), audience?, questions (≥ 1)}`, no extra fields. 201 `{questionnaire_url, assignation_id}`. 400 `VALIDATION_ERROR`, `AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`; 404 `ORGANIZATION_NOT_FOUND`, `QUESTIONNAIRE_NOT_FOUND`; 409 `QUESTIONNAIRE_ALREADY_ASSIGNED` (details `organization_id`, `organization_name`) |
| `/api/v1/assignations/{id}` | GET | public | the enriched assignation; the console owner (or Admin) also gets each follow-up attempt's `answers[]` with `review_state`; anonymous callers never get the description nor the answers and need Feat(`assignations`) on the owner's plan (429). Another account's console user: 404 |
| `/api/v1/assignations/{id}` | PUT, DELETE | write permission, owner or Admin | PUT partial; `type` refused; `due_date: null` clears; organization change: 400 `ASSIGNATION_IN_PROJECT` in a project, a `members` audience not re-sent is reset to everybody. DELETE 204 (keeps the sessions). Both count `assignations` usage on create/delete |
| `/api/v1/assignations/{id}/sessions` | POST | public, rate limited | respondent login in the §7.11 order (404 unknown or inactive, Feat 429, 409 `FOLLOW_UP_COMPLETED`, 400 `MISSING_IDENTIFIER`, 403 `USER_NOT_FOUND` / `NOT_IN_AUDIENCE`, Cap(`responses`) 429). **Bare** `{token, questionnaire: session, flow}`. Lookup by email and/or phone only (phones compared as digits) |
| `/api/v1/assignations/{id}/respondents` | GET | signed in (non-owner: empty page) | `{respondents:[{organization_user_id, organization_user_name, organization_user_email, status, session_id, completed_stages, total_stages, attempts, attempts_detail[]}], next_cursor}`, the audience by name; `page_size`\|`limit` default 10 (1–100, else 400 `INVALID_PAGE_SIZE`), `cursor` (400 `INVALID_CURSOR`) |
| `/api/v1/assignations/{id}/reminders` | POST | write permission, owner or Admin | `{recipients}`. 400 `NOT_A_FOLLOW_UP`, 409 `FOLLOW_UP_COMPLETED`, 422 `NO_RECIPIENTS`, 502 `REMINDER_NOT_SENT`. Daily at 13:00 UTC (`#[AsCronTask]`, or `bin/console app:assignations:send-reminders`) |
| `/api/v1/assignations/{id}/reviews/{question_id}` | PUT | write permission, owner or Admin | `{status: approved\|rejected, comment?}` → `{question_id, review, review_status}`. 400 `NOT_A_FOLLOW_UP`, `QUESTION_LOCKED`; 404 `QUESTION_NOT_FOUND` (also message slides); 409 `FOLLOW_UP_NOT_COMPLETED` |
| `/api/v1/assignations/{id}/retries` | POST | write permission, owner or Admin (D3) | 201 `{attempt, session_id, recipients}`. 409 `FOLLOW_UP_NOT_COMPLETED`, `REVIEW_INCOMPLETE`; 400 `NOT_A_FOLLOW_UP`, `NOTHING_TO_RETRY`; 502 `RETRY_EMAIL_NOT_SENT` (the attempt already exists) |
| `/api/v1/projects` | GET | signed in | `{projects:[enriched], pagination:{page, page_size, total_items, total_pages, has_next, has_previous}}`, newest first; `page_size` default 10, ≤ 100; `status` ∈ review, progress (includes pending), correction, overdue, approved; `q` = every word in the project's or its organization's name. Paginated in SQL without `status` (states come from the sessions, so with `status` every match is evaluated). 400 `INVALID_PROJECT_STATUS`. Admin sees every account |
| `/api/v1/projects` | POST | write permission, Feat(`assignations`) | `{organization_id, name (1–200), description? (≤ 2000), due_date, assignation_ids[]?}` (deduplicated, no extra fields). 201 the enriched project. 400 `VALIDATION_ERROR`, `ASSIGNATION_NOT_FOLLOW_UP`, `ASSIGNATION_ORGANIZATION_MISMATCH`; 403 `FORBIDDEN` (read-only); 404 `ORGANIZATION_NOT_FOUND`, `ASSIGNATION_NOT_FOUND` (also another account's); 409 `ASSIGNATION_IN_OTHER_PROJECT`; 429 plan |
| `/api/v1/projects/{id}` | GET | signed in, owner or Admin | the enriched project (§7.12: `state`, `progress_percent`, `completed/approved/total_assignations`, `assignations[]` with `state`, `progress {completed, total, unit, current_question}`, `review {reviewed, total, approved, rejected}`, `review_status`, `attempt`, `due_date`, `overdue`) plus `available_assignations` (the organization's follow-ups not in another project, for the edit dialog). 404 `PROJECT_NOT_FOUND` (also another account's) |
| `/api/v1/projects/{id}` | PUT, DELETE | write permission, owner or Admin | PUT partial: `name`, `due_date`, `assignation_ids` not null, `assignation_ids` replaces the set (left-out ones are unlinked), `organization_id` only unchanged (400 `VALIDATION_ERROR`); 200 the enriched project. DELETE 204, unlinks its assignations. 404 `PROJECT_NOT_FOUND`, 403 `FORBIDDEN` |
| `/api/v1/customer/{customer_id}/settings` | GET | public, rate limited | the account's CustomerSettings `{language, transcription_url, pixel_id, linkedin_partner_id, linkedin_conversion_id, google_ads_id, google_ads_conversion_label, max_files}` (max_files 10 by default) for the respondent app. 404 `CUSTOMER_NOT_FOUND` |
| `/api/v1/customer/{customer_id}/settings` | PATCH | AG; a non-Admin only on their own account; Cap(`profile`) unless only `language` changes | at least one field, no extra fields; `language` never null; tracking ids ≤ 64 (empty or null clears them); `max_files` strict integer 1–20 (null resets to 10). 200 the settings. 400 `VALIDATION_ERROR`, 403 `FORBIDDEN`, 404 `CUSTOMER_NOT_FOUND` (also another account's), 429 plan. `ProfileEdited` counts `profile` (not for a language-only change) |
| `/api/v1/register` | POST | public | `{email, password (≥ 8), name (1–50), language? (es-CO), source? (default)}` → 201 `{customer_id, user:{email, name, root:true, role:"Customer-Admin"}}`, no tokens (sign in next). The account gets the `starter` plan for a month and onboarding pending; the welcome email goes out in the account language with a BCC to support (D20). 400 `VALIDATION_ERROR`, 409 `EMAIL_ALREADY_EXISTS` (any account) |
| `/api/v1/password-recovery` | POST | public | `{email}` → always 200 "If the account exists, a recovery code is on its way." (a 6-digit code valid 60 min, emailed with a link to `/console/reset-password?code=`). 429 `TOO_MANY_ATTEMPTS` (5 per 15 min per email+IP) |
| `/api/v1/password-recovery/confirm` | POST | public | `{email, code (1–64), password}` → 200. 400 `INVALID_RESET_CODE` (also unknown user, used code), `EXPIRED_RESET_CODE`, `INVALID_PASSWORD` (< 8); 429 `TOO_MANY_ATTEMPTS` (rate limit, or 5 wrong codes burn the code) |
| `/api/v1/auth/google/authorize` | GET | public | `?state&code_challenge` (S256) → `{authorization_url}` of Google's consent screen, returning to `/console/sign-in`. 503 `PROVIDER_NOT_CONFIGURED` without `GOOGLE_OAUTH_CLIENT_ID`/`_SECRET` |
| `/api/v1/auth/google/token` | POST | public | `{code, code_verifier}` → the same tokens as `/auth/token`. A first Google login creates the account (root, starter, language from Google or es-CO); an existing password account is linked and answers 409 `EMAIL_LINKED_RETRY_LOGIN` (the console retries once). 401 `GOOGLE_SIGN_IN_FAILED`, 503 `PROVIDER_NOT_CONFIGURED` |
| `/api/v1/users` | GET | signed in | `{users:[{email, name, root, role, customer_id}]}` of the caller's account, root first, then by name |
| `/api/v1/users` | POST | AG, Cap(`users`) | `{email, password (≥ 8, permanent), name (1–50), role ∈ Customer-Admin \| Customer-Read-Only}` (no extra fields) → 201 `{email, name, root:false, role, customer_id}`. 400 `INVALID_ROLE`/`VALIDATION_ERROR`, 403 `FORBIDDEN`, 409 `EMAIL_ALREADY_EXISTS`, 429 plan. `UserCreated` counts `users` |
| `/api/v1/profile` | GET | signed in | `{customer:{customer_id, name, email, language, logo_url, website, styles}}`: the signed-in user's name/email, the account language, the brand's logo/styles and website (or the onboarding website) |
| `/api/v1/customer/workspace` | PATCH | write permission | D15, onboarding step 2: `{name? (≤ 120), language?, website? (URL)}`, at least one, no extra fields, no plan gate → `{name, language, website}`. 400 `VALIDATION_ERROR`, 403 `FORBIDDEN` |
| `/api/v1/styles` | GET | public, rate limited | `?customer_id=` (and/or `questionnaire_id=`, ignored) → `{styles \| null}` (camelCase §6.18) that the respondent app maps onto its theme (§9.15). 400 `INVALID_REQUEST` without either. Read only here: the branding item adds `POST /styles` |
| `/api/v1/api-keys` | GET, POST | signed in; POST write permission + Feat(`api`) | GET: the active keys, newest first, `[{id, name, created_at, expires_at, last_used_at}]` (never the key). POST `{name (1–100), expiration_days? (1–3650)}` → 201 `{api_key: "QAIRE-" + 64 hex}`, shown only once (stored as its SHA-256). 400 `VALIDATION_ERROR`, 403 `FORBIDDEN`, 429 plan (feature gate: an exhausted quota does not block it) |
| `/api/v1/api-keys/{id}` | DELETE | write permission, owner or Admin | revokes (204, the row stays `revoked`). 404 `API_KEY_NOT_FOUND` (also another account's or an already revoked key) |
| `/api/v1/external/questionnaires` | GET | `X-API-Key`, Cap(`api`), rate limited | `{questionnaires:[{id, flow_id, slug, title, description, is_active, type, created_at, updated_at}], pagination}` (numbered, `page_size` default 50, ≤ 50), root questionnaires newest first. 401 `INVALID_API_KEY` (missing, unknown, revoked or expired: same message), 429. Sets `last_used_at`; `ApiUsage` counts `api` |
| `/api/v1/external/questionnaires/{id}/answers` | GET | `X-API-Key`, Cap(`api`), rate limited | `{questionnaire_id, sessions:[{id, answers:[{title, value, min?, max?}]}], pagination}`, every session of that questionnaire newest first (§7.14 value format). 400 `INVALID_UUID`, 401, 404 `QUESTIONNAIRE_NOT_FOUND` (also another account's) |
| `/api/v1/webhooks` | GET, POST | signed in; POST write permission + Feat(`webhook`) | `{url (https only), event_type? (questionnaire.completed), method? (POST)}` → 201 the webhook `{id, customer_id, url, event_type, method, created_at, updated_at}`. 400 `VALIDATION_ERROR`, 403, 429 |
| `/api/v1/webhooks/{id}` | PUT, DELETE | write permission, owner or Admin | PUT partial (≥ 1 field) → the webhook; DELETE 204 (its delivery log too). 400 `INVALID_UUID`, 404 `WEBHOOK_NOT_FOUND` (also another account's) |
| `/api/v1/webhooks/{id}/deliveries` | GET | signed in, owner or Admin | extension (D19): the latest 20 deliveries `[{id, status: pending\|delivered\|failed, attempts, last_status_code, last_error, next_attempt_at, …}]`. 404 `WEBHOOK_NOT_FOUND` |
| `/api/v1/videos` | GET | signed in | `{videos:[{id, title, description, url, language, category, order, duration_minutes, created_at, updated_at}]}` sorted by `order`, then title. `?language=es\|en`; without it, both languages. 400 `INVALID_LANGUAGE` (anything else, also empty) |
| `/api/v1/admin/videos` | GET, POST | Admin only (Customer-Admin 403 `FORBIDDEN`; ignores `X-Assume-Customer-Id`) | GET: every video (or `?language=`), same order. POST `{title (1–200), url (YouTube video link, ≤ 500), language (es\|en), description? (≤ 2000), category? (≤ 100), order? ≥ 0, duration_minutes? ≥ 0}` (no extra fields) → 201 the video. 400 `VALIDATION_ERROR` |
| `/api/v1/admin/videos/{id}` | GET, PUT, DELETE | Admin only | PUT is a full replacement (same body as POST). DELETE 204. 400 `INVALID_UUID`, 404 `VIDEO_NOT_FOUND` |

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
- **Organizations (D1, D2).** `PUT`/`DELETE /organizations/{id}` require the caller's own organization (another
  account's is 404) or an Admin, and write permission (read-only is 403). Deleting an organization deletes its members,
  and is **refused with 409 `ORGANIZATION_HAS_ASSIGNATIONS`** while any assignation or project points at it: deleting
  them would lose answers and reviews, and keeping them would orphan respondents who log in against its members. Delete
  (or move) the assignations and projects first. Member ids sent for new members are ignored (a fresh id is minted), and
  a reconciliation frees emails before rewriting them, so members can swap emails in one update.
- **Projects.** An assignation within a project is due on its own `due_date` or, without one, on the project's; it is
  overdue once that date is before "today" in UTC−12 (§7.12). Writing a project needs the console's write permission
  (a read-only role gets 403), like D1 for organizations. A review counts only for the attempt it was made in, and a
  locked answer counts as approved (`Assignations\Domain\FollowUpProgress`).
- **Outgoing webhooks (D19).** `questionnaire.completed` fires on every `QuestionnaireSessionCompleted` (every stage, as
  §7.7 step 3 says "in all cases"). The `webhook` capacity is checked once per event, first: rejected, nothing is sent
  or logged. Each subscribed URL gets a row in `webhook_delivery` and its first attempt at once (3 s connect, 5 s read,
  no redirects, private and loopback addresses refused); a non-2xx answer or a network error is retried after 1 min,
  5 min, 30 min, 2 h and 6 h (6 attempts, then `failed`) by a task the worker runs every minute
  (`bin/console app:webhooks:retry` runs it by hand). Each successful delivery counts one `webhook`. The body is
  rebuilt in the PRD's key order on every attempt and signed with `WEBHOOK_SIGNING_SECRET`; an extra `X-Delivery-Id`
  header (the same on retries) lets receivers drop duplicates.
- **API keys and the external API.** Creating and revoking keys and writing webhooks need the console's write
  permission (read-only is 403), as the Integrations screen shows. Every external call (both endpoints) passes
  Cap(`api`) and counts one `api`; a call refused by the plan or answered 404 does not count. The answers endpoint
  lists every session of the questionnaire (in progress too), newest first, as the PRD's shape has no status.
- **Documentation.** The 15 guides are static content in the console bundle (`console/entities/guide/content/{en,es}.ts`,
  typed so both languages must have every guide), not i18n JSON; the guide follows the console's UI language. Their
  screenshots are PNGs in `public/docs/screenshots/{es,en}/` taken from the demo data; a guide hides a screenshot whose
  file is missing. Videos must be YouTube links (watch, youtu.be, embed, shorts) and play through
  `youtube-nocookie.com`; `GET /videos` without `language` lists both languages.

## Known gaps

- Everything the split's items have not built yet is a placeholder page ("This screen is on its way.").
- No S3 adapter: object storage is local with signed URLs (the port allows adding one).
- Error tracking (Sentry) is configuration only; heatmaps (Clarity), GA page views and the account's pixels load in
  the respondent app only when their ids are configured.
- The Claude adapter is written against the official SDK but has not been run against the live API in this repo
  (no key in dev); refusals and output-token exhaustion fail the job with a clear error.
- Google Sheets export (answers and a default assignation's detail) runs in the browser with Google Identity
  Services and needs `GOOGLE_SHEETS_CLIENT_ID`; without it the button is disabled with the reason. It has not been run
  against a real Google account in this repo.
- Respondent app: voice answers transcribe with the browser's speech recognition (Chrome/Edge/Safari); the OpenAI
  real-time transport and the account's `transcription_url` are not wired, and a respondent without either can type
  the answer (D12). `/f/:id` chains stop at "We couldn't prepare your next questions" until the generation item adds
  `POST /questionnaire/prompt`. The PDF report is built in the browser (jsPDF, standard fonts).
- Documentation: the guides' screenshots of AI Experience and Plans are missing (those pages were still placeholders
  when they were taken; retake them from the demo data and drop the PNGs in `public/docs/screenshots/{es,en}/`). The
  demo videos use placeholder YouTube ids, so the embedded player says the video is unavailable until an Admin sets
  real links through `/admin/videos`; there is no admin UI for videos (the "tower" console is out of scope).
