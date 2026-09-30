# UI regression suite

The manual pass to run through the browser after the automated suites are green and before a release. Each case
is something a real user does, written as steps and what must happen. Run them **in order**: later cases use the
data earlier ones create.

Every new feature adds its cases here, in the section of the screen it lives on, in the same format. A case is
only worth adding if it would catch the feature breaking: name the button, the data and what you should see.
Case IDs are stable: a new case takes the next number in its section, and a removed case leaves a gap.

Each run is recorded as a new file in [`runs/`](runs/) with the result of every ID
(`python3 .claude/skills/symfony-react-app/scripts/new-run.py` creates it).

## Before you start

**Start from known data, not from whatever the last run left.** Everything runs in Docker:

```bash
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console <the project's seed or fixtures command>
```

- `docker compose ps`: every service is up, and `docker compose logs node` says the last build compiled.
- Open the mail catcher (Mailpit) next to the app: cases that send email end with "an email arrives".
- Use one browser profile for the run and nothing else on the app's origin in it: tabs share one session.
- Have ready: <!-- the files the cases upload: a small PDF, a PNG… -->

**Accounts.** Dev only, never reuse these passwords.

| Role | Sign in with | Lands on |
|---|---|---|
| <!-- role --> | <!-- email / password --> | <!-- path --> |

**On every screen, whatever the case says**, also check:

- The browser console has no errors, and nothing is a blank page.
- No text shows a raw translation key or a message in the wrong language.
- Tables and forms follow the project's house style (`CLAUDE.md`).
- A success or error message appears after every save, and it belongs to *that* save.

---

## 1. Public pages (no login)

**PUB-01 · The home page loads**
Open `/`. **Expected:** the page renders, with no console errors.

## 2. Authentication

**AUTH-01 · Sign in and out as each role**
Sign in with each account above. **Expected:** each lands on its own space. "Sign out" returns to the login page,
and the back button does not show the signed-in page again.

**AUTH-02 · A role cannot reach another role's space**
Signed in as the least privileged role, open another role's URL. **Expected:** a refusal or a redirect, never the
page. The API answers 403.

**AUTH-03 · Wrong credentials are refused without saying which one is wrong**
**Expected:** one generic message. After repeated attempts, the rate limit applies.

<!-- ## 3. <Area of the app>: one section per area/role, cases numbered AREA-01, AREA-02… -->

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in the mail catcher. **Expected:** no translation keys, the right sender and
branding, and every link opens a real screen.
