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

The SES cases check the sessions API the respondent app runs on. Until the respondent screens exist (RSP) they are
run with `curl` against `http://localhost:8080/api/v1`; afterwards the same checks happen through `/q/{id}` and the
browser's network tab. `{Q}` is the id of an active questionnaire of `owner@acme.test` (create one in the console,
QST cases), `{D}` of a diagnostic one with tiers.

**SES-01 · A respondent starts, saves and submits a questionnaire**
`POST /questionnaire/{Q}/session`, then `PUT /questionnaire/session` with the returned body and a value in the
first question, then `POST /questionnaire/session` with the same body. **Expected:** the start answers the session
itself (no `{message, data}` envelope), `status` `filling` and `on_completed` only `{type}`; the save answers the
value back with `status` still `filling`; the submit answers `{type:"default"}` plus the flow's `cta`/`layout`/
`result_copy` if it has them. Submitting again answers the same, and the console's usage shows one more response.

**SES-02 · A diagnostic's results can be reloaded**
Submit a session of `{D}` with values in its scored questions, then open
`GET /questionnaire/session/{session_id}/results` twice. **Expected:** the submit answers `type` `diagnostic` with
`score {value, max}`, the categories and the reached tier's recommendations (or the lower tier's when it has none);
the results route answers the same diagnostic, the session's `customer_id` and the flow's texts. An unsubmitted
session's results are 404 `SESSION_RESULTS_NOT_FOUND`.

**SES-03 · A file answer gets a signed upload only for a live session**
`POST /signed-urls` with `{filename:"a.pdf", content_type:"application/pdf", customer_id:"<acme id>", session_id,
question_id}` of a session being filled. **Expected:** `{url, fields, key, expires_in:900}` with a key
`{customer_id}/{session_id}/{question_id}/{md5}.pdf`; posting the file with the fields to `url` stores it. The same
call with another `customer_id`, an unknown session or a submitted session is 404 `SESSION_NOT_FOUND`. Signed in as
`owner@acme.test`, `POST /answers-media/download-urls {key}` gives a download link; as `owner@globex.test` it is 403.

**SES-04 · An answer that misses its criteria costs a follow-up**
On a text question with `max_followups` 2 and acceptance criteria, `POST
/questionnaire/session/{id}/answers/{question_id}/evaluate` with the answer "Fine", then poll `GET /jobs/{job_id}`.
**Expected:** the job completes with `status` `not_sense`, an `improvement_message` and `max_followups` 1; a
five-word answer completes with `success` (dev runs on the fake model). `GET /transcription/token` answers a new
`token` each time, with `provider` `browser`.

**SES-05 · A quiz funnel recommends products from the catalog**
Submit a session of a quiz funnel questionnaire whose account has products. **Expected:** the submit answers 202
`{job}` of type `process_completed_session`; polling it ends `COMPLETED` with up to three products of that
catalog, and `GET /questionnaire/session/{id}/results` lists them in `products`. With no products in the catalog the
job completes with `products: []`.

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

**QST-15 · A copy is named in the account's language**
Open a questionnaire that has answers (it is Locked, EDT-22) and press "Create a copy" → "Yes, create copy".
**Expected:** a new row "(copia) {title}" (Acme's language is Spanish; an English account gets "(copy) {title}") whose
link is `…/f/{slug}-copia`; copying again gives "(copia - 2) {title}". A diagnostic copy keeps its tiers, a chain its
prompts, and the copy has no answers.

**EDT-01 · The type picker**
As `owner@acme.test` press "New Questionnaire". **Expected:** "What do you want to create?" with four cards (Regular
(Default) "Best for surveys", Diagnostic "Best for assessments", Quiz Funnel "Imports from your store", Chaining "Best for
AI generation") and their PRD descriptions. As `owner@globex.test` (Starter), a type the plan lacks is disabled and says
"Your plan doesn't include this feature." (or the limit text when used up). `/design-experience` lands here.

**EDT-02 · The creation container**
Open Regular. **Expected:** breadcrumb "Questionnaires / New Regular", chip "Draft · saved when you create it", steps
Details → Questions → When it ends (2 and 3 disabled, "Complete the previous steps first." on hover), Preview, Back,
Continue disabled with "Write a title to continue." in its tooltip and in the list under the form.

**EDT-03 · Details: slug rules**
Type a title: the hint shows `…/f/{slug-of-title}`. Type `My Survey` as slug: "Lowercase letters, numbers and hyphens
only.". Type `acme-satisfaction` and create the questionnaire: back on Details with "That custom link (slug) is already
in use by another questionnaire. Choose a different one." under the field.

**EDT-04 · Details: landing and disclaimer**
Landing page is on for a new questionnaire. Turn Disclaimer on: Continue says "Write the disclaimer text to continue."
until its text is written.

**EDT-05 · Questions: add, duplicate, delete**
Add question, duplicate it (a copy right under it), delete one. The header of each card opens and closes it.

**EDT-06 · Questions: drag and drop, categories**
Give two questions categories A and B (type a new category, leave the field). **Expected:** they are grouped under A and B
headers. Drag a question of A (the grip, mouse or Space + arrows) onto a question of B: it moves into B. Drop onto the
B header area: same.

**EDT-07 · Input types and their fields**
Switch a question through every input type. **Expected:** option types show choices; the two "with score" types show a
score per choice; text shows Data type (Free with All/Letters/Numbers/Symbols, RFC, NIT, Phone / WhatsApp) and Maximum
follow-ups; above 0 follow-ups, Acceptance criteria (up to 10); range shows Min and Max; message and file show nothing
more.

**EDT-08 · Question validation messages**
Leave a title empty ("Question 1 must have a title."), remove every choice ("Question 1 needs at least one answer
choice."), empty a choice label, type a letter as a score, repeat a score, set range 5–5, set follow-ups 2 without
criteria. **Expected:** each PRD message appears in the list and in Continue's tooltip.

**EDT-09 · Live preview**
Press Preview. **Expected:** a phone frame with the landing (title, description, Start) and each question with its
choices; Desktop widens it; edits show at once. Under 1100 px width the preview is a side panel with a close button.

**EDT-10 · Regular: When it ends**
Thank-you message (Title/Message ≤ 300), Call to action (empty title → "The call to action title is required.", URL
`example.com` → "Enter a full URL starting with http:// or https://."), Capture data.

**EDT-11 · Create asks first; the success screen**
Create → "Create the questionnaire?" / "Nothing has been saved yet…" / "Yes, create". **Expected:** "Questionnaire created
successfully!" with its link, Copy link ("Link copied"), View questionnaire (new tab `/f/{slug}`), Keep editing (the
editor, "Editing · saved when you save changes"), Go to Questionnaires (the new row is first), Create another.

**EDT-12 · Diagnostic: categories and scores**
New Diagnostic. **Expected:** the input types exclude Dropdown and Message; a question without category lists "Every
question needs a category — your tiers are built from these categories."; only text questions → "Add at least one scored
question…"; Required is locked on for scored types (tooltip says why); "Max score" and each area's total update live.

**EDT-13 · Diagnostic: seeded tiers**
With one question scored 0/9, continue to Results. **Expected:** "Top score: 9" and Beginner 0–2, Intermediate 3–5,
Advanced 6–9; "Spread tiers evenly" re-seeds after the questions change.

**EDT-14 · Diagnostic: tier rules**
Empty a name ("Give every tier a name."), empty a "to", set from > to, start at 1, end below the top, leave a gap:
each shows its PRD message under the tiers.

**EDT-15 · Diagnostic: blocks and texts**
Toggle each block (Tier, Total score, Score by area, Recommendations, Action plan, PDF report, Call to action, Capture
data); Recommendations and Action plan show one field per tier; "Texts of the results page" has the 15 texts. Create:
"Your diagnostic is ready." Reopen: the tiers, recommendations, blocks and texts are back.

**EDT-16 · Chaining: prompts**
New Chaining. **Expected:** the timeline Starting point → Prompt 1 → Questionnaire 1; "Add prompt" up to 10 (then disabled
with "Up to 10 prompts."); an empty prompt → "Prompt N can't be empty."; Ending: Another questionnaire / Finish /
Diagnostic / Quiz funnel.

**EDT-17 · Chaining: saving uploads the prompts**
Create a chain with two prompts. **Expected (network tab):** `GET /customer/usage` (live `chain` check), two
`POST /signed-urls` with `upload_type: prompt` and two `PUT /storage/put`, then `POST /questionnaire`. Keep editing shows
both prompt texts and the ending.

**EDT-18 · Chain feature gone at save time**
(Admin: set Acme's `chain` limit to 0 while the editor is open.) Create. **Expected:** "Your plan doesn't include chaining
anymore, so this chain can't be saved." and nothing is created.

**EDT-19 · /:id/edit opens the right editor**
From the listing's pencil: a regular one → `/…/edit/regular`, "AI maturity diagnostic" → `/…/edit/diagnostic`, "Discovery
chain" → `/…/edit/prompt`, each with every step available.

**EDT-20 · Editing saves without asking**
Change a title and press Save changes on any step. **Expected:** no confirmation; "Changes saved"; Keep editing returns to
the editor; the listing shows the new title and update date.

**EDT-21 · The generic editor**
Pencil on "Process map: operations". **Expected:** one page (Details with the `…/f/acme-operations-map` preview,
Questions, Call to action, Capture data) and "Update"; saving shows "Changes saved" and keeps its type in the listing.

**EDT-22 · Locked**
Answer a questionnaire (open its link, answer one question), then open its editor and save (or open it once the answers
listing exists). **Expected:** "Locked" badge, "Locked to preserve answers", "Create a copy" → "Create a copy?" / "A new
questionnaire is created in your account, with no answers, ready to edit." / "Yes, create copy" → the copy's editor,
titled "(copia) …".

**EDT-23 · Read-only users**
As `reader@acme.test` open `/questionnaires/new` or any `/edit` URL. **Expected:** back to the listing.

**EDT-24 · Unknown kinds**
Open `/questionnaires/create/nope`. **Expected:** the type picker.

**EDT-25 · Spanish**
Switch to Español in the editor. **Expected:** every label, step, button, message and the success screen are Spanish
("Detalles", "Continuar", "¡Cuestionario creado con éxito!", "Bloqueado para conservar las respuestas").

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
