# UI regression suite

The manual pass to run through the browser after the automated suites are green and before a release. Each case
is something a real user does, written as steps and what must happen. Run them **in order**: later cases use the
data earlier ones create.

Every new feature adds its cases here, in the section of the screen it lives on, in the same format. A case is
only worth adding if it would catch the feature breaking: name the button, the data and what you should see.
Case IDs are stable: a new case takes the next number in its section, and a removed case leaves a gap. While the
app is built in parallel items (`docs/pdr/prd-mappi.md`), each item writes only in its own ID range.

Each run is recorded as a new file in [`runs/`](runs/) with the result of every ID
(`python3 .claude/skills/symfony-react-app/scripts/new-run.py` creates it).

## Before you start

**Start from known data, not from whatever the last run left.** Everything runs in Docker:

```bash
scripts/stack.sh reset       # drops the dev database, migrates, seeds the demo data
scripts/stack.sh status      # every service up; URLs
docker compose logs --tail=5 node   # the last UI build compiled
```

- Open Mailpit (http://localhost:8025) next to the app: cases that send email end with "an email arrives".
- The first request after a code change takes ~30 s (the dev cache rebuilds); wait for it.
- Use one browser profile for the run and nothing else on the app's origin in it: tabs share one session.
- Have ready: a small PDF, a PNG screenshot (to paste), a CSV of members (`name,email,phone,role,area`).

**Accounts.** Dev only, never reuse these passwords. Password for all: `password123`.

| Role | Sign in with | Lands on |
|---|---|---|
| Platform admin (Admin) | `admin@mappi.test` | `/console/ai-experience`, customer selector in the sidebar |
| Account owner (root, Pro plan) | `owner@acme.test` | `/console/ai-experience` |
| Account admin (Customer-Admin) | `admin@acme.test` | `/console/ai-experience` |
| Read-only member | `reader@acme.test` | `/console/ai-experience` → redirected to Questionnaires (no write) |
| Another tenant (Starter plan) | `owner@globex.test` | `/console/ai-experience` |
| New account (onboarding pending) | `owner@newco.test` | `/console/onboarding` |

**On every screen, whatever the case says**, also check:

- The browser console has no errors, and nothing is a blank page.
- No text shows a raw translation key; switching the sidebar language to Español translates the whole screen.
- Tables, forms and buttons are the house components (`CLAUDE.md`); read-only users see disabled controls with a reason.
- A success or error message appears after every save, and it belongs to *that* save.
- Another tenant's data never appears; opening another tenant's URL gives "not found".

---

## 1. Public respondent app (no login) — RSP (item respondent-app), SES (sessions)

**PUB-01 · The root redirects to the marketing site**
Open `/`. **Expected:** the browser goes to the marketing site URL, and Back does not return to `/`.

<!-- RSP-01 – 45: respondent-app. SES-01 – 05: sessions. -->

## 2. Authentication — AUTH (item accounts)

**AUTH-00 · Sign in and out as the owner (item 0)**
Open `/console/login`, type `owner@acme.test` / `password123`, press Sign in. **Expected:** the console opens at
AI Experience with the sidebar; the account block shows the owner and the "Pro" badge. Logout → "Sign Out?" →
Sign out returns to `/console/login`, and Back does not show the console again.

<!-- AUTH-01 – 20: accounts. -->

## 3. Users and profile — USR (accounts), BIL (billing)

<!-- USR-01 – 10: accounts. BIL-01 – 20: billing. -->

## 4. Super-admin — ADM (admin)

<!-- ADM-01 – 15: admin. -->

## 5. Questionnaires — QST (authoring), EDT (editor), QF (commerce), GEN (generation), CHAT (chat), ONB (onboarding)

<!-- QST-01 – 15: authoring. EDT-01 – 25: editor. QF-01 – 15: commerce. GEN-01 – 05: generation.
     CHAT-01 – 15: chat. ONB-01 – 10: onboarding. -->

## 6. Answers and dashboards — ANS, DSH (analytics)

<!-- ANS-01 – 15, DSH-01 – 10: analytics. -->

## 7. Organizations, assignations and projects — ORG, ASG, ARS, PRJ

<!-- ORG-01 – 15: organizations. ASG-01 – 30: assignations. ARS-01 – 15: assignation-respondent.
     PRJ-01 – 12: projects. PRJ-13 – 20: project-wizard. -->

## 8. Branding, integrations and documentation — BRD, INT, DOC

<!-- BRD-01 – 10: branding. INT-01 – 12: integrations. DOC-01 – 08: docs. -->

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, the Mappi layout, the sender
`support@mappi.test`, the account's language, and every link opens a real screen.
