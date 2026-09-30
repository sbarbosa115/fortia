## Definition of done

`scripts/dod.py` checks the items a script can check (branch, gate, migrations on the test database, API types,
audit and run files, the README when routes changed) and lists the rest for you to confirm. Its output goes in
the report.

- [ ] A written plan: role, owning context and FSD slice, layers touched, tests and browser cases, what is left out.
- [ ] If split (§2b): a split table that `split.py plan` accepts, item 0 built first, every item passing
      `dod.py --item` on its own stack and merged into the base branch, which then passes everything below once.
- [ ] Built on `feature/<name>` from a fresh `origin/main` (or `master`), in its own worktree, on its own stack.
- [ ] Every behaviour has a test written before its code: unit for domain rules, functional for each endpoint
      (happy path, each refusal, wrong role, another tenant gets 404), component tests for UI logic.
- [ ] Backend in the owning bounded context and its four layers. Writes go through commands, refusals are
      `DomainError`s, handlers never flush, responses are Output DTOs, `composer deptrac` is clean.
- [ ] Frontend in FSD layers with public APIs only, imports only going down, types from the API schema, lazy
      pages, every string through i18n, the house components used.
- [ ] Migration run on the dev and test databases, schema and TS types regenerated and committed.
- [ ] Static analysis loop finished (§5): PHP-CS-Fixer (`@Symfony`), PHPStan, Deptrac, Prettier (Google style),
      ESLint and `tsc` all exit 0, with no new baseline, ignore or disable entries. Configs created if missing.
- [ ] Security audit recorded in `docs/security/audits/` (§6): every finding fixed with a test, or recorded as a
      known gap and raised. No open critical or high finding.
- [ ] PHPUnit (whole suite) and Vitest green after the style and security fixes; Playwright green if a critical
      flow was touched.
- [ ] No N+1 on new lists, and lists paginate and search with `?q=`.
- [ ] Every new or changed screen and modal opened in the browser on the worktree's stack, with no console errors.
- [ ] Feature cases added to `docs/tests/ui-regression.md` (or the suite created as a baseline), the whole suite
      run, and the run recorded in `docs/tests/runs/`.
- [ ] README (API row, decisions, known gaps) and help updated, and a PR opened from the feature base branch.

Report what was *not* done as plainly as what was.
