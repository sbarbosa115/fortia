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

**QST-01 · The listing shows the account's questionnaires, ten per page**
Sign in as `owner@acme.test`, open Questionnaires. **Expected:** the header says "12 questionnaires"; ten rows, newest
first, each with its title, "N questions", type (Standard, Diagnostic, Chaining, Process mapping), an Active switch and
the creation date; the pager says "1–10 of 12" and page 2 shows the other two. "Globex product feedback" never appears.

**QST-02 · The page size changes and goes back to page 1**
On page 2, choose 20 per page. **Expected:** all twelve rows on one page, "1–12 of 12".

**QST-03 · Search runs on the server, every word, any case or accent**
Press Ctrl+K (the search box gets the focus) and type `CLIMA encuesta`. **Expected:** only "Encuesta de clima laboral".
Type `pulse 3` instead: only "Team pulse, week 3".

**QST-04 · A search that finds nothing says so and clears**
Search `zzz`. **Expected:** "Nothing matches "zzz"." with "Clear search"; pressing it empties the box and the twelve rows
come back.

**QST-05 · Type and state filters, and "No matches"**
Type: Diagnostic → only "AI maturity diagnostic". Type: All types, State: Inactive → only "Onboarding feedback". Type:
Quiz funnel with State: Active → "No matches" with "Clear filters", which resets every filter and the search.

**QST-06 · Sort by update, ascending or descending, and the date column follows**
Choose "Sort: Updated" and "Ascending". **Expected:** the date column header reads "Updated"; the oldest update comes
first. Switch back to "Sort: Created" / "Descending".

**QST-07 · Local or UTC dates, remembered**
Choose Time zone: UTC. **Expected:** the dates shift to UTC. Reload the page: UTC is still chosen.

**QST-08 · The Active switch saves at once and survives a reload**
Turn off "Team pulse, week 1". **Expected:** the switch flips immediately and reads "Inactive"; reload: still inactive;
State: Inactive lists it. Turn it back on.

**QST-09 · A failed toggle reverts**
In DevTools set the network offline, toggle any row. **Expected:** the switch flips back and a red message appears. Go
back online.

**QST-10 · Row actions**
On "Customer satisfaction survey": the eye opens `/f/acme-satisfaction` in a new tab; the copy icon says "Link copied"
and the clipboard holds that link; the pencil and the title open `/questionnaires/…/edit`; "Answers" opens its answers
page; "Analytics" (with the "New" badge) opens its dashboard.

**QST-11 · A read-only member only reads**
Sign in as `reader@acme.test`, open Questionnaires. **Expected:** "New Questionnaire", the Active switches and the pencils
are disabled and say "Your read-only role can't …" on hover; the title opens the public page in a new tab; View, Copy
link, Answers and Analytics still work.

**QST-12 · A brand-new account sees what the section is for**
Sign in as `owner@newco.test` (finish onboarding first if the console asks), open Questionnaires. **Expected:** "No questionnaires created yet / Create your first questionnaire to start collecting
responses." with "New Questionnaire" going to `/questionnaires/new`.

**QST-13 · The public flow opens by slug; an unknown one is "not found"**
Open `/api/v1/flow/acme-satisfaction`. **Expected:** JSON whose questionnaire state only carries `questionnaire_id`;
`/api/v1/flow/acme-ai-maturity` shows no tiers anywhere; `/api/v1/flow/nope` answers 404 `FLOW_NOT_FOUND`.

**QST-14 · Spanish**
Switch the sidebar language to Español on Questionnaires. **Expected:** "Cuestionarios", "12 cuestionarios", "Nuevo
cuestionario", the filters, columns, types ("Estándar", "Diagnóstico", "Encadenado") and empty states are all Spanish.

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
