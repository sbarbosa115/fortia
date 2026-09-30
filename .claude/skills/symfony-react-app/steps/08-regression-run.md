## 8. Record a regression run after every feature

The browser regression suite in `docs/tests/ui-regression.md` is the manual pass that proves the app still works
as real users use it. **Every feature ends with a recorded run.**

`scripts/new-run.py` creates the run file with every case ID of the suite listed as "Not run", and when there is
no suite yet, it also writes the suite skeleton from `templates/ui-regression.md`. Fill it in while running.

**A split feature (§2b)** records one run, on the base branch after every item is merged. Each item only writes its
cases into the suite, in the ID range the split table gave it, and checks its own screens (§7.3).

### 8.1 A suite already exists

1. **Add the feature's cases** to `ui-regression.md`, in the section of the screen they live on, in the suite's
   format (below). A case is only worth adding if it would catch the feature breaking: name the button, the data
   and what must be seen.
2. **Reset to the seed data** (§7.1: drop, create, migrate, seed), so the run does not depend on the last one.
3. **Run the whole suite in order** through the real UI (clicks, typing, file inputs), reading emails in Mailpit,
   and checking the database or logs only where a screen cannot prove the result.
4. **Record it** in `docs/tests/runs/<YYYY-MM-DD>-<feature>.md` (format below).
5. **Fix what failed** on the feature branch with a test where the backend was involved, re-run those cases, and
   mark them "Pass after fix" with the commit.

### 8.2 No suite yet: establish the baseline

If this is the project's first feature, or nobody has written the suite yet, this feature creates it:

1. Write `docs/tests/ui-regression.md` with:
   - **Before you start:** the commands that reset the stack to known data, the services that must be up, the
     test accounts per role (dev-only passwords), and the files to have ready (a PDF, an image…).
   - **Checks on every screen:** no console errors, no blank page, no raw translation keys, tables in the house
     style, a message after every save.
   - **One section per area of the app** (public site, each role's space, emails), with cases for what already
     exists: sign-in and sign-out for every role, each list (search, filter, empty state), each create/edit/delete,
     each email, and access control (a role cannot reach another's URLs, another tenant's data is invisible).
     Order the cases so later ones use the data earlier ones create.
   - The new feature's own cases.
2. Run it end to end in the browser and record it as `docs/tests/runs/<YYYY-MM-DD>-baseline.md`. That run is the
   baseline every later run is compared against.
3. Failures found by the baseline that are **not** caused by the feature go in the run file's findings and the
   README's "Known gaps". Fix them only if the user agrees. The baseline records the truth; it does not have to be
   all green.

### Case format

```markdown
**AREA-NN · What the user does, as a sentence**
Where to go › what to click, with the exact data to type (`Example value`) › the button.
**Expected:** the message that appears, what the row/table shows now, and the email that arrives (subject, recipient).
```

IDs are stable: a case keeps its ID forever. New cases take the next number in their section, and a removed case
leaves a gap.

### Run file format

```markdown
# UI regression run — <date> (<feature or "baseline">)

- **Suite:** [`../ui-regression.md`](../ui-regression.md), at `<commit>`.
- **Branch:** `feature/<name>` at `<commit>`, on the worktree's stack (app :<port>).
- **Data:** reset to the seed before the run / not reset (say why).
- **How:** browser driven through the real UI; emails read in Mailpit; database/logs where a screen could not prove it.

## Summary
| | Cases |
|---|---|
| Cases in the suite | N |
| Pass | N (M after a fix made during the run) |
| Fail | N — IDs |

## Results
| IDs | Result | Notes |
|---|---|---|
| AREA-01 – 05 | Pass | |
| AREA-06 | Pass after fix | what failed, fixed in `<commit>` |

## Findings
Numbered: what happened, which case, the cause, and the fix or why it was left.

## Conditions
Anything about the environment that could have affected the result (a crashed worker, a shared database, a step
that could not be driven in the browser and how it was checked instead).
```
