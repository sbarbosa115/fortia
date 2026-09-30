#!/usr/bin/env python3
"""Create the file for a regression run (steps/08-regression-run.md), and the suite itself when there is none.

Run from anywhere inside the project's git checkout:

    python3 .claude/skills/symfony-react-app/scripts/new-run.py            # docs/tests/runs/<date>-<feature>.md
    python3 .claude/skills/symfony-react-app/scripts/new-run.py --name=release-2.3

It reads every case ID from docs/tests/ui-regression.md (lines like `**AREA-01 · What the user does**`) and lists
each one as "Not run". Fill in the results while running. With no suite yet it writes the skeleton from
templates/ui-regression.md and names the run "baseline": write the cases for what exists first, then run it.
An existing run file is never overwritten.
"""

from __future__ import annotations

import datetime as dt
import re
import subprocess
import sys
from pathlib import Path

SKILL = Path(__file__).resolve().parents[1]
CASE = re.compile(r'^\*\*([A-Z][A-Z0-9]*(?:-[A-Z][A-Z0-9]*)*-\d+)\s*·\s*(.+?)\*\*', re.M)


def git(*args: str) -> str:
    return subprocess.run(['git', *args], capture_output=True, text=True, check=True).stdout.strip()


def main() -> int:
    root = Path(git('rev-parse', '--show-toplevel'))
    tests = root / 'docs' / 'tests'
    suite = tests / 'ui-regression.md'
    branch = git('rev-parse', '--abbrev-ref', 'HEAD')
    commit = git('rev-parse', '--short', 'HEAD')
    name = next((a.split('=', 1)[1] for a in sys.argv[1:] if a.startswith('--name=')), None)

    baseline = not suite.exists()
    if baseline:
        tests.mkdir(parents=True, exist_ok=True)
        suite.write_text((SKILL / 'templates' / 'ui-regression.md').read_text())
        print(f'No suite yet: wrote the skeleton {suite.relative_to(root)}. Add a case for everything that exists, then run it.')
        name = name or 'baseline'
    name = name or branch.removeprefix('feature/').replace('/', '-')

    cases = CASE.findall(suite.read_text())
    run = tests / 'runs' / f'{dt.date.today():%Y-%m-%d}-{name}.md'
    if run.exists():
        print(f'{run.relative_to(root)} exists: not overwritten.')
        return 1
    run.parent.mkdir(parents=True, exist_ok=True)
    rows = '\n'.join(f'| {cid} | Not run | {title} |' for cid, title in cases)
    run.write_text(f'''# UI regression run — {dt.date.today():%Y-%m-%d} ({name})

- **Suite:** [`../ui-regression.md`](../ui-regression.md), at `{commit}`{", first version (the baseline)" if baseline else ""}.
- **Branch:** `{branch}` at `{commit}`, on this checkout's stack (app <!-- URL -->).
- **Data:** <!-- reset to the seed before the run / not reset (why) -->
- **How:** browser driven through the real UI; emails read in the mail catcher; database/logs where a screen could not prove it.

## Summary

| | Cases |
|---|---|
| Cases in the suite | {len(cases)} |
| Pass | <!-- N (M after a fix made during the run) --> |
| Fail | <!-- N: IDs --> |

## Results

Replace "Not run" with Pass, Fail, "Pass after fix" (with the commit) or Blocked (with why). Group consecutive
passes into ranges (`AREA-01 – 05`) once done.

| ID | Result | Case / notes |
|---|---|---|
{rows}

## Findings

<!-- Numbered: what happened, which case, the cause, and the fix (commit) or why it was left. -->

## Conditions

<!-- Anything about the environment that could have affected the result. -->
''')
    print(f'Created {run.relative_to(root)} with {len(cases)} cases to run.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
