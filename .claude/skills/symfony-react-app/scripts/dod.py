#!/usr/bin/env python3
"""Check the definition of done (steps/10-definition-of-done.md) for the current feature branch.

Run from anywhere inside the project's git checkout, with its Docker stack up:

    python3 .claude/skills/symfony-react-app/scripts/dod.py            # everything, including gate and tests
    python3 .claude/skills/symfony-react-app/scripts/dod.py --quick    # leave out the gate and the test suites
    python3 .claude/skills/symfony-react-app/scripts/dod.py --item     # one item of a split (steps/02b-split.md)

An item is checked against the base branch it was cut from (split.py records it), and without the security audit
and the regression run: those are done once, on the base branch, which fails while an item of its split is not merged.

It checks what a script can check and prints the rest as MANUAL items to confirm in the report. Exit 0 only when
nothing FAILs. Settings: PHP_SERVICE (php), NODE_SERVICE (node), APP_DIR (backend).
"""

from __future__ import annotations

import os
import re
import subprocess
import sys
from pathlib import Path

SCRIPTS = Path(__file__).resolve().parent
PHP = os.environ.get('PHP_SERVICE', 'php')
NODE = os.environ.get('NODE_SERVICE', 'node')
APP_DIR = os.environ.get('APP_DIR', 'backend')

MANUAL = [
    'Plan written: role, owning context and FSD slice, layers, tests and browser cases, what is left out',
    'Every behaviour had its test written first; another tenant gets 404 on every new id route',
    'Backend in its bounded context: commands for writes, DomainErrors, handlers never flush, Output DTOs',
    'Frontend in FSD layers with public APIs, types from the API schema, strings through i18n, house components',
    'Every new or changed screen and modal opened in the browser on this stack, no console errors',
    'README: API rows, data model decisions, known gaps; help articles if the project has them',
]


def sh(*args: str, check: bool = False) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, capture_output=True, text=True, check=check)


def git(*args: str) -> str:
    return sh('git', *args, check=True).stdout.strip()


ITEM_MANUAL = [
    'Only what the item owns in the split table changed; nothing in another item\'s files or the contract',
    'Every behaviour had its test written first; another tenant gets 404 on every new id route',
    'Backend in its bounded context: commands for writes, DomainErrors, handlers never flush, Output DTOs',
    'Frontend in FSD layers with public APIs, types from the API schema, strings through i18n, house components',
    'The item\'s screens opened in the browser on its own stack, no console errors',
    'The item\'s regression cases written into docs/tests/ui-regression.md, in its ID range',
]


def main() -> int:
    quick = '--quick' in sys.argv
    item = '--item' in sys.argv
    root = Path(git('rev-parse', '--show-toplevel'))
    os.chdir(root)
    try:
        base = git('symbolic-ref', '--short', 'refs/remotes/origin/HEAD')
    except subprocess.CalledProcessError:
        base = 'origin/main'
    branch = git('rev-parse', '--abbrev-ref', 'HEAD')
    split_base = sh('git', 'config', f'branch.{branch}.splitBase').stdout.strip()
    if item:
        if not split_base:
            print(f'dod.py --item: {branch} is not an item of a split (no branch.{branch}.splitBase; see split.py start)', file=sys.stderr)
            return 1
        base = split_base
    base = next((a.split('=', 1)[1] for a in sys.argv[1:] if a.startswith('--base=')), base)
    feature = branch.removeprefix('feature/').replace('/', '-')
    merge_base = git('merge-base', base, 'HEAD')
    changed = set(git('diff', '--name-only', merge_base).splitlines()) | set(git('ls-files', '--others', '--exclude-standard').splitlines())
    added = sh('git', 'diff', '-U0', merge_base).stdout
    running = sh('docker', 'compose', 'ps', '--status', 'running', '--services').stdout.split()

    results: list[tuple[str, str, str]] = []

    def check(name: str, ok: bool | None, detail: str = '') -> None:
        results.append((name, 'PASS' if ok else 'SKIP' if ok is None else 'FAIL', detail))

    # Branch
    check('On a feature branch, not the base', branch not in ('main', 'master', base.split('/')[-1]), branch)
    behind = sh('git', 'merge-base', '--is-ancestor', base, 'HEAD').returncode == 0
    check(f'Up to date with {base}', behind, '' if behind else f'merge or rebase {base} into the branch')

    # Static analysis gate
    if quick:
        check('Static analysis gate (gate.sh)', None, '--quick')
    else:
        gate = sh(str(SCRIPTS / 'gate.sh'))
        table = '; '.join(l for l in gate.stdout.splitlines() if re.match(r'^\w[\w.-]*\s+(FAIL|MISSING)$', l))
        check('Static analysis gate (gate.sh)', gate.returncode == 0, table)

    # Tests
    if quick:
        check('Test suites', None, '--quick')
    else:
        if (root / APP_DIR / 'phpunit.dist.xml').exists() or (root / APP_DIR / 'phpunit.xml.dist').exists():
            res = sh('docker', 'compose', 'exec', '-T', PHP, 'php', 'bin/phpunit')
            last = [l for l in res.stdout.splitlines() if l.strip()][-1:] or ['no output']
            check('PHPUnit, whole suite', res.returncode == 0, last[0])
        if '"test"' in (root / APP_DIR / 'package.json').read_text() if (root / APP_DIR / 'package.json').exists() else False:
            res = sh('docker', 'compose', 'exec', '-T', NODE, 'npm', 'test', '--silent')
            check('Frontend tests (npm test)', res.returncode == 0, 'see npm test' if res.returncode else '')

    # Migrations on dev and test databases
    if any('/migrations/' in f or f.startswith('migrations/') for f in changed):
        if PHP in running:
            for env in ('dev', 'test'):
                res = sh('docker', 'compose', 'exec', '-T', PHP, 'php', 'bin/console', 'doctrine:migrations:up-to-date', f'--env={env}')
                check(f'Migrations run on the {env} database', res.returncode == 0, res.stdout.strip().splitlines()[-1] if res.stdout.strip() else '')
        else:
            check('Migrations run on dev and test', False, f'{PHP} service not running')

    # API schema and types regenerated when the contract may have changed
    # A controller is part of the API contract when it serves the API, not when it renders a page.
    def api_file(f: str) -> bool:
        if re.search(r'/(Output|Input)/.*\.php$', f):
            return True
        path = root / f
        return bool(re.search(r'/Controller/.*\.php$', f)) and path.exists() and \
            bool(re.search(r'#\[ApiResponse|Route\(\s*[\'"]/api', path.read_text()))

    contract = [f for f in changed if api_file(f)]
    schema = [f for f in changed if f.endswith(('openapi.json', 'api.d.ts'))]
    if contract and list(root.glob(f'{APP_DIR}/assets/**/openapi.json')):
        check('API schema and TS types regenerated', bool(schema), '' if schema else f'{len(contract)} controller/DTO files changed, no openapi.json/api.d.ts')

    # README when routes were added
    if re.search(r'^\+.*#\[Route\(', added, re.M):
        check('README updated for new routes', 'README.md' in changed)

    # Split: an item is checked on its own; the base branch only once every item is merged
    plan = sh('git', 'config', f'branch.{branch}.splitPlan').stdout.strip()
    if item:
        check('Security audit and regression run', None, 'done once on the base branch')
    elif plan:
        res = sh(sys.executable, str(SCRIPTS / 'split.py'), 'status', plan, '--check')
        check('Every item of the split merged', res.returncode == 0, res.stderr.strip().splitlines()[-1] if res.returncode else plan)

    # Security audit
    if not item:
        audits = sorted((root / 'docs/security/audits').glob(f'*-{feature}.md'))
        if not audits:
            check('Security audit recorded', False, f'no docs/security/audits/*-{feature}.md: run audit.py')
        else:
            text = audits[-1].read_text()
            open_leads = len(re.findall(r'^- \[ \]', text, re.M))
            blocking = re.findall(r'^\|\s*\d+\s*\|\s*(Critical|High)\s*\|.*\|\s*(?!Fixed)[^|]*\|\s*$', text, re.M)
            check('Security audit recorded', open_leads == 0, f'{open_leads} leads not resolved' if open_leads else audits[-1].name)
            check('No open critical/high finding', not blocking, f'{len(blocking)} open' if blocking else '')

        # Regression run
        runs = sorted((root / 'docs/tests/runs').glob(f'*-{feature}.md')) + sorted((root / 'docs/tests/runs').glob('*-baseline.md'))
        runs = [r for r in runs if r.name[:10] >= git('log', '-1', '--format=%cs', merge_base)]
        if not runs:
            check('Regression run recorded', False, f'no docs/tests/runs/*-{feature}.md: run new-run.py')
        else:
            # Result rows only (| ID | Not run | …), not the summary's "| Not run | N … |".
            not_run = len(re.findall(r'^\|\s*[A-Z][A-Z0-9-]*-\d+\s*\|\s*Not run\s*\|', runs[-1].read_text(), re.M))
            check('Regression run recorded', not_run == 0, f'{not_run} cases still "Not run"' if not_run else runs[-1].name)

    # Published process doc in sync
    if (root / 'docs/feature-development.md').exists():
        res = sh(sys.executable, str(SCRIPTS / 'build-doc.py'), '--check')
        check('docs/feature-development.md matches the skill', res.returncode == 0, res.stderr.strip())

    width = max(len(n) for n, _, _ in results)
    print(f'Definition of done: {branch} against {base}\n')
    for name, verdict, detail in results:
        print(f'{verdict:<5} {name:<{width}}  {detail}')
    print()
    for manual in ITEM_MANUAL if item else MANUAL:
        print(f'MANUAL {manual}')
    failed = [n for n, v, _ in results if v == 'FAIL']
    print(f'\n{"DONE (confirm the MANUAL items)" if not failed else f"NOT DONE: {len(failed)} failing"}')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
