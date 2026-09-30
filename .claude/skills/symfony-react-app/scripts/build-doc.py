#!/usr/bin/env python3
"""Publish the skill's process (steps/) into the current project as docs/feature-development.md.

Run from anywhere inside the project's git checkout:

    python3 .claude/skills/symfony-react-app/scripts/build-doc.py           # write the doc
    python3 .claude/skills/symfony-react-app/scripts/build-doc.py --check   # exit 1 if the doc is out of date

The doc is how people without the skill (a teammate, a reviewer) read the process. Edit the step files, never the
generated doc: dod.py runs --check, so a stale or hand-edited copy is caught.
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

SKILL = Path(__file__).resolve().parents[1]
ROOT = Path(subprocess.run(['git', 'rev-parse', '--show-toplevel'], capture_output=True, text=True, check=True).stdout.strip())
DOC = ROOT / 'docs' / 'feature-development.md'
HEADER = ('<!-- Generated from the symfony-react-app Claude Code skill (steps/) by scripts/build-doc.py. '
          'Edit the skill\'s step files, not this one. -->\n\n')


def build() -> str:
    parts = [f.read_text().strip() for f in sorted((SKILL / 'steps').glob('*.md'))]
    return HEADER + '\n\n---\n\n'.join(parts) + '\n'


def main() -> int:
    text = build()
    if '--check' in sys.argv:
        if DOC.exists() and DOC.read_text() == text:
            return 0
        print(f'{DOC.relative_to(ROOT)} is out of date: run .claude/skills/symfony-react-app/scripts/build-doc.py', file=sys.stderr)
        return 1
    DOC.parent.mkdir(parents=True, exist_ok=True)
    DOC.write_text(text)
    print(f'wrote {DOC.relative_to(ROOT)}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
