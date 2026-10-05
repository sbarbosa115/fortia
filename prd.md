# PRD — Mappi (technology-agnostic rebuild of Skyline)

| Field | Value |
|---|---|
| Product | **Mappi** (formerly "Skyline" / "QuestionAIre") |
| Document type | Migration PRD with 1:1 functional parity |
| Date | 2026-09-30 |
| Sources | `skyline-backend`, `skyline-ui` (respondent app), `skyline-admin-ui` (operator console) |
| Scope | Everything that exists today, described independently of the technology |
| Language | English. Field names, routes, enums and error codes are left as-is because they are part of the contract |

---

## 0. How to read this document

### 0.1 Agnosticism principle

This PRD describes **what** the system must do, not **what to build it with**. Any language, framework, database, cloud or provider will do as long as it meets the contracts and rules described here.

- External providers are named by **capability**: "identity provider", "payment gateway", "object storage", "language model (LLM)", "email service", "real-time speech transcription", "catalog scraper", "e-commerce platform".
- The current implementation of each capability appears **for reference only** in [Appendix E](#appendix-e--reference-of-the-current-implementation). It is not a requirement.
- The **contracts** (HTTP routes, field names, enums, error codes, public URL formats) are a requirement wherever backward compatibility is needed: links already shared, external integrations using an API key, webhook receivers. If the migration decides to break them, it must do so explicitly and with a redirect plan.

### 0.2 Conventions

- **MUST**: mandatory requirement for parity.
- **SHOULD**: recommended; may be changed if there is a documented reason.
- **[DEBT]**: current behavior that looks like a defect or risk. It is documented so the migration can consciously decide whether to replicate it or fix it (see §15).
- **[CLIENT-SPECIFIC]**: logic specific to one customer that is currently hard-coded. Recommendation: turn it into configuration.

---

## 1. Product summary

Mappi is a **multi-tenant B2B SaaS** platform for creating questionnaires ("experiences"), often with AI assistance, sending them to respondents and turning their answers into actionable results: product recommendations, diagnostics with scores and tiers, profiles, reports and dashboards.

### 1.1 The three business flows

1. **Quiz Funnel (e-commerce).** A store's catalog is imported (by scraping the website or by syncing with the e-commerce platform). The AI generates a questionnaire that is embedded in the store as a widget. When the visitor finishes, the AI recommends products from the catalog.
2. **Process mapping / Organizations.** The customer registers organizations and their members, **assigns** questionnaires to them, tracks progress, reviews answers question by question, requests corrections and groups assignations into **projects** with a due date.
3. **Assessments and diagnostics.** A public link collects answers. A diagnostic scores the answers by category, places the respondent in a tier and gives them recommendations, an action plan and a PDF. **Prompt chains** let an LLM generate the next stage of the questionnaire from the answers to the previous one.

### 1.2 The three parts of the system

| Part | Users | Function |
|---|---|---|
| **API / Backend** | Both apps, external integrators, payment gateway, e-commerce platform | Owner of all data and rules. Asynchronous jobs, scheduled tasks, webhooks |
| **Respondent app** | Anonymous visitors, organization members | Answer questionnaires and view results |
| **Operator console** | Customer (tenant) users and super-admins | Create, publish, assign, review, analyze, configure branding, billing and integrations |

There is also an internal super-admin console ("tower") that is outside the scope of this PRD. Only the `/admin/*` endpoints it consumes are documented.

---

## 2. Goals and non-goals

### 2.1 Goals

1. Rebuild the product on any stack **without losing functionality** (1:1 parity).
2. Keep compatibility for the **public links already distributed** (`/q/{id}`, `/f/{id|slug}`, `/a/{id}`, `/session/{id}/results`) and for the **external API** and **webhooks**.
3. Migrate existing data without loss: accounts, questionnaires, flows, sessions, results, organizations, assignations, projects, plans, products, styles, API keys, webhooks, files.
4. Leave the decisions on technical debt documented (§15).

### 2.2 Non-goals

- Redesigning the product or adding new features (these can be proposed separately).
- Rebuilding the "tower" super-admin console.
- Rebuilding the internal analytics/usage service. It is treated as an external dependency with the contract in §13.8. The migration may decide to absorb it.

---

## 3. Glossary

| Term | Definition |
|---|---|
| **Account / Customer / Tenant** | The paying company. Identified by `customer_id` (8 random alphanumeric characters) |
| **User** | A person who signs in to the console. Belongs to a single account |
| **Root / Owner** | The user who created the account |
| **Respondent** | Whoever answers a questionnaire. Anonymous or a member of an organization |
| **Questionnaire / Experience** | Ordered set of questions with a behavior on completion |
| **Question** | Screen unit of the questionnaire. Has one or more controls, although in practice the first one is used |
| **Control (InputControl)** | The answer field of a question (radio, checkbox, text, audio, file…) |
| **Flow** | State machine that wraps the questionnaire: defines the entry point, the chained stages and the result. Has a public `slug` |
| **State** | Node of the flow: `questionnaire`, `prompt`, `diagnostic`, `quiz_funnel`, `result`, `regular` |
| **Chain** | Flow with `prompt` states: each stage is generated by an LLM from the previous answers |
| **Session** | A response in progress or finished. It is a copy of the questionnaire with the respondent's values |
| **Session result** | What is computed on completion: products, diagnostic, profile |
| **Diagnostic** | Scoring configuration: tiers, and recommendations and action plan per tier |
| **Tier** | Score band `[min, max]` with a name and description |
| **Organization** | Group of people (members) belonging to a customer, to which questionnaires are assigned |
| **Member (OrganizationUser)** | Person within an organization. Has a name, email and/or phone, role and area |
| **Assignation** | Sending of a questionnaire to an organization, with an audience and a type (`default` or `follow_up`) |
| **Follow-up** | Assignation with a **shared** session: all members contribute to the same session, with daily reminders, review and retries |
| **Attempt** | Each round of a follow-up. A retry ("send for correction") creates attempt n+1 |
| **Project** | Grouping of follow-up assignations from the same organization, with a due date |
| **Plan / Feature** | Commercial catalog. A plan assigns limits to each feature |
| **Job** | Asynchronous work that can be queried by polling |
| **Styles** | The account's visual branding applied to the respondent app |

---

## 4. Actors, roles and multi-tenancy

### 4.1 Actors

| Actor | Authentication | Has access to |
|---|---|---|
| Visitor / anonymous respondent | None | Respondent app on public routes |
| Organization member (assignation respondent) | Identity "login" against the member list; receives a respondent session token | `/a/{id}` |
| `Customer-Admin` console user | Email + password or Google | The whole console, read and write |
| `Customer-Read-Only` console user | Same | Read-only console |
| Owner (`root = true`) | Same | Same as `Customer-Admin` |
| `Admin` super-admin | Same (assigned by hand in the identity provider) | All accounts; `/admin/*` endpoints; "assume" any account |
| External integrator | `X-API-Key` header | `/external/*` |
| Payment gateway | Signature in header | Payments webhook |
| E-commerce platform | HMAC in header | Compliance webhooks (GDPR) |
| Customer's webhook receiver | Verifies the HMAC signature we send | Receives `questionnaire.completed` |

### 4.2 Roles

| Role | Meaning | How it is granted |
|---|---|---|
| `Admin` | Super-user of the whole platform | Only by hand in the identity provider |
| `Customer-Admin` | Account administrator | Automatically on sign-up (root). Assignable with `POST /users` |
| `Customer-Read-Only` | Read-only member | Assignable with `POST /users` |

- The user attribute `root` (`"true"`/`"false"`) marks the account owner.
- `ADMIN_GROUPS = {Admin, Customer-Admin}`: the groups that can create users and use "admin" write endpoints.
- `ASSIGNABLE_ROLES = {Customer-Admin, Customer-Read-Only}`.
- **Write permission in the console** = `root` **or** belongs to `Admin` or `Customer-Admin`. Checks use the full list of groups, never the displayed role.
- **Displayed role** (precedence): `Admin > Customer-Admin > Customer-Read-Only`.

**Permission matrix shown when creating a user:**

| Action | Admin | Read-only |
|---|---|---|
| View questionnaires | ✓ | ✓ |
| Create and edit questionnaires | ✓ | – |
| Delete questionnaires | ✓ | – |
| View responses | ✓ | ✓ |
| Export reports | ✓ | ✓ |
| Invite users | ✓ | – |
| Edit billing and workspace | ✓ | – |

### 4.3 Multi-tenancy

- The tenant is the **account** (`customer_id`). Every customer entity MUST carry `customer_id`.
- The user's identity (name, email) lives in the identity provider, not in the account row.
- Reads and writes MUST be filtered by the caller's `customer_id`, except for `Admin`, who sees all accounts in listings (questionnaires, organizations, assignations).
- Console user emails are **unique across the whole system**.

### 4.4 Impersonation ("assume customer")

- An `Admin` can send the header `X-Assume-Customer-Id: <customer_id>` (header name is case-insensitive).
- The request runs **as the root user of that account**: groups `[Customer-Admin]`, `root = true`, the root's email and name. There is no admin bypass while assuming, and the plan limits of the assumed customer apply.
- A caller who is not `Admin` and sends the header receives `403 ASSUME_NOT_ALLOWED`. A nonexistent account gives `404 ASSUMED_CUSTOMER_NOT_FOUND`.
- The `/admin/*` endpoints ignore the header and use the real caller.
- [DEBT] It is not audited. Recording who assumed whom and when is recommended.

---

## 5. Logical architecture (agnostic)

```
                 ┌────────────────────┐       ┌──────────────────────┐
 Respondents  ──►│   Respondent app   │       │   Operator console   │◄── Account users / Admin
                 └─────────┬──────────┘       └──────────┬───────────┘
                           │   HTTP JSON (API /api/v1)   │
                           ▼                             ▼
                 ┌───────────────────────────────────────────────────┐
 Integrators  ──►│                       API                         │◄── Incoming webhooks
 (X-API-Key)     │     auth · validation · plan gate · use cases     │    (payments, e-commerce)
                 └──┬──────────┬──────────┬──────────┬──────────┬────┘
                    │          │          │          │          │
               Database     Job        Object      Event      Usage/analytics
             (persistence)  queue      storage     bus        service
                    │          │                     │
                    │     Async workers         Outgoing webhook
                    │    (AI, styles, scraping) dispatcher
                    │
              Daily scheduled task (reminders)

 External dependencies by capability: identity provider (+ Google sign-in),
 LLM, real-time transcription, transactional email, payment gateway,
 e-commerce platform, catalog/profile scraper, headless browser,
 error tracking, marketing pixels.
```

**Architecture requirements:**

- **A1.** The API MUST be able to answer every synchronous request in ≤ 29 s. Anything that takes longer (AI generation, scraping, styles, answer evaluation, product recommendation, chat) MUST run as an **asynchronous job** that can be queried with `GET /jobs/{job_id}`.
- **A2.** The processing order of each request MUST be: **authentication → input validation → plan gate → execution**.
- **A3.** The console has no data of its own: everything goes through the API.
- **A4.** There is no real-time channel (websocket) between the API and the apps. Asynchronous progress is queried by polling.
- **A5.** Usage and analytics events are emitted asynchronously and must not block the response to the user.

---

## 6. Data model

General rules:

- Dates and times are stored in ISO-8601 UTC (`Z`). Calendar dates are stored as `YYYY-MM-DD`.
- IDs are UUIDv4 unless another format is stated.
- **There is no soft delete** except in two cases: API keys are revoked (`status = revoked`) and questionnaires are deactivated (`is_active = false`). Everything else is physically deleted.
- The choice of database is free. The listed "indexes" are **access patterns** that the implementation MUST support efficiently.

### 6.1 Customer (account)

| Field | Type | Notes |
|---|---|---|
| `customer_id` | string(8) | PK, random alphanumeric |
| `created_at` | datetime | |
| `language` | enum `es-CO` \| `en-US` | Default `es-CO` |
| `source` | string | Sign-up origin, default `"default"`. Accessed by `source` + `created_at` |
| `settings` | `CustomerSettings` object | See below |
| `plan` | `CustomerPlan` object \| null | Embedded |
| `shopify_shop`, `shopify_token`, `shopify_refresh_token` | string \| null | Connection with the e-commerce platform. The tokens are secrets |
| `onboarding_completed` | bool \| null | null = legacy account (derived, §7.15) |
| `stripe_trial_used_at` | datetime \| null | "Already used their trial in the gateway" marker. Written once and never cleared |

**CustomerSettings:**

| Field | Type | Rule |
|---|---|---|
| `language` | `es-CO` \| `en-US` | Never null |
| `transcription_url` | string \| null | Alternative URL for the transcription service |
| `pixel_id` | string ≤ 64 \| null | Meta pixel |
| `linkedin_partner_id`, `linkedin_conversion_id` | string ≤ 64 \| null | |
| `google_ads_id`, `google_ads_conversion_label` | string ≤ 64 \| null | |
| `max_files` | int 1–20 | Default 10. Maximum number of files per file-type question |

**CustomerPlan (embedded):**

| Field | Type |
|---|---|
| `plan_id` | string |
| `from_at`, `to_at` | date (validity period, inclusive) |
| `billing_interval` | `month` \| `year` (default `month`) |
| `stripe_customer_id`, `stripe_subscription_id` | string \| null (IDs in the payment gateway) |
| `trial_end` | datetime \| null |
| `discount` | `{coupon_id, promotion_code?, percent_off?, amount_off?, currency?, duration, ends_at?}` \| null |
| `created_at`, `updated_at` | datetime |

### 6.2 User (lives in the identity provider)

| Field | Notes |
|---|---|
| `email` | Globally unique; it is the username; automatically verified |
| `name` | 1–50 |
| `customer_id` | ≤ 16 |
| `root` | `"true"` / `"false"` |
| groups | `Admin`, `Customer-Admin`, `Customer-Read-Only` |

### 6.3 Feature (global catalog)

- `id` = slug of the name; never changes.
- `feature_name` (1–100), `feature_description` (≤ 1000, default `""`), timestamps.
- **Canonical slugs:** `regular`, `diagnostic`, `quiz-funnel`, `chain`, `chat`, `organizations`, `assignations`, `styles`, `analytics`, `dashboards`, `users`, `api`, `webhook`, `profile`, `responses`.

### 6.4 Plan (global catalog)

| Field | Type | Rule |
|---|---|---|
| `id` | string | Slug of the name |
| `plan_name` | 1–100 | Unique |
| `plan_description` | ≤ 1000 | Default `""` |
| `features` | `[{feature_id, limit:int}]` | Unique IDs. **limit < 0 = unlimited; 0 = not included** |
| `max_questionnaires` | int \| null | null = no cap; negative = unlimited |
| `max_responses` | int \| null | Same |
| `price_amount` | int ≥ 0 \| null | In minor units (cents). null = "price on request" |
| `currency` | 3 letters | Default `usd` |
| `stripe_price_id` | ≤ 255 \| null | ID of the monthly price in the gateway |
| `yearly_price_amount`, `stripe_yearly_price_id` | | Yearly price |
| `trial_days` | 0–365 | Default 0 |

A plan with id `starter` MUST exist (trial plan on sign-up).

### 6.5 Questionnaire

| Field | Type | Notes |
|---|---|---|
| `questionnaire_id` | UUIDv4 | PK |
| `customer_id` | string | Accessed by account |
| `title` | string | Required |
| `description`, `disclaimer` | string \| null | |
| `capture_user_data` | bool | Default false. Asks for name, email and phone at the end |
| `landing_page` | bool | Default false. Shows a cover page before starting |
| `type` | enum | `default`, `ecommerce`, `quiz_funnel`, `samurai8`, `ai_team_profile`, `diagnostic`, `prompt` |
| `is_active` | bool | Default true |
| `on_completed` | `OnCompleted` \| null | What happens on completion |
| `parent` | `"ROOT"` \| questionnaire_id | The generated stages of a chain point to their root. Accessed by `parent` |
| `origin_session_id` | UUID \| null | Session that originated the generated stage. Accessed by this field |
| `session_id`, `started_at`, `ended_at` | null | Always null in the stored questionnaire; filled in on the session |
| `question_count`, `is_chain`, `slug` | derived | Denormalized copies for listings |
| `questions` | `Question[]` | |
| `created_at`, `updated_at` | datetime | |

**Question:**

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | |
| `order` | int | 0-based |
| `title` | string | |
| `description`, `disclaimer` | string \| null | |
| `theme_name` | enum \| null | `gender`, `quote`, `weight`, `height`, `celebration`, `user-capture-data`, `jeans-size`, `weight-composite`, `organization-users-login` |
| `statements` | any \| null | |
| `visibility` | string[] | Genders for which it is shown (`male`, `female`) |
| `options` | `InputControl[]` | The first control that can be rendered is used |
| `acceptance_criteria` | string[] ≤ 10 | Criteria for the AI evaluation |
| `max_followups` | int 0–5 \| null | Retries allowed when the AI rejects the answer |
| `attachment_required` | bool \| null | |
| `category` | string \| null | Diagnostic scoring category |
| `required` | bool | Default true |
| *Session only:* `improvement_message`, `flagged_answer`, `review` | | See §6.10 |

**InputControl:**

| Field | Type | Notes |
|---|---|---|
| `name` | string (UUID) | |
| `type` | enum | `radio`, `checkbox`, `select`, `range`, `text`, `audio`, `ranking`, `file`, `message`, `email`, `tel`, `phone` |
| `options` | `[{label, value?, visibility[]}]` | If `value` is null, `label` is used |
| `validations` | `[{type, value?, message?, pattern?}]` | `type`: `min`, `max`, `required`, `format` |
| `default_value` | any | Placeholder or initial slider value |
| *Session only:* `value` | string \| string[] | Selected values, transcriptions or file keys |
| *Session only:* `timestamp`, `skipped` (default false), `locked` | | `locked` is used in retries |

**OnCompleted** (union discriminated by `type`):

- `default {message?}`
- `quiz_funnel {products[]}`
- `diagnostic {tiers[], recommendations[], action_plan[]}`
  - Tier: `{id, name, description?, min, max, visible = true}`
  - Recommendation: `{tier_id, recommendation, visible}`
  - Action: `{tier_id, action, visible}`
- `process_mapping {message?}`

### 6.6 Flow

| Field | Type | Rule |
|---|---|---|
| `id` | string(20) | Short ID |
| `slug` | string 1–100 | `^[a-z0-9]+(-[a-z0-9]+)*$`, **unique across the whole system** |
| `detail` | string | |
| `customer_id` | string | |
| `questionnaire_id` | UUID | **One flow per questionnaire** |
| `source_url` | string \| null | Store origin (quiz funnel). Accessed by this field |
| `states` | `State[]` | See below |
| `cta` | object \| null | `{title 1–120, description ≤ 200, button:{text 1–50, url 1–2048 starting with http(s)://}}` |
| `layout` | string[] \| null | Each of `score`, `tier`, `categories`, `recommendations`, `action_plan`, `pdf`, `cta` at most once |
| `result_copy` | object \| null | 15 optional texts ≤ 300 characters (trimmed; empty = default text): `eyebrow, title, subtitle, tier_label, overall_score, categories_title, categories_subtitle, chart_title, chart_subtitle, chart_legend, recommendations, action_plan, report_title, report_subtitle, download` |
| `created_at`, `updated_at` | datetime | |

**State:** `{state_id (15 characters, unique within the flow), type, parameters{}, outputs{}, next?}`.

- `type`: `questionnaire`, `regular`, `quiz_funnel`, `diagnostic`, `prompt`, `result`.
- `parameters.questionnaire_id` in `questionnaire` states.
- `prompt` states store the reference to the prompt text in object storage.

**Displayed flow type:** the first special state present, in this priority order: `prompt` → `diagnostic` → `quiz_funnel` → if none is present, `default`.

### 6.7 Diagnostic

`{id, questionnaire_id, tiers, recommendations, action_plan}`. Accessed by `questionnaire_id`.

### 6.8 Prompt

`{id, questionnaire_id, customer_id, s3_path (reference to the text in object storage), outcome?, order = 0}`. Accessed by `questionnaire_id`.

### 6.9 Session (response)

Full copy of the questionnaire plus these fields:

| Field | Notes |
|---|---|
| `session_id` | UUIDv4, PK. Accessed by `questionnaire_id` |
| `started_at`, `ended_at` | |
| `flow_id` | |
| `status` | State machine: `filling` → `filled_out` → `processing` → `completed`. Legacy values: `in_progress` (= filling), `submitted` (= filled_out) |
| `user_data` | `{name, email, phone}` if captured |
| `assignations_id`, `organization_user_id` | Only in assignation sessions |
| `assignation_type` | `follow_up` \| null |
| `attempt` | int, default 1 |
| Per question: `review` | `{status: approved \| rejected, comment?, reviewed_at, attempt}` |
| Per question: `improvement_message`, `flagged_answer` | Result of the AI evaluation |

### 6.10 SessionResults

`{session_id (PK), products?, ai_team_profile?, diagnostic?}`.

**DiagnosticResult:**

```
{ type: "diagnostic",
  score: {value, max},
  categories: [{id, name, score, max}],
  tiers: [...visible ones only],
  recommendations: [{tier_id, recommendation}],
  action_plan: [{tier_id, action}] }
```

### 6.11 Product

`{product_id, customer_id, name, description (HTML), price (decimal, leniently parsed from text), image_url?, product_url?, source_url?, questionnaire_id?, created_at, updated_at}`.

Accessed by `customer_id`, `source_url` and `questionnaire_id`.

### 6.12 Organization

`{organization_id (UUIDv4), customer_id, name (1–120), domain_email? (unique across the whole system, lowercase), description? (≤ 1000), active = true, timestamps}`.

Accessed by `domain_email` and by `customer_id`.

### 6.13 OrganizationUser (member)

| Field | Rule |
|---|---|
| `organization_user_id` | UUIDv4 |
| `organization_id` | Accessed by this field |
| `name` | 1–200. **Normalized:** lowercase, no accents, single spaces |
| `email` | Lowercase. Accessed by email |
| `phone` | Digits only with optional leading `+`, ≤ 50. Accessed by phone |
| `role`, `area` | Free text ≤ 120 |
| timestamps | |

Each member needs at least an email or a phone. The email is unique within the organization.

### 6.14 Assignation

| Field | Rule |
|---|---|
| `assignations_id` | UUIDv4 |
| `customer_id`, `organization_id`, `questionnaire_id` | Accessed by `questionnaire_id`, `customer_id` and `project_id` |
| `name` | 1–200 |
| `description` | ≤ 2000. Internal note; the respondent never sees it |
| `max_follow_ups` | ≥ 0 (the console sends 2) |
| `active` | Default true |
| `type` | `default` \| `follow_up`. **Immutable** |
| `due_date` | `YYYY-MM-DD`, follow-up only |
| `audience` | `{type: all \| members \| area \| role, values[] ≤ 500}`. `all` has no values; the others have at least one. `members` = member UUIDs. `area` and `role` are compared ignoring case and accents |
| `questions` | ≥ 1. This is the **registration slide** (respondent login), not the questionnaire |
| *Server-managed:* `project_id?`, `shared_session_id?`, `attempts[{number ≥ 1, session_id, created_at}]`, `last_reminder_sent_at?` | |
| timestamps | |

**Rule:** a questionnaire can be assigned to **any number of organizations** (and more than once to the same one), always as it is — never copied. Each assignation keeps its own sessions and answers (`questionnaire_session.assignations_id`); the answers of an organization are read from its assignation.

### 6.15 AssignationAnswer

Key `(assignations_id, organization_user_id)` → `{session_id}`. Written when the member submits the first stage.

### 6.16 Project

`{project_id, customer_id, organization_id (immutable), name (1–200), description? (≤ 2000), due_date (required on creation; may be null in legacy rows), timestamps}`.

An assignation belongs to at most one project.

### 6.17 Job

| Field | Values |
|---|---|
| `job_id` | `"job_"` + time-sortable ID (ULID-like) |
| `job_type` | `styles`, `profile_customization` (legacy), `answer_evaluation`, `linkedin_questionnaire`, `prompt_questionnaire`, `create_quiz_funnel`, `scrape_products`, `process_completed_session`, `chat`, `chat-questionnaire-created`, `chat-questionnaire-drafted`, `chat-questionnaire-approved` |
| `status` | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `CANCELLED` |
| `payload` | Internal. **Never returned** |
| `result` | On failure: `{error:{type, message}}` |
| `stage` | Progress sub-stage (e.g. styles: `reading_website` → `designing_styles` → `saving`) |
| timestamps | |

Accessed by `job_type` and by `status`.

### 6.18 Styles

Key `customer_id` → `{website?, styles}`. `styles` (camelCase):

```
logoUrl
font {family, url}
body {background, color}
h1 | h2 | h3 | p {fontSize, fontWeight, color}
label {fontSize, fontWeight, color, textTransform, letterSpacing}
a {color}
button.primary | button.secondary {background, backgroundHover, color, border, padding, fontSize, fontWeight, borderRadius}
input {background, color, placeholderColor, border, borderFocus, borderRadius, padding, fontSize}
```

There is a set of **default styles** defined by the platform.

### 6.19 QuestionnaireDashboard

`{questionnaire_id (PK), customer_id, type, charts[{id, chart_type, title, question_ids[], order}], created_at}`. Created only once.

- `type`: `satisfaction`, `knowledge`, `profiling`, `recommendations`, `eligibility`, `opinion`.
- `chart_type`: `kpi`, `gauge`, `line`, `donut`, `bar`, `horizontal_bar`, `stacked_bar`, `treemap`, `histogram`, `boxplot`, `ranking_avg`, `heatmap`, `tier_distribution`.

### 6.20 ApiKey

`{id = SHA-256 of the key, customer_id, name (1–100), status: active | revoked, created_at, expires_at?, last_used_at?}`. **The plaintext key is never stored.**

### 6.21 Webhook

`{id (UUID), customer_id, url (https only), event_type: questionnaire.completed, method: POST, timestamps}`.

### 6.22 Video (documentation)

`{id (UUID), title (1–200), description (≤ 2000), url (1–500, YouTube), language: es | en, category (≤ 100), order ≥ 0, duration_minutes ≥ 0, timestamps}`.

### 6.23 AppSetting

`{key, value}`. Example: default LLM model for generating questionnaires.

### 6.24 SystemPrompt

**Versioned** Markdown text per key (12 keys, see [Appendix D](#appendix-d--system-prompt-keys)). The version history is the edit history: `{key, text, version_id, updated_at, updated_by}`.

### 6.25 Data that is NOT stored locally

- **Coupons and promotion codes:** live only in the payment gateway.
- **Payments:** live in the gateway and in the usage/analytics service.
- **Usage counters:** live in the usage/analytics service (§13.8).
- There are legacy tables (`session`, `questionnaire-analytics`) that the migration may discard after verifying they hold no live data.

---

## 7. Business rules

### 7.1 Plan gate

**Applicable plan.** The account MUST have a plan, the plan row MUST exist, and today's date (UTC) MUST fall within `from_at..to_at` inclusive. Otherwise the request is rejected with:

| Condition | Reason |
|---|---|
| The account has no plan | `NO_PLAN` |
| Today is outside the validity window | `PLAN_INACTIVE` |
| The plan does not exist in the catalog | `PLAN_NOT_FOUND` |

**Limits:**

- Feature missing from the plan → `FEATURE_NOT_IN_PLAN`. `limit < 0` → unlimited. `limit ≥ 0` → rejected when `used >= limit` (`FEATURE_LIMIT_REACHED`).
- `max_questionnaires` limits the **sum** of the counters `regular + diagnostic + quiz-funnel + chain + chat` → `QUESTIONNAIRE_LIMIT_REACHED`.
- `responses` is limited by `max_responses`, not by the feature list → `RESPONSE_LIMIT_REACHED`.
- Rejection response: `429 PLAN_LIMIT_REACHED` with `details: {reason, feature}`.
- If the usage service does not respond: `503 USAGE_UNAVAILABLE`.

**Two kinds of gate:**

| Kind | What it checks | Consumes quota | Can return 503 |
|---|---|---|---|
| **Capacity** | Limit against usage counters | Yes (the action counts) | Yes |
| **Feature** | Only that the plan includes the feature (limit present and ≠ 0) | No | No |

- `Admin` always passes the gate.
- **No gate applies to:** admin endpoints, deletions, child stages of a chain, generation from LinkedIn, checkout, onboarding, changing the account language.
- Counters are **per monthly period** (the plan window).

### 7.2 What counts as usage

| Action | Feature incremented |
|---|---|
| Create a questionnaire (POST, copy, quiz funnel, chat, LinkedIn) | By its type: `regular`, `diagnostic`, `quiz-funnel`, `chain`, `chat`. LinkedIn counts as `diagnostic` |
| Complete the **last stage** of a chain (or a simple questionnaire) | `responses` (one per respondent) |
| Styles job completed (if it fails, it does not count) | `styles` |
| Query analytics or dashboard data | `analytics` |
| Generate a dashboard (once per questionnaire) | `dashboards` |
| Each call to the external API | `api` |
| Each successful webhook delivery | `webhook` |
| Edit account settings (except language) | `profile` |
| Create a team user | `users` |
| Create or delete an organization | `organizations` |
| Create or delete an assignation | `assignations` |

### 7.3 Sign-up and free trial

- **Native sign-up or first Google login:** creates the account with a new `customer_id`, the root user in `Customer-Admin`, `onboarding_completed = false`, and the `starter` plan from today until today + 1 calendar month (the day is clamped to the month's length).
- **Trial in the payment gateway:** only once per account, and only if the plan has `trial_days > 0`. A card is always required. If there is no payment method when the trial ends, the subscription is canceled. The first time a subscription enters a trial, `stripe_trial_used_at` is set.

### 7.4 Billing

**Checkout:**

- It is a subscription with a single line item on the payment page hosted by the gateway.
- Metadata: `{customer_id, plan_id, billing_interval}`.
- Reuses the gateway customer if one already exists. Promotion codes are always allowed.
- Return URLs: `{ADMIN_URL}/profile/plans?checkout=success|cancel`.

**Classifying a plan change** (compared against the plan the gateway is actually charging today). The first matching rule applies:

1. The target has no price → **downgrade**.
2. From yearly to monthly → always **downgrade**.
3. The current plan has no price, or the target costs more → **upgrade**.
4. The target costs less → **downgrade**.
5. Same price, from monthly to yearly → **upgrade**. Any other same-price case → **downgrade**.

**Effect:**

- **Upgrade:** immediate price change; the prorated difference is charged right away. If that charge fails, the change fails.
- **Downgrade:** the cheaper plan is scheduled as the next phase for one cycle. The schedule is then released and the subscription renews on the new plan.
- Upgrade and cancellation first release any pending schedule.
- **Cancel** = cancel at period end. **Resume** = remove that cancellation (idempotent).
- **Revert** = undo a scheduled downgrade.

**Payment webhook events** (all idempotent):

| Event | Effect |
|---|---|
| `checkout.session.completed` | Assign the plan with the subscription's period; `SubscriptionCreated` event |
| `invoice.paid` | Reassign the period; record the payment in analytics (deduplicated by event id; if it fails, respond 500 so the gateway retries). If `billing_reason = subscription_cycle`, `SubscriptionRenewed` event |
| `customer.subscription.updated` | Reassign. If the plan changed: `PlanChanged` event; if the new plan is more expensive, **reset all usage counters to 0** |
| `customer.subscription.deleted` | `SubscriptionCancelled` event; delete the stored subscription id. The paid window expires on its own |
| `customer.subscription.trial_will_end` | `TrialWillEnd` event |
| `invoice.payment_failed` | `PaymentFailed` event |

**Coupons:**

- The gateway only restricts coupons by product, so billing intervals are controlled per product. A plan whose monthly and yearly prices share a product cannot accept a coupon limited to a single interval (`COUPON_INTERVAL_NEEDS_OWN_PRODUCT`).
- Without `plan_ids`, the coupon applies to all purchasable plans.
- If creating the promotion code fails, the coupon is deleted.

### 7.5 Creating and editing questionnaires and flows

**Flow validation:**

- Exactly **one** `questionnaire` state.
- Unique `state_id`s; `next` must point to an existing state.
- At most **10** `prompt` states.
- A `diagnostic` state needs tiers, unless it is the last state of a prompt chain (no `next`). In that case the LLM generates the tiers.

**The diagnostic MUST be scorable:**

- at least one tier; unique tier ids; `min ≤ max`;
- recommendations and actions reference existing tiers;
- maximum score > 0;
- the sorted tiers start at 0, end exactly at the maximum, and are **contiguous**: `next.min = previous.max + 1`.

**Maximum score of a question:** sum over its controls. A `checkbox` or `ranking` control contributes the **sum** of its option values; any other control contributes the **highest** value.

**Duplicate option values** within a control are fixed automatically: a repeated number is bumped above the highest one in use; a repeated text gets a `-2`, `-3`… suffix.

**Locking by responses:**

- A questionnaire with any response (value or skip in any session) **cannot be edited**: `409 QUESTIONNAIRE_ALREADY_ANSWERED`. The UI offers to create a copy.
- On edit, the diagnostic and prompts are rebuilt and the flow keeps its id.

**Other rules:**

- A prompt's `outcome` is the next terminal state in the flow (`diagnostic`, `quiz_funnel`, or `result`).
- **Copy:**
  - Title `"(copia) X"`; if it already exists, `"(copia - N) X"` ("(copy) X").
  - Slug `<base>-copia[-N]`.
  - The diagnostic and prompts are copied.
  - [DEBT] The prefixes are hard-coded in Spanish.

### 7.6 Cleaning up LLM output (always when generating questions)

1. Keep only the first control of each question.
2. Remove questions without a control.
3. Clear any runtime fields the LLM filled in.
4. Renumber `order` from 0.
5. Deduplicate option values (§7.5).
6. Assign a new UUID to each question and each control name.
7. If no question remains, fail.

### 7.7 Processing on session submission

1. Set `ended_at` and `status = filled_out`.
2. Compute the result according to the type:
   - **Diagnostic:**
     - Only questions with a `category` and a maximum > 0 count.
     - Category score = sum of the selected values. Total = sum of categories, rounded *half-up*.
     - Tier = the band that contains the total.
     - A diagnostic at the end of a chain scores **all stages together**, with question ids prefixed by stage.
   - **E-commerce / quiz funnel** (asynchronous, returns a job):
     - The LLM picks product ids from the stored catalog. It looks up first by questionnaire and then by account.
     - Empty catalog → no products and no LLM call.
     - The results are stored.
   - **AI Team Profile:**
     - Fixed scoring over 8 radio questions mapped to 5 dimensions (D1–D5).
     - 5 stages, from "No usage" to "Transformation".
     - Radar, reference percentiles, and a fixed report structure.
   - **Samurai8** [CLIENT-SPECIFIC] (a fixed questionnaire_id or the `mateo` customer):
     - 5 dimensions; a 3-question dimension is scaled `round(sum × 6 / 9)`.
     - Tiers: 0–5 Explorador, 6–10 Practicante, 11–15 Estratega, 16–20 Arquitecto, 21–25 Constructor, 26–30 Maestro.
     - Returns strengths (dimensions ≥ 4), weakest dimension, quick wins, roadmap, and CTA.
   - **Livingood** [CLIENT-SPECIFIC] (`livingood` customer): four scores out of 100 (Fat Loss, Gut Health, Hormone Balance, Energy & Vitality), plus profile and action plan.
   - **Default:** `{type: "default"}`.
3. Then, **in all cases**:
   - `status = completed`;
   - publish the `questionnaire.completed` webhook event;
   - emit the `QuestionnaireSessionCompleted` event;
   - count 1 response (only on the final stage of a chain).
4. The response includes the flow's `cta`, `layout`, and `result_copy` when they exist.

### 7.8 Prompt chains

- Each stage is generated from: the previous stage's answers, the account owner's prompt (**treated as untrusted data**), and the platform rules.
- Files attached to answers: up to **5** per stage and **32 MB** in total.

  | Type | Limit |
  |---|---|
  | pdf | ≤ 32 MB |
  | png, jpg, jpeg, webp, gif | ≤ 20 MB |
  | txt, md, csv, json | ≤ 256 KB and ≤ 100,000 characters |

- Up to 3 generation attempts. A retry happens if the result is empty or if any tier comes without recommendations.
- Tier bands are computed on the server.
- Generated stages are stored as child questionnaires (`parent` = root, `origin_session_id` = session).

### 7.9 AI evaluation of answers (follow-ups)

- Applies to `text` or `audio` questions with `max_followups > 0` and a non-empty answer.
- The LLM grades each acceptance criterion from 0 to 50.
- **Passes** if the answer is related to the question **and** the average is ≥ 30.
- If it does not pass: `max_followups` decreases by 1 (minimum 0) and `improvement_message` is returned. The result is `{type:"evaluation", status:"not_sense", question}`. If it passes: `status:"success"`.
- The respondent app **fails open**: if the evaluation fails or times out, the respondent moves on.

### 7.10 Dashboards

- The LLM picks the dashboard type and the charts **only once**; the choice is stored permanently.
- **Cleaning up the LLM's selection:** charts not allowed for the type, unknown or duplicate questions, incompatible control types, and out-of-range question counts are discarded.
- `heatmap` and `stacked_bar` require all their questions to share the same answer scale.
- At most **10** charts. Each type appears at most **2** times; the excess ones are switched to a sibling type of the same family.
- If no chart survives: `502 DASHBOARD_GENERATION_FAILED` and nothing is stored.
- If no dashboard exists and the plan has no capacity for `dashboards`: respond 200 with `locked: {feature: "dashboards", reason}` and no charts.
- The visualization formulas are in §10.9.

### 7.11 Assignations

**Progress:**

- Default: `{completed: audience members with a response, total: audience size, unit: "respondents"}`.
- Follow-up: `{completed: answerable questions answered or skipped, total, unit: "questions"}`.

**Follow-up complete** when its shared session has `ended_at`. From then on, creating sessions and saving return `409 FOLLOW_UP_COMPLETED`.

**`current_question`** = the first answerable question that has not been answered or skipped.

**Respondent login** (`POST /assignations/{id}/sessions`), in this order:

1. `assignations` feature gate on the owner's plan.
2. In follow-up, `409 FOLLOW_UP_COMPLETED` is checked before looking up the member.
3. `400 MISSING_IDENTIFIER` if there is neither phone nor email.
4. Look up the member **only by phone and/or email, never by name**. Every identifier sent must match.
5. Lookup errors: `403 USER_NOT_FOUND` or `403 NOT_IN_AUDIENCE`.
6. `responses` capacity gate.
7. Issue a respondent session token that binds `assignations_id`, `organization_user_id`, and `session_id`.

**Shared session (follow-up):** all members write to the same session.

- On save, an empty incoming value **does not overwrite** an existing one (an answer wins over a skip).
- Reviews and locked values are always preserved.

**Review state** (`review_status`): `not_ready`, `in_review`, `changes_requested`, `approved`.

- A locked question counts as approved.
- A review only counts for the attempt in which it was made.

**Review** (`PUT /assignations/{id}/reviews/{question_id}`): only in follow-up, only if it is complete, not on locked questions or message slides.

**Retry ("enviar a corrección" — send for correction):**

1. Create a new session (attempt n+1).
2. Copy the approved answers and **lock them**; clear the rejected ones while keeping their review.
3. Append the attempt to `attempts`, move `shared_session_id` to the new session, and clear `last_reminder_sent_at`.
4. Send an email to the recipients. If the email fails, the attempt already exists (`502 RETRY_EMAIL_NOT_SENT`).

**Other rules:**

- `type` cannot be changed.
- The organization cannot be changed if the assignation is in a project (`ASSIGNATION_IN_PROJECT`).
- If the organization is changed with a `members` audience, the audience must be reset.

### 7.12 Projects

**State of an assignation within the project** (first matching rule):

1. Completed → `review` if `in_review`, `correction` if `changes_requested`, otherwise `approved`.
2. Overdue → `overdue`. "Today" is computed in **UTC−12**, so the due date itself never counts as overdue in any time zone.
3. Changes requested or attempt > 1 → `correction`.
4. Has some progress → `progress`.
5. Otherwise → `pending`.

**Project state:** the first one found among its assignations, in this order: `review`, `overdue`, `correction`, `progress`, `pending`, `approved`. No assignations → `empty`.

**`progress_percent`:** rounded average of completed/total of each assignation (a complete follow-up counts as 100%).

**The response also includes** `completed_assignations`, `approved_assignations`, and `total_assignations`.

**Rules:**

- Only **follow-up** assignations from the **same organization**.
- An assignation belongs to only one project.
- Deleting a project unlinks its assignations without deleting them.

### 7.13 Reminders

They fire every day at **13:00 UTC** and also via a manual button.

- **Which assignations:** active follow-ups, not completed, not reminded today (UTC).
- **Respondents:** emails of the audience members, deduplicated, with a link to `/a/{id}`.
- **Account root users:** status email with progress, percentage, due date, how many were reminded, and a link to `{ADMIN_URL}/assignations/{id}`. An owner who is also a member only receives the reminder.
- **Subject** based on the remaining days: no date / due today / due in N days / N days overdue.
- **Language:** the account's, `en` or `es` (default `es`).
- The day is marked only if **both** emails are sent successfully. A failure is logged and counts as skipped.
- **Manual send:**

  | Condition | Error |
  |---|---|
  | The assignation is not a follow-up | `400 NOT_A_FOLLOW_UP` |
  | The follow-up is already complete | `409 FOLLOW_UP_COMPLETED` |
  | There are no recipients | `422 NO_RECIPIENTS` |
  | Sending fails | `502 REMINDER_NOT_SENT` |

  On success, returns `{recipients: n}`.

### 7.14 Outgoing webhooks

**Event:** `questionnaire.completed` with this body:

```
{ customer_id, event_type, questionnaire_id,
  data: { id, answers: [{title, value, min?, max?}] } }
```

**`value` format by control type:**

| Type | Value |
|---|---|
| audio | List of transcriptions |
| text | String (lists are joined with `", "`) |
| file | List of storage keys |
| range | Number, with `min` and `max` |
| Selection | The option labels when the values are numeric. A list for checkbox and ranking |

`message` slides are omitted.

**Delivery:**

- The `webhook` capacity is checked first. If rejected, nothing is delivered.
- `POST` to each subscribed URL with the headers `X-Signature: sha256=<hex HMAC-SHA256 of the body>` and `X-Event-Type`.
- Timeouts: 3 s connect and 5 s read. **No retries.**

### 7.15 Onboarding

- The console sets the flag explicitly.
- For older accounts with `onboarding_completed = null`, the value is derived from "does it have any questionnaire?" and stored.
- Callers without an account row receive `true`.

### 7.16 Brand styles (job)

- **If the website changed:**
  1. A headless browser extracts the site's CSS.
  2. The LLM designs a set of styles.
  3. Text contrast ≥ 3:1 is enforced, falling back to white/black or muted variants.
  4. The logo is chosen from the candidates found on the page.
  5. The partial styles in the request are ignored.
- **If it did not change:** the partial styles are deep-merged over the existing ones (or the defaults).
- Visible job stages: `reading_website` → `designing_styles` → `saving`.

### 7.17 Product scraping and quiz funnel creation

**Scraping:**

- 1–30 products per run. Fails if none are found.
- Persists nothing on its own.

**Quiz funnel creation:**

1. The store URL is reduced to its origin. If none is sent, the connected store from the e-commerce platform is used.
2. The products the merchant left on screen are stored, replacing the stored catalog for that origin.
3. The LLM generates the questionnaire (type `ecommerce`) in the account's language. Variants: `experience` or `profiling`.
4. A 2-state flow is created (`questionnaire` → `quiz_funnel`) with a random lowercase slug.

**Sync with the e-commerce platform:**

- Refreshes the token if there is a refresh token.
- **Replaces all** of the account's products.
- Only the first variant's price is used.

### 7.18 Generation from LinkedIn

- Receives a LinkedIn profile URL and a language.
- Extracts the profile and generates a diagnostic questionnaire.
- [CLIENT-SPECIFIC] The questionnaire is owned by a fixed account (`XhEFtqTt`).

### 7.19 Chat assistant (AI)

The conversation is stored by the **client**; the backend keeps no chat state.

**`create` mode.** Builds a questionnaire in phases: `basics` → `questions` → `ending` → `review`.

- **Required basics:** title, type (`regular` / `diagnostic` / `chain`), topic, landing, disclaimer, and data capture. They are accepted only if they come from the user's own words, and then they need explicit confirmation.
- **Creation:** nothing is created until the user approves the review of the complete draft. Creating and editing use the same flow-saving logic.
- **Limits:** up to 100 questions; 8 tool rounds per turn; 90 s per turn.
- **Actions on the account:**
  - Reads run immediately.
  - Writes are queued (maximum 50) and only run when the user says yes.
  - **Deliberately excluded:** creating API keys and inviting users.
- **Quick replies** (e.g., "Sí"/"No", "Ver 5 más" — "Yes"/"No", "See 5 more"). Lists are shown as tables of 5 or 10 rows.
- A questionnaire with responses is not edited; the chat offers to duplicate it.

**`draft` mode.** Drafts a simple questionnaire (up to 100 source questions) without saving anything. It ends as `chat-questionnaire-drafted` or `chat-questionnaire-approved`.

**Account tools available to the chat:**

| Area | Tools |
|---|---|
| Questionnaires | `list_questionnaires`, `get_questionnaire`, `list_questionnaire_answers`, `get_questionnaire_analytics`, `set_questionnaire_active`, `copy_questionnaire` |
| Organizations | Organization CRUD |
| Assignations | Assignation CRUD, respondents, `send_follow_up_reminder` |
| Projects | Project CRUD |
| Plan and billing | `get_plan_and_usage`, `list_plans`, `start_checkout`, `open_billing_portal`, `change_plan`, `revert_plan_change`, `cancel_subscription`, `resume_subscription` |
| Account | `get_profile`, `get_account_settings`, `update_account_language`, `update_account_settings`, `extract_brand_styles`, `list_team_users` |
| Integrations | `list_api_keys`, `revoke_api_key`, webhook CRUD, `get_shopify_connection` |
| Documentation | `list_videos` |

**Job result:**

- `type`: `chat`, `chat-questionnaire-created`, `chat-questionnaire-drafted`, or `chat-questionnaire-approved`.
- Optional fields: `draft`, `quick_replies`, `actions`. An action may carry a `job_id` of a background job, such as the styles job.

### 7.20 Editable system prompts

- 12 keys ([Appendix D](#appendix-d--system-prompt-keys)) stored as versioned Markdown.
- Some keys require placeholders: `{admin_instructions}`, `{base_rules}`, `{dashboard_catalog}`. If missing: `400 INVALID_PLACEHOLDERS` with `details.missing`.
- 5-minute cache. If storage fails, the platform's default text is used.

### 7.21 Emails

| Email | Trigger | Language |
|---|---|---|
| Password recovery | Identity provider. Code + link `{ADMIN_URL}/reset-password?code=####` | — |
| Reminder to respondents | §7.13 | Account |
| Status for the root | §7.13 | Account |
| Retry / correction | §7.11 | Account |
| Sales lead | `POST /contact`. Subject `"[Ventas] {name} está interesado en el plan {plan}"` ("[Sales] {name} is interested in the {plan} plan"). [CLIENT-SPECIFIC] 3 fixed recipients | es |
| Welcome | es/en templates exist with a BCC to support, **but nothing sends them** | — |

All are sent from `SUPPORT_EMAIL` with HTML templates in es and en.

---

## 8. API contract

### 8.1 Conventions

- **Prefix:** `/api/v1`. UTF-8 JSON.
- **Success:** `{"message": string, "data": ...}`. Some older routes return JSON without an envelope ("bare"); this is noted in each case.
- **Error:** `{"error": {"code", "message", "details?"}}`.
- **204:** empty body.
- **User authentication:** `Authorization: Bearer <id_token from the identity provider>`. The token carries `customer_id`, groups, email, name, and `root`.
- **Common errors:**

  | Code | Case |
  |---|---|
  | `400 INVALID_JSON` | Body that is not valid JSON |
  | `400 VALIDATION_ERROR` | Messages flattened as `"campo: msg; ..."` |
  | `400 INVALID_REQUEST` | A path parameter is missing |
  | `400 INVALID_UUID` | Malformed id |
  | `401 UNAUTHORIZED` | No valid authentication |
  | `403 FORBIDDEN` | "Admin privileges are required." |

- **Pagination**, three styles:
  1. **Numbered** with an object `{page, page_size, total_items, total_pages, has_next, has_previous}`.
  2. **Questionnaire listing:** `{items, page, page_size, total, total_pages}`.
  3. **Opaque cursor** (base64) with `next_cursor`: responses and respondents.
- **Text search:** word-based, case- and accent-insensitive; all words must appear.
- **CORS:** open (`*`).
- **Access legend:** **P** = public · **A** = authenticated · **AG** = authenticated and in `ADMIN_GROUPS` · **Own** = the resource must belong to the caller's account (except `Admin`) · **Cap(x)** = capacity gate · **Feat(x)** = feature gate.

### 8.2 Authentication and account

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `POST /register` | P | `email` (normalized to lowercase), `password` (≥ 8), `name` (1–50), `language` (default `es-CO`), `source` (default `default`) | `{customer_id, user:{email, name, root:true, role:"Customer-Admin"}}`. Returns no tokens: the client then signs in with the identity provider. `409 EMAIL_ALREADY_EXISTS`. `UserRootRegistered` event |
| `POST /login` | P | `{email, password}` | [DEBT] Legacy login against a single configured user. Returns `{token}` with a 730 h expiration. Wrong credentials return 500. Only used by a QA access |
| `POST /password-recovery` | P | `email` | Always 200 ("If the account exists, a recovery code is on its way"), so as not to reveal which accounts exist. `429 TOO_MANY_ATTEMPTS` |
| `POST /password-recovery/confirm` | P | `email`, `code` (1–64), `password` (≥ 8) | `400 INVALID_RESET_CODE` (also if the user does not exist), `400 EXPIRED_RESET_CODE`, `400 INVALID_PASSWORD`, `429 TOO_MANY_ATTEMPTS` |
| `POST /users` | AG, Cap(users) | `email`, `password` (≥ 8, set as permanent), `name` (1–50), `role` ∈ assignable roles | `{email, name, root:false, role, customer_id}`. `400 INVALID_ROLE`, `409 EMAIL_ALREADY_EXISTS`. `UserCreated` event |
| `GET /users` | A | — | `{users:[{email, name, root, role, customer_id}]}`. Root first, then by name |
| `GET /profile` | A | — | `{customer:{customer_id, name, email, language, logo_url, website, styles}}` |
| `GET /customer/onboarding` | A | — | `{onboarding_completed}` (§7.15). No gate |
| `PATCH /customer/onboarding` | A | `{completed: bool}` (no extra fields allowed) | `404 CUSTOMER_NOT_FOUND` |

Not available: email invitation, user editing, user deletion, MFA.

### 8.3 Settings, usage, plans, and billing

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /customer/{customer_id}/settings` | P | — | `CustomerSettings` (`max_files` default 10). `404 CUSTOMER_NOT_FOUND` |
| `PATCH /customer/{customer_id}/settings` | AG; if not `Admin`, only on their own account; Cap(profile) if anything other than `language` changes | At least one field; no extra fields allowed. `language` is never null. Tracking IDs: empty or null clears them. `max_files`: strict integer 1–20; null resets it to the default | `ProfileEdited` event |
| `GET /customer/usage` | A | — | `{customer_plan, plan (without features), usage:{questionnaires_used, from_at, to_at} \| null, plan_active, features:{feature_id:{allowed, reason, limit, used}}}` (all features in the catalog) |
| `GET /plans` | A | — | `{current_plan_id, current_billing_interval, active_until, scheduled_plan_id, scheduled_billing_interval, cancel_at_period_end, trial_eligible, trial_end, discount, plans[]}`. Each plan: `{id, plan_name, plan_description, price_amount, currency, purchasable, yearly_price_amount, yearly_purchasable, max_questionnaires, max_responses, trial_days, features:[{feature_id, feature_name, limit}]}`. Priced plans first (by price), then by name. The schedule and cancellation fields are read live from the gateway; if that fails, "nothing pending" is assumed |
| `POST /checkout/session` | A (no gate) | `plan_id`, `billing_interval` (`month` default \| `year`) | `{checkout_url}`. `404 PLAN_NOT_FOUND`, `400 PLAN_NOT_PURCHASABLE`, `502 STRIPE_UNAVAILABLE` |
| `POST /checkout/plan-change` | A | Same | `{type:"changed", plan_id, billing_interval, change:"upgrade"\|"downgrade", effective_at}` or `{type:"checkout", plan_id, billing_interval, checkout_url}` if there is no subscription. `400 SAME_PLAN`, `404`, `400 PLAN_NOT_PURCHASABLE`, `502` |
| `POST /checkout/plan-change/revert` | A | — | `{subscription_id, plan_id, renews_at}`. `400 NO_SUBSCRIPTION`, `400 NO_SCHEDULED_CHANGE` |
| `POST /checkout/cancel` | A | — | `{subscription_id, plan_id, active_until}`. `400 NO_SUBSCRIPTION` |
| `POST /checkout/resume` | A | — | `{subscription_id, plan_id, renews_at}`. Idempotent. `400 NO_SUBSCRIPTION`; 502 if the period has already expired |
| `POST /checkout/portal` | A | — | `{portal_url}` of the gateway's billing portal, returning to `{ADMIN_URL}/profile/plans`. `400 NO_STRIPE_CUSTOMER` |
| `POST /checkout/webhook` | P + signature | Gateway event | Plain text: 401 if the signature is bad, 500 to force a retry, 200 OK |
| `POST /contact` | A | `type` (`plan`), `plan_id` (required if type = plan), `email`, `phone` (1–50) | Sends the sales lead. `404 PLAN_NOT_FOUND`, `502 EMAIL_UNAVAILABLE` |

### 8.4 Questionnaires, flows, and sessions

**`GET /questionnaire`** · A

Query parameters:

| Parameter | Values | Error |
|---|---|---|
| `type` | `default`, `quiz_funnel`, `diagnostic`, `process_mapping` | `INVALID_TYPE` |
| `sort_by` | `created_at`, `updated_at` | `INVALID_SORT` |
| `order` | `asc`, `desc` (default `desc`) | `INVALID_ORDER` |
| `is_active` | `true`, `false`, `1`, `0` | `INVALID_IS_ACTIVE` |
| `parent` | `ROOT` (default) or an id | — |
| `page` | ≥ 1 (default 1) | — |
| `page_size` | ≤ 100 (default 20) | — |
| `search` | Text, trimmed to 200 characters. Searches the title | — |

- Response: `{items, page, page_size, total, total_pages}`.
- Each item: `questionnaire_id, customer_id, parent, origin_session_id, title, description, created_at, updated_at, is_active, on_completed, status, landing_page, capture_user_data, question_count, is_chain, slug, type`.
- `Admin` sees all accounts.

**Creation, editing, and management:**

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `POST /questionnaire` | AG, Cap(by type: `regular` / `diagnostic` / `chain` / `quiz-funnel`) | A **flow**: `{slug, states[], cta, layout?, result_copy?}`. The `questionnaire` state contains the full questionnaire | `{questionnaire_id}`. `409 SLUG_ALREADY_IN_USE`. `QuestionnaireCreated` event |
| `PUT /questionnaire` | AG, Own | Same, plus `questionnaire_id` | `data:null`. `404`, `403`, `409 QUESTIONNAIRE_ALREADY_ANSWERED`, `409 SLUG_ALREADY_IN_USE` |
| `GET /questionnaire/{id}` | A, Own | — | Full questionnaire, with the diagnostic tiers merged into `on_completed`. `404 QUESTIONNAIRE_NOT_FOUND`, `403` |
| `PATCH /questionnaire/{id}` | AG, Own | Exactly `{is_active: strict bool}` | The updated row |
| `POST /questionnaire/{id}/copy` | AG, Own, Cap(type of the original) | — | The new questionnaire (§7.5). `QuestionnaireCreated` event |
| `GET /questionnaire/{id}/prompts` | AG, Own | — | `{prompts:[{..., text}]}` in order, with the text loaded |

**Responses and analytics:**

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /questionnaire/{id}/answers` | A, Own | `status`: `completed` (default), `filling`, `filled_out`, `processing`, `all` (legacy aliases `in_progress`, `submitted`). `limit`: 20, 50, or 100 (default 100). `cursor`. `include_chain=true` | `{items: sessions enriched with member data, next_cursor}` |
| `GET /questionnaire/{id}/analytics` | A, Own, Cap(analytics) | — | `{questionnaire_id, questions_analytics, total_sessions, sessions_completed}`. `AnalyticsFetched` event |
| `GET /questionnaire/{id}/dashboard` | A, Own, Cap(analytics) | — | `{questionnaire_id, customer_id, type, charts[], created_at, questions:[{id, title, type, options:[{label, value}], min, max}], locked}`. `502 DASHBOARD_GENERATION_FAILED`. `DashboardGenerated` event |
| `GET /questionnaire/{id}/dashboard/data` | A, Own, Cap(analytics) | — | See §10.9. `502 ANALYTICS_UNAVAILABLE` |

**Flows and generation:**

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /questionnaire/find?url=` | P | Page URL | Bare: `{questionnaire_url: {FRONTEND_URL}/f/{flowId}}`. Finds the most recent flow whose store URL matches (normalized), falling back to the site's origin. 400 if `url` is missing; 404 if there is no match. Used by the store widget |
| `GET /flow/{identifier}` | P | flow id, slug, or questionnaire id | `{id, slug, detail, states, customer_id, questionnaire_id, source_url, cta, layout, result_copy, created_at, updated_at}`. `404 FLOW_NOT_FOUND` if the questionnaire is assigned to an organization and was looked up by slug or flow id |
| `POST /questionnaire/quiz-funnel` | AG, Cap(quiz-funnel) | `type` (`experience` \| `profiling`), `source_url?`, `products?` | `202 {job}`. Result: `{type:"create_quiz_funnel", flow, questionnaire_url}` |
| `POST /questionnaire/linkedin` | P | `linkedin_url` (`https?://([a-z]{2,3}\.)?(www\.)?linkedin.com/in/...`), `language` (`en`/`es`; any other value becomes `es`) | `202 {job}`. Result: `{type:"linkedin_questionnaire", questionnaire_id}` |
| `POST /questionnaire/prompt` | P | `questionnaire_id` (the parent), `answers: [{question, answer}]`, `session_id?` | `202 {job}`. Result: `{type:"prompt_questionnaire", questionnaire_id}` |

**Respondent sessions:**

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `POST /questionnaire/{questionnaire_id}/session` | P (optional respondent Bearer). Cap(responses) only on root questionnaires | — | Bare: the session (copy of the questionnaire with `session_id`, `started_at`, `flow_id`, `status:"filling"`). `on_completed` is reduced to `{type}` so as not to expose the scoring configuration. 404 if it is assigned to an organization, does not exist, or is inactive. `QuestionnaireSessionCreated` event |
| `PUT /questionnaire/session` | P (Bearer if it belongs to an assignation) | The full session | Saves progress. In follow-up, merges per §7.11. `409 FOLLOW_UP_COMPLETED`. `QuestionnaireSessionUpdated` event |
| `POST /questionnaire/session` | P; for assignations the respondent Bearer is required | The full session + `user_data?` | Runs §7.7. Quiz funnel → `{job}`. Other types → `{type, ...result, cta?, layout?, result_copy?}`. `409 FOLLOW_UP_COMPLETED`. [DEBT] With an invalid assignation token it responds 200 without processing anything |
| `GET /questionnaire/session/{session_id}/results` | P (UUIDv4) | — | `{session_id, customer_id, questionnaire_id, cta, layout, result_copy, products, ai_team_profile, diagnostic}`. `404 SESSION_RESULTS_NOT_FOUND` |
| `GET /questionnaire/session/{session_id}/chain` | A, Own | — | `{stages:[...], total_stages}`: all the stages the respondent went through. `404 SESSION_NOT_FOUND` |
| `POST /questionnaire/session/{session_id}/answers/{question_id}/evaluate` | P | The question object | `{job}` (§7.9) |

**Files and transcription:**

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /transcription/token` | P | — | `{token}`: ephemeral secret (~1 min) for real-time transcription. One per recording |
| `POST /signed-urls` | P | `filename`, `content_type` (`type/subtype`), `customer_id`, `upload_type` (`answer_media` default \| `prompt`), `session_id` and `question_id` (required for `answer_media`) | `answer_media`: signed form-style upload `{url, fields, key, expires_in:900}`. Key `{customer_id}/{session_id}/{question_id}/{md5}{ext}`. Size from 1 byte to 500 MB. `prompt`: signed direct upload `{url, key, expires_in}`. Key `prompts/{customer_id}/{uuid}{ext\|.txt}` |
| `POST /answers-media/download-urls` | A | `key` (exactly 4 non-empty segments separated by `/`, no `..`, ≤ 1024), `disposition` (`inline` \| `attachment` default) | `{url, expires_in:900}`. 403 if the key's first segment is not the caller's `customer_id` (except `Admin`) |

### 8.5 Jobs and styles

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /jobs/{job_id}` | P | — | `{job:{job_id, job_type, status, result, stage, created_at, updated_at}}`, never the payload. `404 JOB_NOT_FOUND`, `500 INVALID_JOB_DATA` |
| `POST /styles` | AG, Cap(styles) | `website?` (empty = none), `styles?` (partial, camelCase, validated) | `{job_id}` (§7.16) |
| `GET /styles?customer_id=&questionnaire_id=` | P | One of the two is required (400 if both are missing). Only `customer_id` is used | `{styles \| null}` |

### 8.6 Products and e-commerce platform

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /customer/{customer_id}/products` | P | — | Bare: `[{product_id, name, description, price, image_url, product_url}]` |
| `GET/POST/PUT/DELETE /customer/{id}/products/{pid}` | A | — | Product CRUD used by the console on the hidden `/products` screen |
| `POST /scrapers/products` | A | `url`, `limit` (1–30, default 10) | `202 {job}`. Result: `{type:"scrape_products", products}`. Persists nothing |
| `GET /auth/shopify?shop=` | A | `shop` matching `[a-z0-9][a-z0-9-]*\.myshopify\.com` | OAuth `{url}` with a read-only products scope |
| `GET /auth/shopify/callback?code&shop&state` | P | — | Exchanges the code for a token + refresh token and stores them. Syncs products on the first connection. Returns an HTML page that closes itself. `400 INVALID_REQUEST`, `404 CUSTOMER_NOT_FOUND`, `400 TOKEN_EXCHANGE_FAILED` |
| `GET /shopify/connection` | A | — | `{shop \| null}` |
| `GET /shopify/sync/products` | A | — | §7.17. `400 SHOPIFY_NOT_CONNECTED`, `400 SHOPIFY_TOKEN_EXPIRED` |
| `POST /shopify/webhooks/customers/data_request`, `/customers/redact`, `/shop/redact` | P + HMAC | Raw body | Verify HMAC-SHA256 (base64) against the app secret. They only log and respond 200; 401 if the signature is bad |

### 8.7 Organizations

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /organizations` | A | — | Bare: `{organizations:[{...org, organization_users:[...]}]}`. `Admin` sees all |
| `POST /organizations` | AG, Cap(organizations) | `name` (1–120, no extra whitespace, not empty), `domain_email?` (domain-shaped, lowercase), `description?` (≤ 1000), `active` (default true), `organization_users[]`: `{organization_user_id? (UUIDv4), name (1–200), email?, phone? (≤ 50), role? (≤ 120), area? (≤ 120)}`. Each member with an email or phone; unique emails in the list. No extra fields allowed | 201 with the organization and its members. `409 DOMAIN_EMAIL_CONFLICT`. `OrganizationCreated` event |
| `PUT /organizations/{id}` | A (+ **SHOULD** require Own; see §15) | Partial, at least one field. If `organization_users` is sent, it is **reconciled**: matched by id, then by email, then by name + phone; existing members that do not match are deleted | `404 ORGANIZATION_NOT_FOUND` |
| `DELETE /organizations/{id}` | A (+ **SHOULD** require Own) | — | 204. `OrganizationDeleted` event. [DEBT] Does not delete the members |

There is no `GET /organizations/{id}`: the console filters the listing on the client side.

### 8.8 Assignations

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /assignations?page&page_size&type` | A | `page_size` default 20, max. 100. `type`: `default` \| `follow_up` | `{assignations:[enriched], pagination}`. Newest first; `Admin` sees all |
| `POST /assignations` | AG, Cap(assignations) | `organization_id`, `questionnaire_id` (UUIDv4), `name` (1–200), `description?` (≤ 2000), `max_follow_ups` (≥ 0), `active` (default true), `type`, `due_date?` (follow-up only), `audience` (default `{type:"all"}`), `questions` (≥ 1). No extra fields allowed | `201 {questionnaire_url: {FRONTEND_URL}/a/{id}, assignation_id}`. `400 AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`. `AssignationCreated` event |
| `GET /assignations/{id}` | P (used by the respondent page) | — | Enriched assignation with detailed `attempts`, `organization_name`, `questionnaire_name`, `questionnaire_url`, `completed`, `review_status`. For anonymous callers only: Feat(assignations) → 429 |
| `PUT /assignations/{id}` | A, owner or `Admin` | Partial. `type` forbidden ("type cannot be changed"). `due_date:null` clears it | `404`, `400 ASSIGNATION_IN_PROJECT`, `400 VALIDATION_ERROR`, `400 AUDIENCE_MEMBER_NOT_IN_ORGANIZATION` |
| `DELETE /assignations/{id}` | A, owner or `Admin` | — | 204. `AssignationDeleted` event |
| `POST /assignations/{id}/sessions` | P | `name` (required, 1–200), `email?`, `phone?`, `role?`, `area?`. No extra fields allowed | Bare: `{token, questionnaire: session, flow}` (§7.11). `400 MISSING_IDENTIFIER`, `403 USER_NOT_FOUND`, `403 NOT_IN_AUDIENCE`, `404 ASSIGNATION_NOT_FOUND`, `404 QUESTIONNAIRE_NOT_FOUND`, 429, `409 FOLLOW_UP_COMPLETED` |
| `GET /assignations/{id}/respondents?page_size\|limit&cursor` | A (a non-owner receives an empty page) | `page_size` default 10, max. 100 | `{respondents:[{organization_user_id, organization_user_name, organization_user_email, status: pending\|in_progress\|completed, session_id, completed_stages, total_stages, attempts, attempts_detail[]}], next_cursor}`. `400 INVALID_PAGE_SIZE`, `400 INVALID_CURSOR` |
| `POST /assignations/{id}/reminders` | A, owner or `Admin` | — | `{recipients: n}` (§7.13) |
| `PUT /assignations/{id}/reviews/{question_id}` | A, owner or `Admin` (another account's returns 404) | `status` (`approved` \| `rejected`), `comment?` (≤ 1000; empty becomes null) | `{question_id, review, review_status}`. `400 NOT_A_FOLLOW_UP`, `409 FOLLOW_UP_NOT_COMPLETED`, `404 QUESTION_NOT_FOUND`, `400 QUESTION_LOCKED` |
| `POST /assignations/{id}/retries` | A (+ **SHOULD** require owner) | — | `201 {attempt, session_id, recipients}`. `409 REVIEW_INCOMPLETE`, `400 NOTHING_TO_RETRY`, `502 RETRY_EMAIL_NOT_SENT` |

### 8.9 Projects

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `GET /projects?page&page_size&status&q` | A | `page_size` default 10, max. 100. `status` ∈ `review`, `progress` (includes `pending`), `correction`, `overdue`, `approved`. `q` searches name + organization | `{projects, pagination}`. `400 INVALID_PROJECT_STATUS` |
| `GET /projects/{id}` | A | — | Enriched project (§7.12). `404 PROJECT_NOT_FOUND` |
| `POST /projects` | A, Feat(assignations) | `organization_id`, `name` (1–200), `description?` (≤ 2000), `due_date` (required), `assignation_ids[]` (deduplicated). No extra fields allowed | 201. `404 ORGANIZATION_NOT_FOUND`, `404 ASSIGNATION_NOT_FOUND`, `400 ASSIGNATION_ORGANIZATION_MISMATCH`, `400 ASSIGNATION_NOT_FOLLOW_UP`, `409 ASSIGNATION_IN_OTHER_PROJECT` |
| `PUT /projects/{id}` | A | `name`, `due_date`, and `assignation_ids` cannot be null; `organization_id` cannot be changed (400). `assignation_ids` **replaces** the set | — |
| `DELETE /projects/{id}` | A | — | Unlinks the assignations and deletes the project. 204 |

### 8.10 Chat

`POST /chat` · AG, Cap(chat).

**Input:**

- `messages[]`: 1–40 elements `{role: user|assistant, content 1–20000}`. The last one must be `user`.
- `mode`: `create` (default) \| `draft`.
- `draft?`: the current draft.
- `item?`: `{kind: questionnaire|organization|assignation|project, id}`.

**Output:** `202 {job}` (§7.19).

### 8.11 API keys, external API, and webhooks

| Method and route | Access | Input | Output / errors |
|---|---|---|---|
| `POST /api-keys` | A, Feat(api) | `name` (1–100), `expiration_days?` (1–3650) | `201 {api_key: "QAIRE-" + 64 hex}`. **Shown only once** |
| `GET /api-keys` | A | — | Active ones only: `[{id, name, created_at, expires_at, last_used_at}]`, newest first |
| `DELETE /api-keys/{id}` | A, Own | — | Revokes. `404 API_KEY_NOT_FOUND` |
| `GET /external/questionnaires?page&page_size` | `X-API-Key`, Cap(api) on every call | `page_size` default 50, max. 50 | `{questionnaires:[{id, flow_id, slug, title, description, is_active, type, created_at, updated_at}], pagination}`. `401 INVALID_API_KEY` (same message whether missing, revoked, or expired). Sets `last_used_at`. `ApiUsage` event |
| `GET /external/questionnaires/{id}/answers?page&page_size` | `X-API-Key` | — | `{questionnaire_id, sessions:[{id, answers:[{title, value, min?, max?}]}], pagination}`, newest first. 404 if it belongs to another account |
| `POST /webhooks` | A, Feat(webhook) | `url` (https only), `event_type` (`questionnaire.completed`), `method` (`POST`) | 201 with the webhook |
| `GET /webhooks` · `PUT /webhooks/{id}` (partial) · `DELETE /webhooks/{id}` (204) | A, Own | — | `404 WEBHOOK_NOT_FOUND` if it belongs to another account |

### 8.12 Documentation videos

`GET /videos?language=es|en` · A. Sorted by `order` and then by title. `400 INVALID_LANGUAGE`.

### 8.13 Super-admin (`/admin/*`, `Admin` only; `Customer-Admin` receives 403)

**Accounts:**

| Route | Description / errors |
|---|---|
| `GET /admin/customers?page&page_size&search` | `{customers:[{id, name, email}], pagination}`. `page_size` default 10, max. 100. `search` does a substring match on id, name, or email |
| `GET /admin/customers/{id}/users` | `{users:[{id, name, email}], pagination}`. `404 CUSTOMER_NOT_FOUND` |
| `GET /admin/customers/{id}/plan` | `{customer_plan \| null}`. `404 CUSTOMER_NOT_FOUND` |
| `PUT /admin/customers/{id}/plan` | `{plan_id, from_at, to_at, billing_interval}`. `400 UNKNOWN_PLAN`, `400 INVALID_DATE_RANGE`, 404 |
| `GET /admin/customers/{id}/usage` | `{customer_id, customer_plan, plan, plan_active, usage:{from_at, to_at, features} \| null, features}`. Never returns 404 |
| `PUT /admin/customers/{id}/usage` | `{features:{feature_id: int ≥ 0}}`. Merged into the open period's counters. `400 UNKNOWN_FEATURE`, `503 USAGE_UNAVAILABLE` |

**Feature catalog:** `GET`, `POST /admin/features`, `PUT /admin/features/{id}` (full replacement), `DELETE /admin/features/{id}`.

- Body: `feature_name` (1–100), `feature_description` (≤ 1000). The id is the slug of the name and does not change.
- Errors: `409 FEATURE_ALREADY_EXISTS`, `400 INVALID_NAME`, `404 FEATURE_NOT_FOUND`.
- `POST` emits `FeatureCreated`.

**Plan catalog:** `GET`, `POST /admin/plans`, `PUT /admin/plans/{id}` (full replacement), `DELETE /admin/plans/{id}`.

- Body: fields from §6.4.
- Errors: `400 UNKNOWN_FEATURE`, `409 PLAN_ALREADY_EXISTS`, `404 PLAN_NOT_FOUND`, `400 INVALID_STRIPE_PRICE`. The latter covers: the price does not exist, is archived, has a different interval, or does not match in amount or currency.

**Coupons** (in the gateway only): `GET`, `POST /admin/coupons`, `DELETE /admin/coupons/{promotion_code_id}` (deactivates the code).

| Field | Rule |
|---|---|
| `code` | Uppercased; `^[A-Z0-9_-]{3,32}$` |
| `type` | `percent` \| `amount` |
| `percent_off` | 0 < x ≤ 100 |
| `amount_off` | > 0, with `currency` (3 letters) |
| `duration` | `once` \| `repeating` \| `forever` |
| `duration_in_months` | ≥ 1, only with `repeating` |
| `plan_ids[]` | Empty = all purchasable plans |
| `billing_intervals` | ≥ 1, no repeats. `repeating` only allows `["month"]` |
| `expires_at` | Date not in the past; valid until 23:59:59 UTC of that day |
| `max_redemptions` | ≥ 1 |

- Derived state: `active`, `expired`, `exhausted`, `inactive`. Also `times_redeemed`.
- Errors: `400 INVALID_COUPON`, `400 UNKNOWN_PLAN`, `400 PLAN_NOT_PURCHASABLE`, `400 COUPON_CURRENCY_MISMATCH`, `400 COUPON_INTERVAL_NEEDS_OWN_PRODUCT`, `409 COUPON_CODE_TAKEN`, `404 COUPON_NOT_FOUND`.

**Videos:** CRUD on `/admin/videos` with the fields from §6.22.

**System prompts:**

| Route | Description |
|---|---|
| `GET /admin/system-prompts` | Summaries `{key, description, required_placeholders, source: s3\|default, updated_at, updated_by, version_id}` |
| `GET /admin/system-prompts/{key}?version_id` | Adds `text` |
| `PUT /admin/system-prompts/{key}` | `text` (1–65,536, not blank). `400 INVALID_PLACEHOLDERS` |
| `GET /admin/system-prompts/{key}/versions` | Version history |

An unknown key returns `404 UNKNOWN_PROMPT`.

### 8.14 Health

`GET /health` · P. Returns `data = checks`: 200 "ok" or 500 "error".

---

## 9. Respondent app

### 9.1 Principles

- Mobile-first, with no accounts or passwords. Only assignations have a lightweight identity login.
- **Local-first:** progress is saved in the browser and on the server at every step.
- All branding (colors, font, logo) comes from the account that owns the questionnaire.
- Bilingual: es/en.

### 9.2 Route map

| Route | Access | Description |
|---|---|---|
| `/` | P | Redirect (replace) to the marketing site (`https://getmappi.com`) |
| `/q/:id` | P | Questionnaire by id (legacy but still active) |
| `/f/:id` | P | Flow by flow id, slug or questionnaire id. The URL does not change between stages |
| `/f/:id/generating` | P | AI generation of the next stage |
| `/a/:id` | Identity login | Assignation |
| `/a/:id/generating` | Identity login | Generation within an assignation |
| `/session/:sessionId/results` | P (sends Bearer if there is a token) | **Canonical results link**; can be reloaded and shared |
| `/results`, `/q/:id/results` | P | Legacy results (in memory only) |
| `/privacy` | P | Privacy policy |
| `/tiktok` | P | Changes the title, fires a page_view and redirects to the console |
| `/:id` | P | Legacy: redirects to `/q/:id` (if `id === 'results'`, shows the results) |
| `/internal/qa/*` | Internal | QA tools (local transcription, cloud transcription, error testing). **Optional** in the migration |

**Global:** neutral (unbranded) loading skeleton screen until the styles are applied. An unhandled error shows "Algo salió mal / Something went wrong" with a "Recargar / Reload" button.

### 9.3 Questionnaire screen (`/q/:id`)

**Page title:** `"<Marca> - {title}"`.

**Rendering priority** (the first applicable case is shown):

1. **Limit reached** (session creation returns 429): "The response limit has been reached."
2. **Not found** (404 or another error): "This questionnaire does not exist." with the button "Want to create this questionnaire?" → console URL.
3. **Processing** (AI evaluation in progress): full screen. Eyebrow "One moment", title "We're reviewing your answer", subtitle "This only takes a few seconds. We're reviewing what you wrote to make sure we have everything we need."
4. **Disclaimer declined:** "You can now close this tab."
5. **Disclaimer modal** (if `disclaimer` is not empty and there is no saved consent):
   - "Before you start", the disclaimer text (preserves line breaks, scrolls at max 45vh) and the "Private" badge.
   - "Accept and continue" saves consent for 24 h.
   - "Not now, thanks" tries to close the tab; if it can't, it moves to screen 4.
6. **Audio tutorial** (if any question has an `audio` control and the tutorial has not been seen): §9.8.
7. **Landing** (if `landing_page` and there is no progress yet): eyebrow "Get started", serif title, description, "Start questionnaire" button and the note "{count} questions".
8. **Data capture** (if `capture_user_data`, when leaving the last question): §9.6.
9. **Questions view.**

**Resume modal** (over 7–9 when a saved session with progress exists):

- Badge "Saved just now" / "Saved N minute(s)/hour(s)/day(s) ago" (in minute, hour and day buckets).
- "Pick up where you left off" and "You have an unfinished questionnaire. We saved your answers — you can continue from the same question."
- "Question {current} of {total}" box with percentage and bar.
- "Continue" and the "Start over" link, with the warning "Your {count} saved answer(s) will be deleted".

**Questions header:**

- Brand logo or fallback monogram.
- Progress: continuous bar on mobile; one dot per question on medium screens and up.
- "Step {n} of {total}" and "{pct}%" with `pct = round(step/total×100)`.
- "Stage {current} of {total}" badge in multi-stage flows.
- Language selector.

**Question body:**

- Eyebrow with the order number+1, zero-padded ("01").
- Title (serif), description and disclaimer in italics.
- "{count} attempt(s) left" badge on an AI follow-up retry.
- **Review banners** (assignation retries):
  - "Approved / This answer was approved and can't be changed." (locked question).
  - "Needs correction" with the reviewer's comment, or "Your reviewer asked you to answer this question again."
- The answer control.

**Navigation footer:**

- "Back": disabled on the first question.
- "Skip": only if `required === false` and the question is not locked.
- "Next", or "Finish" on the last one. Shows "Saving…" while saving.
- **Enter** outside a field advances if nothing is blocking.
- The footer is hidden in the themes that require it.

**Visibility by gender:** a question whose `visibility` does not include the current gender is skipped automatically. Options are filtered as well. The default gender is `male` and it is set by the `gender` theme.

**URL hash:** reflects the current question's id.

### 9.4 Answer controls

Each question uses **a single control**: the first one that can be rendered.

- **`radio`**
  - Cards with a letter (A, B, C…).
  - Options are filtered by gender and duplicate values are removed. If `value` is null, `label` is used.
  - Selecting unlocks Next.
- **`checkbox`**
  - Multiple selection; the value is an array.
  - An option whose value contains `none`, `n/a`, `not applicable` or `neither` is **exclusive**: checking it clears the others, and checking another one clears it.
  - Next is blocked while nothing is checked.
- **`select`**: dropdown list with the placeholder "Select an option". Empty blocks Next.
- **`range`**
  - Slider. `min` and `max` come from the validations; defaults are 0 and 10.
  - The control starts at `default_value` if it is within range, otherwise at `min`. **That position does not count as an answer:** until the user moves or taps it, "Move or tap the slider to answer" is shown and Next stays blocked.
  - An out-of-range value shows the validation's `message`.
- **`text`**
  - 3-row text area with autofocus. Placeholder: `default_value`, then an example from the preset, then "Type your answer here...".
  - Empty or whitespace-only blocks Next. **Enter submits**; there is no line break.
  - Validation `{type:'format', value}`:

    | `value` | Rule | Message |
    |---|---|---|
    | `letters` | Letters only | "Only letters are allowed" |
    | `numbers` | Numbers only, no spaces | "Only numbers are allowed, no spaces" |
    | `symbols` | Symbols only | "Only symbols are allowed" |
    | Combinations (`letters,numbers`, etc.) | Combined classes | "Only letters and numbers are allowed", etc. |
    | `rfc` | `^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$` with a real 19YY/20YY date | "Enter a valid RFC: 3 or 4 letters, a YYMMDD date and the homoclave. E.g.: ABC680524P76" |
    | `nit` | 9–10 digits | "Enter a valid NIT: 9 or 10 digits, with no dots or dash. E.g.: 9001234568" |
    | `phone` | Leading `+`, then digits, spaces, `()` or `-`, with 8–15 digits | "Enter a phone number with the country code. E.g.: +52 55 0000 0000" |

  - Internal spaces are allowed except in "numbers only".
  - Any validation with a `pattern` (regex) is also applied.
  - Mobile keyboard: numeric for `nit` and "numbers only"; phone for `phone`.
- **`email` / `tel` / `phone`**
  - Email: `^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$`. Error "Enter a valid email address (e.g. name@example.com)."
  - Phone: normalized to digits with an optional `+` and must match `^\+?\d{7,15}$`. Error "Enter a valid phone number: digits only, optional international prefix (e.g. +57)."
  - The error is shown when leaving the field.
- **`ranking`**
  - List reorderable with mouse, touch and keyboard, numbered 1 to n. Hint "Drag the options to put them in your preferred order".
  - **The initial order is already a valid answer** and is saved immediately. The value is the array of values in order.
- **`file`**: §9.9.
- **`audio`**: §9.7.
- **`message`** (or no options): only displays content. Next enabled. The value `"viewed"` is saved with a timestamp.

**Locked controls** (retries): shown disabled and never written.

### 9.5 Special themes (`theme_name`)

| Theme | Behavior |
|---|---|
| `gender` | Two large cards with the options from `options[0].options`, values `male` / `female`. Sets the gender that filters subsequent questions and options |
| `quote` | Transition screen that **advances on its own after 2500 ms** (or finishes if it is the last question), with no navigation footer. Text: "We're finding the perfect product for your needs." [CLIENT-SPECIFIC] With `livingood`: "Health Profile part of your plan" and "Calculating your Health Profile..." |
| `celebration` | 2500 ms transition screen with the question's title, description and disclaimer |
| `weight` | Unit selector KG (default) or lbs, plus a numeric field. Valid: 20–635 kg or 44–1400 lbs |
| `height` | Selector CM (default) or ft/in. Valid: 50–272 cm or 1.6–8.9 ft |
| `weight-composite` | Three fields: "Current weight" and "Goal weight" (same unit, lbs or kg) and "Current height" (ft/in or cm). They are matched by substrings of the label. Saved as `"{valor} {unidad}"`. All three are required |
| `jeans-size` | A system selector (`us-sizes`, `inches`, `centimeters`) decides which of `options[1..3]` is shown as a radio |
| `user-capture-data` | In-flow email capture, with no footer. "You're Done!" / "Your Personalized Health Profile Is Ready". Fields "Your name (optional)" and email (required). The "See My Results" button ends the flow |
| `organization-users-login` | Assignation login form (§9.10) |

### 9.6 Data capture phase (end of the questionnaire)

- **Copy:**
  - Eyebrow "One last step"
  - Title "Where should we send your results?"
  - Description "Share your contact details so we can deliver your personalized recommendations."
  - Privacy note "Your information is private and will only be used to deliver your results."
- **Fields (all required):**

  | Field | Placeholder | Rule |
  |---|---|---|
  | Name | "Your full name" | Not empty |
  | Email | "you@email.com" | Email regex |
  | Phone | "Your phone number" | While typing, only accepts digits and a leading `+`; 7–15 digits |

- Errors appear on submit and are cleared when editing the field.
- "See my results" button with a spinner; prevents double submission.
- Attaches `user_data: {name, email, phone}` to the submission.

### 9.7 Audio question (voice answer)

| State | UI |
|---|---|
| Idle | Large microphone button: "Tap to record your answer" |
| Connecting | Spinner: "Preparing microphone..." |
| Recording | Red stop button, 9 wave bars following the microphone level, "Listening... mm:ss" and the **live transcription** |

**Recording result:**

- Each recording adds a text segment. The value is an array of strings.
- Each segment can be deleted ("Remove recording") or re-recorded (replaces that segment).
- Other actions: "Add to my answer" and "Record again from scratch".
- A recording with no text shows "No transcription available."
- Next is blocked if there are no recordings, while recording or connecting, or if a follow-up requires a change.

**Errors:**

| Case | Message |
|---|---|
| Insecure context | "Recording requires a secure (HTTPS) connection." |
| Unsupported browser | "Your browser does not support audio recording." |
| No microphone | "No microphone was found on this device." |
| Microphone busy | "The microphone is already in use by another application." |
| Other access error | "Unable to access the microphone." |
| Connection (everything else) | "We couldn't connect to start recording. Check your internet connection and try again." |

**Permission denied.** Detected before recording or from the error. Opens a help panel:

- "Microphone access is blocked"
- Explanation that it is a browser permission and not a platform problem.
- Step: "Open the [candado] in the address bar → turn on **Microphone** → try again."
- "Try again" button
- Note "We only use your microphone while you're recording."

**Transcription:**

- Language = UI language (`es`/`en`).
- An ephemeral token is requested per recording (`GET /transcription/token`).
- The account can define `transcription_url` to use an alternative transport.

### 9.8 Audio tutorial (once, before voice questions)

1. **Intro:**
   - "Quick mic check" / "Let's test your microphone" / "Let's check your microphone. It only takes a few seconds."
   - "Read this out loud:" → "Hello, my microphone is working great."
   - Prompt "Tap here ONCE and wait until it turns red — then start talking".
2. **Test:** "Preparing your microphone..." and then "Listening... read the sentence, then tap to stop".
3. **Result:**
   - Success: "Your microphone works!" / "We heard you loud and clear…" / "We heard" + transcription. "Continue to the questionnaire" marks the tutorial as seen for 24 h.
   - No text: "We didn't catch anything. Tap the microphone and try again."
   - Error: "We couldn't complete the mic check" with the detail. Reported to error tracking.

[DEBT] There is no way to skip the tutorial: a respondent without a microphone is stuck.

### 9.9 File question

**Limits:**

- Any file type, **up to 500 MB** each.
- Maximum number of files = the account's `settings.max_files` (1–20, default 10).

**Ways to add files:**

- "Choose files" (several at once).
- Drag and drop.
- **Paste with Ctrl+V** anywhere on the page. Pasted screenshots are renamed `screenshot-YYYYMMDD-HHmmss[-n].{ext}`.

**Per file:** progress "Uploading... {progress}%", "Try again" after an error ("Could not upload your file. Please try again.") and "Remove {name}".

**Other copy:**

| Situation | Text |
|---|---|
| Help | "Any file type, up to 500 MB each. Large files may take a while to upload on slow connections." |
| Paste help | "…or paste a screenshot with Ctrl+V" |
| Counter | "{count} / {max} files" |
| Limit reached | "You've reached the limit of {max} files. Remove one to add another." |
| Large file | "That file is larger than 500 MB. Please choose a smaller one." |
| Discarded files | "{count} file(s) was/were not added: you can attach up to {max} files." |

**Upload:** `POST /signed-urls` and then a direct upload to object storage with progress. **The saved value is the object key**, not a URL.

Next is blocked until at least one file is uploaded and none is still uploading.

### 9.10 Assignation (`/a/:id`)

**1. Load:** `GET /assignations/{id}`. The account's branding and language are applied. With 429 the limit screen is shown; with any other error, "This questionnaire does not exist."

**2. Follow-up already completed** (`completed: true`):

| `review_status` | Message | Hint |
|---|---|---|
| `in_review` / `changes_requested` | "Your answers are being reviewed" (amber hourglass) | "If something needs changes, you'll receive an email with the link to correct it." |
| `approved` | "Your answers were approved" (green check) | "Thank you for taking part. There is nothing else to do." |
| null / `not_ready` | "This follow-up has already been completed." | "There is no need to answer it again." |

**3. Automatic resume without login** when all three conditions are met:

- the saved token has an `assignations_id` equal to the one in the URL;
- its `session_id` is that of the current attempt (the last one in `attempts`), or the assignation has no attempts;
- local progress exists.

**4. Login** (`organization-users-login` theme). Built from `questions[0].options`.

- Title "Sign in". Each option is a field; it is required if it has the `required` validation. The button is enabled when all required fields are filled.
- Fields are recognized by type or by a keyword in the label (English or Spanish):

  | Key | Recognized by | Label / placeholder |
  |---|---|---|
  | `email` | type `email` or "email"/"correo" | "Email" / "you@email.com" |
  | `phone` | type `tel`/`phone` or "phone"/"tel" | "Phone" / "Your phone number" |
  | `role` | "role"/"cargo" | "Role" / "Your role" |
  | `area` | "area"/"área" | "Area" / "Your area" |
  | `name` | "name"/"nombre" | "Full name" / "Your full name" |

- Unrecognized fields are not sent.
- They are sent normalized: name lowercased, without accents, with collapsed spaces; phone as digits with an optional `+`.
- **Login outcomes:**

  | Response | Outcome |
  |---|---|
  | 409 | Completed screen |
  | 429 | Limit screen |
  | `NOT_IN_AUDIENCE` | "This assessment isn't addressed to you. If you think this is a mistake, contact whoever sent it to you." |
  | `USER_NOT_FOUND` | "We can't find you in this organization. Check the name and email you were invited with." |
  | Other | "We could not sign you in. Please try again." |

**5. After login:**

- The token is saved and sent as `Authorization: Bearer` on session and results calls.
- Local answers from the **same attempt** are merged; never those from a previous attempt.
- In a follow-up with no local answers but with shared progress on the server, the respondent lands on the first unresolved question.
- **There is no resume modal.**
- On a retry, the respondent lands on the first rejected question.
- "Start over" clears the answers in place and keeps the session and the locked answers.
- On finishing, the token is deleted, unless the chain continues.

### 9.11 Multi-stage flow (`/f/:id` or a flow behind `/a/`)

1. The flow is resolved with `GET /flow/{id|slug}` (or a saved run of the same flow is reused) and the branding is preloaded. If the flow does not exist or is malformed: "This flow could not be found."
2. The entry point is the single `questionnaire` state.
3. When each questionnaire finishes, the next state decides:
   - **Another `questionnaire`:** loaded in place; the URL does not change.
   - **`prompt`:**
     1. Answers are flattened to `[{question: title, answer: valores unidos con ", "}]` (answer: values joined with ", "), excluding unanswered ones.
     2. Navigate to `{base}/generating` and call `POST /questionnaire/prompt`.
     3. The job is polled until there is a `questionnaire_id`, and that generated stage is loaded.
   - **null or a display state** (`diagnostic`, `result`, `quiz_funnel`…): the flow ends and the results are shown.
4. **Stage badge:** "Stage n of total". Counts the entry plus each `prompt` reachable by following `next`, capped at 12.
5. **Generation screen:**
   - "One moment" / "Preparing your next questions".
   - 5 rotating messages every 3.5 s: "We're tailoring the next step from your answers." / "Reviewing what you've told us so far…" / "Picking the questions that matter most for you." / "Almost there — putting the finishing touches on it." / "Thanks for your patience, this only takes a moment."
   - Error: "We couldn't prepare your next questions." with "Try again".
   - **It can be reloaded:** the job id is saved and polling resumes.

### 9.12 Submission and results

**On finishing:**

1. Lock the submission so it is not sent twice.
2. Clear the local snapshot, internally go back to question 0 and remove the hash.
3. `POST /questionnaire/session`. If it returns a job (quiz funnel), poll it.
4. If it is a standalone questionnaire or the last stage: navigate to `/session/{session_id}/results`. If it is an intermediate stage: advance the flow.
5. **If the submission fails:** return to the questionnaire, re-save the session to keep the answers and position on the last question.
6. **After success:**
   - Fire the "Lead" conversion on all configured pixels, only if this completed the flow.
   - Going back from results starts a new session.

**Results loading:**

- If there is no result in memory, `GET /questionnaire/session/{id}/results` is called and the branding of the returned `customer_id` is applied. If it fails, navigate to `/`.
- Loading screen: "Processing" / "We're finishing documenting your answers".
- The type is inferred from the data: `ai_team_profile` → profile; `diagnostic` → diagnostic; `products` → ecommerce; otherwise, default.

**Variants:**

- **ecommerce**
  - "Your Recommended Products" / "Based on your answers, we have curated these selections specifically for your needs."
  - Grid of 1, 2 or 3 columns.
  - Each card: lazy-loaded image (fallback icon), price (hidden if "$0.00"; USD en-US format), "Top Match" label, name (2 lines), HTML description (3 lines, fallback "No description available.") and "View in Store", which opens `product_url` in a new tab.
  - Empty: "No products recommended at this time."
  - **MUST sanitize the description HTML** (§15).
- **diagnostic**
  - Language selector.
  - Hero: "Successfully completed" / "Thank you so much for your support" / "Here's how you scored across each area." [CLIENT-SPECIFIC] Customer `3zWj6Nrg` changes the title to "This is the strategic diagnosis of your operation".
  - **Tier:** "Your level" with the tier's name and description. Tier = band with `min ≤ score ≤ max`; if they overlap, the one with the highest `min` wins.
  - **Overall score:** "Overall score" as value/maximum with a bar. `pct = clamp(round(score/max×100), 0..100)`.
  - **By category:** "Score by area" / "How you performed in each category.", with bar, % and score/maximum.
  - **Radar** (only with ≥ 3 categories; each axis is the % of its own maximum): "Your category profile", legend "Your score (%)".
  - **"Recommendations"** (bullets) and **"Action plan"** (numbered). They come from the tier reached; if that tier has none, from the nearest lower tier that has them. **Never from a higher one.**
  - **Closing:** "Full report in PDF" / "Includes the detail by category and the action plan." / "Download" ("Preparing your PDF…"). File `YourResults.pdf`, with the brand's primary color as the accent. The CTA goes in the same card.
  - **`layout`** limits which elements appear. Without `layout`: if any tier has `visible:false`, score, tier and categories are hidden.
  - **`result_copy`** overrides the 15 texts.
- **ai_team_profile**
  - Hero "AI Growth · Maturity result": stage, score/maximum, bar and stage track (done/current/pending).
  - "Potential" % and "Percentile", plus a quote.
  - "Your 5 dimensions" with colored bars per dimension.
  - Radar "You vs average vs top 10%".
  - "Your strengths" (dimensions ≥ 4/6), "Your biggest opportunity", "Your 90-day roadmap" ("Days {days}:").
  - "Download my result (PDF)".
  - Note "All answers are anonymous · Results are analyzed only at group level".
  - No report: "We couldn't generate your profile this time. Please try again later."
- **samurai8** [CLIENT-SPECIFIC]
  - Fixed Spanish copy.
  - Tiers Explorador…Maestro (Explorer…Master) with percentiles 100 %, 55 %, 18 %, 10 %, 3 %, 1 %.
  - Maximum 30; each dimension max. 6: Contexto, Datos, Automatización, Calidad, Autonomía (Context, Data, Automation, Quality, Autonomy). Per-dimension scores are estimated on the client.
  - 30-day roadmap for Explorador and 90-day for the rest.
  - Dates in `dd.mm.yy` format.
- **default**
  - "Your answers have been submitted successfully." / "Thank you for completing the questionnaire. Your answers have been saved and the team can now review them."
  - `result_copy.title`/`subtitle` override them.

**CTA (all variants):** `{title, description, button:{text, url}}`. Only shown if there is a URL and text. Opens in a new tab.

**PDF:** generated client-side or server-side (implementation's choice) with the diagnostic or profile content.

### 9.13 Privacy (`/privacy`)

- Forced light theme, without customer branding.
- "Legal" / "Privacy Policy" / "Last updated April 20, 2026".
- 9 sections: Who we are, Information we collect, How we use it, Use of AI, Third parties, Retention (90 days after uninstalling), Rights, Security, Changes.
- Contact by email and footer "© {year}. All rights reserved."

### 9.14 Respondent local persistence

All reads and writes are fault-tolerant. 24 h expiration.

| Logical key | Content |
|---|---|
| `questionnaire_session:{qid}` (or `:{assignationId}:{qid}` in assignations) | `{questionnaireId, questionId, currentPosition, questionnaire, timestamp}` |
| `questionnaire_disclaimer:{qid}` | Time of acceptance |
| `questionnaire_audio_tutorial:{qid}` | Time it was seen |
| `organization-user-token` | Respondent token; deleted on completion |
| `assignation_progress:{assignationId}` | `{assignationId, questionnaireId, flowId?, updatedAt}` |
| `flowRun` | Flow, active state, active questionnaire, pending answers, pending job, pending session |

**When it is saved:** on every answer or step change (once there is progress), before the page closes and right after the session is created.

**Local-first load:** if there is a snapshot less than 24 h old and with a `session_id`, it is restored **without calling the network**. Otherwise, `POST /questionnaire/{id}/session`, which creates a session on every call; concurrent calls are deduplicated.

**Autosave:** every Next or Skip does `PUT /questionnaire/session` with the full session. Skip marks all controls with `skipped:true`, an empty value and a timestamp.

**"Start over"** in the resume modal: deletes the snapshot and the disclaimer and tutorial flags, and creates a new session.

### 9.15 Applying the branding

- Sanitization:
  - Hex colors of 3, 4, 6 or 8 digits.
  - Lengths in px, rem, em or %.
  - Font name `^[A-Za-z][A-Za-z0-9 _-]*$` of ≤ 60 characters.
  - Font URL only from an allowed font provider (currently Google Fonts).
- Mapping:

  | Styles field | Theme token |
  |---|---|
  | `body.background` | Background |
  | `body.color` | Text |
  | `button.primary.background` | Primary |
  | `button.primary.color` | Text on primary |
  | `a.color` | Accent |
  | `p.color` | Muted text |
  | `input.borderRadius` | Radius |
  | `button.primary.borderRadius` | Button radius |
  | `font.family` | Heading font |

- Cards use the input background only if its contrast with the text color is ≥ 2.5; otherwise, the page background.
- Borders and muted tones = mixes of text and background at 20 % and 10 %.
- The favicon and the OG image are not overridden.

**Language:**

- Initial language: the browser's, with `es` as fallback.
- Overridden by the account's `language`, unless the user has already picked one manually during this visit.
- `<html lang>` is kept in sync.

### 9.16 Marketing measurement in the respondent app

| Tool | Configuration | Events |
|---|---|---|
| Platform web analytics | Global ID | `page_view` on every route or query change (not hash) |
| Meta Pixel | The account's `pixel_id`; fallback: global ID | `PageView` on start and on every route; `Lead` on completion. Only to the resolved pixel |
| LinkedIn Insight | The account's `linkedin_partner_id` | `linkedin_conversion_id` conversion on completion |
| Google Ads | The account's `google_ads_id` and label | `conversion` on completion |
| Heatmaps / session recording | Global ID | — |
| Error tracking | Global DSN | Traces, replays of sessions with errors, logs |

---

## 10. Operator console

### 10.1 General structure

- **Page title:** "Mappi - {title}". 404: "404 / Oops! Page not found / Return to Home".
- **Composition of authenticated routes:** authentication gate → onboarding gate → layout (sidebar + assumed-customer banner + usage banner + content).
- **Sidebar:**

  | Group | Entries |
  |---|---|
  | Design | AI Experience (highlighted), Design Experience, Questionnaires, Customization |
  | Send and track | Organizations, Assignations, Projects |
  | Settings | Users, Integrations, Profile, Documentation |

  - Footer: language selector; account block (initials, name derived from the email, plan badge and email) that links to `/profile`; Logout.
  - Eyebrow "Admin Console". The logo links to `/ai-experience`.
  - All entries are real links (middle-click and Ctrl+click work). Sub-routes highlight their section.

**Permissions in the UI:**

- Read-only users see controls disabled with a tooltip: "Your read-only role can't create resources." / "Your read-only role can't make changes."
- Every `/new` or `/:id/edit` route redirects anyone without write permission.
- `RequireFeature` waits for the plan verdict and redirects to the listing if the feature is not allowed.
- **Never blocked by plan:** `/profile/plans`, `/projects`, `/assignations`.

**Session:**

- Before each request, if the token expires in less than 2 min, it is refreshed. Concurrent requests share a single refresh.
- A 401, or a network error with an expired token, ends the session and goes to `/login`.
- Tabs are synchronized with each other.
- After login, the user returns to the route they were trying to open.

### 10.2 Authentication (public routes)

**`/login`** (tabs "Sign in" / "Sign up"):

- Badge "14 days free · no card".
- **"Continue with Google":** OAuth with PKCE against the identity provider; fires the marketing event `CompleteRegistration`.
- **Validation:**

  | Field | Rule | Message |
  |---|---|---|
  | Name (sign-up only) | Required | "Please enter your name." |
  | Email | `^[^\s@]+@[^\s@]+\.[^\s@]+$` | "Enter a valid email address." |
  | Password | ≥ 8 | "Password must be at least 8 characters." |

- **Strength meter** (sign-up): 1–4 bars (weak / fair / good / strong). Adds one point for each of: ≥ 8 characters, ≥ 12, upper and lower case, digit, symbol. Clamped to 1–4. Always shows the label in addition to the color.
- **Error mapping:**

  | Code | Message |
  |---|---|
  | Invalid credentials / nonexistent user | "Invalid email or password." |
  | `EMAIL_ALREADY_EXISTS` | "An account with this email already exists. Try signing in instead." |
  | Invalid password | "Password must be at least 8 characters." |
  | Not confirmed | "Your account isn't confirmed yet. Please check your email." |
  | Provider not configured | "Sign-in is temporarily unavailable…" |
  | Other | "Something went wrong. Please try again." |

- **Sign-up flow:** `POST /register` with the normalized language (`es`→`es-CO`, `en`→`en-US`) and the lowercased email. Then sign-in. Toast "Account created. Welcome to Mappi!".
- **Brand panel:**
  - "Smart questionnaires that end in action."
  - Stats: 32 % "average conversion", 6.4× "more than unguided", "< 8 min" "first result".
  - 3 testimonials that rotate every 9 s.
  - Privacy / Terms / Support links ([DEBT] they lead nowhere).

**`/sign-in`** (OAuth return):

- Exchanges `?code` for tokens and shows "Signing you in...".
- If the error contains `EMAIL_LINKED_RETRY_LOGIN` (the Google account was just linked to an existing password account), it automatically retries **once**.

**`/forgot-password`:**

- One email field → `POST /password-recovery` → `/reset-password`, remembering the email.
- Errors: "Too many attempts. Please try again in a few minutes." / "We could not start the recovery. Please try again."

**`/reset-password`:**

- Fields: email, code (prefilled from `?code`), new password and confirmation.
- Validation in order:
  1. Valid email.
  2. Code required: "Enter the code we sent you."
  3. Password ≥ 8.
  4. They match: "The two passwords do not match."
- Errors:

  | Code | Message |
  |---|---|
  | `INVALID_RESET_CODE` | "That code is not valid. Check it and try again." |
  | `EXPIRED_RESET_CODE` | "That code expired. Request a new one." |
  | `INVALID_PASSWORD` | "That password does not meet the requirements." |

- Success: toast "Password updated. Sign in with your new password." and redirect to `/login`.

**`/logout`:** confirmation card "Sign Out?".

**`/internal/qa-access`:** QA access with the old login. Optional.

### 10.3 Onboarding (`/onboarding`)

**When it appears:** for an account with `onboarding_completed:false`.

- **Fails open:** a super-admin, a missing account or a network error count as onboarding completed.
- Header "8–12 minutes" with the link "Explore on my own", which sets the flag and goes to `/ai-experience`.
- 7-step progress bar.

**Step 1. Goal:** "What do you want to achieve with Mappi?". "Continue" disabled until one is chosen.

| Goal | Description | Label |
|---|---|---|
| Diagnose | "Measure a situation and deliver a level, recommendations, and a plan." | "Scoring + levels" |
| Qualify or recommend | "Direct each person to the right service or next step." | "Segments + CTA" |
| Capture processes | "Understand how a team works through text, audio, and follow-ups." | "Evidence + follow-ups" |
| Collect information | "Create a structured survey without mandatory scoring." | "Regular survey" |

**Step 2. Workspace:** name, language and website. [DEBT] This data is not sent to the backend.

**Step 3. Template:** recommended according to the goal.

| Goal | Template |
|---|---|
| Diagnose | AI Maturity Diagnostic: 8 questions; categories Estrategia, Datos, Procesos, Talento, Cultura, Tecnología (Strategy, Data, Processes, Talent, Culture, Technology) |
| Qualify | Service Qualification: 6 questions |
| Capture processes | Process Discovery: 7 questions, mostly free text |
| Collect information | Customer Discovery: 6 questions |

- The template questions are in Spanish.
- "Use template" or "Start from scratch →" (the latter completes onboarding).
- Slug = `slugify(title)-` + 4 random hex characters.

**Step 4. Builder:**

- Checklist: "Confirm your questionnaire title", "Edit a question to fit your context", "Review what the person will receive at the end".
- The first 4 questions can be edited inline.
- "Save and publish" is enabled once the checklist is complete; after confirming, it creates the questionnaire.

**Step 5. Publish:** editable slug with the prefix `{FRONTEND_URL}/f/`. "Publish and open test" activates the questionnaire.

**Step 6. Test:**

- Opens `{publicUrl}?test=1` in a new tab.
- Polls the answers (`limit=1`): first at 5 s and then every 4 s until one appears.
- Shows "processing" for 3 s and then "ready".

**Step 7. Result:**

- Share link (copies), Customize more → `/customization`, Invite team → `/users/new`, Go to dashboard → `/ai-experience`.
- Every exit first sets `completed:true`. If it fails: "We couldn't finish your setup…".

### 10.4 AI creation (`/ai-experience`, home page)

**Access:** write permission and the `chat` feature. `/` redirects here.

**Interface:**

- Full-screen chat with live preview. Buttons: Back, "New chat" and show/hide preview.
- Heading "What do you want to create today?". Greeting "Hi! How can I help you today? ✨".
- Composer:
  - Enter sends; Shift+Enter inserts a line break.
  - Maximum 20,000 characters; the counter appears past 90 %.
  - Placeholder "Type your message…".
- No cap on the conversation: the screen keeps every message, and each turn sends only the **last 40** (the oldest
  dropped first, the window starting with a user message). The draft and the pending writes travel apart, so they are
  never dropped.

**Each turn:**

1. `POST /chat` and polling of the job every 2 s, with a 5 min limit.
2. Depending on the result:
   - **`chat-questionnaire-created`:** "Questionnaire created" card with "Edit questionnaire" and "View questionnaire".
   - **`draft`:** feeds the preview.
   - **`quick_replies`:** one-click buttons.
   - **`actions`:** all caches are invalidated. If the account language changed, the UI switches language.
   - **Action with `job_id`** (styles): polling every 2 s up to 5 min. Visible steps: "Reading your website" → "Choosing colors and fonts" → "Saving your styles". Ends with the palette, the font and "Open Customization".
3. Clicking an entity link sends "Show me the details of the {kind} {name}" along with `item`.
4. If it fails: "Something went wrong processing your message." with "Retry" (no Retry when the error is a plan limit).

**Preview:** walks through disclaimer → landing → questions (they can be answered) → ending. A diagnostic ending is scored live with the preview's answers. "Watch again" restarts it.

### 10.5 Manual questionnaire creation

**`/questionnaires/new`:** "What do you want to create?"

| Card | Description | Label | Feature |
|---|---|---|---|
| Regular (Default) | "A classic questionnaire that ends with a custom thank-you message of your choice." | "Best for surveys" | `regular` |
| Diagnostic | "Score each respondent and place them into tiers, each with its own result and action plan." | "Best for assessments" | `diagnostic` |
| Quiz Funnel | "Turn store visitors into buyers with a guided quiz that recommends the right product at the end." | "Imports from your store" | `quiz-funnel` |
| Chaining | "Add your questions plus a prompt; the answers and prompt are used to generate a tailored questionnaire." | "Best for AI generation" | `chain` |

- A card the plan does not allow is disabled with the limit text.
- Redirects: `/design-experience` → `/questionnaires/new`; `/questionnaires/create/chat` → `/ai-experience`.

**Creation container** (shared by Regular, Diagnostic and Chaining):

- Header: breadcrumb; chip "Draft · saved when you create it" or "Editing · saved when you save changes"; 3-step stepper; preview toggle; Back; main action ("Continue" / "Create" / "Save changes").
- A disabled action shows **why** in a tooltip.
- A step can only be reached if the previous ones are valid; when editing, all are available.
- **There is no autosave.**
- Creating asks for confirmation: "Create the questionnaire?" / "Nothing has been saved yet. Once you confirm, the questionnaire is created in your account." / "Yes, create". Editing saves without asking.
- Preview in a mobile or desktop frame. Below 1100 px it opens as a side panel.

**Step 1. Details:**

| Field | Rule |
|---|---|
| Title | Required. "Write a title to continue." |
| Slug ("Custom link (slug)") | Hint "Lowercase letters, numbers and hyphens only. Leave empty to generate it from the title." Rule from §6.6. Conflict: "That custom link (slug) is already in use by another questionnaire. Choose a different one." |
| Description | Optional |
| Enable landing page | Toggle; on by default for new questionnaires |
| Disclaimer | Toggle + text; if on, the text is mandatory: "Write the disclaimer text to continue." |

**Step 2. Questions:**

- Reorder by dragging. Dropping onto another category moves the question to that category. Questions are grouped by category.
- Add, duplicate and delete.
- **Fields per question:**
  - Title (required), description, disclaimer.
  - Category: combobox that allows creating new ones.
  - Required: on by default; locked on for the scorable types of a diagnostic.
  - Input type: `radio` "Single selection", `checkbox` "Multiple selection", `select`, `ranking`, `selection_with_score`, `single_selection_with_score`, `text`, `audio`, `range`, `message`, `file`.
- **Extra fields by type:**

  | Type | Extra fields |
  |---|---|
  | `text` / `audio` | Maximum follow-ups 0–5. If > 0: acceptance criteria (up to 10) |
  | `text` | Data type: "Free (choose characters)" with All / Letters / Numbers / Symbols checkboxes (at least one checked), or preset RFC / NIT / "Phone / WhatsApp". Stored as `{type:'format', value, message:''}` |
  | `range` | Min and max, stored as `min` and `max` validations |
  | Option types | Labeled options; scorable ones also carry a numeric score per option |

- **Validation messages:**
  - "Question {{n}} must have a title."
  - "…needs at least one answer choice."
  - "All answer choices in question "…" must have a label."
  - "…must have a numeric score."
  - "…must have a unique score value."
  - "…range input: both min and max are required and min must be lower than max."
- **Encoding:**
  - Non-scorable option: `value = slug(label)`. Scorable: `value = score`.
  - Scorable types travel as `checkbox`/`radio`. On load, a radio or checkbox whose values are all numeric is read as scorable.

**Step 3 by type:**

- **Regular: "When it ends".** Available blocks:
  - Thank-you message: Title and Message ≤ 300 each, sent as `result_copy.title/subtitle`.
  - Call to action.
  - Capture data (`capture_user_data`).

  CTA validation:

  | Field | Rule | Message |
  |---|---|---|
  | Title | Required, ≤ 120 | "The call to action title is required." / "…too long." |
  | Description | ≤ 200 | "The call to action description is too long." |
  | Button text | Required, ≤ 50 | "The button text is required." / "…too long." |
  | URL | Starts with `http(s)://` | "Enter a full URL starting with http:// or https://." |

- **Diagnostic.**
  - Question rules:
    - All with a category: "Every question needs a category — your tiers are built from these categories."
    - Allowed types: single or multiple selection (with or without score), ranking, range, text, audio and file.
    - At least one scorable (`selection_with_score`, `single_selection_with_score`, `ranking`, `range`).
  - **Maximum:**
    - Per question: sum for checkbox, `selection_with_score` and ranking; maximum for the rest (includes range).
    - Category = sum of its questions. Total = sum of all.
  - **Results:**
    - Seed tiers Beginner / Intermediate / Advanced spread evenly over 0..max. Each tier has a name, from, to and description.
    - Tier rules:

      | Rule | Message |
      |---|---|
      | Named | "Give every tier a name." |
      | Complete range | "Fill in the score range (from and to) for every tier." |
      | from ≤ to | "A tier's "from" score can't be greater than its "to" score." |
      | The first starts at 0 | "Your first tier must start at 0." |
      | The last reaches the maximum | "Your tiers must reach the top score of {{max}}." |
      | Contiguous | "Tiers can't leave gaps or overlap — each one must start right after the previous." |

    - Blocks: Tier, Total score, Score by area, Recommendations (one per tier), Action plan (one per tier), PDF report, CTA, Capture data.
    - Saved as flow `layout` + `on_completed` of type `diagnostic` + `result_copy` (15 texts).

- **Chaining: Prompts.**
  - Timeline "Starting point" → Prompt N → Questionnaire N, up to **10** prompts.
  - Ending: "Another questionnaire", "Finish" (→ `result`), "Diagnostic" (→ `diagnostic`) or "Quiz funnel" (→ `quiz_funnel`).
  - Prompts cannot be left empty.
  - On save:
    1. The `chain` feature is revalidated live.
    2. The text of each prompt is uploaded to object storage (`POST /signed-urls` with `upload_type:'prompt'` + direct upload).
    3. The flow is saved with `prompt` states pointing to those keys.

**Success screen:**

- "Questionnaire created successfully!" / "Your diagnostic is ready." / "Changes saved".
- Buttons: Copy link, View questionnaire, Keep editing, Go to Questionnaires, Create another.

**Quiz Funnel** (`/questionnaires/create/quizfunnel`, feature `quiz-funnel`). Steps: Store → Products → Generate.

- **Via e-commerce platform:**
  - The store is never typed by hand: it comes from `?shop=` (captured at startup and validated) or from `GET /shopify/connection`.
  - Authorize / Reconnect opens the OAuth URL in a new tab. "Install" links to the app installation.
  - "Load products": syncs and lists the products.
  - After creating: steps to enable the embed in the store theme (open the theme editor, save and visit the store).
- **Via website:**
  - Store URL: the scheme is added if missing; the host must contain a dot.
  - Number of products 5 / 10 / 20 / 30 (default 10).
  - Scraping via job with polling every 5 s up to 5 min.
  - Rotating messages: "Analyzing the website...", "Analyzing the online store...", "Polishing your catalog...", "Fixing some details...", "Organizing all the information...".
  - Products can be removed (confirmation "Are you sure you want to delete your product?").
  - Loading products is optional: Generate does the scraping if needed.
- **Generate:**
  - Type "Design Experience" (`experience`) or "Profiling" (`profiling`).
  - Job with polling of up to 5 min. Result `{questionnaire_url, flow:{id, slug, questionnaire_id}}`.
- **Errors:**
  - "Please enter a valid store URL"
  - "Could not access URL. Please ensure it is a public store."
  - "Generation failed. Please try again."
  - "We couldn't start the Shopify connection…"
  - Dedicated messages for `SHOPIFY_NOT_CONNECTED` and `SHOPIFY_TOKEN_EXPIRED`.

### 10.6 Questionnaire listing (`/questionnaires`)

**Header:** "Questionnaires", "{{count}} questionnaires" and the "New Questionnaire" button.

**Toolbar:**

- Search (shortcut ⌘/Ctrl+K). [DEBT] It only filters the loaded rows; the backend already supports `search`.
- Type: All types / Standard / Quiz funnel / Diagnostic / Process mapping.
- State: All status / Active / Inactive.
- Sort: "Sort: Created" / "Sort: Updated", Descending / Ascending.
- Time zone Local / UTC (persistent preference).

**Pagination:** 10 / 20 / 50 / 100 per page (default 10). Numbered pages with a window of 5 and "{{start}}–{{end}} of {{total}}".

**Columns:**

- Title: link to edit, or to the public page for read-only users. Below it, "{{count}} questions".
- Type: Standard / Quiz funnel / Diagnostic / Process mapping / Chaining.
- State with an Active toggle: optimistic, reverted if it fails.
- Creation or update date, depending on the sort.
- Actions: View (new tab), Copy link, Edit, Answers and "Analytics" (with a "New" badge) that opens the dashboard.

**Empty states:**

- "No questionnaires created yet" / "Create your first questionnaire to start collecting responses."
- "No matches" + "Clear filters".
- "Nothing matches "{{query}}"." + "Clear search".

### 10.7 Editing (`/questionnaires/:id/edit`)

**Load:** the questionnaire, the flow (slug, states, cta, layout, result_copy) and all answers, to know whether it is locked.

**Without answers**, it redirects to the specific editor:

| Type | Route |
|---|---|
| Diagnostic | `/:id/edit/diagnostic` |
| Chain | `/:id/edit/prompt` |
| Regular | `/:id/edit/regular` |

**Other cases** (generic editor): title, slug with preview `…/f/{slug}`, description, data capture, landing, disclaimer, CTA, questions and the "Update" button.

**Locked** (has answers, or saving returns 409 without a slug code):

- "Locked" badge and the text "Locked to preserve answers".
- "Create a copy" → confirmation "Create a copy?" / "A new questionnaire is created in your account, with no answers, ready to edit." / "Yes, create copy" → copy.

### 10.8 Answers

**`/questionnaires/:id/answers`:**

- Header: "Questionnaire Answers: {title}". Clicking the title copies the public link.
- **Export to Google Sheets** (§13.9).
- State legend: Filling / Filled out / Processing / Completed.
- **Filters:** state (default Completed, or All statuses), time zone, page size 100 / 50 / 20 (default 100).
- **Pagination:** cursor-based (Previous / Next, "Page N"). The total is obtained by walking all cursors.
- **Columns:**
  - Started At.
  - Name ("Anonymous" if none).
  - Email ("N/A" if none).
  - Phone (only if some row has one).
  - Progress: answered/total, not counting meta topics.
  - State: 4-segment bar, or "Stage X of N" for chains.
  - "View".
- **Chains:** hint "The status filter applies to the first stage; the chain may still be in progress."
- Old generated child stages appear in the "Generated questionnaires" card.

**`/questionnaires/:id/answers/:sessionId`:**

- "Response Details - {{name}}".
- **Summary:** Email, Name, Phone, Total Time (seconds, or "Uncompleted") and Started At.
- **Session chain:** if it responds 403 or 404, the standalone session and its results are used.
- **Per stage:** Question / Answer / Time Spent table (difference between answer timestamps). Special values: "Not answered", "Skipped" or "Viewed".
- **Files:** one cell per key. "View" opens a preview modal and "Download" downloads; both request a signed URL on click.

  | Type | Extensions | Behavior |
  |---|---|---|
  | Image | png, jpg, jpeg, gif, webp | Preview |
  | PDF | pdf | Preview |
  | Audio | mp3, wav, m4a, ogg, aac | Preview |
  | Video | mp4, mov, webm | Preview |
  | Other | — | "No preview"; download only |

- **Result card:**
  - Products: name, link and price.
  - Diagnostic: score/maximum, tier, bars per category, and the tier's recommendations and plan.
  - AI profile.
  - If none: "No result was generated for this session".
- The back link returns to the assignation if it was reached from there.

### 10.9 Dashboard (`/questionnaires/:id/dashboard`, feature `analytics`)

**Data:** `GET .../dashboard` (layout) and `GET .../dashboard/data`:

```
sessions: { total, completed, completion_rate (0..1),
            timeline: [{date, started, completed}],
            by_source,
            duration_seconds: {avg, median} }
questions: [{ question_id, answers_count,
              values: [{value, count}],
              numeric: {count, avg, min, q1, median, q3, max} }]
```

**Summary** (free on all plans):

- Completion rate = `round(completion_rate×100)` %, with "X of N sessions completed".
- Sessions = total.
- Average time = `round(avg)` formatted as "M min S s".
- Biggest drop-off = the question Qn with the largest drop relative to the previous step; percentage = `round(drop/previous×100)`.

**Funnel:** Started → each question in order → Completed.

- Reach of a question = `max(answers to that question or any later one, completed)`, capped at total. This way the funnel never goes up.
- `pct = round(count/total×100)`.
- With more than 6 questions, stretches with no drop are grouped into "Qa–Qb · no drop-off", with "Show all N questions".
- Message: "Biggest drop at Qn" or "…at the end".

**Charts** (13 types):

- Distributions are sorted by count and group the tail into "Other": 6 slices by default, 8 in bar, 12 in horizontal bar.
- The histogram fills every integer of the scale if the range is ≤ 30.
- Gauge: fraction = `(avg − min)/(max − min)`.
- Heatmap and stacked bar: columns per option label.
- **NPS** (0–10 or 1–10 scales): detractors ≤ 6, passives 7–8, promoters 9–10; `NPS = round((promoters − detractors)/total×100)`. Other scales are split into thirds: low, medium, high.

**Locked** (without `dashboards`): a blurred sample dashboard with the plan text and "Get your dashboards" → `/profile/plans`.

**States:**

- Loading: rotating message every 4.5 s ("Gathering the information…", "Analyzing the data…", "Generating the report…", "Finishing up…"). The first generation takes between 10 and 25 s.
- Empty: "No answers yet" / "Charts appear here as soon as people start answering this questionnaire."
- Errors: 4xx are not retried; generation or analytics failures show "Try again".

**Type badge:** Satisfaction / Knowledge / Profiling / Recommendations / Eligibility / Opinion.

### 10.10 Organizations

**`/organizations`:** card grid.

- Each card: initial with a color derived from a hash, name (truncated to 16), Active/Inactive, domain, number of members. View, Edit and Delete buttons.
- Delete: "Delete organization?" / "This will permanently delete "{{name}}" and cannot be undone."
- "New Organization" requires write permission and the `organizations` feature.
- Empty: "No organizations yet. Create your first one."

**`/organizations/new` and `/:id/edit`:**

- **Fields:** Name (required: "Name is required"), Email domain (placeholder "ejemplo.com"), Description, Active (default yes) and the member list.
- **Member:** name required; email or phone ("Each member needs at least an email or a phone"); role and area optional. The email must be valid.
  - Duplicates by normalized email or phone: "This member is already in the list".
  - They are edited inline and can be removed.
- **Normalization:** name without accents, lowercased and with collapsed spaces; email trimmed and lowercased; phone as digits with `+`.
- **Domain warning** (non-blocking): "{{count}} member(s) use a domain other than {{domain}}. They will be saved anyway."
- **CSV import:**
  - The UTF-8 BOM is stripped.
  - Delimiter `,` or `;`: whichever appears most in the header.
  - Supports quoted fields.
  - Header aliases: name/nombre; email/correo; phone/telefono/teléfono; role/rol/cargo; area/área.
  - Missing: "The CSV must include a 'name' column" / "…at least an 'email' or 'phone' column".
  - Skipped rows: "Row {{line}} — {{name}}: {{reason}}". Reasons: empty name, invalid email, no email or phone, already in the list.
  - Success: "Imported {{count}} members".
- **CSV template:** `organization_members_template.csv` with columns `name,email,phone,role,area`.
- **Save:**
  - Create and then go to the detail.
  - Update by sending the full member list (existing ones keep their id).
  - Errors point to the row: "Member {{position}} ({{name}}): {{detail}}".

**`/organizations/:id/view`:** detail (description, domain, created, updated), members table (Name, Email, Phone) and the Edit button.

### 10.11 Assignations

**`/assignations`:**

- **Type filter:** All / Default / Follow-up.
- **Pagination:** fixed at 10 per page, numbered.
- **Columns:**
  - Name, with the organization line (link) and the questionnaire line (link to edit if permitted).
  - Audience chip: "Everybody", "2 people", "Area: Sales +1".
  - "Attempt N" chip when a follow-up has more than one attempt.
  - Type (only in All).
  - Progress: "{{completed}} of {{total}} people/questions" with a bar, or "Completed".
  - Due (only in Follow-up): date and urgency label.
  - Created.
  - Active toggle.
  - Actions: View, Edit, Copy link, Delete and "Send reminder" (follow-up only; disabled if complete).
- **Delete:** "Delete assignation?".
- **Reminder:** "Send the reminder now?" / "An email with the link to "{{name}}" will be sent to the people who answer it." → toast "Reminder sent to {{count}} recipient(s)".
- **Due date urgency** (whole calendar days):

  | Level | Days remaining | Label |
  |---|---|---|
  | later | > 14 | "in N days" |
  | soon | 8–14 | "in N days" |
  | near | 3–7 | "in N days" |
  | urgent | 0–2 | "due today" (0) or "in N days" |
  | overdue | < 0 | "overdue by N days" |
  | done | Completed | "completed" |

**`/assignations/new` and `/:id/edit`** (creating requires the `assignations` feature). Wizard Basic → Registration → Save.

- **Basic:**
  - **Type** (only when creating):
    - Default: "The members you pick answer, each with their own session".
    - Follow-up: "The members you pick share one session and receive a daily reminder until it is completed".
  - **Due date** (follow-up only, optional, may be in the past). Hint: "The day this follow-up should be completed. Reminders keep arriving until it is."
  - **Organization\***: client-side search across all words, case- and accent-insensitive.
  - **"Who responds?"** (disabled without an organization):
    - Everybody; People (checkboxes with search by name, email, area or role); Area; Role.
    - Area and role list the distinct values with a count of people.
    - Live counter: "N people will respond".
    - Changing the organization resets the audience to Everybody.
  - **Questionnaire\***: server-side search with a 300 ms debounce and infinite scroll in batches of 20; only the latest response is kept.
  - **Name\***.
  - **Description:** "An internal note about this assignation. Respondents never see it."
  - **Validations:** "Organization is required", "Check at least one person, area or role, or choose Everybody", "Questionnaire is required", "Name is required", "Enter a valid due date".
- **Registration:** configures the login slide.

  | Field | Visible by default | Required by default |
  |---|---|---|
  | Full name | Yes (fixed) | Yes (fixed) |
  | Email | Yes | Yes |
  | Phone | No | No |
  | Role | No | No |
  | Area | No | No |

  - Email or phone must be visible and required: "At least one of email or phone must be required".
  - It is built as a question with the `user-capture-data` topic.
- **Save:**
  - Sends `max_follow_ups: 2`. `due_date` only for follow-up (null clears it when editing). `type` is never sent when editing.
  - Final: "Assignation created!" / "Assignation updated!", share link with Copy and "Go to Assignations".

**`/assignations/:id` — default type:**

- **Header:** name; "org · X of N people · N completed · N pending" (with "+" while pages remain to be loaded); badge; audience; Copy link; Export CSV; Export to Google Sheets.
- **Respondents:** infinite scroll in batches of 20 with "Load more".
- **Search** by name or email over what is loaded. Hint: "Search only covers the respondents loaded so far. Load more to search the rest."
- **Sections:** Completed and Pending.
- **Columns:** Name, Email, Attempts, Status (chain pill) and "View answers". The attempt history expands per member.
- **CSV:** columns Name, Email, Attempts, Status. Loads all pages first. File `{name}.csv`.
- **Sheets:** the questionnaire's sessions filtered by the assignation.

**`/assignations/:id` — follow-up type:**

- **Header:** Follow-up badge, audience, review state (In review / Changes requested / Approved), due date and Copy link.
- **Main action:** "Send reminder" while not complete; "Send for correction" when it is complete and there is a review state.
- **Current question:** "On question {{n}} of {{total}}" / "Nobody has opened the follow-up yet" / "Every question is answered".
- **Shared session table:** #, Question, Answer, Answered, Review, with "View answer".
- **Review dialog:**
  - Shows the answer, a comment (≤ 1000, optional), Reject / Approve and previous/next arrows.
  - After deciding, it jumps to the next unreviewed one. When finished: "Every answer of this attempt is reviewed".
  - Only the current attempt can be reviewed, when it is complete and with write permission.
- **States per answer:** Not reviewed / Approved / Rejected / "Approved before" (locked in a previous attempt).
- **Send for correction:**
  - Only with `review_status = changes_requested`.
  - The dialog lists the rejected answers with their comments.
  - Toast "Attempt N sent to M recipients". With `502 RETRY_EMAIL_NOT_SENT` it reloads anyway, because the attempt already exists.
- **Attempt selector** in the URL (`?attempt=N`). Previous attempts are read-only.
- **Next-step notices:**
  - "You rejected N answers — …can't correct them until you click Send for correction"
  - "Review every answer … · N left"
- **Members card:** "Who can carry it on (N)".

### 10.12 Projects

**`/projects`:**

- **Tabs:** All, To review, In progress, In correction, Overdue, Completed.
- **Search** with a 300 ms debounce. 10 per page, newest first.
- **Columns:**
  - Project: chevron, organization initials, name, organization and "created {{date}}".
  - State: Not started / In progress / Needs your review / In correction / Completed / Overdue / No assignations.
  - Assignations: "{{approved}} of {{total}} approved" with a bar.
  - Deadline with the urgency levels.
  - Next step: Review answers / Open overdue / See correction / See progress / See results / Add assignations.
  - ⋯ menu: Edit, Delete.
- **Expanded row:** Assignation, Answers ("Question 4 of 8" / "X of N questions"), Review ("R of T reviewed", "N sent back to the client"), Status and Review/Open.
- State **legend**.
- **Delete:** "Delete this project?" / "…Its assignations and their answers are kept; they just stop belonging to a project."
- **Edit (dialog):**
  - Name required ≤ 200.
  - Organization read-only.
  - Description ≤ 2000.
  - Deadline required; it can be moved but not cleared ("Choose a deadline for the project" / "Enter a valid deadline").
  - Available assignations = the organization's follow-ups that are not in another project.
- "New project" requires the `assignations` feature included in the plan.

**`/projects/new`:** 3-step wizard; nothing is saved until "Create".

1. **Questions:** chat in `draft` mode (approving the draft saves the questionnaire) or pick an existing questionnaire.
2. **Organization:** pick or create one (dialog). Audience. The default assignation name is "{org}: {title}".
3. **Project:** pick one of the organization's or create one (default name = questionnaire title; deadline required).

- Summary panel: "What we're going to create".
- **On create, in order**, remembering each id so that a retry duplicates nothing:
  1. New organization, if applicable.
  2. Questionnaire (slug = `slugify(title)` + 6 hex, ≤ 100). A questionnaire other organizations already have is assigned as it is.
  3. Follow-up assignation with `max_follow_ups: 2` and the default registration.
  4. Create the project, or update it with its assignations + the new one.
- Toast "Done: questionnaire, assignation and project created".

### 10.13 Customization (`/customization`, saving requires the `styles` feature)

**Fields:**

- Website URL. Hint: "Don't worry — we'll fetch the styles from your website automatically…".
- Logo URL with preview.
- Font, one of 6: Inter, Roboto, Poppins, Montserrat, Playfair Display, Lora.
- Brand color (`#RRGGBB`). From it are derived:
  - primary button background;
  - hover 12 % darker;
  - readable text: `#0F172A` if the luminance is > 0.6, otherwise white;
  - link color;
  - input focus border.

**Behavior:**

- Live preview of what the respondent sees.
- **Reset:** only restores the default values locally. Toast "Reset to default values".
- **Save:** styles job with polling every 5 s up to 2 min.
  - If the website changed, only `{website}` is sent.
  - Otherwise, `{website, styles}`.
  - Then the profile is reloaded. Toast "Styles updated successfully".

### 10.14 Profile (`/profile`)

**"Plan & usage" tab:**

- Plan name and Active/Expired.
- Usage bars: "Questionnaires (all types)", "Responses" and one per feature. Negative limit = "Unlimited". Amber from 75 % and red from 90 %.
- "Change plan" / "Choose a plan" → `/profile/plans`.
- "Manage billing" (only if there is a subscription) → gateway portal in the same tab.

**"Settings" tab** (requires the `profile` feature and write permission):

- Account language (`es-CO` / `en-US`). Affects emails and respondent screens, **not** the console UI.
- "Maximum files per question": integer 1–20, default 10. Error "Enter a whole number from 1 to 20."; Save disabled while invalid.
- Tracking (each ≤ 64): Meta Pixel ID; LinkedIn Partner ID and Conversion ID; Google Ads Conversion ID (AW-…) and label.
- **Submission:** only the modified tracking fields (empty → null), `max_files` only if it changed, and `language` always.

### 10.15 Plans (`/profile/plans`, never blocked)

**Plan card:**

- Price from minor units with local currency formatting. No price: "Price on request".
- Limits: "Experiences" (`max_questionnaires`), "Responses" and each feature.
- Button: "Subscribe", "Upgrade" or "Switch to this plan" (price ≤ current). A non-purchasable plan shows "Get in touch", which opens the contact form.
- "Buy yearly" / "Switch to yearly" with "Save {{percent}}%", where `percent = round((1 − yearly/(monthly×12))×100)`.
- **Badges:** "{{count}} days free" (if trial-eligible and `trial_days > 0`), "Current plan · Monthly/Yearly", "Next plan" with "Starts on …".
- **Notes on the current card:** "Renews on …", "N days left · until …", "Ends on …", "Free trial until …", "{{value}} off until/forever".

**Checkout:**

- Without a plan: start checkout and redirect in the same tab.
- With a plan: plan change. If the response is `checkout`, redirect; if it is `changed`, show the result.

**Confirmations:**

- Downgrade or going back from yearly to monthly: "Switch to a smaller plan?" / "Go back to monthly billing?".
- Cancel: "Cancel your subscription?" with "Keep my plan".

**Without confirmation:** "Resume subscription" and "Keep my current plan" (revert).

**Other:**

- Return from the gateway with `?checkout=success|cancel`: notice, plan and usage refresh, and the parameter is removed.
- Hint: "Have a promo code? Apply it at checkout."
- **Contact:** valid email and phone required. Success: "Request received / You'll be contacted soon."

### 10.16 Users

**`/users`:**

- Columns: User (initials and name), Email, Role (Admin / Read only) and Type (Owner = root, otherwise Member).
- Empty: "No users yet. Invite your first team member."
- "New user" requires write permission and the `users` feature.

**`/users/new`:**

- Fields: Full name\*, Email\*, Password\* (≥ 8, with meter) and role as cards:
  - Admin: "Full access, including creating other users."
  - Read only: "Can view resources but cannot make changes." (default)
- The permission matrix from §4.2 is shown.
- Errors: `EMAIL_ALREADY_EXISTS`, `INVALID_ROLE`, `FORBIDDEN`, `VALIDATION_ERROR` and a generic one.
- Toast "User {{name}} created" and back to `/users`.
- Without permission: "Admins only".

### 10.17 Integrations (`/integrations`, 3 tabs)

**API keys** (require write permission and the `api` feature included; an exhausted quota does not block key management):

- Rows: Name, Created, Expires ("Never"), Last used ("Never") and Revoke ("Revoke API key?").
- Create: name (required, ≤ 100) and expiration 7 / 30 / 60 / 90 days or Never (default 7).
- The plaintext key is shown **only once**, with a Copy button.

**Webhooks** (`webhook` feature included):

- Rows: URL, event ("Response completed"), method `POST`, Edit and Delete.
- The URL must start with `https://`.
- Delivery reference with the headers and the sample payload (§7.14).

**API reference:** static documentation of the external API (§8.11) with copyable `curl` examples.

### 10.18 Documentation (`/documentation`)

**Guides:**

- 15 static bilingual guides across 7 topics: getting-started, questionnaires, organizations, projects, analytics, brand-integrations, account.
- Case- and accent-insensitive search and filter by topic.
- Reading time at 200 words/min.
- Each guide (`/documentation/guides/:guideId`) has previous/next, a table of contents and per-language screenshots.

**Videos:** list from `GET /videos?language=`, embedded in privacy-enhanced mode.

### 10.19 Hidden routes

`/products` (no sidebar entry):

- Listing and CRUD of the product catalog.
- E-commerce platform card (Authorize, Install, "Sync Products Now").
- "Create Experience" creates a quiz funnel.

### 10.20 Assume customer (super-admin only)

- **Selector** at the top of the sidebar:
  - Search with a 300 ms debounce and "Load more" (20 per page).
  - Shows name and email.
  - Selection is explicit: click, or Enter after moving with the arrow keys.
- The choice is stored in the browser.
- **While active:**
  - Every request except `/admin/*` sends `X-Assume-Customer-Id`.
  - The effective user becomes `Customer-Admin` + root.
  - Banner "Viewing as {{name}} ({{email}})" with "Stop assuming".
  - The entire cache is cleared and the content is remounted at `/ai-experience`.
- If the API responds `ASSUME_NOT_ALLOWED`, `CUSTOMER_NOT_FOUND` or `ASSUMED_CUSTOMER_NOT_FOUND`, assuming stops automatically.

### 10.21 Plan alerts in the console

- Usage (`GET /customer/usage`) is loaded once per account.
- **429 `PLAN_LIMIT_REACHED`:** amber toast with the reason text and a usage refresh.
- **Usage banner:**
  - Appears when some row reaches 50 % (`used/limit`, capped at 100; limit 0 = 100 %).
  - Tiers 50 / 75 / 90 / 100; red from 90.
  - Dismissing it silences it until the next tier. Not persisted.
  - Text: "You're using {{percent}}% of your plan." with "See all (N)" and "Upgrade".
- **Notifications:** request failures use a single component: amber for plan limits, red for the rest, with the backend code's text first ([Appendix B](#appendix-b--error-code-catalog)). Client-side validations use destructive toasts.

### 10.22 Dates

- A backend timestamp without a zone is interpreted as UTC.
- Calendar dates (`YYYY-MM-DD`) are interpreted as local midnight.
- The Local/UTC preference applies to the listings.

---

## 11. Asynchronous jobs and scheduled tasks

| Worker / task | Trigger | Time limit | Notes |
|---|---|---|---|
| Generic job worker | Queue | 10 min | Evaluation, prompts, quiz funnel, scraping, LinkedIn, e-commerce sessions, chat |
| Styles worker | Queue | 5 min, no retries | Needs a headless browser (~2 GB of RAM) |
| Analytics events worker | Queue | — | Sends events to the usage/analytics service |
| Webhook dispatcher | Event bus (pub/sub) | 3 s connect, 5 s read | No retries |
| Reminders | Daily at 13:00 UTC | — | §7.13 |
| Identity provider triggers | Before sign-up and before token issuance | — | Google linking, automatic account creation on first Google login, `UserSignedIn` event and last-session timestamp (excludes machine clients) |

**Client-side polling:**

| Job | Interval | Limit |
|---|---|---|
| Chat turn / background chat job | 2 s | 5 min |
| Quiz funnel, scraper | 5 s | 5 min |
| Styles | 5 s | 2 min |
| Evaluation, prompt, completion (respondent) | 5 s | 120 attempts (~10 min) |
| Onboarding test | 5 s, then 4 s | No limit |

A job ends when its state leaves `PENDING`/`PROCESSING`: `COMPLETED` is success; `FAILED` and `CANCELLED` are failure. In the respondent app, **evaluation fails open**; prompt and completion throw an error.

---

## 12. Domain events

Emitted to the usage/analytics service. Some carry `tags.feature` to count usage (§7.2):

`UserRootRegistered`, `UserCreated`, `UserSignedIn`, `ProfileEdited`, `QuestionnaireCreated`, `QuestionnaireSessionCreated`, `QuestionnaireSessionUpdated`, `QuestionnaireSessionCompleted`, `AnalyticsFetched`, `DashboardGenerated`, `OrganizationCreated`, `OrganizationDeleted`, `AssignationCreated`, `AssignationDeleted`, `ApiUsage`, `FeatureCreated`, `SubscriptionCreated`, `SubscriptionRenewed`, `SubscriptionCancelled`, `PlanChanged`, `TrialWillEnd`, `PaymentFailed`.

In addition, styles and webhook usage is counted on completion.

---

## 13. External integrations (by capability)

### 13.1 Identity provider

- Username = email, automatically verified.
- Password policy: **minimum 8 characters, no character-type requirements**.
- Temporary password valid for 7 days. Recovery only via verified email, with an attempt limit.
- Tokens: id and access tokens last **24 h**; refresh token lasts **30 days**.
- Login with email + password and **federated with Google** (OAuth code + PKCE, scopes openid, email and profile).
- Custom attributes: `customer_id`, `root`. Groups = roles.
- **First Google login:**
  - Automatically confirms the user and creates the account (new `customer_id`, root, `Customer-Admin`, plan `starter`, `onboarding_completed=false`).
  - Language = Google's language, or `es-CO`.
  - If a password account with that email already exists, it links them and asks the user to retry the login (`EMAIL_LINKED_RETRY_LOGIN`).
- There is a machine client for internal processes; its sign-ins do not count as customer sessions.
- **Assignation respondent token:** signed by the API with its own secret. Contains `assignations_id`, `organization_user_id` and `session_id`. [DEBT] It does not expire.

### 13.2 Payment gateway

Monthly and annual subscriptions, hosted checkout, billing portal, scheduled changes (for downgrades), coupons and promotion codes, free trials and signed webhooks. Rules in §7.4.

### 13.3 Language model (LLM)

Must support **structured output** (JSON with schema). Uses:

| Use | Model |
|---|---|
| Generate questionnaires (quiz funnel, chain, LinkedIn, chat) | Configurable via `AppSetting` (the most capable one) |
| Recommend products, evaluate answers | Fast, low-cost model |
| Choose the dashboard, design styles, chat with tools | — |

System instructions are read from the editable system prompts (§7.20).

### 13.4 Real-time speech transcription

- Streaming transcription from the browser with an ephemeral token (~1 min) issued by the API.
- Low-latency primary transport and an alternative transport via a per-account configurable URL (`transcription_url`); today audio is sent as PCM16 at 24 kHz.
- Partial and final text events.
- Language es or en.

### 13.5 Object storage

| Container | Access | Use |
|---|---|---|
| Answer files | Private. Signed upload and download (15 min). CORS for PUT/POST | Key `{customer_id}/{session_id}/{question_id}/{md5}{ext}`; up to 500 MB |
| Prompt / media files | Public read | Prompt texts for the chains |
| System prompts | Private and versioned | §7.20 |

### 13.6 Transactional email

Sender `SUPPORT_EMAIL`; HTML templates in es and en (§7.21).

### 13.7 Scraping

- Web store catalogs (1–30 products per run).
- LinkedIn profiles.
- Headless browser to extract the brand's CSS.

### 13.8 Usage/analytics service (internal, dependency)

Authenticated with an API key. **Minimum contract:**

| Endpoint | Purpose |
|---|---|
| `POST /events` | Receive domain events with `tags.feature` |
| `POST /payments` | Record payments |
| `GET /customers/{id}/usage` | Counters for the period |
| `PUT /customers/{id}/usage` | Adjust counters (merge) |
| `GET /analytics/{qid}/general` | General questionnaire analytics |
| `GET /questionnaire/{qid}/data` | Aggregated dashboard data (shape in §10.9) |

- Staging and production share a database and are distinguished by `source`.
- The migration MAY absorb this service into the API if it keeps the same per-period counter semantics.

### 13.9 Spreadsheet export (Google Sheets, from the console)

- OAuth in the browser with the minimum scope to create and edit the app's own files.
- It looks for an existing sheet tagged with the property `skylineExportKey = questionnaireId|assignationId`. If it exists, it is cleared and rewritten; otherwise, it is created.
- Title "Answers - {title}".
- Columns: Started At, User, Email, Phone (if present), then one per question in order.
- Values: "Skipped", "File uploaded" / "N files uploaded", option labels, empty.
- It opens in a new tab.

### 13.10 E-commerce platform (Shopify)

- OAuth with read-only product scope. Expiring tokens with refresh.
- Product sync (price of the first variant).
- GDPR compliance webhooks with HMAC.
- App embedded in the store theme: uses `GET /questionnaire/find?url=` to resolve which questionnaire to show.

### 13.11 Others

| Integration | Use |
|---|---|
| Error tracking | Backend and both frontends (includes PII in the backend) |
| Web analytics, heatmaps, TikTok pixel (console), customer pixels (respondent) | Marketing and measurement |
| YouTube | Documentation videos |
| Web font provider | Brand typefaces |

---

## 14. Non-functional requirements

### 14.1 Performance and limits

- Synchronous requests ≤ 29 s. Regular functions ≤ 45 s; submitting, creating and editing questionnaires up to 120 s. Everything slow is done with jobs.
- 30 s client timeout in the respondent app (the timeout is reported as `status 0, code TIMEOUT`).
- Pagination **SHOULD** happen in the database. Today many listings are loaded in full and paginated in memory (scalability [DEBT]).

### 14.2 Security

- Tenant isolation on **all** reads and writes (see the [DEBT] exceptions in §15).
- API keys are stored only as a SHA-256 hash.
- Outgoing webhooks signed with HMAC-SHA256.
- Incoming webhooks verified: the gateway's signature and the e-commerce platform's HMAC.
- Short-lived signed URLs (15 min).
- Do not reveal whether accounts exist during password recovery.
- The customer's prompt in chains is treated as untrusted data (defense against prompt injection).
- Never return job `payload` or the scoring configuration to the respondent.
- Sanitize all CSS and HTML coming from styles, products or the LLM.
- CORS is open today. It **SHOULD** be restricted to the origins of the apps, the store widget and integrators.
- No rate limiting of its own today (only plan quotas and the identity provider's throttling). It **SHOULD** be added on public endpoints (§15).

### 14.3 Availability and data

- Persistence with continuous backup (point-in-time recovery) and deletion protection on the main tables.
- Idempotency in payment webhooks and in the client-side composite creation of projects.

### 14.4 Internationalization

- UI in **es** and **en**; fallback `es`. All text externalized; never hardcoded.
- Account language (`es-CO` / `en-US`) separate from the console UI language. It controls emails, generated content and the respondent's initial language.
- Tolerate longer Spanish texts.
- Disable the browser's automatic translation in both apps.

### 14.5 Accessibility

- **WCAG 2.1 AA.**
- AA contrast (violet on white is 5.3:1).
- Keyboard-operable with visible focus. All controls labeled.
- Accessible modal dialogs. Progress bars with values. Errors announced; status panels with a live region.
- Respect `prefers-reduced-motion`.
- **Color is never the only cue:** password strength with a label; named tiers; errors with text.
- `<html lang>` synchronized with the language.

### 14.6 Responsive

- Mobile-first respondent app:
  - Variants for short screens (≤ 740 px tall).
  - Content readjusts when the mobile keyboard opens and the active field stays in view.
  - Full touch drag in ranking.
- Console: the creation preview becomes a side panel below 1100 px.

### 14.7 Observability

- Structured logs with a configurable level.
- Error tracking in all three pieces, with traces and session replay in the frontends (10 % of sessions and 100 % of sessions with errors).
- `GET /health` with checks.

### 14.8 SEO (respondent app)

- Meta description and keywords, robots "index, follow", Open Graph and Twitter large card.
- Configurable base title.

### 14.9 Environments

| Environment | API | Respondent app | Console |
|---|---|---|---|
| dev | api.rev-ops.ai | rev-ops.ai | app.rev-ops.ai |
| staging | api.qa-questionaire.com | qa-questionaire.com | app.qa-questionaire.com |
| prod | api.questionaire.shop | q.getmappi.com | app.getmappi.com |

Continuous deployment per branch (`ai-develop`, `staging`, `master`). Automated tests on every pull request.

---

## 15. Technical debt and pending decisions

1:1 parity means the migration is aware of all these points. For each one, it MUST be decided whether to **replicate** or **fix** it.

| # | Topic | Current situation | Recommendation |
|---|---|---|---|
| D1 | `PUT` and `DELETE /organizations/{id}` | They do not check that the organization belongs to the account | **Fix:** require Own |
| D2 | Deleting an organization | Leaves orphaned members | **Fix:** cascade delete, or block if it has assignations |
| D3 | `POST /assignations/{id}/retries` | Ownership is not explicitly verified | **Fix:** require owner or `Admin` |
| D4 | Sensitive public endpoints | `GET /customer/{id}/products`, `/settings`, `/styles`, `/jobs/{id}`, `/signed-urls` (accepts any `customer_id`), generation via LinkedIn and via prompt | Keep public those the respondent needs, but limit `/signed-urls` to valid sessions and add rate limiting |
| D5 | E-commerce platform OAuth | `state = customer_id`, no anti-forgery nonce | **Fix:** signed nonce |
| D6 | Assignation respondent token | Does not expire | **Fix:** reasonable expiration (e.g. 30 days) + renewal |
| D7 | Submission with an invalid assignation token | Responds 200 without processing | **Fix:** 401 |
| D8 | Customer-specific logic | `livingood`, Samurai8 (`mateo` / hardcoded id), LinkedIn owner `XhEFtqTt`, title of `3zWj6Nrg`, sales email recipients, default questionnaire id | **Turn into configuration** (configurable result types and global settings) |
| D9 | Legacy `/login` | Hardcoded credentials; 500 on wrong credentials | Remove, or keep only in non-production environments |
| D10 | `POST /profile/customization` and user migration trigger | Declared in the infrastructure, no code | Do not migrate |
| D11 | Product HTML | Rendered without sanitizing | **Fix:** sanitize |
| D12 | Audio tutorial | No option to skip it | **Fix:** allow continuing without audio (or typing) |
| D13 | Email validation | Different regexes across assignation login, capture and contact | **Unify** |
| D14 | Analytics and pixel IDs | Hardcoded in the HTML | Move to configuration |
| D15 | Onboarding step 2 | The workspace is not saved | Save it (name, language, site) or remove the step |
| D16 | Questionnaire list search | Client-side only | Use the server's `search` |
| D17 | In-memory pagination | Full listings in memory | Paginate in the database |
| D18 | Impersonation | No audit trail | Log actions taken while impersonating |
| D19 | Outgoing webhooks | No retries | Retries with backoff and a delivery log |
| D20 | Welcome email | Unused templates | Send on sign-up or remove |
| D21 | Privacy / Terms / Support links on the login | Lead nowhere | Link them |
| D22 | Dead code | Old landing/home pages and unused services in the respondent app | Do not migrate |
| D23 | Hardcoded Spanish texts | Copy prefixes, sales email, Samurai8 content, onboarding templates | Externalize to i18n |
| D24 | CORS `*` and lack of rate limiting | — | Restrict and limit |
| D25 | `VITE_RESULT_LAYOUT_V2` | Console flag to freely reorder result blocks; the backend does not support it yet | Out of scope, or design it during the migration |

---

## 16. Migration plan and acceptance criteria

### 16.1 Backward compatibility (MUST)

1. The public URLs `/q/{id}`, `/f/{id|slug}`, `/a/{id}`, `/session/{id}/results`, `/:id` (legacy) and `/privacy` keep working.
2. The external API `/external/*` keeps its routes, `X-API-Key`, the `QAIRE-…` key format and response shape. Existing keys remain valid (the hash is migrated).
3. Outgoing webhooks keep the body, headers and signing algorithm. The signing secret is preserved or rotated with notice.
4. The store widget keeps resolving `GET /questionnaire/find?url=`.
5. Slugs and the ids of flows, questionnaires, sessions and assignations are preserved.
6. Answer file keys are preserved (or the storage is migrated keeping the key structure).
7. Users can sign in with their current password. If the identity provider changes, a lazy migration (validating against the previous provider on first login) or a communicated forced reset is required.
8. Subscriptions in the payment gateway remain associated with their account (`stripe_customer_id` and `stripe_subscription_id`).
9. The connection to the e-commerce platform is preserved (tokens migrated).

### 16.2 Data migration

For each entity in §6: extract, transform (if the schema changes), load, and verify counts and checksums.

**Suggested order:**

1. Catalog: features, plans, videos, app settings, system prompts with history.
2. Accounts and users.
3. Styles.
4. Products.
5. Questionnaires, flows, diagnostics and prompts.
6. Organizations and members.
7. Assignations, assignation answers and projects.
8. Sessions and session results.
9. Dashboards.
10. API keys and webhooks.
11. Jobs: optional; only recent ones.
12. Object storage files.

### 16.3 Parity acceptance criteria

One automated test or documented manual test per item:

1. **Sign-up:** with email and with Google (including linking to an existing account) → account with a 1-month `starter` plan and pending onboarding.
2. **Full 7-step onboarding** → published questionnaire and test response detected.
3. **Create each type** (Regular, Diagnostic, Chaining, Quiz Funnel via website and via e-commerce, chat) → it appears in the listing and its public link works.
4. **Diagnostic:** a session's score matches §7.7 exactly (cases with checkbox, ranking, range, categories, boundary tiers and recommendations inherited from the lower tier).
5. **3-stage chain** with a generated final diagnostic → combined score across all stages.
6. **Respondent:**
   - resume after reload;
   - disclaimer;
   - landing;
   - validations for each control;
   - exclusive checkbox;
   - slider that does not count until touched;
   - Skip only on optional questions;
   - files (limit, paste, retry);
   - audio with transcription;
   - AI evaluation with retries and fail-open;
   - data capture;
   - results reloadable via URL;
   - PDF.
7. **Default assignation:** login by email/phone, `USER_NOT_FOUND` and `NOT_IN_AUDIENCE` errors, per-respondent progress, CSV and Sheets exports.
8. **Follow-up assignation:**
   - session shared between two members (merge without overwriting with empty values);
   - complete;
   - review (approve and reject);
   - send for correction → attempt 2 with locked answers and rejected ones empty;
   - completion screens by `review_status`.
9. **Reminders:** daily job and manual button; subjects according to the days remaining; no duplicates on the same UTC day.
10. **Projects:** states and percentages according to §7.12, including the overdue calculation in UTC−12.
11. **Plan gate:** each rejection reason produces its 429 and its UI text; `Admin` passes; counters add up according to §7.2; the usage banner appears at the thresholds.
12. **Billing:** checkout, immediate upgrade with proration, scheduled downgrade and its reversal, cancel and resume, portal, coupons, trial only once per account, counter reset on upgrade.
13. **Dashboard:** one-time generation, blocked without `dashboards`, funnel and NPS formulas.
14. **External API and webhooks:** signature verifiable by a test receiver; revoked key → 401.
15. **Impersonation:** header honored, 403 for non-`Admin` users, banner and automatic exit on errors.
16. **Super-admin:** CRUD of features, plans, coupons, videos and system prompts (with placeholder and version validation); adjusting an account's plan and usage.
17. **i18n:** all screens in es and en; emails in the account's language.
18. **Accessibility:** automated AA audit with no critical errors on the main screens.

---

## Appendix A — Branding and design

### A.1 Console (Mappi)

**Personality:** confident, clear and friendly. Simple, reassuring tone, never exaggerated: it states the result and steps aside.

**Palette:**

| Token | Value |
|---|---|
| Primary (single accent) | `#8249df` (HSL 263 70% 58%) |
| Hover | `#6c3aed` |
| Soft tint | `#f0e8fc` |
| Sidebar | `#13111d` (near black) |
| "AI Experience" highlight | `#06b6d4` (cyan) |
| Neutrals | Cool and light |

**Typography:** Inter for the interface; Fraunces (serif) only for large headings (type selector, success screen).

**Components:** 40 px tall pill buttons; white cards with a 14 px radius and a thin border.

**Theme:** light only. Dark-mode CSS exists but is not enabled.

**Visual reference:** the creation screens. A single container for all types, an editor centered at 880 px and a live preview of what the respondent sees.

**Principles:**

1. **Clarity over decoration:** one main task per screen.
2. **Calm confidence:** a single violet accent, generous whitespace.
3. **Show the result, not the machinery.**
4. **Warmth is a feature:** human copy, serif in the big moments and the live preview.
5. **Speed and trust:** good defaults, forgiving forms, honest empty and error states.

The accent is used only on what matters: the main button, the current step and the selected card. Every disabled action says why.

**Anti-references:**

- Generic Material/Bootstrap-style admin.
- Gradient SaaS aesthetic: gradients, gradient text, huge metric cards.
- Dark glass with neon.
- Cluttered enterprise (dense toolbars, tiny text).

### A.2 Respondent app (default theme, without customer branding)

| Token | Value |
|---|---|
| Background | Warm off-white (HSL 40 30% 96%) |
| Text | zinc-900 |
| Cards | White |
| Primary | Near black (zinc-900) |
| Accent | Terracotta `#C45A3D` |
| Success | HSL 142 76% 36% |
| Warning | HSL 38 92% 50% |
| Radii | 0.5rem; pill buttons; 1rem cards |
| Fonts | Montserrat for body text; serif for headings |

- Its own logos and favicon.
- **The customer's branding overrides these tokens** (§9.15).

### A.3 Historical names

"QuestionAIre" ("Q" monogram, tagline "make the shopping experience a total breeze"), domains `questionaire.shop` and `getmappi.com`, contact `info@questionaire.shop`.

The new product is presented as **Mappi**. It MUST be decided whether the respondent app switches to showing "Mappi" instead of "QuestionAIre".

---

## Appendix B — Error code catalog

| Code | HTTP | Context |
|---|---|---|
| `INVALID_JSON` | 400 | Body that is not JSON |
| `VALIDATION_ERROR` | 400 | Field validation |
| `INVALID_REQUEST` | 400 | Missing parameter |
| `INVALID_UUID` | 400 | Malformed id |
| `UNAUTHORIZED` | 401 | No valid authentication |
| `FORBIDDEN` | 403 | No privileges or ownership |
| `ASSUME_NOT_ALLOWED` | 403 | Impersonation without being `Admin` |
| `ASSUMED_CUSTOMER_NOT_FOUND` | 404 | Impersonation of a non-existent account |
| `PLAN_LIMIT_REACHED` | 429 | With `details.reason` ∈ `NO_PLAN`, `PLAN_INACTIVE`, `PLAN_NOT_FOUND`, `FEATURE_NOT_IN_PLAN`, `FEATURE_LIMIT_REACHED`, `RESPONSE_LIMIT_REACHED`, `QUESTIONNAIRE_LIMIT_REACHED` |
| `USAGE_UNAVAILABLE` | 503 | Usage service fails |
| `EMAIL_ALREADY_EXISTS` | 409 | Sign-up or user creation |
| `INVALID_ROLE` | 400 | User creation |
| `TOO_MANY_ATTEMPTS` | 429 | Password recovery |
| `INVALID_RESET_CODE`, `EXPIRED_RESET_CODE`, `INVALID_PASSWORD` | 400 | Password recovery |
| `CUSTOMER_NOT_FOUND` | 404 | Account |
| `PLAN_NOT_FOUND` | 404 | Plan |
| `PLAN_NOT_PURCHASABLE`, `SAME_PLAN`, `NO_SUBSCRIPTION`, `NO_SCHEDULED_CHANGE`, `NO_STRIPE_CUSTOMER` | 400 | Billing |
| `STRIPE_UNAVAILABLE` | 502 | Gateway fails |
| `EMAIL_UNAVAILABLE` | 502 | Contact email fails |
| `INVALID_TYPE`, `INVALID_SORT`, `INVALID_ORDER`, `INVALID_IS_ACTIVE` | 400 | Questionnaire listing |
| `QUESTIONNAIRE_NOT_FOUND` | 404 | |
| `QUESTIONNAIRE_ALREADY_ANSWERED` | 409 | Editing blocked |
| `SLUG_ALREADY_IN_USE` | 409 | |
| `FLOW_NOT_FOUND` | 404 | |
| `SESSION_NOT_FOUND`, `SESSION_RESULTS_NOT_FOUND` | 404 | |
| `DASHBOARD_GENERATION_FAILED`, `ANALYTICS_UNAVAILABLE` | 502 | |
| `JOB_NOT_FOUND` | 404 | |
| `INVALID_JOB_DATA` | 500 | |
| `SHOPIFY_NOT_CONNECTED`, `SHOPIFY_TOKEN_EXPIRED`, `TOKEN_EXCHANGE_FAILED` | 400 | E-commerce |
| `DOMAIN_EMAIL_CONFLICT` | 409 | Organization |
| `ORGANIZATION_NOT_FOUND` | 404 | |
| `INTERNAL_ERROR` | 500 | |
| `AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`, `ASSIGNATION_IN_PROJECT` | 400 | Assignation |
| `ASSIGNATION_NOT_FOUND` | 404 | |
| `MISSING_IDENTIFIER` | 400 | Respondent login |
| `USER_NOT_FOUND`, `NOT_IN_AUDIENCE` | 403 | Respondent login |
| `FOLLOW_UP_COMPLETED` | 409 | Follow-up closed |
| `NOT_A_FOLLOW_UP` | 400 | |
| `NO_RECIPIENTS` | 422 | Reminder |
| `REMINDER_NOT_SENT` | 502 | Reminder |
| `FOLLOW_UP_NOT_COMPLETED` | 409 | Review |
| `QUESTION_NOT_FOUND` | 404 | Review |
| `QUESTION_LOCKED` | 400 | Review |
| `REVIEW_INCOMPLETE` | 409 | Retry |
| `NOTHING_TO_RETRY` | 400 | Retry |
| `RETRY_EMAIL_NOT_SENT` | 502 | Retry (the attempt was already created) |
| `INVALID_PAGE_SIZE`, `INVALID_CURSOR` | 400 | Respondents |
| `INVALID_PROJECT_STATUS` | 400 | |
| `PROJECT_NOT_FOUND` | 404 | |
| `ASSIGNATION_ORGANIZATION_MISMATCH`, `ASSIGNATION_NOT_FOLLOW_UP` | 400 | Project |
| `ASSIGNATION_IN_OTHER_PROJECT` | 409 | Project |
| `API_KEY_NOT_FOUND`, `WEBHOOK_NOT_FOUND` | 404 | |
| `INVALID_API_KEY` | 401 | External API |
| `INVALID_LANGUAGE` | 400 | Videos |
| `UNKNOWN_PLAN`, `INVALID_DATE_RANGE`, `UNKNOWN_FEATURE` | 400 | Admin |
| `FEATURE_ALREADY_EXISTS`, `PLAN_ALREADY_EXISTS`, `COUPON_CODE_TAKEN` | 409 | Admin |
| `INVALID_NAME`, `INVALID_STRIPE_PRICE`, `INVALID_COUPON`, `COUPON_CURRENCY_MISMATCH`, `COUPON_INTERVAL_NEEDS_OWN_PRODUCT` | 400 | Admin |
| `FEATURE_NOT_FOUND`, `COUPON_NOT_FOUND`, `UNKNOWN_PROMPT` | 404 | Admin |
| `INVALID_PLACEHOLDERS` | 400 | System prompts |

**Codes with their own text in the console:** `SLUG_ALREADY_IN_USE`, `SHOPIFY_NOT_CONNECTED`, `SHOPIFY_TOKEN_EXPIRED`, `FOLLOW_UP_COMPLETED`, `NO_RECIPIENTS`, `ASSUME_NOT_ALLOWED`, `ASSUMED_CUSTOMER_NOT_FOUND`, `ASSIGNATION_IN_OTHER_PROJECT`, `ASSIGNATION_ORGANIZATION_MISMATCH`, `ASSIGNATION_NOT_FOLLOW_UP`, `PROJECT_NOT_FOUND`, `ASSIGNATION_IN_PROJECT`, `AUDIENCE_MEMBER_NOT_IN_ORGANIZATION`, `FOLLOW_UP_NOT_COMPLETED`, `REVIEW_INCOMPLETE`, `NOTHING_TO_RETRY`, `QUESTION_LOCKED`, `RETRY_EMAIL_NOT_SENT`, `QUESTION_NOT_FOUND`, plus the seven plan-limit reasons.

---

## Appendix C — Configuration variables (by purpose)

Names are indicative. What is required is the **ability** to configure each item per environment. Secrets go in a secrets manager, never in the code.

**API:**

| Group | Variables |
|---|---|
| URLs | Public API, respondent app (`FRONTEND_URL`), console (`ADMIN_FRONTEND_URL`) |
| Persistence | Connection or collection names for each entity in §6 |
| Object storage | Containers for answer files, prompts/media and system prompts |
| Queues and bus | Job queue, styles queue, analytics events queue, webhooks topic |
| Secrets | Outgoing webhook signing; respondent token signing; LLM API; Google OAuth client; e-commerce app (key + secret); payment gateway (key + webhook secret); scraper (token + base URL); usage/analytics service (URL + API key); identity provider (pool/client); error tracking (DSN) |
| Others | Default LLM model; email sender (`SUPPORT_EMAIL`); log level |

**Respondent app:** API URL; the app's own public URL; base title; default Meta pixel ID; web analytics ID; heatmaps ID; error DSN.

**Console:** API URL; respondent app URL; identity provider configuration (pool, client, federated login domain); OAuth client for spreadsheet export; error DSN; heatmaps ID; e-commerce app install URL; mock login mode for development; `RESULT_LAYOUT_V2` flag.

---

## Appendix D — System prompt keys

| Key | Required placeholders |
|---|---|
| `shared--basic-rules-to-create-a-questionnaire` | — |
| `quiz-funnel--rules-to-create-profiling-questionnaires` | — |
| `quiz-funnel--rules-to-create-product-questionnaires` | — |
| `quiz-funnel--rules-to-recommend-products` | — |
| `chat--conversation-rules` | — |
| `chat--rules-to-build-questionnaires` | — |
| `chain--rules-to-create-questionnaires` | `{admin_instructions}`, `{base_rules}` |
| `diagnostic--rules-to-create-diagnostics` | — |
| `linkedin--rules-to-create-diagnostic-questionnaires` | — |
| `followups--rules-to-evaluate-answers` | — |
| `styles--rules-to-extract-brand-styles` | — |
| `dashboards--select-dashboard-type` | `{dashboard_catalog}` |

The current text of each key (including the platform's default text) MUST be exported from the current system and migrated with its history.

---

## Appendix E — Reference of the current implementation

**Informational only. Not a requirement.** It serves to identify what needs replacing if providers change.

| Capability | Current implementation |
|---|---|
| API | Serverless functions behind an API gateway (Python 3.10), in the us-east-1 region |
| Persistence | Managed key-value NoSQL database (DynamoDB), on-demand billing, PITR |
| Queues / bus | Asynchronous function invocation; pub/sub topic for webhooks |
| Scheduled task | Managed cron `0 13 * * ? *` |
| Object storage | S3 |
| Identity | AWS Cognito + federated Google (Hosted UI) |
| Email | AWS SES with HTML templates |
| Payments | Stripe (Checkout, Billing Portal, Subscription Schedules, Coupons/Promotion Codes) |
| LLM | OpenAI ("gpt-5.5" models for generation and "gpt-5-mini" for recommending and evaluating) |
| Transcription | OpenAI Realtime (`gpt-realtime-whisper`) over WebRTC or WebSocket with an ephemeral token |
| Scraping | Apify (e-commerce catalog and LinkedIn); Playwright/Chromium for styles |
| E-commerce | Shopify Admin API |
| Spreadsheets | Google Drive + Sheets from the browser |
| Frontends | React + TypeScript SPAs, hosted on AWS Amplify |
| Errors | Sentry |
| Measurement | Google Analytics, Meta Pixel, LinkedIn Insight, Google Ads, TikTok Pixel, Microsoft Clarity |
| Infrastructure as code | SAM + Terraform; CI per branch |
