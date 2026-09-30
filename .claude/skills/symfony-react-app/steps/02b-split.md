## 2b. Split a big feature into parallel items

Most features are built straight through (§3 onwards). Split one only when the plan shows **more than one bounded
context or more than one new screen**, and the parts can be named so that no two of them change the same aggregate
or the same screen. A split costs a merge per item and a coordinator's time: for a feature one person would build in
a day or two, it is slower than building it in one go.

### 2b.1 Contract first: item 0

Parallel branches of this stack collide in a few places, always the same ones: the database schema, the API contract
(`openapi.json`, `api.d.ts`), and the shared files every screen touches. **Item 0 takes the collisions out before
anything runs in parallel.** It is built on the feature base branch itself (§3), alone, and holds:

- the new entities and value objects, their mapping and **the migration** (on the dev and test databases);
- the repository ports (and Doctrine adapters) the other items will call;
- the Output DTOs (and Input DTOs) of every new endpoint, with the controller routes returning a fixed example or
  `501`, and the regenerated `openapi.json` + `api.d.ts`, so the UI items build against real types;
- the i18n namespaces, CSS files and regression-suite sections the items will fill (empty, with a comment naming
  the item that owns each).

Its tests cover the domain rules it adds and the shape of each response. When it is green and committed on the base
branch, the other items start from it.

### 2b.2 Cutting the rest

- **One item per bounded context on the backend, one per FSD slice (feature, widget or page) on the frontend.** An
  endpoint's use case and the screen that calls it may be one item or two; two is better when the screen is big.
- **Never two items on the same aggregate, the same screen or the same component.** If they must, they run one after
  the other (`Depends on`), not side by side.
- **Each item owns its files.** The plan names them, and an item that finds it must change another item's file stops
  and tells the coordinator: that is a missed dependency, and the plan changes, not the other branch.
- **Shared files are split up front:** each item gets its i18n key prefix, its CSS file or block, its README API rows,
  and **its range of regression case IDs** (`ORD-06 – 09`), so two branches never take the same number.
- **A migration after item 0** is allowed only for a table the item alone owns (named in the plan). A change to a
  shared table goes back to the coordinator as a new contract item (`0b`), merged before the items that need it.
- **Generated files are never merged by hand.** When `openapi.json`/`api.d.ts` conflict, take either side, then
  regenerate them on the merged code (§4.1).

### 2b.3 The split table

The table goes in the PRD (`docs/pdr/prd-<feature>.md`, from `templates/split-plan.md`), under `## Split`:

```markdown
| # | Slug | Item | Owns (context / slice, files) | Tests first | Browser cases | Depends on |
|---|---|---|---|---|---|---|
| 0 | contract | Schema, ports, DTOs, types | Ordering/Domain, migration, Output DTOs, api.d.ts | OrderLineTest, response shapes | — | — |
| 1 | orders-api | Owner lists and marks orders | Ordering/Application + UI/Http | OrderListApiTest (owner, other tenant 404) | — | 0 |
| 2 | orders-ui | Orders tab in the dashboard | widgets/order-list, features/mark-handled | OrderList.test.tsx | ORD-06 – 09 | 0 |
| 3 | orders-email | Daily digest email | Ordering/Application/EventHandler, emails.* `digest.` | DigestTest | ORD-10 | 1 |
```

`scripts/split.py plan <prd>` checks the table (item 0 first, known dependencies, no cycles, no case ID range used
twice) and prints the **waves**: the items that can run at the same time. Items 1 and 2 above are wave 1; item 3 is
wave 2, after item 1 merges.

**How many at once:** each item runs its own Docker stack (§7.1), a few GB of RAM each, so three or four at a time on
one machine. Browser checks share one browser: items verify their screens one at a time.

### 2b.4 Running the items

`scripts/split.py start <prd> [slug… | --wave=N]` creates, for each item, the branch `feature/<feature>-<slug>` from
the base branch, its worktree `../<repo>-<feature>-<slug>`, and a gitignored `.env` with free host ports; it records
the base branch in git config (read by `dod.py --item`) and prints the item's brief. `split.py status <prd>` shows,
per item, its worktree, commits and whether it is merged.

Two ways to run the items; pick one per feature:

| | Subagents (one coordinating session) | One session per item |
|---|---|---|
| How | The coordinator launches one agent per item with `split.py prompt <prd> <slug>` as its prompt, all of a wave at once | Open a Claude session in each item's worktree and paste its brief |
| Good for | Items that are well specified and mostly backend or logic | Items with UI judgement, or that need the user's decisions while built |
| Watch | Agents do not share what they learn; the coordinator reads each report before merging. An agent's shell may start in the main checkout: every command `cd`s into the item's worktree | The user relays questions; the coordinator still does the merges |

Either way, **each item goes through §4–§5 and §7 on its own stack**: test-first, the gate, its own screens in the
browser, and `dod.py --item`. It adds its regression cases (in its ID range) but does not record a run, and it does not
run the security audit: those happen once, on the merged base branch.

### 2b.5 Merging

The coordinator merges each finished item into the base branch (never into `main`), in dependency order:

```bash
cd ../<repo>-<feature>                           # the base branch's worktree
git merge --no-ff feature/<feature>-<slug>
# conflicts in openapi.json / api.d.ts: regenerate, never edit
docker compose exec php php bin/console doctrine:migrations:migrate -n          # and --env=test
.claude/skills/symfony-react-app/scripts/gate.sh && docker compose exec php php bin/phpunit && docker compose exec node npm test
```

A merge that breaks the base branch is fixed on the base branch right away, before the next merge. When a wave is
merged, the next wave starts from the updated base branch (`split.py start --wave=N`).

When every item is merged, the base branch goes through the rest of the process **once**, as one feature: §6 security
audit, §7 verify, §8 the whole regression run, §9 finish and one pull request. `dod.py` (without `--item`) fails
while an item of the split is not merged. Then remove the items' stacks and worktrees (§9).
