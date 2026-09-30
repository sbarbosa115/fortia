## 9. Finish

- **Docs:** a row in the README's API reference for every new endpoint (path, methods, filters, status and error
  codes). Add a "Data model decisions" bullet for a real decision (the *why*), and a "Known gaps" bullet for what
  was left out. If the project has user help articles, add or update the guide for the roles that use the feature.
- **Renamed a word the user sees?** Grep every place it lives (i18n, email translations, templates, tests, help)
  and change them together.
- **Stack changes** (new env var, cron line, queue message, feature flag) are documented where the deploy reads
  them and work on the production host.
- **CI runs the same gate.** If the project has no workflow, install `templates/ci.yml` as
  `.github/workflows/ci.yml` (it runs the style checks, the analysers and the test suites in the project's Docker
  stack). A check that only runs locally will stop being run.
- **Pull request** from `feature/<name>` to `main`/`master`: link the plan, summarise what changed per layer,
  link the regression run file and the security audit file, and list what was not done.
- After the merge: `docker compose down` in the worktree (add `--volumes` to drop its data) and
  `git worktree remove ../<repo>-<name>`. For a split feature, do the same for every item's worktree
  (`split.py status` lists them) and delete the merged item branches.
