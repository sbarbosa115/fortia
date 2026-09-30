# Mappi — the whole app, built in parallel items

The product requirements are [`/prd.md`](../../prd.md) (the PRD: every rule, route, screen and error code). This
file is the **build plan** of the `symfony-react-app` skill: how the PRD is cut into items, what item 0 fixed so they
can run in parallel, and the decisions every item follows. Read it all, then the PRD sections your item owns.

## What it is for

Mappi is a multi-tenant B2B SaaS for creating questionnaires (often with AI), sending them to respondents and turning
answers into results (PRD §1). Three roles use it: **operators** in the console (`/console/*`: Customer-Admin,
Customer-Read-Only, and the platform's Admin who can assume any account), **respondents** in the public respondent
app (`/q/:id`, `/f/:id`, `/a/:id`, `/session/:id/results`), and **integrators** through the external API
(`X-API-Key`).

## Plan

- **One Symfony project** in `backend/`: the JSON API under `/api/v1`, the console under `/console`, the respondent
  app on the public routes, one database, one worker. Two React apps built by Encore from `assets/console` and
  `assets/respondent`, sharing `assets/shared`.
- **Owning contexts (backend):** Identity, Billing, Platform, Jobs, Questionnaires, Responses, Reporting,
  Organizations, Assignations (incl. Projects), Branding, Commerce, Integrations, Content, Generation, Chat — plus
  the Shared kernel. `tools/deptrac.py` lists them.
- **FSD slices (frontend):** one page slice per route (all pre-registered by item 0 in `app/router.tsx` with a
  placeholder), widgets for the layout's banners and the profile tabs, features/entities as each item needs.
- **External providers:** ports with a real adapter and a deterministic fake (user decision, 2026-09-30). Dev and
  tests run offline on the fakes.
- **Technical debt (PRD §15):** apply every recommended fix (user decision). Each item applies the D-items in its
  area; the table in "Decisions" says which.
- **Left out on purpose** (goes to the README's "Known gaps"): the "tower" admin UI (PRD non-goal; its `/admin/*`
  API is built), S3 as an adapter (local signed storage only), Sentry/Clarity loaders (config only), Google Sheets
  export needs a Google OAuth client id to work, LinkedIn and headless-browser scraping run on fakes/simple HTTP,
  real-time transcription uses the browser's Web Speech API unless an OpenAI key is configured, `/internal/qa/*`.

## Contract (item 0)

Item 0 is built and committed on `feature/mappi` (the base branch). It fixed:

- **The stack:** `docker-compose.yml` (php, worker, reloader, nginx, database MySQL 8.4, node, mailpit, e2e profile),
  `scripts/stack.sh up|reset|status|down`. `vendor/` and `var/` live in volumes; Symfony runs without debug-mode
  freshness checks and the `reloader` service clears the cache when code changes (first request after a change
  rebuilds it, ~30 s). PHPUnit drops a stale test cache itself (`tests/bootstrap.php`).
- **The whole schema:** every entity of PRD §6 in `src/<Context>/Domain/Model` and one migration. An item may add a
  migration **only** for a table it alone owns and the split table names (billing's gateway tables, etc.).
- **The shared kernel** (`src/Shared`): DomainError kinds → HTTP status; the `{message,data}` / `{error:{code,message,
  details}}` envelopes (`ApiResponse`); `#[Payload]` input mapping with strict types, `allowExtraFields: false`,
  `TracksProvidedFields` for partial updates; `RouteId::uuid()`; `Caller` (with impersonation and its audit log);
  `RespondentTokens` + `RespondentBearer` (the `rt.` bearer, expiring, 401 when invalid); `CommandBus` (one
  transaction per command), `EventBus` (after commit, async on the worker); `ObjectStorage` (local, HMAC-signed
  form/PUT/download URLs served by `StorageController`); `Mailer` (Twig templates, sent synchronously); the
  `LanguageModel` port with the Claude adapter (official SDK; `claude-opus-5-5` for generation, `claude-haiku-4-5`
  fast) and the fake (`FakeLlmResponder` per purpose); the questionnaire document model (`Shared/Domain/Document`:
  `Questions`, `Scoring`, `OptionValues`, `GeneratedQuestions` = PRD §7.6); `Ids`, `Text`, `Iso`, `Clock`.
- **Jobs:** `Jobs::start()` inside a command handler (or dispatch `StartJob`); a `JobHandler` per type
  (`#[AutoconfigureTag]` via the interface); `GET /api/v1/jobs/{id}` → `{job}`; stages via `JobProgress`.
- **Plan gate and usage:** `Billing\Application\PlanGate::capacity|feature($caller, Features::X)` (Admin passes; 429
  `PLAN_LIMIT_REACHED {reason, feature}`), `capacityForAccount()` for anonymous flows; counting = a domain event
  whose `feature()` is set (`CountUsage`), or `Usage::record()` when there is no event. `GET /customer/usage`.
- **Cross-context reads** (Application-level, return PRD-shaped arrays/views, never entities):
  `QuestionnaireQueries`, `SessionQueries`, `OrganizationQueries`, `AssignationQueries`, `AccountQueries`,
  `ProductQueries`, `StylesQueries`, `JobQueries`, `UsageQueries`, `SystemPrompts` (12 keys, defaults in
  `config/system_prompts/*.md`).
- **Events other items listen to:** `Responses\Domain\Event\QuestionnaireSession{Created,Updated,Completed}`,
  `Questionnaires\Domain\Event\QuestionnaireCreated`. An item adds its own events in its context's `Domain/Event`.
- **Sign-in:** `POST /api/v1/auth/token` (email + password → id token 24 h + refresh token 30 d), `POST /auth/refresh`,
  `GET/PATCH /customer/onboarding`, and the minimal `/console/login` and `/console/logout` pages.
- **Shared output DTOs** (`Shared/UI/Http/Output/Document`, in `openapi.json` already): `QuestionnaireOutput`,
  `QuestionOutput`, `InputControlOutput`, `FlowOutput`, `SessionOutput`, `SessionResultsOutput`,
  `DiagnosticResultOutput`, `ProductOutput`, `JobOutput`… Endpoints that return these shapes use these classes.
- **Frontend:** `assets/shared` (api client with envelope/errors/token/assume/plan-limit hooks, `pollJob`,
  i18n with per-slice namespaces auto-collected, lib, the house UI kit), `assets/console/app` (router with every
  route and its guards, sidebar layout, session + plan-usage entities), `assets/respondent/app` (routes of PRD §9.2,
  default theme). Every page is a placeholder its item replaces.
- **Demo data:** `bin/console app:seed-demo` runs every `DemoSeeder` (catalog, accounts, plans). Accounts in
  `Shared\Application\Seed\DemoAccounts` (password `password123`, dev only). An item seeds its own data with its own
  seeder (priority ≤ 50).
- **Test support:** `tests/Support/ApiTestCase` (`account()`, `user()`, `admin()`, `api()`, `assertApiError()`,
  `data()`, `clock()`, `llm()`), `TestClock`, `EchoJobHandler`; Vitest + `testI18n('console'|'respondent')`.

## Split

**How the items are built (decision, 2026-09-30):** everything lives in the one `fortia-mappi` folder — no git
worktrees or sibling project folders. The table below is the order of work and the ownership of each area; items are
built one after another on `feature/mappi` in this folder (in wave order), each through the skill's steps 4–5 and 7.
The first wave was started in parallel and stopped; its unfinished work is saved on the local branches
`feature/mappi-<slug>` and is merged and finished item by item here.

<!-- Case ID prefixes are per area; each item owns the whole range it is given. -->

| # | Slug | Item | Owns (context / slice, files) | Tests first | Browser cases | Depends on |
|---|---|---|---|---|---|---|
| 0 | contract | Stack, schema, kernel, contracts, app shells | everything listed in "Contract (item 0)" | kernel unit tests, API conventions, auth, usage, jobs | — | — |
| 1 | accounts | Sign-up, sign-in, recovery, users, profile, settings (PRD §8.2, §8.3 settings, §10.2, §10.14 Settings tab, §10.16) | Identity (Application/UI/Infrastructure), Google sign-in port, `PATCH /customer/workspace` (D15); pages login (full), sign-in, forgot-password, reset-password, users, user-new; widget account-settings; `templates/emails/identity/` | RegisterTest, PasswordRecoveryTest, UsersTest (AG, Cap(users), 409), SettingsTest (profile gate, max_files), ProfileTest | AUTH-01 – 20, USR-01 – 10 | 0 |
| 2 | billing | Plans, checkout, plan changes, cancel/resume/portal, payment webhooks, contact (PRD §7.3–7.4, §8.3, §10.15, §10.21) | Billing (Application/UI/Infrastructure except the gate and usage of item 0), `PaymentGateway` port (fake + Stripe), fake hosted checkout page (`/fake-gateway/*`), tables of its own (payments, processed webhook events, fake gateway state); pages plans; widgets plan-usage-panel, usage-banner; `templates/emails/billing/` | PlanChangeClassificationTest (unit, §7.4 rules), CheckoutTest, WebhookTest (idempotent, reset on upgrade), ContactTest | BIL-01 – 20 | 0 |
| 3 | admin | Super-admin API (PRD §8.13 except videos) and "assume customer" (§10.20) | `UI/Http/Controller/Admin/*` in Identity (customers), Billing (customer plan/usage, features, plans, coupons via the gateway port), Platform (system prompts); widget assume-customer | AdminCustomersTest, AdminPlansTest (INVALID_STRIPE_PRICE), AdminCouponsTest, SystemPromptsTest (INVALID_PLACEHOLDERS, versions); Customer-Admin gets 403 | ADM-01 – 15 | 1, 2 |
| 4 | authoring | Questionnaires and flows API (PRD §7.5, §8.4 questionnaire/flow/find) and the listing (§10.6) | Questionnaires context (commands `SaveFlow` create/update, copy, activate; flow + diagnostic validation; listing with DB pagination and server-side search, D16/D17); page questionnaires | FlowValidationTest, DiagnosticRulesTest (unit), QuestionnaireApiTest (409 answered, slug in use, other tenant 404), CopyTest, FlowLookupTest | QST-01 – 15 | 0 |
| 5 | editor | Manual creation and editing (PRD §10.5, §10.7) | pages questionnaire-new, questionnaire-editor (regular, diagnostic, chaining, generic edit, locked, success); features/entities it needs | editor model tests (validation messages, encoding, tier seeding and rules, max score) | EDT-01 – 25 | 4 |
| 6 | sessions | Respondent sessions API (PRD §7.7, §7.9, §8.4 sessions, files, transcription) | Responses context: `StartSession` command (also used by assignations), save with follow-up merge (§7.11), submit + scoring (diagnostic, chain, ai_team_profile, samurai8/livingood as configured result types, D8), results, chain, evaluate job, recommendation job (quiz funnel), signed-urls (D4: valid session only), answers-media download, transcription token port | DiagnosticScoringTest (unit, §16.3 #4 cases), SessionApiTest, FollowUpMergeTest, EvaluationTest, SignedUrlsTest | SES-01 – 05 | 0 |
| 7 | respondent-app | The respondent app (PRD §9 except /a/:id) | respondent pages questionnaire, flow (incl. generating), results, privacy; widgets/features/entities of the runner (controls, themes, persistence, audio, files, data capture, results variants, PDF, branding §9.15, language, pixels §9.16) | runner model tests (validations, exclusive checkbox, slider untouched, persistence), results formulas | RSP-01 – 45 | 6 |
| 8 | organizations | Organizations and members (PRD §8.7, §10.10) | Organizations context (reconcile members, D1 ownership, D2 cascade); pages organizations, organization-form, organization-view (CSV import) | OrganizationApiTest (domain conflict, reconcile, other tenant 404), CSV parser tests | ORG-01 – 15 | 0 |
| 9 | assignations | Assignations API, reminders, reviews, retries (PRD §7.11, §7.13, §8.8, §10.11) | Assignations context except Projects; daily reminders `#[AsCronTask]`; emails `templates/emails/assignations/`; pages assignations, assignation-form, assignation-detail | AssignationApiTest, RespondentLoginTest (§7.11 order), ReviewTest, RetryTest, ReminderTest (subjects, UTC day, D3) | ASG-01 – 30 | 6, 8, 15 |
| 10 | assignation-respondent | The respondent's /a/:id (PRD §9.10) | respondent page assignation (login slide, completed screens, resume, retries, token) reusing the runner of item 7 | login field recognition, outcome mapping tests | ARS-01 – 15 | 7, 9 |
| 11 | projects | Projects API and screen (PRD §7.12, §8.9, §10.12 except /projects/new) | Assignations/…/Project* (commands, state rules, UTC−12 overdue); page projects | ProjectStateTest (unit), ProjectApiTest | PRJ-01 – 12 | 9 |
| 12 | project-wizard | /projects/new (PRD §10.12) | page project-new | wizard model tests (idempotent retry) | PRJ-13 – 20 | 11, 17 |
| 13 | integrations | API keys, external API, webhooks (PRD §7.14, §8.11, §10.17) | Integrations context (delivery with retries and a log, D19); page integrations | ApiKeyTest, ExternalApiTest (revoked 401, other account 404), WebhookDispatchTest (signature, capacity first) | INT-01 – 12 | 0 |
| 14 | branding | Brand styles (PRD §7.16, §8.5 styles, §10.13) | Branding context, styles job, `BrandExtractor` port (HTTP fetch + fake), style design via the LLM (+ fake responder); page customization | StylesJobTest, contrast rule unit tests, StylesApiTest | BRD-01 – 10 | 0 |
| 15 | analytics | Answers, analytics and dashboards (PRD §7.10, §8.4 answers/analytics/dashboard, §10.8, §10.9, §13.9) | Reporting context (answers listing with cursor, analytics, dashboard selection + cleanup, dashboard data); pages questionnaire-answers, answer-detail, questionnaire-dashboard; `assets/shared/lib/sheets.ts` (Google Sheets export) | DashboardSelectionCleanupTest (unit), DashboardApiTest (locked, once, 502), AnswersApiTest (cursor), funnel/NPS formula tests (Vitest) | ANS-01 – 15, DSH-01 – 10 | 0 |
| 16 | commerce | Products, scraping, Shopify, quiz funnel creation (PRD §7.17, §8.6, §10.5 Quiz Funnel, §10.19) | Commerce context (`CatalogScraper` port: schema.org HTTP + fake; `CommercePlatform` port: Shopify + fake incl. OAuth page; GDPR webhooks HMAC; D5 signed state), quiz funnel job via Questionnaires `SaveFlow`; pages quiz-funnel-create, products | ScrapeJobTest, QuizFunnelJobTest, ShopifyOAuthTest (state), GdprWebhookTest | QF-01 – 15 | 4 |
| 17 | chat | The AI assistant (PRD §7.19, §8.10, §10.4) | Chat context (turn job, tool registry calling other contexts' Application layer, write queue, draft mode); page ai-experience with live preview | ChatTurnTest (fake LLM scripted), tool permission tests | CHAT-01 – 15 | 1, 2, 4, 8, 9, 11, 13, 14 |
| 18 | generation | Chain stages and LinkedIn (PRD §7.8, §7.18, §8.4 prompt/linkedin) | Generation context (prompt job: untrusted owner prompt, attachments limits, 3 attempts, tier bands; LinkedIn port + fake; owner account as config, D8) | PromptStageJobTest, LinkedinJobTest | GEN-01 – 05 | 4, 6 |
| 19 | onboarding | Onboarding (PRD §7.15, §10.3) | page onboarding (7 steps, templates in i18n, D15 saves the workspace, D23) | onboarding model tests | ONB-01 – 10 | 1, 4 |
| 20 | docs | Documentation and videos (PRD §8.12, §8.13 videos, §10.18) | Content context (GET /videos, /admin/videos CRUD); pages documentation, documentation-guide (15 bilingual guides) | VideosApiTest, guide search tests | DOC-01 – 08 | 0 |

## Decisions

Everything an item must agree on that the code does not show yet.

**API shapes.** The PRD is the contract: routes, field names, enums, error codes and envelope/bare as §8 says. Output
DTO properties are snake_case (they are the JSON). A new endpoint that returns a shared document shape uses the
shared DTOs. After changing a controller or DTO: `bin/console nelmio:apidoc:dump --format=json >
assets/types/openapi.json && npm run -s api:types` (in the node container), commit both.

**Commands other items call** (the owner builds them; the caller waits for the owner's merge via "Depends on"):

| Command / query | Owner | Used by |
|---|---|---|
| `Questionnaires\Application\Command\SaveFlow` (create or update a questionnaire from a flow payload, returns the id; emits `QuestionnaireCreated` with the feature of its type) and `CreateGeneratedStage` (child stage of a chain, no usage) | authoring (4) | commerce, generation, chat, project-wizard |
| `Questionnaires\Application\Command\CopyQuestionnaire` | authoring (4) | assignations (copy on conflict), chat |
| `Responses\Application\Command\StartSession` (questionnaire id, optional assignation binding and attempt; returns the session id) | sessions (6) | assignations |
| `Responses\Application\Query\SessionQueries` additions (answers of a session as `[{title, value, min?, max?}]` for webhooks and the external API) | sessions (6) | integrations, analytics (reads) |
| `Organizations\Application\Command\SaveOrganization` | organizations (8) | chat, project-wizard (via API is fine too) |
| `Assignations\Application\Command\*` (create/update/delete, reminders) and `Project*` | assignations (9), projects (11) | chat |
| `Billing\Application\Port\PaymentGateway` | billing (2) | admin (coupons, price validation) |

Integrations (13) is in wave 1 and needs the answers of a completed session: until sessions (6) merges, it formats
them from `SessionQueries::find()` (the document's questions) with its own `WebhookPayload` builder, which is the
PRD §7.14 value format; sessions reuses it later if useful.

**Emails.** Templates in `templates/emails/<context>/<name>.html.twig` extending `templates/emails/layout.html.twig`
(item 0), texts in `translations/emails_<context>+intl-icu.{es,en}.yaml`. Language = the account's (`es` default).

**Technical debt (PRD §15), who fixes what:** D1, D2 organizations · D3, D6, D7 assignations/sessions (D6, D7 are
already in the kernel: tokens expire, invalid ones are 401) · D4 sessions (signed-urls only for a valid session;
rate limit `public_api` on public endpoints) · D5 commerce · D8 each owner (config in `.env`: SAMURAI8_*,
LIVINGOOD_*, LINKEDIN_OWNER_CUSTOMER_ID, SALES_LEAD_RECIPIENTS, DIAGNOSTIC_TITLE_OVERRIDES) · D9 accounts (no legacy
`/login`) · D10 nobody (not migrated) · D11 respondent-app + commerce (sanitize with DOMPurify / Symfony HtmlSanitizer)
· D12 respondent-app (skip the mic check) · D13 kernel (`EMAIL_PATTERN` in shared/lib; backend uses the same regex)
· D14 kernel config · D15 onboarding + accounts · D16, D17 every listing (DB pagination and `?search`/`?q`) · D18
kernel · D19 integrations · D20 accounts (send the welcome email) · D21 accounts (links to /privacy and the support
email) · D22 not migrated · D23 each owner (texts in i18n) · D24 kernel (CORS by env) + sessions (rate limits) · D25
out of scope.

**Frontend.** Types from `Schema<'XOutput'>` only. Every string through i18n (`react/jsx-no-literals` enforces it),
both `en.json` and `es.json` of your slice. House components from `@shared/ui` (Button, Card, PageHeader, Field,
TextInput, Select, Toggle, Modal, ConfirmDialog, Table, Pagination, CursorPagination, FilterBar, SearchInput,
EmptyState, ErrorState, LoadingState, Badge, ProgressBar, Tabs, ChoiceCards, Tooltip, useToast). Read `CLAUDE.md`.

**Case IDs.** Each item writes its cases in `docs/tests/ui-regression.md` under its section, only in its range.
