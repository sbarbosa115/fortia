#!/usr/bin/env python3
"""Run a feature as parallel items (steps/02b-split.md), from the split table in its PRD.

Run from anywhere inside the project's git checkout:

    split.py plan   docs/pdr/prd-<feature>.md                 # check the table, print the waves
    split.py start  docs/pdr/prd-<feature>.md <slug>…         # branch + worktree + .env ports for these items
    split.py start  docs/pdr/prd-<feature>.md --wave=1        # … for every item of a wave
    split.py status docs/pdr/prd-<feature>.md [--check]       # per item: worktree, commits, merged? (--check: exit 1
                                                              #   while an item is not merged)
    split.py prompt docs/pdr/prd-<feature>.md <slug>          # the brief for the agent or session building the item

The feature is the PRD's file name without "prd-" (or --feature=<name>); its base branch is feature/<feature>, and
item <slug> is built on feature/<feature>-<slug> in ../<repo>-<feature>-<slug>. Item 0 is built on the base branch
itself. `start` refuses an item whose dependencies are not merged into the base branch (--force to override).
"""

from __future__ import annotations

import re
import socket
import subprocess
import sys
from dataclasses import dataclass, field
from pathlib import Path

SLUG = re.compile(r'^[a-z0-9][a-z0-9-]*$')
CASES = re.compile(r'([A-Z][A-Z0-9]*)-(\d+)(?:\s*[–-]\s*(?:[A-Z][A-Z0-9]*-)?(\d+))?')
COMPOSE_PORT = re.compile(r'\$\{(\w+):-(\d+)\}:\d+')
PORT_OFFSET, PORT_STEP = 10000, 100


@dataclass
class Item:
    id: str
    slug: str
    title: str
    owns: str = ''
    tests: str = ''
    cases: str = ''
    depends: list[str] = field(default_factory=list)
    row: str = ''


class SplitError(Exception):
    pass


def sh(*args: str, cwd: Path | None = None, check: bool = True) -> str:
    res = subprocess.run(args, capture_output=True, text=True, cwd=cwd)
    if check and res.returncode:
        raise SplitError(f'{" ".join(args)}: {res.stderr.strip() or res.stdout.strip()}')
    return res.stdout.strip()


def git(*args: str, cwd: Path | None = None, check: bool = True) -> str:
    return sh('git', *args, cwd=cwd, check=check)


def blank(cell: str) -> bool:
    return cell.strip() in ('', '—', '-', '–', 'none')


# ── The split table ──────────────────────────────────────────────────────────────────────────────────────────────


def parse(prd: Path) -> list[Item]:
    lines = prd.read_text().splitlines()
    try:
        start = next(i for i, l in enumerate(lines) if re.match(r'^##\s+Split\s*$', l))
    except StopIteration:
        raise SplitError(f'{prd}: no "## Split" section') from None
    table = []
    for line in lines[start + 1:]:
        if line.startswith('## '):
            break
        if line.strip().startswith('|'):
            table.append(line.strip())
    if len(table) < 3:
        raise SplitError(f'{prd}: the "## Split" section has no table rows')

    def cells(line: str) -> list[str]:
        return [c.strip() for c in line.strip('|').split('|')]

    header = [h.lower() for h in cells(table[0])]

    def col(*names: str) -> int:
        for i, h in enumerate(header):
            if any(h.startswith(n) for n in names):
                return i
        raise SplitError(f'{prd}: the split table has no "{names[0]}" column')

    ci, cs, ct = col('#'), col('slug'), col('item')
    co, cte, cb, cd = col('owns'), col('tests'), col('browser'), col('depends')
    items = []
    for line in table[2:]:
        c = cells(line)
        if len(c) < len(header):
            raise SplitError(f'{prd}: row has {len(c)} cells, the header {len(header)}: {line}')
        deps = [] if blank(c[cd]) else [d.strip() for d in re.split(r'[,\s]+', c[cd]) if d.strip()]
        items.append(Item(c[ci], c[cs], c[ct], c[co], c[cte], c[cb], deps, line))
    return items


def validate(items: list[Item]) -> list[str]:
    errors = []
    ids = [i.id for i in items]
    slugs = [i.slug for i in items]
    for dup in {x for x in ids if ids.count(x) > 1}:
        errors.append(f'item # {dup} is used twice')
    for dup in {x for x in slugs if slugs.count(x) > 1}:
        errors.append(f'slug "{dup}" is used twice')
    for i in items:
        if not SLUG.match(i.slug):
            errors.append(f'item {i.id}: slug "{i.slug}" must be lowercase letters, digits and dashes')
        for d in i.depends:
            if d not in ids:
                errors.append(f'item {i.id} depends on unknown item {d}')
            if d == i.id:
                errors.append(f'item {i.id} depends on itself')
    if '0' not in ids:
        errors.append('there is no item 0 (the contract, built first on the base branch)')
    elif next(i for i in items if i.id == '0').depends:
        errors.append('item 0 cannot depend on anything')
    for i in items:
        if i.id != '0' and not i.depends:
            errors.append(f'item {i.id} depends on nothing: every item builds on item 0 (write "0")')

    # Case ID ranges: one owner per number.
    owner: dict[tuple[str, int], str] = {}
    for i in items:
        if blank(i.cases):
            continue
        found = CASES.findall(i.cases)
        if not found:
            errors.append(f'item {i.id}: cannot read the browser cases "{i.cases}" (write e.g. ORD-06 – 09)')
        for prefix, lo, hi in found:
            for n in range(int(lo), int(hi or lo) + 1):
                if (prefix, n) in owner:
                    errors.append(f'{prefix}-{n:02d} is claimed by items {owner[(prefix, n)]} and {i.id}')
                owner[(prefix, n)] = i.id

    if all(d in ids for i in items for d in i.depends) and len(set(ids)) == len(ids):
        try:
            waves(items)
        except SplitError as e:
            errors.append(str(e))
    return errors


def waves(items: list[Item]) -> dict[str, int]:
    by_id = {i.id: i for i in items}
    wave: dict[str, int] = {}

    def visit(iid: str, path: tuple[str, ...]) -> int:
        if iid in path:
            raise SplitError(f'dependency cycle: {" → ".join(path + (iid,))}')
        if iid not in wave:
            deps = by_id[iid].depends
            wave[iid] = 0 if not deps else 1 + max(visit(d, path + (iid,)) for d in deps)
        return wave[iid]

    for i in items:
        visit(i.id, ())
    return wave


# ── Git, worktrees and ports ─────────────────────────────────────────────────────────────────────────────────────


@dataclass
class Repo:
    root: Path  # the checkout split.py runs in
    main: Path  # the main worktree
    feature: str

    @property
    def base(self) -> str:
        return f'feature/{self.feature}'

    def branch(self, item: Item) -> str:
        return self.base if item.id == '0' else f'{self.base}-{item.slug}'

    def worktree(self, item: Item) -> Path:
        suffix = '' if item.id == '0' else f'-{item.slug}'
        return self.main.parent / f'{self.main.name}-{self.feature}{suffix}'

    def exists(self, branch: str) -> bool:
        return bool(git('rev-parse', '--verify', '--quiet', f'refs/heads/{branch}', cwd=self.root, check=False))

    def merged(self, item: Item) -> bool:
        if not self.exists(self.base):
            return False
        if item.id == '0':
            # Item 0 lives on the base branch: done once the base branch has its own commits.
            default = default_branch(self.root)
            return int(git('rev-list', '--count', f'{default}..{self.base}', cwd=self.root) or 0) > 0
        branch = self.branch(item)
        if not self.exists(branch):
            return False
        tip = git('rev-parse', branch, cwd=self.root)
        # A branch just started has no commits: its tip is already in the base branch but nothing was merged.
        started_at = git('config', f'branch.{branch}.splitFrom', cwd=self.root, check=False)
        if tip == started_at:
            return False
        return subprocess.run(['git', 'merge-base', '--is-ancestor', branch, self.base], cwd=self.root).returncode == 0


def default_branch(root: Path) -> str:
    ref = git('symbolic-ref', '--short', 'refs/remotes/origin/HEAD', cwd=root, check=False)
    return ref or 'origin/main'


def worktrees(root: Path) -> list[Path]:
    return [Path(l.split(' ', 1)[1]) for l in git('worktree', 'list', '--porcelain', cwd=root).splitlines() if l.startswith('worktree ')]


def compose_ports(main: Path) -> dict[str, int]:
    for name in ('docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'):
        if (main / name).exists():
            return {var: int(default) for var, default in COMPOSE_PORT.findall((main / name).read_text())}
    return {}


def read_env(path: Path) -> dict[str, str]:
    env = {}
    if path.exists():
        for line in path.read_text().splitlines():
            m = re.match(r'^\s*([A-Za-z_]\w*)\s*=\s*(.*?)\s*$', line)
            if m:
                env[m.group(1)] = m.group(2).strip('"\'')
    return env


def port_free(port: int) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            s.bind(('0.0.0.0', port))
        except OSError:
            return False
    return True


def pick_ports(repo: Repo, taken_extra: set[int]) -> dict[str, int]:
    """The same step above each compose default for every port, clear of other worktrees' .env and of listeners."""
    defaults = compose_ports(repo.main)
    if not defaults:
        return {}
    taken = set(taken_extra) | set(defaults.values())
    siblings = {p.parent for p in worktrees(repo.root)} | {repo.main.parent}
    for folder in siblings:
        for env_file in folder.glob('*/.env'):
            for var in defaults:
                value = read_env(env_file).get(var, '')
                if value.isdigit():
                    taken.add(int(value))
    for k in range(1, 200):
        ports = {var: d + PORT_OFFSET + k * PORT_STEP for var, d in defaults.items()}
        if all(p < 65536 and p not in taken and port_free(p) for p in ports.values()):
            return ports
    raise SplitError('no free set of host ports found')


# ── Commands ─────────────────────────────────────────────────────────────────────────────────────────────────────


def load(argv: list[str]) -> tuple[Repo, list[Item], Path, list[str]]:
    args = [a for a in argv if not a.startswith('--')]
    if not args:
        raise SplitError('give the PRD: split.py <command> docs/pdr/prd-<feature>.md …')
    root = Path(git('rev-parse', '--show-toplevel'))
    prd = Path(args[0])
    prd = prd if prd.is_absolute() else (Path.cwd() / prd)
    if not prd.exists():
        raise SplitError(f'{args[0]}: no such file')
    feature = next((a.split('=', 1)[1] for a in argv if a.startswith('--feature=')), None)
    feature = feature or prd.stem.removeprefix('prd-')
    if not SLUG.match(feature):
        raise SplitError(f'feature name "{feature}" (from the file name) must be lowercase letters, digits and dashes; use --feature=')
    main = Path(git('rev-parse', '--path-format=absolute', '--git-common-dir', cwd=root)).parent
    items = parse(prd)
    errors = validate(items)
    if errors:
        raise SplitError('the split table has problems:\n  - ' + '\n  - '.join(errors))
    return Repo(root, main, feature), items, prd, args[1:]


def cmd_plan(argv: list[str]) -> int:
    repo, items, prd, _ = load(argv)
    wave = waves(items)
    print(f'{prd.name}: {len(items)} items, base branch {repo.base}\n')
    for w in range(max(wave.values()) + 1):
        members = [i for i in items if wave[i.id] == w]
        label = 'Wave 0 (on the base branch, alone)' if w == 0 else f'Wave {w} (in parallel)'
        print(label)
        for i in members:
            deps = f'  after {", ".join(i.depends)}' if i.depends else ''
            print(f'  {i.id:>3}  {i.slug:<20} {i.title}{deps}')
        print()
    widest = max(sum(1 for i in items if wave[i.id] == w) for w in range(1, max(wave.values()) + 1)) if len(items) > 1 else 0
    if widest > 4:
        print(f'Note: a wave of {widest} items means {widest} Docker stacks at once; start them in groups of 3–4.')
    return 0


def cmd_start(argv: list[str]) -> int:
    repo, items, prd, slugs = load(argv)
    force = '--force' in argv
    wave_arg = next((a.split('=', 1)[1] for a in argv if a.startswith('--wave=')), None)
    wave = waves(items)
    if wave_arg is not None:
        chosen = [i for i in items if str(wave[i.id]) == wave_arg]
    else:
        unknown = [s for s in slugs if s not in {i.slug for i in items}]
        if unknown:
            raise SplitError(f'unknown slug(s): {", ".join(unknown)}')
        chosen = [i for i in items if i.slug in slugs]
    if not chosen:
        raise SplitError('no items chosen: give slugs or --wave=N')
    if any(i.id == '0' for i in chosen):
        raise SplitError('item 0 is built on the base branch itself (steps/03-branch.md), not started here')
    if not repo.exists(repo.base):
        raise SplitError(f'no branch {repo.base}: create the base branch and build item 0 first (steps/03-branch.md)')

    by_id = {i.id: i for i in items}
    rel_prd = prd.relative_to(repo.root) if prd.is_relative_to(repo.root) else prd
    git('config', f'branch.{repo.base}.splitPlan', str(rel_prd), cwd=repo.root)
    claimed: set[int] = set()
    started = 0
    for item in chosen:
        branch, path = repo.branch(item), repo.worktree(item)
        waiting = [d for d in item.depends if not repo.merged(by_id[d])]
        if waiting and not force:
            print(f'SKIP  {item.slug}: waits for item(s) {", ".join(waiting)} to be merged into {repo.base} (--force to start anyway)')
            continue
        if path.exists():
            print(f'SKIP  {item.slug}: {path} already exists')
            continue
        if repo.exists(branch):
            git('worktree', 'add', str(path), branch, cwd=repo.root)
        else:
            git('worktree', 'add', '-b', branch, str(path), repo.base, cwd=repo.root)
        git('config', f'branch.{branch}.splitBase', repo.base, cwd=repo.root)
        git('config', f'branch.{branch}.splitFrom', git('rev-parse', branch, cwd=repo.root), cwd=repo.root)
        ports = pick_ports(repo, claimed)
        claimed |= set(ports.values())
        env = path / '.env'
        if ports and not env.exists():
            env.write_text(''.join(f'{k}={v}\n' for k, v in ports.items()))
            if subprocess.run(['git', 'check-ignore', '-q', '.env'], cwd=path).returncode:
                print(f'WARN  {path}/.env is not gitignored: do not commit it')
        started += 1
        print(f'START {item.slug}: {branch} in {path}' + (f'  ({", ".join(f"{k}={v}" for k, v in ports.items())})' if ports else ''))
    if started:
        print('\nNext, for each item: bring up its stack (steps/07-verify.md §7.1), then hand it its brief:')
        print(f'  python3 {Path(__file__).resolve()} prompt {rel_prd} <slug>')
    return 0


def cmd_status(argv: list[str]) -> int:
    repo, items, _, _ = load(argv)
    wave = waves(items)
    open_items = []
    print(f'{"#":>3}  {"slug":<20} {"wave":<5} {"branch":<45} {"ahead":<6} state')
    for item in items:
        branch, path = repo.branch(item), repo.worktree(item)
        merged = repo.merged(item)
        if item.id == '0':
            ahead = git('rev-list', '--count', f'{default_branch(repo.root)}..{repo.base}', cwd=repo.root, check=False) if repo.exists(repo.base) else '-'
            state = 'built on the base branch' if merged else 'not built yet'
        elif not repo.exists(branch):
            ahead, state = '-', 'not started'
        else:
            ahead = git('rev-list', '--count', f'{repo.base}..{branch}', cwd=repo.root)
            state = 'merged' if merged else ('in progress' if path.exists() else 'branch only, no worktree')
            if merged and path.exists():
                state += ' (worktree can be removed)'
        if not merged:
            open_items.append(item.slug)
        print(f'{item.id:>3}  {item.slug:<20} {wave[item.id]:<5} {branch:<45} {ahead:<6} {state}')
    if '--check' in argv:
        if open_items:
            print(f'\nNot merged: {", ".join(open_items)}', file=sys.stderr)
            return 1
    return 0


def cmd_prompt(argv: list[str]) -> int:
    repo, items, prd, slugs = load(argv)
    if len(slugs) != 1:
        raise SplitError('give one slug: split.py prompt <prd> <slug>')
    item = next((i for i in items if i.slug == slugs[0]), None)
    if item is None:
        raise SplitError(f'unknown slug: {slugs[0]}')
    if item.id == '0':
        raise SplitError('item 0 is built on the base branch by the coordinator; it has no brief')
    path = repo.worktree(item)
    env = read_env(path / '.env')
    http = next((v for k, v in env.items() if 'HTTP' in k), None)
    rel_prd = prd.relative_to(repo.root) if prd.is_relative_to(repo.root) else prd
    others = [i for i in items if i.id not in ('0', item.id)]
    print(f"""You are building one item of a feature split into parallel items, with the symfony-react-app skill
(.claude/skills/symfony-react-app/). Read its steps 04, 05 and 07, and 02b-split.md, and the project's README.md and
CLAUDE.md, before writing code.

**Item {item.id} · {item.title}** (`{item.slug}`) of `{repo.feature}`: the plan is `{rel_prd}` (read it all, above
all "Contract" and "Decisions").

| Owns | Tests first | Browser cases | Depends on |
|---|---|---|---|
| {item.owns} | {item.tests} | {item.cases} | {', '.join(item.depends)} |

**Where:** worktree `{path}`, branch `{repo.branch(item)}`, cut from `{repo.base}`. Your shell may start somewhere
else: begin every command with `cd {path} &&`. Use this worktree's Docker stack only{f' (app http://localhost:{http})' if http else ''}:
bring it up first (steps/07-verify.md §7.1). Never touch the main checkout, `main`, or another item's branch.

**Scope:** only what this item owns. Other items are being built at the same time:
{chr(10).join(f'- {i.id} `{i.slug}`: {i.title} (owns {i.owns})' for i in others) or '- none'}
If the item needs a change in a file another item owns, or in the contract (schema, a shared table, an Output DTO
of another endpoint), stop and report it instead of making the change.

**Shared files:** only your own i18n keys, CSS and README API rows, and regression cases only in your range
({item.cases}). No migration unless the plan names a table only this item owns. After changing a controller or a
DTO, regenerate `openapi.json` and `api.d.ts` (never edit them).

**Done means:** test-first (step 04), `gate.sh` clean (step 05), the whole PHPUnit and Vitest suites green, your
screens opened in the browser on this stack with no console errors (step 07.3), your regression cases written into
`docs/tests/ui-regression.md`, and `dod.py --item` passing. Commit in small steps on `{repo.branch(item)}`. Do not
merge, push, run the security audit or record a regression run: the coordinator does those once, on the base branch.

**Report back:** what you built per layer, the tests you wrote, the commands' results (gate, suites, dod.py --item),
anything you could not do, and anything the coordinator must decide or change in the plan.""")
    return 0


COMMANDS = {'plan': cmd_plan, 'start': cmd_start, 'status': cmd_status, 'prompt': cmd_prompt}


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] not in COMMANDS:
        print(__doc__.strip())
        return 2
    try:
        return COMMANDS[sys.argv[1]](sys.argv[2:])
    except SplitError as e:
        print(f'split.py: {e}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
