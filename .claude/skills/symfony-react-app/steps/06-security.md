## 6. Security audit

After development and static analysis, and before verifying in the browser, audit the change against the common
attacks. **The process, the checklist and every finding live in [`docs/security/`](security/README.md).** In
short:

1. **Scope:** list what the feature adds that an attacker can reach: endpoints, parameters, uploads, rendered
   user content, emails, background jobs, new dependencies, new config or secrets.
2. **Run the tools.** `scripts/audit.py` does all of this, plus a grep of the diff for risky patterns
   (`dangerouslySetInnerHTML`, `|raw`, `exec(`, `unserialize(`, string-built queries, new routes), and creates
   the audit file from the template. By hand:
   ```bash
   docker compose exec php composer audit                 # known CVEs in PHP dependencies
   docker compose exec node npm audit --omit=dev          # known CVEs in shipped JS dependencies
   docker compose exec php php bin/console debug:firewall # which firewall and access rules cover the new routes
   git diff origin/main... | grep -nEi "password|secret|api[_-]?key|token|BEGIN .*PRIVATE"   # secrets in the diff
   ```
3. **Walk the checklist** in `docs/security/README.md` (OWASP Top 10, as it applies to Symfony + React) against
   every item in the scope. **Try the attack** where you can: another tenant's id, a role that should be refused,
   a `<script>` in every text field, a `.php` file renamed `.png`.
4. **Record** the audit in `docs/security/audits/<YYYY-MM-DD>-<feature>.md`, including the checks that found
   nothing, so the next person knows they were done.
5. **Fix and move on.** Write a failing test that proves the vulnerability (red), fix it (green), re-run §5, and
   mark the finding fixed with its commit. A finding that cannot be fixed inside the feature (it is in code the
   feature did not touch, or needs a product decision) is recorded with its severity, added to the README's
   "Known gaps", and raised with the user. It is never silently skipped. **Critical and high findings block the
   merge.**
