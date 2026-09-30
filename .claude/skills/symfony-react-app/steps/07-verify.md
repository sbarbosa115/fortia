## 7. Verify on the local Docker stack

"Compiled successfully" and green tests prove the backend and the bundle, not the screen. A feature is verified
at all three levels below, **on the worktree's own stack**.

### 7.1 Run your own stack

Every checkout is its own Compose project (containers and volumes are named after the folder), so a worktree gets
its own database, Mailpit, worker and build. Only host ports can clash: give the worktree free ports in its
gitignored `.env` (e.g. app :8081, Mailpit :8026, DB :3307) and point `APP_URL` at them. A helper script that
does this is worth having in the project (a `stack.py up` / `status` / `down`). Without one:

```bash
docker compose up -d
docker compose exec php composer install
docker compose exec node npm ci
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --env=test -n
docker compose exec php php bin/console app:seed-demo      # or the project's fixtures
```

Never test a feature against another checkout's stack: its code, database and build are not yours.

### 7.2 Automated suites

The static analysis gate (§5.4) must already be green. Then run the tests:

```bash
docker compose exec php php bin/phpunit                # all of it; read the final OK line
docker compose exec node npm test                      # Vitest
docker compose logs --tail=30 node | grep -iE "error|compiled"
docker compose --profile e2e run --rm e2e              # when the feature touches a critical flow
```

**The testing pyramid:**

| Layer | Tool | Where | Covers |
|---|---|---|---|
| Domain rules | PHPUnit | `tests/Unit/<Context>/` | arithmetic, dates, invariants: fast, no HTTP |
| API endpoints | PHPUnit `WebTestCase` | `tests/Functional/Api/` | each role, tenant isolation, refusals, emails |
| UI logic | Vitest + Testing Library | next to the file (`X.test.tsx`) | what the user types and sees |
| Critical flows | Playwright | `e2e/*.spec.ts` | sign-in, money: few on purpose |
| Whole app, by hand | Browser + regression suite | `docs/tests/ui-regression.md` | every screen, as real users (§8) |

**Baselines hold the past, not the present.** A new PHPStan, ESLint or Deptrac finding gets fixed, not added to a
baseline or suppressed. A deliberate exception goes in the config file with its reason next to it.

**Performance checks while you are here:** open the profiler's Doctrine panel for each new list. More queries than
rows is an N+1. Add a test that fails when a list's query count grows with its rows.

### 7.3 The browser

Open the app **on this worktree's port**, as the role that uses the feature, and for every page, tab and modal it
adds or changes:

1. **A row:** every action works, in the house order, and a success or error message follows each save.
2. **Every modal, opened.** That is where a missing import hides. Type into its fields, including dates.
3. **Filtered to nothing:** the empty state says so and offers a way back that clears every filter.
4. **Nothing at all:** what a brand-new account sees: what the section is for, and its primary action.
5. **Next to a sibling screen:** header, filters, table and actions look and behave the same.
6. **The console has no errors**, no string shows a raw translation key, and any email lands in Mailpit.

Traps that make a working change look broken: a **cached bundle** (refetch `/build/` assets and reload),
**browser autofill** on login forms (clear the fields before typing), a **leftover session** in another tab of the
same origin (use one browser profile, one tab), and **native pickers** that screenshots never show (set the value
and dispatch an `input` event).

If you could not open a screen, say so in the report rather than implying it renders.
