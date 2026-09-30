## 1. The stack

| Layer | Technology | Notes |
|---|---|---|
| Runtime | Docker Compose | Nothing runs on the host: no PHP, no Node. Every command is `docker compose exec <service> …` |
| Backend | PHP 8.x, Symfony, Doctrine ORM, Doctrine Migrations | JSON API under `/api/`; Twig only for public, non-React pages |
| Database | MySQL (or the project's RDBMS) | A separate `_test` database for the test suite |
| Async work | Symfony Messenger + a `worker` container | Email, exports and third-party calls go on the queue |
| Mail (dev) | Mailpit | Every email sent locally is caught and readable in its web UI |
| Frontend | React + TypeScript (strict), built by Webpack Encore or Vite | A `node` container rebuilds on every save; read its logs instead of running a build |
| API contract | OpenAPI generated from the controllers → `openapi-typescript` | The UI's types come from the API schema, never written by hand |
| Static analysis | PHPStan, Deptrac, ESLint, `tsc --noEmit` | Deptrac enforces the DDD layers; ESLint's `react/jsx-no-undef` catches blank screens |
| Code style | PHP-CS-Fixer (`@Symfony`), Prettier (Google style) | Formatting is applied by the tools, never argued about in review (§5) |
| Security | `composer audit`, `npm audit`, the checklist in `docs/security/` | Run on every feature (§6) |
| Tests | PHPUnit (unit + functional), Vitest + Testing Library, Playwright | See the testing pyramid in §7.2 |

Typical services in `docker-compose.yml`: `php`, `nginx`, `database`, `worker`, `node`, `mailpit`, and `e2e`
behind a profile. The commands used throughout this guide are:

```bash
docker compose exec php php bin/console …          # Symfony console
docker compose exec php php bin/phpunit            # the whole PHP suite
docker compose exec php vendor/bin/phpstan         # PHP static analysis
docker compose exec php composer deptrac           # layer and context dependencies
docker compose exec node npm run -s lint           # ESLint
docker compose exec node npm run -s typecheck      # TypeScript
docker compose exec node npm test                  # Vitest
docker compose --profile e2e run --rm e2e          # Playwright
docker compose logs --tail=30 node                 # did the UI build compile?
```
