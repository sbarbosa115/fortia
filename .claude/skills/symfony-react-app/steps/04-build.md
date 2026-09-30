## 4. Build it test-first

Every change follows **red → green → refactor**:

1. **Red:** write the test that describes the behaviour and watch it fail for the right reason, not because of a
   typo or a missing class.
2. **Green:** write the least code that makes it pass.
3. **Refactor:** clean up with the tests green. Run the whole suite, not just the new file.

Name tests as sentences about behaviour (`testAnotherTenantCannotSeeTheRecord`,
`it('disables Save until the form is valid')`), and give assertions a message that explains *why*. Those messages
are the real documentation of the rule.

**An item of a split (§2b)** builds only what its row in the split table owns, against the contract item 0 fixed,
on its own stack. It commits on its own branch and never merges: the coordinator merges it into the base branch.

### 4.1 Backend: Symfony with DDD and hexagonal layers

Work as a senior Symfony developer: thin controllers, explicit use cases, rules in the domain, framework code at
the edges.

**Bounded contexts.** `src/` has one folder per bounded context, plus `Shared` (the kernel: ids, money, clock,
error kinds, the command and event buses, API plumbing). A feature goes in the context that **owns the data it
changes**. A screen that only reads several contexts belongs to a read-side context (e.g. `Reporting`). A new area
gets a new context: all four folders, its Doctrine mapping, its routes entry and its Deptrac layers.

```
src/<Context>/
  Domain/            the model and its rules: no framework, no persistence calls
    Model/           entities (ORM attributes only), value objects, enums
    Repository/      write ports: get(id) (throws NotFound), add(), what a rule needs to look up
    Error/           final classes extending a Shared DomainError kind
    Event/           domain events (the only Domain classes other contexts may name)
  Application/       the use cases
    Command/         <Verb><Noun> (readonly DTO) + <Verb><Noun>Handler
    Query/           <Noun>Queries interfaces and read services
    Port/            what the context needs from outside (storage, mail, another context)
    EventHandler/    what runs after a command commits
  Infrastructure/    adapters: Persistence/Doctrine<Noun>Repository, Mail/, Storage/, Queue/
  UI/Http/           Controller/, Input/ (request DTOs), Output/ (response DTOs)
  UI/Cli/            console commands
```

Deptrac enforces the direction: UI → Application → Domain, and Infrastructure → both. A context reaches another
only through its `Application` layer or its domain events. The layer baseline stays empty. A cross-context
dependency kept on purpose goes in the contexts baseline, with its reason.

**The order, and the test that comes first at each step:**

| Step | Write first (red) | Then (green) |
|---|---|---|
| Domain rule | Unit test in `tests/Unit/<Context>/` (arithmetic, dates, state transitions, invariants) | Entity method / value object / domain service |
| Persistence | — (covered by the functional test) | Mapping + `doctrine:migrations:diff`, run on the dev **and** test databases |
| Use case | Functional test of the endpoint: happy path as the role that uses it | Command + handler, repository port + Doctrine adapter |
| Refusals | Functional tests: 422 with violations, 409 on conflict, 403 for the wrong role, 404 for another tenant's id | Input constraints, `DomainError`s |
| Response | Assert on the JSON shape | Output DTO + `#[ApiResponse]`, then regenerate the schema and TS types |

**Rules that fail silently when broken:**

- **Constructors take the required fields.** A state change is a method named for what happens (`suspend()`,
  `markHandled()`), not a setter per field.
- **Writes go through the command bus; reads do not.** The controller maps the Input, dispatches a command, reads
  back what it presents, and returns an Output DTO. The bus wraps the handler in a transaction: **handlers never
  flush**, and they return an id or a small result, not an entity.
- **A rule's "no" is a `DomainError`, never an HTTP exception.** Pick the kind that fits (Rejected 400,
  NotAllowed 403, NotFound 404, Conflict 409, InvalidValue 422, TooManyAttempts 429…). One exception subscriber
  turns it into the JSON the UI reads. Shape checks (required, length, format) stay on the Input DTO.
- **A refusal the domain must remember is a result, not an exception.** A throw rolls the transaction back, so a
  failed attempt that must be counted returns a result object and the command commits.
- **Side effects after a commit are events.** Emails and file cleanup are published on the event bus and handled
  after the commit. Slow or unreliable work (email, exports, third parties) goes on the Messenger queue.
- **The Application layer names no framework class.** Configuration is wired in `services.yaml`, and upload checks
  happen in UI, which passes the command a path.
- **Another context's record is an id** (plus a foreign key in the migration), except for shared reference data
  that lists deliberately join on.
- **Ownership and isolation.** If the app is multi-tenant, every owned entity carries its owner and is filtered by
  it. Load by id through a repository method that goes through that filter, and prove in a test that another
  tenant gets 404 (not 403) and sees no rows. This is the most important test in a multi-tenant app.
- **Lists paginate and accept `?q=`**, with `%` and `_` escaped so they match literally. Fetch-join what the Output
  reads (no N+1), and put indexes on the owner column first, then the filter/sort column.
- **Controller docblocks and Output DTOs are the API contract.** After changing either, regenerate:
  ```bash
  docker compose exec php php bin/console nelmio:apidoc:dump --format=json > assets/types/openapi.json
  docker compose exec node npm run -s api:types
  ```
  Commit both generated files with the change.

### 4.2 Frontend: React with Feature-Sliced Design

Work as a React UI/UX engineer: typed from the API schema, consistent with the screens next to it, usable without
training.

**The layers.** Imports only go **down**: a layer may use the layers below it, never the ones above, and never a
sibling slice on its own layer.

```
src/ (assets/react/)
  app/        providers, router, global styles, the app shell                   (no slices)
  pages/      one slice per route: composes widgets and features                (pages/<page>/)
  widgets/    self-contained blocks of a page: a list with its filters, a panel (widgets/<widget>/)
  features/   one user action each: "create X", "filter X", "archive X"         (features/<action>/)
  entities/   business entities: their API calls, types, model, small UI       (entities/<entity>/)
  shared/     no business knowledge: ui kit, api client, lib, config, i18n     (segments, no slices)
```

Each slice is split into **segments** (`ui/`, `model/`, `api/`, `lib/`, `config/`) and exposes a **public API**
through `index.ts`. Other code imports `@/entities/invoice`, never `@/entities/invoice/ui/InvoiceRow`. Enforce
both rules with an ESLint boundaries rule so a wrong import fails lint, not review:
`templates/eslint-fsd-boundaries.mjs` is the config (with a `legacy` element type for code not yet moved).

**Adopting FSD in an existing codebase.** When the UI predates FSD (`components/`, `lib/`, `pages/`), move it
gradually. New code goes into FSD slices. Existing generic components move into `shared/ui` and helpers into
`shared/lib`/`shared/api` as they are touched. Never do a big-bang move inside a feature branch.

**The order, test first:**

| Step | Write first (red) | Then (green) |
|---|---|---|
| Entity | Vitest for its mappers/formatters (`model/`, `lib/`) | `entities/<x>/api` typed with the generated schema types, `model`, a small `ui` |
| Feature | Testing Library test: what the user types, clicks and sees, queried by role and label | `features/<action>/ui` (the button, form or modal) + `model` (state, validation) |
| Widget / page | A render test for loading, empty, error and one row | Compose features and entities; lazy-load the page route |
| Critical flow | Playwright spec, only for flows that would be a disaster to break (sign-in, money) | — |

**UI/UX rules:**

- **Types come from the API schema** (`Schema<'XOutput'>`), not from hand-written interfaces. A changed response
  becomes a type error where it is used.
- **Every page behind a login is lazy-loaded.** Fix re-renders shown by the React Profiler, not by wrapping
  everything in `memo`.
- **Every visible string goes through the i18n function**, including placeholders, options and button labels. API
  errors are shown by error code, and validation messages arrive already translated.
- **One component per concern, reused everywhere:** tables, filter bars, action buttons, money inputs, empty
  states. A hand-rolled table or button is how screens start to disagree. The project's `CLAUDE.md` holds the
  house style. Follow it exactly and extend it before inventing a variant.
- **Every list has loading, error, empty and "filtered to nothing" states.** The last one offers a way back
  ("show all") that clears every filter.
- **Colour is never the only signal.** Every icon-only button has an accessible name, and every form field has a
  label.
- **A missing import is a blank screen, not a build error.** The bundler compiles it and React throws at render.
  ESLint's `react/jsx-no-undef` and opening the page in the browser (§7.3) are what catch it.
