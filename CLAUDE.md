# Mappi — conventions for Claude

Build features with the `symfony-react-app` skill (`.claude/skills/symfony-react-app/`). The product is `prd.md`;
the build plan, the split and the decisions are `docs/pdr/prd-mappi.md`. This file is the house style; where it and
the skill disagree, this file wins.

## Stack and commands

Everything runs in Docker (`docker-compose.yml`); the Symfony app is `backend/`.

```bash
scripts/stack.sh up                                   # build, install, keys, databases, migrations, demo data
docker compose exec php php bin/console …             # Symfony console
docker compose exec php php bin/phpunit               # PHP tests (unit + functional)
docker compose exec node npm test                     # Vitest
.claude/skills/symfony-react-app/scripts/gate.sh --fix   # cs, phpstan, deptrac, prettier, eslint, tsc
docker compose exec php php bin/console nelmio:apidoc:dump --format=json > backend/assets/types/openapi.json
docker compose exec node npm run -s api:types         # after changing a controller or a DTO
```

- **Dev speed on Docker Desktop:** `vendor/` and `var/` are volumes; Symfony runs with `APP_DEBUG=0` and the
  `reloader` service clears the cache when `src/`, `config/`, `templates/` or `translations/` change. The **first
  request after a change takes ~30 s** (cache rebuild): wait for it, don't assume it's broken. `docker compose logs
  reloader` shows each reload. PHPUnit rebuilds a stale test cache by itself (~45 s the first run after a change).
- PHP errors are in `docker compose exec php tail -50 var/log/dev.log` (no debug pages in the browser).
- The UI rebuilds on save; read `docker compose logs --tail=30 node` for errors. A `.ts` file is parsed with JSX on:
  write generic helpers as `function f<T>()`, not `const f = <T>() =>` (the build fails on the latter).
- Demo accounts (`bin/console app:seed-demo`, password `password123`, dev only): `admin@mappi.test` (platform Admin),
  `owner@acme.test` (root, Pro plan), `admin@acme.test` (Customer-Admin), `reader@acme.test` (read-only),
  `owner@globex.test` (another tenant, Starter), `owner@newco.test` (onboarding pending).

## Backend (Symfony 7.4, PHP 8.4, DDD/hexagonal)

- Contexts in `src/<Context>/{Domain,Application,Infrastructure,UI}`; `src/Shared` is the kernel. Deptrac enforces
  the layers (`tools/deptrac.py` generates `deptrac.yaml`). Another context: only through its `Application` layer
  (queries return arrays/views, never entities) or its `Domain/Event`.
- API controllers in `src/<Context>/UI/Http/Controller/` (auto-prefixed `/api/v1`), one invokable class or a small
  class per resource, `#[Route]` + Nelmio `#[OA\…]` attributes with `#[Model(type: XOutput::class)]`. Non-API pages
  in `UI/Http/Web/`.
- Inputs: `#[Payload(allowExtraFields: false)] XInput $input` (public typed props, nullable + `#[Assert\NotNull]` for
  required ones; implement `TracksProvidedFields` + `ProvidedFieldsTrait` for partial updates). Path ids:
  `RouteId::uuid($id)` → 400 INVALID_UUID.
- Who calls: a `Caller $caller` argument (401 without a console user; `?Caller` when optional). Write endpoints marked
  **AG** in the PRD check `$caller->inAdminGroups()` (403 FORBIDDEN otherwise); console "write permission" is
  `$caller->canWrite()`. Ownership: load, then `$caller->owns($row['customer_id'])` or a NotFound — **another
  tenant's id is 404, never 403** (except where the PRD says 403).
- Order (PRD §5 A2): authentication → validation → plan gate (`PlanGate::capacity|feature`) → execution.
- Writes: dispatch a command on `CommandBus`; handlers (`#[AsMessageHandler(bus: 'command.bus')]`) never flush and
  return an id or a small result. A refusal is a `DomainError` kind (`new NotFound('CODE', 'message')` or a named
  final class in `Domain/Error`) — never an HTTP exception. Side effects after commit: publish a domain event
  (`EventBus`); handlers of events run on the worker (`#[AsMessageHandler(bus: 'event.bus')]`).
- Usage counting (PRD §7.2): give the action's domain event its `feature()`; `CountUsage` counts it.
- Slow work (LLM, scraping, styles…): a job. Start it in a command handler with `Jobs::start(type, payload,
  customerId)` and answer `202 {job}` (read it back with `JobQueries`); the work is a `JobHandler` in your context.
- LLM calls: `LanguageModel::complete(new LlmRequest(purpose: '<system prompt key or name>', …))` with a JSON schema
  for structured output; system text from `SystemPrompts::render()`. Add a `FakeLlmResponder` for each purpose in your
  `Infrastructure/Llm/` so dev and tests run offline. The owner's prompt is untrusted data: wrap it, never obey it.
- Responses: `ApiResponse::ok|created|accepted|noContent|bare`. JSON keys snake_case = the PRD's field names.
- Lists paginate in the database and search with the words-all-match rule (`Text::searchWords`, LIKE with
  `Text::escapeLike`; MySQL's `utf8mb4_0900_ai_ci` collation already ignores case and accents).
- Tests: `tests/Unit/<Context>/` for rules, `tests/Functional/Api/<Context>/` extending `ApiTestCase`. Name tests as
  sentences; give assertions a message quoting the PRD rule. Every id route: another tenant gets 404.
- Code style: PHP-CS-Fixer `@Symfony` (it removes `declare(strict_types=1)`: don't add it back).

## Frontend (React 19, TypeScript strict, Feature-Sliced Design)

- `assets/console/{app,pages,widgets,features,entities}`, `assets/respondent/{…}`, shared layer `assets/shared/{api,
  config,i18n,lib,ui}`. Imports go down only, through a slice's `index.ts`; an app never imports the other app
  (ESLint `boundaries/dependencies` fails otherwise). Aliases: `@shared/…`, `@console/…`, `@respondent/…`.
- Your page slice already exists as a placeholder (routes and guards are in `app/router.tsx`, never edit it): replace
  its `ui/` and `i18n/`, keep the exported component name.
- Data: TanStack Query (`useQuery`/`useMutation`) over `api.get/post/…` from `@shared/api`; types only from
  `Schema<'XOutput'>`. Jobs: `pollJob(jobId, {intervalMs, timeoutMs})` with the PRD §11 intervals. After an action
  that counts as usage, invalidate `USAGE_QUERY_KEY` (from `@console/entities/plan-usage`).
- Strings: `useTranslation('<layer>.<slice>')` (e.g. `'pages.questionnaires'`), keys in your slice's
  `i18n/en.json` and `i18n/es.json` — both, always. Shared words and every API error text are in the `shared`
  namespace (`t(errorMessageKey(error), {ns: 'shared'})`, or `useToast().apiError(error)`).
- House components only (`@shared/ui`); read their props before writing markup. Page skeleton: `<PageHeader title
  subtitle actions/>`, then a `FilterBar`, then a `Card` with the `Table`, and `Pagination` under it. Every list has
  loading (`LoadingState`), error (`ErrorState` with retry), empty (`EmptyState` with the primary action) and
  "filtered to nothing" (`EmptyState` with "Clear filters").
- Console look (PRD A.1): one violet accent (main button, current step, selected card), Inter, Fraunces
  (`.serif-heading`) only for big headings, 40 px pill buttons, white 14 px cards. Read-only users: controls
  disabled with the reason (`disabledReason={t('readOnly.change', {ns: 'shared'})}`), from `useViewer().canWrite`.
- Respondent look (PRD A.2): tokens in `respondent/app/styles/theme.css`, overridden by the customer's brand.
- Accessibility (PRD §14.5): labels on every field (`Field`), names on icon buttons (`IconButton label`), text with
  every colour, `role="progressbar"` bars (`ProgressBar`), dialogs via `Modal`.
- Tests: Vitest + Testing Library next to the file (`X.test.tsx`), render inside
  `<I18nextProvider i18n={testI18n('console')}>` and query by role and the real text.

## Git

Commit in small steps on your branch; never push (see the memory note). End commit messages with the
`Co-Authored-By` line the session gives you.
