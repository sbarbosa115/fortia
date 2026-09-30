---
name: symfony-react-app
description: Build a feature (or a whole app from a PRD) in a Symfony + React application running in Docker — plan, branch into a worktree, build test-first (DDD/hexagonal backend, Feature-Sliced Design frontend), static-analysis gate, security audit, browser verification, regression run, definition of done and PR. Use when asked to develop, build or implement a feature, PRD or spec in a Symfony + React project, including splitting a big requirement into items built in parallel (worktrees, subagents or separate sessions) and merging them back.
---

# Symfony + React app: feature process

Follow the steps in `steps/` in order. Read each step file before doing that step; they are the process, and the
project's own `README.md`, `CLAUDE.md` and `.claude/skills/` override them where they disagree.

| Step | File | Tooling |
|---|---|---|
| Overview | `steps/00-overview.md` | |
| 1. Stack | `steps/01-stack.md` | |
| 2. Plan | `steps/02-plan.md` | |
| 2b. Split a big feature into parallel items (optional) | `steps/02b-split.md` | `scripts/split.py plan\|start\|status\|prompt`, `templates/split-plan.md` |
| 3. Branch (worktree from fresh `origin/main`) | `steps/03-branch.md` | |
| 4. Build test-first (DDD backend, FSD frontend) | `steps/04-build.md` | `templates/eslint-fsd-boundaries.mjs` |
| 5. Static analysis and code style | `steps/05-static-analysis.md` | `scripts/gate.sh [--fix] [--skip=…]`, `templates/*` configs |
| 6. Security audit | `steps/06-security.md` | `scripts/audit.py`, `templates/security-README.md` |
| 7. Verify on the local Docker stack | `steps/07-verify.md` | |
| 8. Regression run | `steps/08-regression-run.md` | `scripts/new-run.py`, `templates/ui-regression.md` |
| 9. Finish (docs, CI, PR) | `steps/09-finish.md` | `templates/ci.yml`, `scripts/build-doc.py` |
| Definition of done | `steps/10-definition-of-done.md` | `scripts/dod.py [--quick] [--item]` |

Scripts live in `.claude/skills/symfony-react-app/scripts/` (project-level skill); run them from the project root.
Settings via env: `PHP_SERVICE` (php), `NODE_SERVICE` (node), `APP_DIR` (backend).

Report what was not done as plainly as what was.
