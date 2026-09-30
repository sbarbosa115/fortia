#!/usr/bin/env python3
"""The tool part of the security audit (steps/06-security.md), for the current branch against its base.

Run from anywhere inside the project's git checkout, with its Docker stack up:

    python3 .claude/skills/symfony-react-app/scripts/audit.py                 # base = origin's default branch
    python3 .claude/skills/symfony-react-app/scripts/audit.py --base=origin/develop

It runs `composer audit` and `npm audit --omit=dev` in the containers, greps what the branch adds (committed,
uncommitted and untracked) for secrets and for risky patterns, lists the routes and security config it touches, and
creates docs/security/audits/<date>-<feature>.md from the template with those results filled in (an existing file is
never overwritten). It also installs docs/security/README.md (the checklist) if the project has none.

The patterns are leads, not verdicts: each one is checked against the checklist and recorded as a finding or as
"checked, nothing found". Exit 1 when a dependency audit reports advisories or a secret-looking line is added.
Settings: PHP_SERVICE (php), NODE_SERVICE (node), APP_DIR (backend).
"""

from __future__ import annotations

import datetime as dt
import os
import re
import subprocess
import sys
from pathlib import Path

SKILL = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_SERVICE', 'php')
NODE = os.environ.get('NODE_SERVICE', 'node')
APP_DIR = os.environ.get('APP_DIR', 'backend')

SECRETS = re.compile(
    r'''(?ix)
    (password|passwd|secret|api[_-]?key|access[_-]?key|token|private[_-]?key)\s*[:=]\s*['"][^'"\s]{8,}['"]
    | -----BEGIN\ [A-Z ]*PRIVATE\ KEY-----
    | \bAKIA[0-9A-Z]{16}\b | \bgh[pousr]_[A-Za-z0-9]{36}\b | \bxox[abpr]-[A-Za-z0-9-]{10,} | \bsk_live_[A-Za-z0-9]{16,}
    ''')

# (category, description, file suffixes or None for all, pattern)
LEADS = [
    ('A03 XSS', 'dangerouslySetInnerHTML: is the content sanitised?', ('.js', '.jsx', '.ts', '.tsx'), r'dangerouslySetInnerHTML'),
    ('A03 XSS', '|raw in Twig: is it user content?', ('.twig',), r'\|\s*raw\b'),
    ('A03 XSS', 'user-controlled href/src: is the scheme checked (javascript:)?', ('.jsx', '.tsx'), r'(href|src)=\{(?!["\'`/#])'),
    ('A03 Injection', 'value concatenated into a query: bind it with setParameter', ('.php',),
     r'(createQuery|createNativeQuery|executeQuery|executeStatement|fetchAll\w*|->(and|or)?[wW]here|->orderBy|->addOrderBy|->having)\s*\(.*(\.\s*\$|\{\$|"\$)'),
    ('A03 Injection', 'shell execution: use Process with an argument array', ('.php',), r'\b(exec|shell_exec|system|passthru|proc_open|popen)\s*\('),
    ('A03 Injection', 'eval / new Function', None, r'\beval\s*\(|new\s+Function\s*\('),
    ('A08 Deserialisation', 'unserialize(): never on user input', ('.php',), r'\bunserialize\s*\('),
    ('A08 Uploads', 'file upload handling: MIME from content, size limit, stored outside the web root', ('.php',), r'UploadedFile|->move\s*\(|getClientOriginalName|getClientMimeType'),
    ('A08 Path traversal', 'filesystem path built from a variable: normalise and keep it inside the root', ('.php',), r'(fopen|file_get_contents|file_put_contents|unlink|readfile|BinaryFileResponse)\s*\(\s*[^)]*\$'),
    ('A10 SSRF', 'server-side request: is the URL user-supplied?', ('.php',), r'HttpClientInterface|->request\s*\(\s*[\'"](GET|POST)|curl_init|file_get_contents\s*\(\s*\$'),
    ('A01 Access control', 'new route: firewall, role and ownership checked, and a test for another tenant', ('.php',), r'#\[Route\('),
    ('A01 Access control', 'access check removed or loosened?', ('.php', '.yaml', '.yml'), r'IsGranted|access_control|PUBLIC_ACCESS|IS_AUTHENTICATED'),
    ('A04 Mass assignment', 'entity built from the request by the serializer: use an Input DTO', ('.php',), r'deserialize\s*\(.*Entity|denormalize\s*\('),
    ('A07 Auth', 'token/code handling: random_bytes, single use, hash_equals, expiry', ('.php',), r'\b(rand|mt_rand|uniqid)\s*\('),
    ('A05 Config', 'security/CORS/framework config changed', ('security.yaml', 'nelmio_cors.yaml', 'framework.yaml'), r'.'),
    ('Frontend', 'open redirect: only follow same-origin relative paths', ('.js', '.jsx', '.ts', '.tsx'), r'(window\.)?location(\.href)?\s*=\s*[^\'"]|navigate\(\s*(searchParams|params|query)'),
    ('Frontend', 'target=_blank without rel="noopener noreferrer"', ('.jsx', '.tsx', '.twig', '.html'), r'target=["\']_blank["\'](?!.*noopener)'),
    ('Frontend', 'token or secret in client code/storage', ('.js', '.jsx', '.ts', '.tsx'), r'localStorage\.setItem\([^)]*(token|secret)|process\.env\.[A-Z_]*(SECRET|KEY|TOKEN)'),
]
SKIP = re.compile(r'(^|/)(vendor|node_modules|var|public/build|\.git)/|\.lock$|package-lock\.json$|\.min\.js$|/assets/types/|^docs/')


def git(*args: str) -> str:
    return subprocess.run(['git', *args], capture_output=True, text=True, check=True).stdout


def default_base() -> str:
    try:
        return git('symbolic-ref', '--short', 'refs/remotes/origin/HEAD').strip()
    except subprocess.CalledProcessError:
        return 'origin/main'


def added_lines(base: str) -> list[tuple[str, int, str]]:
    """Every line the branch adds (committed, uncommitted and untracked), as (file, line number, text)."""
    merge_base = git('merge-base', base, 'HEAD').strip()
    out: list[tuple[str, int, str]] = []
    path, line = '', 0
    for raw in git('diff', '-U0', '--no-color', merge_base).splitlines():
        if raw.startswith('+++ '):
            path = raw[6:] if raw.startswith('+++ b/') else ''
        elif raw.startswith('@@'):
            line = int(re.search(r'\+(\d+)', raw).group(1))  # type: ignore[union-attr]
        elif raw.startswith('+') and path:
            out.append((path, line, raw[1:]))
            line += 1
    for path in git('ls-files', '--others', '--exclude-standard').splitlines():
        try:
            for number, text in enumerate(Path(path).read_text(errors='replace').splitlines(), 1):
                out.append((path, number, text))
        except (IsADirectoryError, FileNotFoundError):
            pass
    return [(p, n, t) for p, n, t in out if not SKIP.search(p)]


def running(service: str) -> bool:
    out = subprocess.run(['docker', 'compose', 'ps', '--status', 'running', '--services'], capture_output=True, text=True)
    return service in out.stdout.split()


def container(service: str, *cmd: str) -> tuple[int, str]:
    if not running(service):
        return -1, f'the {service} service is not running: not checked'
    res = subprocess.run(['docker', 'compose', 'exec', '-T', service, *cmd], capture_output=True, text=True)
    return res.returncode, (res.stdout + res.stderr).strip()


def main() -> int:
    base = next((a.split('=', 1)[1] for a in sys.argv[1:] if a.startswith('--base=')), default_base())
    root = Path(git('rev-parse', '--show-toplevel').strip())
    os.chdir(root)
    branch = git('rev-parse', '--abbrev-ref', 'HEAD').strip()
    feature = branch.removeprefix('feature/').replace('/', '-')
    commit = git('rev-parse', '--short', 'HEAD').strip()
    lines = added_lines(base)
    app = root / APP_DIR

    tools = []
    status = 0
    if (app / 'composer.json').exists():
        code, out = container(PHP, 'composer', 'audit', '--no-interaction')
        tools.append(('composer audit', code, out))
    if (app / 'package.json').exists():
        code, out = container(NODE, 'npm', 'audit', '--omit=dev')
        tools.append(('npm audit --omit=dev', code, out))
    for _, code, _ in tools:
        status |= code > 0

    secrets = [(p, n, t.strip()) for p, n, t in lines if SECRETS.search(t)]
    status |= bool(secrets)
    leads = []
    for category, why, suffixes, pattern in LEADS:
        rx = re.compile(pattern)
        for p, n, t in lines:
            if (suffixes is None or p.endswith(suffixes)) and rx.search(t):
                leads.append((category, why, p, n, t.strip()[:140]))
    files = sorted({p for p, _, _ in lines})

    print(f'Security audit leads for {branch} ({commit}) against {base}: {len(files)} files, {len(lines)} added lines\n')
    for name, code, out in tools:
        verdict = 'clean' if code == 0 else ('NOT RUN' if code < 0 else 'ADVISORIES')
        print(f'{name}: {verdict}')
        if code != 0:
            print('  ' + '\n  '.join(out.splitlines()[-25:]))
    print(f'\nSecret-looking lines added: {len(secrets)}')
    for p, n, t in secrets:
        print(f'  {p}:{n}  {t[:120]}')
    print(f'\nLeads to check against the checklist: {len(leads)}')
    for category, why, p, n, t in leads:
        print(f'  [{category}] {p}:{n}  {why}\n      {t}')

    security = root / 'docs' / 'security'
    (security / 'audits').mkdir(parents=True, exist_ok=True)
    if not (security / 'README.md').exists():
        (security / 'README.md').write_text((SKILL / 'templates' / 'security-README.md').read_text())
        print(f'\nInstalled the checklist: {(security / "README.md").relative_to(root)}')
    audit = security / 'audits' / f'{dt.date.today():%Y-%m-%d}-{feature}.md'
    if audit.exists():
        print(f'\n{audit.relative_to(root)} exists: not overwritten. Update it by hand.')
    else:
        tool_lines = '\n'.join(f'  - `{n}`: ' + ('clean' if c == 0 else 'not run (stack down)' if c < 0 else 'advisories, see Findings') for n, c, _ in tools)
        lead_lines = '\n'.join(f'- [ ] [{c}] `{p}:{n}`: {w}' for c, w, p, n, _ in leads) or '- none'
        audit.write_text(f'''# Security audit — {feature} — {dt.date.today():%Y-%m-%d}

- **Branch:** `{branch}` at `{commit}`, against `{base}`
- **Scope:** <!-- routes, inputs, uploads, rendered content, jobs, dependencies this feature added or changed -->
- **Tools:**
{tool_lines}
  - secrets in the diff: {len(secrets)} found{"" if not secrets else " (see Findings)"}

## Leads from audit.py

Each one checked against the checklist in `docs/security/README.md` and resolved into a finding or "nothing found".

{lead_lines}

## Findings

| # | Severity | Category | Where | What an attacker could do | Status |
|---|---|---|---|---|---|

## Checked, nothing found

<!-- every checklist section that applies, with what was checked -->

## Not applicable

<!-- sections that do not apply, and why -->
''')
        print(f'\nCreated {audit.relative_to(root)}: fill in scope, findings, and what was checked.')
    return status


if __name__ == '__main__':
    sys.exit(main())
