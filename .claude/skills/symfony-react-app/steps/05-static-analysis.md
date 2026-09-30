## 5. Static analysis and code style

Once the feature works and its tests are green, the code goes through the analysers and formatters, **in a loop
until every one of them exits clean**. Tools apply the style, so reviews never discuss formatting.

| Language | Analyser (finds bugs) | Formatter / style (fixes layout) | Standard |
|---|---|---|---|
| PHP | PHPStan (+ Deptrac for layers) | PHP-CS-Fixer | Symfony (`@Symfony`, `@Symfony:risky`) |
| JS / TS | ESLint (+ `tsc --noEmit`) | Prettier | Google JavaScript Style Guide |

### 5.1 The loop

`scripts/gate.sh` runs step 1 with `--fix` and step 2 always, prints one PASS/FAIL/MISSING table, and exits 0 only
when every check passed. A tool the project lacks is MISSING, which fails the gate until it is installed and
configured (§5.2), or deliberately skipped with `--skip=<check>` and reported. A project adds its own checks as
executables in `.claude/gate.d/` (each one is a check named after its file). The commands it runs:

```bash
# 1. Fix what the tools can fix by themselves
docker compose exec php vendor/bin/php-cs-fixer fix
docker compose exec node npx prettier --write .
docker compose exec node npx eslint . --fix

# 2. Check: every command must exit 0
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php vendor/bin/phpstan
docker compose exec php composer deptrac
docker compose exec node npx prettier --check .
docker compose exec node npm run -s lint
docker compose exec node npm run -s typecheck

# 3. Fix what is left by hand, re-run the tests, and go back to 1
```

- **Loop until all six checks pass with no findings.** Stop only then, not when "most" are clean. A fix by hand
  often moves formatting, which is why the loop starts again at step 1.
- **Re-run the tests after fixing by hand.** PHPStan findings are sometimes real bugs, and the "fix" can change
  behaviour. `@Symfony:risky` rules change code too (strict comparisons, `static` closures), so the suite must stay
  green after them.
- **Fix findings. Never baseline or suppress them.** A PHPStan baseline, `// @phpstan-ignore`,
  `// eslint-disable` or `// prettier-ignore` is only acceptable for a deliberate exception, with the reason on
  the same line. Read a finding before "fixing" it: removing a guard to please the analyser can remove a real
  check.
- **Keep formatting and behaviour in separate commits.** The first time a formatter runs over an existing codebase,
  commit that alone (`style: apply Symfony/Google code style`) and add its hash to `.git-blame-ignore-revs`
  (`git config blame.ignoreRevsFile .git-blame-ignore-revs`), so `git blame` skips it. After that, each feature's
  formatting is part of its own commits.
- **Ignore generated files** (`var/`, `vendor/`, `node_modules/`, `public/build/`, generated API types) in every
  tool. Never hand-format generated code: regenerate it.

### 5.2 Default configuration when the project has none

Check first: `ls -a` for `.php-cs-fixer.dist.php`, `phpstan.dist.neon`, `eslint.config.*`, `.prettierrc*` and
`.editorconfig`. **Keep the configs the project already has**, and add only the missing ones:

**Installing the tools** (dev dependencies only):

```bash
docker compose exec php composer require --dev friendsofphp/php-cs-fixer phpstan/phpstan \
    phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/phpstan-phpunit phpstan/extension-installer
docker compose exec node npm install --save-dev prettier eslint-config-prettier
```

**`.php-cs-fixer.dist.php`**, the Symfony standard (the same ruleset Symfony's own code uses):

```php
<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor', 'node_modules', 'public'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
    ])
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder)
;
```

Add `.php-cs-fixer.cache` to `.gitignore`.

**PHP_CodeSniffer instead?** If the project already uses PHPCS, keep it and point it at the Symfony standard
(`composer require --dev escapestudios/symfony2-coding-standard`, `<rule ref="Symfony"/>` in `phpcs.xml.dist`),
and use `phpcbf` in step 1 of the loop and `phpcs` in step 2. **Never run both fixers on the same code:** their
rules differ in places and each undoes the other.

**`phpstan.dist.neon`**:

```neon
parameters:
    level: 6            # raise one level at a time once clean; never lower it
    paths:
        - src
        - tests
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:   # only with a loader script; see phpstan-doctrine's README
        objectManagerLoader: tests/object-manager.php
```

(`phpstan/extension-installer` loads the Symfony, Doctrine and PHPUnit extensions. If PHPStan cannot find the
container XML, warm it first with `bin/console cache:warmup --env=dev`.)

**`.prettierrc.json`**, the Google JavaScript Style Guide expressed as Prettier options:

```json
{
    "printWidth": 80,
    "tabWidth": 2,
    "useTabs": false,
    "semi": true,
    "singleQuote": true,
    "quoteProps": "consistent",
    "trailingComma": "all",
    "bracketSpacing": false,
    "arrowParens": "always",
    "endOfLine": "lf"
}
```

**`.prettierignore`**:

```
public/build/
vendor/
var/
node_modules/
assets/types/api.d.ts
assets/types/openapi.json
*.min.js
```

**ESLint:** Prettier owns formatting, and ESLint owns correctness plus the non-layout rules of the Google guide. Put
`eslint-config-prettier` **last** in `eslint.config.mjs`, so no ESLint rule fights Prettier:

```js
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    // …the project's existing configs (recommended, typescript-eslint, react, react-hooks)…
    {
        rules: {
            // Google JavaScript Style Guide rules that are not layout
            'no-var': 'error',
            'prefer-const': 'error',
            'prefer-rest-params': 'error',
            'prefer-spread': 'error',
            'eqeqeq': ['error', 'always', {null: 'ignore'}],
            'new-cap': 'error',
            'no-throw-literal': 'error',
            'guard-for-in': 'error',
            '@typescript-eslint/naming-convention': ['error',
                {selector: 'default', format: ['camelCase']},
                {selector: 'variable', format: ['camelCase', 'UPPER_CASE', 'PascalCase']},
                {selector: 'function', format: ['camelCase', 'PascalCase']},   // PascalCase: React components
                {selector: 'typeLike', format: ['PascalCase']},
                {selector: 'property', format: null},                          // API payloads keep their names
                {selector: 'import', format: null},
            ],
        },
    },
    prettier,
);
```

`eslint-config-google` is not used: it is unmaintained and names rules ESLint 9 removed (`valid-jsdoc`,
`require-jsdoc`), so it fails to load. Google's own maintained tool for TypeScript is `gts`. It is a reasonable
alternative if the project wants Google's exact rule set, but check that its version supports the project's
ESLint version before adopting it.

**`.editorconfig`**, so editors agree with the formatters before they run:

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true

[*.php]
indent_style = space
indent_size = 4

[*.{js,jsx,ts,tsx,mjs,mts,json,css,scss,yml,yaml}]
indent_style = space
indent_size = 2
```

**Scripts**, so the loop is two commands:

```json
// package.json
"format": "prettier --write . && eslint . --fix",
"format:check": "prettier --check . && eslint ."
```

```json
// composer.json
"cs": "php-cs-fixer fix --dry-run --diff",
"cs:fix": "php-cs-fixer fix",
"analyse": ["@cs", "phpstan analyse", "@deptrac"]
```

**Format on save.** `scripts/format-on-edit.sh` formats each file Claude writes, with PHP-CS-Fixer or Prettier,
but only when the project has that tool's config (it does nothing otherwise). Install it once as a `PostToolUse`
hook on `Write|Edit` in `.claude/settings.json`. The skill's README has the snippet.

### 5.3 Adopting the style on an existing codebase

A codebase formatted another way (e.g. 4-space JS) changes almost every line the first time. Do it **once, on its
own branch, before any feature work**. Run the fixers over everything, run the whole test suite and the browser
smoke checks, and merge that alone, with its hash in `.git-blame-ignore-revs`. Never mix it into a feature
branch: it hides the feature's diff and conflicts with every other open branch. Tell open branches to merge it
and re-run the formatters.

### 5.4 The gate

The feature leaves this step when all of these exit 0 with no new baseline or ignore entries:
`php-cs-fixer fix --dry-run`, `phpstan`, `deptrac`, `prettier --check`, `eslint`, `tsc --noEmit`, and the test
suites again afterwards.
