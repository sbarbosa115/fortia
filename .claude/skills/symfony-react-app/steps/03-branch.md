## 3. Branch from a fresh `main` into a feature base branch

Start from what is on the remote, not from whatever the local checkout holds, and work in a **separate
worktree**:

```bash
git fetch origin
git status --short                     # note anything uncommitted; it is not yours, so never stash or commit it
BASE=$(git symbolic-ref --short refs/remotes/origin/HEAD | sed 's@^origin/@@')   # main or master
git worktree add -b feature/<name> ../<repo>-<name> "origin/$BASE"
cd ../<repo>-<name>
```

- **`feature/<name>` is the feature base branch.** When the feature is split (§2b), item 0 (the contract) is built
  on it, and every other item gets a sub-branch `feature/<name>-<slug>` in its own worktree, created by
  `scripts/split.py start` and merged back into the base branch. (`feature/<name>/<slug>` is not possible: git cannot
  have a branch and a folder of branches with the same name.) Only the base branch opens a pull request against
  `main`/`master`.
- **Use a worktree, not `git switch`.** Another session may be working in the main checkout, and switching its
  branch changes its files and carries its uncommitted work into yours.
- If the plan or PRD sits on an unmerged branch, bring it in first (`git cherry-pick` / `git merge`) so the plan
  travels with the code.
- A fresh worktree has no `vendor/`, `node_modules/`, keys or built UI. Bring up **its own** stack (§7.1) before
  running anything.
- Commit in small steps that each leave the suite green, and keep the base branch rebased on (or merged with)
  `main` while it lives.
